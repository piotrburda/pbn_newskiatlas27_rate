<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Frontend;

use Pbn\PbnNewskiatlas27Rate\Configuration\RateSettings;
use Pbn\PbnNewskiatlas27Rate\Domain\Repository\QuestionRepository;
use Pbn\PbnNewskiatlas27Rate\Domain\Repository\VoteRepository;
use Pbn\PbnNewskiatlas27Rate\Service\LockService;
use Pbn\PbnNewskiatlas27Rate\Service\ResultService;
use Pbn\PbnNewskiatlas27Rate\Service\TargetService;
use Pbn\PbnNewskiatlas27Rate\Service\TokenService;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * USER_INT JSON endpoint (page typeNum = settings.ajaxTypeNum).
 *
 * POST pbnrate[action]=status  pbnrate[table] pbnrate[uid]
 *   -> {success, locked, lockedUntil, token}
 * POST pbnrate[action]=vote    pbnrate[table] pbnrate[uid] pbnrate[token] pbnrate[answers][<question>]=1..5
 *   -> {success, lockedUntil, results} | {success:false, error, lockedUntil?, missing?}
 */
class AjaxEndpoint
{
    /** @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer|null */
    public $cObj;

    public function handle(string $content, array $conf): string
    {
        try {
            return $this->json($this->dispatch(RateSettings::fromTypoScript($conf)));
        } catch (\Throwable $exception) {
            GeneralUtility::makeInstance(LogManager::class)
                ->getLogger(__CLASS__)
                ->error('Rating endpoint failed: ' . $exception->getMessage(), array('code' => $exception->getCode()));

            return $this->json(array('success' => false, 'error' => 'server_error'));
        }
    }

    protected function dispatch(RateSettings $settings): array
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return array('success' => false, 'error' => 'bad_request');
        }

        $input = GeneralUtility::_POST('pbnrate');
        if (!is_array($input)) {
            return array('success' => false, 'error' => 'bad_request');
        }

        $table = (string)($input['table'] ?? '');
        $uid = (int)($input['uid'] ?? 0);
        if (!GeneralUtility::makeInstance(TargetService::class)->isValidTarget($settings, $table, $uid)) {
            return array('success' => false, 'error' => 'invalid_target');
        }

        $action = (string)($input['action'] ?? '');
        if ($action === 'status') {
            return $this->status($settings, $table, $uid);
        }
        if ($action === 'vote') {
            return $this->vote($settings, $table, $uid, $input);
        }

        return array('success' => false, 'error' => 'bad_request');
    }

    protected function status(RateSettings $settings, string $table, int $uid): array
    {
        $now = $this->now();
        $lockedUntil = $this->getLockedUntil($settings, $table, $uid, $now);

        return array(
            'success' => true,
            'locked' => $lockedUntil > 0,
            'lockedUntil' => $lockedUntil > 0 ? $lockedUntil : null,
            'token' => $lockedUntil > 0 ? null : GeneralUtility::makeInstance(TokenService::class)->create($table, $uid, $now),
        );
    }

    protected function vote(RateSettings $settings, string $table, int $uid, array $input): array
    {
        $now = $this->now();

        // Honeypot: pretend success so bots do not learn anything.
        if (trim((string)($input['website'] ?? '')) !== '') {
            return array('success' => true, 'lockedUntil' => $now + $settings->lockPeriod, 'results' => null);
        }

        $tokenState = GeneralUtility::makeInstance(TokenService::class)
            ->validate($settings, (string)($input['token'] ?? ''), $table, $uid, $now);
        if ($tokenState !== TokenService::VALID) {
            return array('success' => false, 'error' => $tokenState);
        }

        $lockedUntil = $this->getLockedUntil($settings, $table, $uid, $now);
        if ($lockedUntil > 0) {
            return array('success' => false, 'error' => 'locked', 'lockedUntil' => $lockedUntil);
        }

        $questionRepository = GeneralUtility::makeInstance(QuestionRepository::class);
        $questions = $questionRepository->flatten(
            $questionRepository->findGroupedForTable($settings->questionStoragePids, $table)
        );

        $answers = $this->filterAnswers($input['answers'] ?? array(), $questions, $settings->maxStars);
        $missing = array();
        foreach ($questions as $questionUid => $question) {
            if ($question['required'] && !isset($answers[$questionUid])) {
                $missing[] = $questionUid;
            }
        }
        if ($missing !== array()) {
            return array('success' => false, 'error' => 'required', 'missing' => $missing);
        }
        if ($answers === array()) {
            return array('success' => false, 'error' => 'no_answers');
        }

        $lockService = GeneralUtility::makeInstance(LockService::class);
        GeneralUtility::makeInstance(VoteRepository::class)->createVote(
            $settings->voteStoragePid,
            $table,
            $uid,
            $this->getFeUserUid(),
            $lockService->hashClient((string)GeneralUtility::getIndpEnv('REMOTE_ADDR'), $table, $uid),
            $answers,
            $now
        );

        $cookieMap = $lockService->readCookieMap($settings, $now);
        $cookieMap[LockService::targetKey($table, $uid)] = $now;
        $lockService->writeCookie($settings, $cookieMap, $now);

        $this->flushCache($table, $uid);

        $results = GeneralUtility::makeInstance(ResultService::class)->build($settings, $table, $uid, $questions, $now);

        return array(
            'success' => true,
            'lockedUntil' => $now + $settings->lockPeriod,
            'results' => $results['visible'] ? $results : array('visible' => false, 'totalVotes' => $results['totalVotes']),
        );
    }

    /**
     * @param mixed $raw
     * @return array<int,int> valid question uid => value
     */
    protected function filterAnswers($raw, array $questions, int $maxStars): array
    {
        $answers = array();
        if (!is_array($raw)) {
            return $answers;
        }

        foreach ($raw as $questionUid => $value) {
            $questionUid = (int)$questionUid;
            if (!isset($questions[$questionUid]) || !is_scalar($value) || !ctype_digit((string)$value)) {
                continue;
            }
            $value = (int)$value;
            if ($value >= 1 && $value <= $maxStars) {
                $answers[$questionUid] = $value;
            }
        }

        return $answers;
    }

    protected function getLockedUntil(RateSettings $settings, string $table, int $uid, int $now): int
    {
        $lockService = GeneralUtility::makeInstance(LockService::class);

        return $lockService->getLockedUntil(
            $settings,
            $table,
            $uid,
            $lockService->readCookieMap($settings, $now),
            $this->getFeUserUid(),
            $lockService->hashClient((string)GeneralUtility::getIndpEnv('REMOTE_ADDR'), $table, $uid),
            $now
        );
    }

    protected function getFeUserUid(): int
    {
        $feUser = $GLOBALS['TSFE']->fe_user ?? null;

        return is_object($feUser) && is_array($feUser->user) ? (int)($feUser->user['uid'] ?? 0) : 0;
    }

    protected function flushCache(string $table, int $uid): void
    {
        GeneralUtility::makeInstance(CacheManager::class)
            ->flushCachesInGroupByTag('pages', TargetService::cacheTag($table, $uid));
    }

    protected function now(): int
    {
        return (int)($GLOBALS['EXEC_TIME'] ?? time());
    }

    protected function json(array $data): string
    {
        return (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
