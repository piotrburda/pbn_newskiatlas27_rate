<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Service;

use Pbn\PbnNewskiatlas27Rate\Configuration\RateSettings;
use Pbn\PbnNewskiatlas27Rate\Domain\Repository\VoteRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Layered vote lock:
 *  1. logged-in frontend user (fe_users.uid)          - exact
 *  2. signed cookie with vote times per record        - exact per browser
 *  3. HMAC hash of anonymised IP + record, with limit - catches cookie removal / incognito
 *
 * No raw IP address is stored. IPv4 is used as is, IPv6 is shortened to its /64 prefix
 * (privacy extensions rotate the lower 64 bits). The hash also contains the record,
 * so hashes of different resorts cannot be linked with each other.
 */
class LockService
{
    private const HMAC_COOKIE = 'pbn_newskiatlas27_rate_cookie';
    private const HMAC_IP = 'pbn_newskiatlas27_rate_ip';
    private const MAX_COOKIE_ENTRIES = 60;

    /** @var VoteRepository */
    protected $voteRepository;

    public function __construct(?VoteRepository $voteRepository = null)
    {
        $this->voteRepository = $voteRepository ?? GeneralUtility::makeInstance(VoteRepository::class);
    }

    /**
     * Returns the unix time until which the visitor is locked for the record, 0 = not locked.
     */
    public function getLockedUntil(
        RateSettings $settings,
        string $table,
        int $uid,
        array $cookieMap,
        int $feUser,
        string $ipHash,
        int $now
    ): int {
        $since = $now - $settings->lockPeriod;
        $lockedUntil = 0;

        $cookieTime = (int)($cookieMap[self::targetKey($table, $uid)] ?? 0);
        if ($cookieTime > $since) {
            $lockedUntil = max($lockedUntil, $cookieTime + $settings->lockPeriod);
        }

        $feUserTime = $this->voteRepository->findLatestVoteTimeForFeUser($feUser, $table, $uid, $since);
        if ($feUserTime > $since) {
            $lockedUntil = max($lockedUntil, $feUserTime + $settings->lockPeriod);
        }

        if ($settings->ipLimit > 0 && $ipHash !== '') {
            $times = $this->voteRepository->findRecentVoteTimesForIpHash(
                $ipHash,
                $table,
                $uid,
                $since,
                $settings->ipLimit
            );
            if (count($times) >= $settings->ipLimit) {
                // Newest first: the limit frees up when the oldest of these expires.
                $lockedUntil = max($lockedUntil, (int)end($times) + $settings->lockPeriod);
            }
        }

        return $lockedUntil > $now ? $lockedUntil : 0;
    }

    public static function targetKey(string $table, int $uid): string
    {
        return substr(hash('sha256', $table . ':' . $uid), 0, 16);
    }

    public function hashClient(string $ip, string $table, int $uid): string
    {
        $normalized = self::normalizeIp($ip);
        if ($normalized === '') {
            return '';
        }

        return hash_hmac('sha256', $normalized . '|' . $table . '|' . $uid, $this->secret(self::HMAC_IP));
    }

    public static function normalizeIp(string $ip): string
    {
        $packed = @inet_pton(trim($ip));
        if ($packed === false) {
            return '';
        }

        if (strlen($packed) === 16) {
            // IPv4-mapped IPv6 (::ffff:a.b.c.d) -> IPv4
            if (substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
                return (string)inet_ntop(substr($packed, 12));
            }

            return (string)inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8));
        }

        return (string)inet_ntop($packed);
    }

    /**
     * @return array<string, int> target key => vote time
     */
    public function decodeCookie(string $value, int $now, int $lockPeriod): array
    {
        $parts = explode('.', $value, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return array();
        }

        $expected = hash_hmac('sha256', $parts[0], $this->secret(self::HMAC_COOKIE));
        if (!hash_equals($expected, $parts[1])) {
            return array();
        }

        $json = base64_decode(strtr($parts[0], '-_', '+/'), true);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data)) {
            return array();
        }

        return $this->prune($data, $now, $lockPeriod);
    }

    public function encodeCookie(array $map, int $now, int $lockPeriod): string
    {
        $map = $this->prune($map, $now, $lockPeriod);
        $payload = rtrim(strtr(base64_encode((string)json_encode($map)), '+/', '-_'), '=');

        return $payload . '.' . hash_hmac('sha256', $payload, $this->secret(self::HMAC_COOKIE));
    }

    public function readCookieMap(RateSettings $settings, int $now): array
    {
        $value = $_COOKIE[$settings->cookieName] ?? '';

        return is_string($value) ? $this->decodeCookie($value, $now, $settings->lockPeriod) : array();
    }

    public function writeCookie(RateSettings $settings, array $map, int $now): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie($settings->cookieName, $this->encodeCookie($map, $now, $settings->lockPeriod), array(
            'expires' => $now + $settings->lockPeriod,
            'path' => '/',
            'secure' => (bool)GeneralUtility::getIndpEnv('TYPO3_SSL'),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    /**
     * Keeps only valid, still running entries (newest first, limited count).
     */
    protected function prune(array $map, int $now, int $lockPeriod): array
    {
        $since = $now - $lockPeriod;
        $clean = array();
        foreach ($map as $key => $time) {
            if (is_string($key) && preg_match('/^[a-f0-9]{16}$/', $key) && (int)$time > $since && (int)$time <= $now + 60) {
                $clean[$key] = (int)$time;
            }
        }
        arsort($clean);

        return array_slice($clean, 0, self::MAX_COOKIE_ENTRIES, true);
    }

    protected function secret(string $purpose): string
    {
        $key = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        if ($key === '') {
            throw new \RuntimeException('TYPO3 encryptionKey is empty, rating locks cannot be signed.', 1759046400);
        }

        return hash('sha256', $key . '|' . $purpose);
    }
}
