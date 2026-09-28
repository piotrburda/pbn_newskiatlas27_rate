<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Domain\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Stores votes and computes aggregated results with plain DBAL
 * (fast, no Extbase persistence session in the JSON endpoint).
 */
class VoteRepository
{
    public const VOTE_TABLE = 'tx_pbnnewskiatlas27rate_domain_model_vote';
    public const ANSWER_TABLE = 'tx_pbnnewskiatlas27rate_domain_model_voteanswer';

    /**
     * Latest vote time of a logged-in frontend user for the record since $since.
     */
    public function findLatestVoteTimeForFeUser(int $feUser, string $table, int $uid, int $since): int
    {
        if ($feUser <= 0) {
            return 0;
        }

        $queryBuilder = $this->getVoteQueryBuilder();
        $time = $queryBuilder
            ->selectLiteral('MAX(' . $queryBuilder->quoteIdentifier('crdate') . ')')
            ->from(self::VOTE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('fe_user', $queryBuilder->createNamedParameter($feUser, \PDO::PARAM_INT)),
                $this->targetConstraint($queryBuilder, $table, $uid),
                $queryBuilder->expr()->gt('crdate', $queryBuilder->createNamedParameter($since, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->execute()
            ->fetchColumn(0);

        return (int)$time;
    }

    /**
     * Vote times (newest first) of one anonymised client for the record since $since.
     *
     * @return int[]
     */
    public function findRecentVoteTimesForIpHash(string $ipHash, string $table, int $uid, int $since, int $limit): array
    {
        if ($ipHash === '' || $limit <= 0) {
            return array();
        }

        $queryBuilder = $this->getVoteQueryBuilder();
        $rows = $queryBuilder
            ->select('crdate')
            ->from(self::VOTE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('ip_hash', $queryBuilder->createNamedParameter($ipHash)),
                $this->targetConstraint($queryBuilder, $table, $uid),
                $queryBuilder->expr()->gt('crdate', $queryBuilder->createNamedParameter($since, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('crdate', 'DESC')
            ->setMaxResults($limit)
            ->execute()
            ->fetchAll();

        return array_map(static function (array $row) {
            return (int)$row['crdate'];
        }, $rows);
    }

    /**
     * Creates a vote together with its answers in one transaction.
     *
     * @param array<int,int> $answers question uid => value
     */
    public function createVote(
        int $pid,
        string $table,
        int $uid,
        int $feUser,
        string $ipHash,
        array $answers,
        int $now
    ): int {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        $voteConnection = $pool->getConnectionForTable(self::VOTE_TABLE);
        $answerConnection = $pool->getConnectionForTable(self::ANSWER_TABLE);

        $voteConnection->beginTransaction();
        try {
            $voteConnection->insert(self::VOTE_TABLE, array(
                'pid' => $pid,
                'tstamp' => $now,
                'crdate' => $now,
                'foreign_table' => $table,
                'foreign_uid' => $uid,
                'fe_user' => $feUser,
                'ip_hash' => $ipHash,
                'answers' => count($answers),
            ));
            $voteUid = (int)$voteConnection->lastInsertId(self::VOTE_TABLE);

            foreach ($answers as $questionUid => $value) {
                $answerConnection->insert(self::ANSWER_TABLE, array(
                    'pid' => $pid,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'vote' => $voteUid,
                    'question' => (int)$questionUid,
                    'value' => (int)$value,
                ));
            }

            $voteConnection->commit();
        } catch (\Throwable $exception) {
            $voteConnection->rollBack();
            throw $exception;
        }

        return $voteUid;
    }

    /**
     * Number of visible votes for the record.
     */
    public function countVotes(string $table, int $uid, int $since = 0): int
    {
        $queryBuilder = $this->getVoteQueryBuilder();
        $queryBuilder
            ->count('uid')
            ->from(self::VOTE_TABLE)
            ->where(
                $this->targetConstraint($queryBuilder, $table, $uid),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('deleted', 0)
            );

        if ($since > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, \PDO::PARAM_INT))
            );
        }

        return (int)$queryBuilder->execute()->fetchColumn(0);
    }

    /**
     * Average and answer count per question for the record.
     *
     * @return array<int, array{average: float, count: int}>
     */
    public function aggregateByQuestion(string $table, int $uid, int $since = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::ANSWER_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select('a.question')
            ->addSelectLiteral(
                'AVG(' . $queryBuilder->quoteIdentifier('a.value') . ') AS ' . $queryBuilder->quoteIdentifier('average_value'),
                'COUNT(' . $queryBuilder->quoteIdentifier('a.uid') . ') AS ' . $queryBuilder->quoteIdentifier('answer_count')
            )
            ->from(self::ANSWER_TABLE, 'a')
            ->join(
                'a',
                self::VOTE_TABLE,
                'v',
                $queryBuilder->expr()->eq('v.uid', $queryBuilder->quoteIdentifier('a.vote'))
            )
            ->where(
                $queryBuilder->expr()->eq('v.foreign_table', $queryBuilder->createNamedParameter($table)),
                $queryBuilder->expr()->eq('v.foreign_uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('v.hidden', 0),
                $queryBuilder->expr()->eq('v.deleted', 0),
                $queryBuilder->expr()->eq('a.deleted', 0),
                $queryBuilder->expr()->gt('a.value', 0)
            )
            ->groupBy('a.question');

        if ($since > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('v.crdate', $queryBuilder->createNamedParameter($since, \PDO::PARAM_INT))
            );
        }

        $result = array();
        foreach ($queryBuilder->execute()->fetchAll() as $row) {
            $result[(int)$row['question']] = array(
                'average' => (float)$row['average_value'],
                'count' => (int)$row['answer_count'],
            );
        }

        return $result;
    }

    /**
     * Removes the anonymised client hash from votes older than $olderThan
     * (the hash is only needed while the lock period is running).
     */
    public function clearIpHashesOlderThan(int $olderThan): int
    {
        $queryBuilder = $this->getVoteQueryBuilder();

        return (int)$queryBuilder
            ->update(self::VOTE_TABLE)
            ->set('ip_hash', '')
            ->where(
                $queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter($olderThan, \PDO::PARAM_INT)),
                $queryBuilder->expr()->neq('ip_hash', $queryBuilder->createNamedParameter(''))
            )
            ->execute();
    }

    public function countIpHashesOlderThan(int $olderThan): int
    {
        $queryBuilder = $this->getVoteQueryBuilder();

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::VOTE_TABLE)
            ->where(
                $queryBuilder->expr()->lt('crdate', $queryBuilder->createNamedParameter($olderThan, \PDO::PARAM_INT)),
                $queryBuilder->expr()->neq('ip_hash', $queryBuilder->createNamedParameter(''))
            )
            ->execute()
            ->fetchColumn(0);
    }

    protected function getVoteQueryBuilder(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::VOTE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder;
    }

    protected function targetConstraint(QueryBuilder $queryBuilder, string $table, int $uid): string
    {
        return (string)$queryBuilder->expr()->andX(
            $queryBuilder->expr()->eq('foreign_table', $queryBuilder->createNamedParameter($table)),
            $queryBuilder->expr()->eq('foreign_uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT))
        );
    }
}
