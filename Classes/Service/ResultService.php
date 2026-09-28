<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Service;

use Pbn\PbnNewskiatlas27Rate\Configuration\RateSettings;
use Pbn\PbnNewskiatlas27Rate\Domain\Repository\VoteRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds result structures for Fluid and the JSON endpoint.
 */
class ResultService
{
    /** @var VoteRepository */
    protected $voteRepository;

    public function __construct(?VoteRepository $voteRepository = null)
    {
        $this->voteRepository = $voteRepository ?? GeneralUtility::makeInstance(VoteRepository::class);
    }

    /**
     * @param array<int, array> $questions flat question list keyed by uid
     * @return array{visible: bool, totalVotes: int, questions: array<int, array>}
     */
    public function build(RateSettings $settings, string $table, int $uid, array $questions, int $now): array
    {
        $since = $settings->resultsMaxAge > 0 ? $now - $settings->resultsMaxAge : 0;
        $totalVotes = $this->voteRepository->countVotes($table, $uid, $since);
        $aggregates = $totalVotes > 0 ? $this->voteRepository->aggregateByQuestion($table, $uid, $since) : array();

        $results = array();
        foreach ($questions as $questionUid => $question) {
            $aggregate = $aggregates[$questionUid] ?? array('average' => 0.0, 'count' => 0);
            $results[$questionUid] = $this->formatResult($settings, $aggregate['average'], $aggregate['count']);
        }

        return array(
            'visible' => $settings->showResults && $totalVotes > 0 && $totalVotes >= $settings->minVotesToShowResults,
            'totalVotes' => $totalVotes,
            'questions' => $results,
        );
    }

    /**
     * Adds the result to each question of the grouped structure.
     */
    public function mergeIntoGroups(array $groups, array $results): array
    {
        foreach ($groups as $groupIndex => $group) {
            foreach ($group['questions'] as $questionIndex => $question) {
                $result = $results['questions'][(int)$question['uid']] ?? null;
                if (is_array($result)) {
                    $result['show'] = !empty($results['visible']) && $result['count'] > 0;
                }
                $groups[$groupIndex]['questions'][$questionIndex]['result'] = $result;
            }
        }

        return $groups;
    }

    /**
     * The question marked as summary, otherwise the first question.
     */
    public function findSummaryQuestion(array $questions): ?array
    {
        foreach ($questions as $question) {
            if (!empty($question['isSummary'])) {
                return $question;
            }
        }

        $first = reset($questions);

        return $first !== false ? $first : null;
    }

    protected function formatResult(RateSettings $settings, float $average, int $count): array
    {
        $average = round($average, 2);
        $percent = $count > 0 ? round($average / $settings->maxStars * 100, 1) : 0.0;

        return array(
            'average' => $average,
            'averageFormatted' => number_format($average, 1, $settings->decimalSeparator, ''),
            'count' => $count,
            'percent' => $percent,
            // Locale independent value for inline CSS (width: 86.7%)
            'percentCss' => number_format($percent, 1, '.', ''),
        );
    }
}
