<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Configuration;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Typed access to plugin.tx_pbnnewskiatlas27rate.settings.
 */
class RateSettings
{
    /** @var int */
    public $lockPeriod = 259200;

    /** @var int */
    public $ipLimit = 3;

    /** @var int */
    public $minSubmitTime = 3;

    /** @var int */
    public $tokenLifetime = 7200;

    /** @var string */
    public $cookieName = 'pbnrate';

    /** @var int[] */
    public $questionStoragePids = array();

    /** @var int */
    public $voteStoragePid = 0;

    /** @var string[] */
    public $allowedTables = array();

    /** @var int */
    public $ajaxTypeNum = 2727;

    /** @var int */
    public $ajaxPid = 0;

    /** @var int */
    public $maxStars = 5;

    /** @var bool */
    public $showResults = true;

    /** @var int */
    public $minVotesToShowResults = 1;

    /** @var int */
    public $resultsMaxAge = 0;

    /** @var string */
    public $decimalSeparator = ',';

    /** @var bool */
    public $includeCss = true;

    /** @var bool */
    public $includeJs = true;

    /**
     * @param array $conf USER / USER_INT configuration (TypoScript array with dots)
     */
    public static function fromTypoScript(array $conf): self
    {
        $raw = isset($conf['settings.']) && is_array($conf['settings.']) ? $conf['settings.'] : array();
        $settings = new self();

        $settings->lockPeriod = self::intValue($raw, 'lockPeriod', 259200, 60);
        $settings->ipLimit = self::intValue($raw, 'ipLimit', 3, 0);
        $settings->minSubmitTime = self::intValue($raw, 'minSubmitTime', 3, 0);
        $settings->tokenLifetime = self::intValue($raw, 'tokenLifetime', 7200, 60);
        $settings->ajaxTypeNum = self::intValue($raw, 'ajaxTypeNum', 2727, 1);
        $settings->ajaxPid = self::intValue($raw, 'ajaxPid', 0, 0);
        $settings->voteStoragePid = self::intValue($raw, 'voteStoragePid', 0, 0);
        $settings->maxStars = min(10, self::intValue($raw, 'maxStars', 5, 2));
        $settings->minVotesToShowResults = self::intValue($raw, 'minVotesToShowResults', 1, 0);
        $settings->resultsMaxAge = self::intValue($raw, 'resultsMaxAge', 0, 0);
        $settings->showResults = self::boolValue($raw, 'showResults', true);
        $settings->includeCss = self::boolValue($raw, 'includeCss', true);
        $settings->includeJs = self::boolValue($raw, 'includeJs', true);

        $cookieName = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)($raw['cookieName'] ?? ''));
        $settings->cookieName = $cookieName !== '' ? $cookieName : 'pbnrate';

        $separator = (string)($raw['decimalSeparator'] ?? ',');
        $settings->decimalSeparator = $separator !== '' ? $separator : ',';

        $settings->questionStoragePids = array_values(array_filter(
            GeneralUtility::intExplode(',', (string)($raw['questionStoragePid'] ?? ''), true),
            static function ($pid) {
                return $pid > 0;
            }
        ));

        $tables = GeneralUtility::trimExplode(',', (string)($raw['allowedTables'] ?? ''), true);
        $settings->allowedTables = array_values(array_filter($tables, static function ($table) {
            return (bool)preg_match('/^[a-z0-9_]+$/', $table);
        }));

        return $settings;
    }

    public function isTableAllowed(string $table): bool
    {
        return $table !== '' && in_array($table, $this->allowedTables, true);
    }

    public function getDefaultTable(): string
    {
        return $this->allowedTables[0] ?? '';
    }

    /**
     * @return int[] e.g. [1,2,3,4,5]
     */
    public function getStarValues(): array
    {
        return range(1, $this->maxStars);
    }

    private static function intValue(array $raw, string $key, int $default, int $min): int
    {
        if (!isset($raw[$key]) || trim((string)$raw[$key]) === '') {
            return $default;
        }

        return max($min, (int)$raw[$key]);
    }

    private static function boolValue(array $raw, string $key, bool $default): bool
    {
        if (!isset($raw[$key]) || trim((string)$raw[$key]) === '') {
            return $default;
        }

        return (bool)(int)$raw[$key];
    }
}
