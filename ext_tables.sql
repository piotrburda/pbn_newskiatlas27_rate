#
# Question groups, e.g. "Ogolna ocena z Twojego wyjazdu", "Dla kogo osrodek"
#
CREATE TABLE tx_pbnnewskiatlas27rate_domain_model_questiongroup (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
    deleted smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden smallint(5) unsigned DEFAULT '0' NOT NULL,
    sorting int(11) DEFAULT '0' NOT NULL,

    title varchar(255) DEFAULT '' NOT NULL,
    description text,
    questions int(11) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);

#
# Single rating question (1..maxStars)
#
CREATE TABLE tx_pbnnewskiatlas27rate_domain_model_question (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
    deleted smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden smallint(5) unsigned DEFAULT '0' NOT NULL,
    sorting int(11) DEFAULT '0' NOT NULL,

    questiongroup int(11) unsigned DEFAULT '0' NOT NULL,
    title varchar(255) DEFAULT '' NOT NULL,
    description text,
    required smallint(5) unsigned DEFAULT '0' NOT NULL,
    is_summary smallint(5) unsigned DEFAULT '0' NOT NULL,
    applies_to varchar(1024) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY questiongroup (questiongroup)
);

#
# One submitted vote (header). A visitor may create a new vote for the same
# record after the lock period - older votes are kept.
#
CREATE TABLE tx_pbnnewskiatlas27rate_domain_model_vote (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    deleted smallint(5) unsigned DEFAULT '0' NOT NULL,
    hidden smallint(5) unsigned DEFAULT '0' NOT NULL,

    foreign_table varchar(255) DEFAULT '' NOT NULL,
    foreign_uid int(11) unsigned DEFAULT '0' NOT NULL,
    fe_user int(11) unsigned DEFAULT '0' NOT NULL,
    ip_hash varchar(64) DEFAULT '' NOT NULL,
    answers int(11) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY target (foreign_table(100), foreign_uid, crdate),
    KEY iplock (ip_hash, crdate),
    KEY feuserlock (fe_user, foreign_uid, crdate)
);

#
# Answer to a single question inside a vote
#
CREATE TABLE tx_pbnnewskiatlas27rate_domain_model_voteanswer (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    deleted smallint(5) unsigned DEFAULT '0' NOT NULL,

    vote int(11) unsigned DEFAULT '0' NOT NULL,
    question int(11) unsigned DEFAULT '0' NOT NULL,
    value smallint(5) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY vote (vote),
    KEY question (question)
);
