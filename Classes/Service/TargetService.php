<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Service;

use Pbn\PbnNewskiatlas27Rate\Configuration\RateSettings;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Validates that a rated record is allowed and visible in the frontend.
 */
class TargetService
{
    /** @var array<string, bool> */
    protected $cache = array();

    public function isValidTarget(RateSettings $settings, string $table, int $uid): bool
    {
        if ($uid <= 0 || !$settings->isTableAllowed($table) || !isset($GLOBALS['TCA'][$table])) {
            return false;
        }

        $key = $table . ':' . $uid;
        if (!isset($this->cache[$key])) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder->setRestrictions(GeneralUtility::makeInstance(FrontendRestrictionContainer::class));
            $count = $queryBuilder
                ->count('uid')
                ->from($table)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)))
                ->execute()
                ->fetchColumn(0);
            $this->cache[$key] = (int)$count > 0;
        }

        return $this->cache[$key];
    }

    public static function cacheTag(string $table, int $uid): string
    {
        return 'pbnrate_' . $table . '_' . $uid;
    }
}
