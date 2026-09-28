/*!
 * pbn_newskiatlas27_rate – star rating widget (no dependencies, IE11 not supported)
 */
(function () {
    'use strict';

    function format(template, value) {
        return String(template || '').replace('%s', value);
    }

    function formatDate(timestamp) {
        var date = new Date(timestamp * 1000);
        var lang = document.documentElement.lang || 'pl';
        try {
            return date.toLocaleString(lang, {
                day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit'
            });
        } catch (e) {
            return date.toLocaleString();
        }
    }

    function RatingWidget(root) {
        this.root = root;
        this.form = root.querySelector('[data-pbnrate-form]');
        this.message = root.querySelector('[data-pbnrate-message]');
        this.submit = root.querySelector('.pbnrate__submit');
        this.endpoint = root.getAttribute('data-endpoint');
        this.table = root.getAttribute('data-table');
        this.uid = root.getAttribute('data-uid');
        this.token = null;
        this.busy = false;

        this.bindStars();
        this.form.addEventListener('submit', this.onSubmit.bind(this));
        this.loadStatus();
    }

    RatingWidget.prototype.msg = function (key) {
        return this.root.getAttribute('data-msg-' + key) || '';
    };

    RatingWidget.prototype.setMessage = function (text, type) {
        this.message.textContent = text || '';
        this.message.classList.remove('is-error', 'is-success');
        if (type) {
            this.message.classList.add('is-' + type);
        }
    };

    RatingWidget.prototype.request = function (action, formData) {
        var data = formData || new FormData();
        data.append('pbnrate[action]', action);
        data.append('pbnrate[table]', this.table);
        data.append('pbnrate[uid]', this.uid);

        return fetch(this.endpoint, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        });
    };

    RatingWidget.prototype.loadStatus = function () {
        var self = this;
        self.setMessage(self.msg('loading'));

        return self.request('status').then(function (data) {
            if (!data || !data.success) {
                throw new Error(data && data.error ? data.error : 'status');
            }
            if (data.locked) {
                self.lock(data.lockedUntil, 'locked');
                return;
            }
            self.token = data.token;
            self.root.classList.add('is-open');
            self.setMessage('');
        }).catch(function () {
            self.setMessage(self.msg('error'), 'error');
        });
    };

    RatingWidget.prototype.lock = function (lockedUntil, messageKey, type) {
        this.root.classList.remove('is-open');
        this.root.classList.add('is-locked');
        this.token = null;
        this.setMessage(lockedUntil ? format(this.msg(messageKey), formatDate(lockedUntil)) : '', type);
    };

    RatingWidget.prototype.bindStars = function () {
        var groups = this.root.querySelectorAll('[data-pbnrate-input]');
        Array.prototype.forEach.call(groups, function (group) {
            var labels = group.querySelectorAll('.pbnrate__star');
            var radios = group.querySelectorAll('.pbnrate__radio');

            function paint(value) {
                Array.prototype.forEach.call(labels, function (label, index) {
                    label.classList.toggle('is-filled', index < value);
                });
            }
            function checkedValue() {
                var checked = group.querySelector('.pbnrate__radio:checked');
                return checked ? parseInt(checked.value, 10) : 0;
            }

            Array.prototype.forEach.call(labels, function (label, index) {
                label.addEventListener('mouseenter', function () { paint(index + 1); });
            });
            group.addEventListener('mouseleave', function () { paint(checkedValue()); });
            Array.prototype.forEach.call(radios, function (radio) {
                radio.addEventListener('change', function () {
                    paint(checkedValue());
                    var question = group.closest('[data-pbnrate-question]');
                    if (question) {
                        question.classList.remove('has-error');
                    }
                });
            });
        });
    };

    RatingWidget.prototype.validate = function () {
        var missing = [];
        var answered = 0;
        var questions = this.root.querySelectorAll('[data-pbnrate-question]');
        Array.prototype.forEach.call(questions, function (question) {
            var checked = question.querySelector('.pbnrate__radio:checked');
            if (checked) {
                answered++;
            } else if (question.getAttribute('data-required') === '1') {
                missing.push(question);
            }
            question.classList.toggle('has-error', !checked && question.getAttribute('data-required') === '1');
        });

        if (missing.length) {
            this.setMessage(this.msg('required'), 'error');
            var first = missing[0].querySelector('.pbnrate__radio');
            if (first) {
                first.focus();
            }
            return false;
        }
        if (!answered) {
            this.setMessage(this.msg('no-answers'), 'error');
            return false;
        }
        return true;
    };

    RatingWidget.prototype.markMissing = function (uids) {
        var root = this.root;
        (uids || []).forEach(function (uid) {
            var question = root.querySelector('[data-pbnrate-question="' + uid + '"]');
            if (question) {
                question.classList.add('has-error');
            }
        });
    };

    RatingWidget.prototype.onSubmit = function (event) {
        event.preventDefault();
        var self = this;
        if (self.busy || !self.token || !self.validate()) {
            return;
        }

        var data = new FormData(self.form);
        data.append('pbnrate[token]', self.token);
        self.busy = true;
        self.submit.disabled = true;

        self.request('vote', data).then(function (response) {
            if (response && response.success) {
                self.lock(response.lockedUntil, 'thanks', 'success');
                self.updateResults(response.results);
                return;
            }

            var error = response && response.error ? response.error : 'error';
            if (error === 'locked') {
                self.lock(response.lockedUntil, 'locked');
            } else if (error === 'required') {
                self.markMissing(response.missing);
                self.setMessage(self.msg('required'), 'error');
            } else if (error === 'no_answers') {
                self.setMessage(self.msg('no-answers'), 'error');
            } else if (error === 'too_fast') {
                self.setMessage(self.msg('too-fast'), 'error');
            } else if (error === 'token_expired' || error === 'token_invalid') {
                self.setMessage(self.msg('expired'), 'error');
            } else {
                self.setMessage(self.msg('error'), 'error');
            }
        }).catch(function () {
            self.setMessage(self.msg('error'), 'error');
        }).then(function () {
            self.busy = false;
            self.submit.disabled = false;
        });
    };

    RatingWidget.prototype.updateResults = function (results) {
        if (!results || !results.visible || !results.questions) {
            return;
        }
        var root = this.root;
        var total = root.querySelector('[data-pbnrate-total]');
        if (total) {
            total.textContent = format(this.msg('votes'), results.totalVotes);
        }

        Object.keys(results.questions).forEach(function (uid) {
            var result = results.questions[uid];
            var container = root.querySelector('[data-pbnrate-result="' + uid + '"]');
            if (!container || !result) {
                return;
            }
            var fill = container.querySelector('[data-pbnrate-fill]');
            var average = container.querySelector('[data-pbnrate-average]');
            var count = container.querySelector('[data-pbnrate-count]');
            if (fill) { fill.style.width = result.percentCss + '%'; }
            if (average) { average.textContent = result.averageFormatted; }
            if (count) { count.textContent = result.count; }
            container.hidden = result.count < 1;
        });
    };

    function init() {
        if (!window.fetch || !window.FormData) {
            return;
        }
        var widgets = document.querySelectorAll('[data-pbnrate]');
        Array.prototype.forEach.call(widgets, function (root) {
            if (!root.pbnRate) {
                root.pbnRate = new RatingWidget(root);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
