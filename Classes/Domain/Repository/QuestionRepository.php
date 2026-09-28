<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Reads backend-defined question groups and questions.
 */
class QuestionRepository
{
    public const GROUP_TABLE = 'tx_pbnnewskiatlas27rate_domain_model_questiongroup';
    public const QUESTION_TABLE = 'tx_pbnnewskiatlas27rate_domain_model_question';

    /**
     * Returns active groups with their active questions for the rated table.
     *
     * @param int[] $storagePids empty = no pid restriction
     * @return array<int, array{uid:int,title:string,description:string,questions:array}>
     */
    public function findGroupedForTable(array $storagePids, string $table): array
    {
        $groups = array();
        foreach ($this->findActiveRows($storagePids) as $row) {
            if (!$this->appliesTo((string)$row['applies_to'], $table)) {
                continue;
            }

            $groupUid = (int)$row['group_uid'];
            if (!isset($groups[$groupUid])) {
                $groups[$groupUid] = array(
                    'uid' => $groupUid,
                    'title' => (string)$row['group_title'],
                    'description' => (string)$row['group_description'],
                    'questions' => array(),
                );
            }

            $groups[$groupUid]['questions'][] = array(
                'uid' => (int)$row['uid'],
                'title' => (string)$row['title'],
                'description' => (string)$row['description'],
                'required' => (bool)$row['required'],
                'isSummary' => (bool)$row['is_summary'],
            );
        }

        return array_values($groups);
    }

    /**
     * Flat list of questions keyed by uid.
     *
     * @param array $groups result of findGroupedForTable()
     * @return array<int, array>
     */
    public function flatten(array $groups): array
    {
        $questions = array();
        foreach ($groups as $group) {
            foreach ($group['questions'] as $question) {
                $questions[(int)$question['uid']] = $question;
            }
        }

        return $questions;
    }

    /**
     * @return array[]
     */
    protected function findActiveRows(array $storagePids): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::QUESTION_TABLE);
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select(
                'q.uid',
                'q.title',
                'q.description',
                'q.required',
                'q.is_summary',
                'q.applies_to',
                'g.uid AS group_uid',
                'g.title AS group_title',
                'g.description AS group_description'
            )
            ->from(self::QUESTION_TABLE, 'q')
            ->join(
                'q',
                self::GROUP_TABLE,
                'g',
                $queryBuilder->expr()->eq('g.uid', $queryBuilder->quoteIdentifier('q.questiongroup'))
            )
            ->where(
                $queryBuilder->expr()->eq('q.deleted', 0),
                $queryBuilder->expr()->eq('q.hidden', 0),
                $queryBuilder->expr()->eq('g.deleted', 0),
                $queryBuilder->expr()->eq('g.hidden', 0)
            )
            ->orderBy('g.sorting')
            ->addOrderBy('q.sorting');

        if ($storagePids !== array()) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in(
                    'g.pid',
                    $queryBuilder->createNamedParameter($storagePids, Connection::PARAM_INT_ARRAY)
                )
            );
        }

        return $queryBuilder->execute()->fetchAll();
    }

    protected function appliesTo(string $appliesTo, string $table): bool
    {
        if (trim($appliesTo) === '') {
            return true;
        }

        return in_array($table, GeneralUtility::trimExplode(',', $appliesTo, true), true);
    }
}
