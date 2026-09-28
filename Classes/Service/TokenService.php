<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Service;

use Pbn\PbnNewskiatlas27Rate\Configuration\RateSettings;

/**
 * Short-lived form token issued by the uncached status request.
 * It is required for voting, so a cross-site form post (which cannot read the
 * status response) cannot create votes, and bots must wait minSubmitTime.
 */
class TokenService
{
    public const VALID = 'valid';
    public const INVALID = 'token_invalid';
    public const TOO_FAST = 'too_fast';
    public const EXPIRED = 'token_expired';

    public function create(string $table, int $uid, int $now): string
    {
        $nonce = bin2hex(random_bytes(8));
        $payload = $now . ':' . $nonce;

        return $payload . ':' . $this->sign($table, $uid, $payload);
    }

    public function validate(RateSettings $settings, string $token, string $table, int $uid, int $now): string
    {
        $parts = explode(':', $token);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_xdigit($parts[1])) {
            return self::INVALID;
        }

        $payload = $parts[0] . ':' . $parts[1];
        if (!hash_equals($this->sign($table, $uid, $payload), $parts[2])) {
            return self::INVALID;
        }

        $age = $now - (int)$parts[0];
        if ($age < 0 || $age > $settings->tokenLifetime) {
            return self::EXPIRED;
        }
        if ($age < $settings->minSubmitTime) {
            return self::TOO_FAST;
        }

        return self::VALID;
    }

    protected function sign(string $table, int $uid, string $payload): string
    {
        $key = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');
        if ($key === '') {
            throw new \RuntimeException('TYPO3 encryptionKey is empty, rating tokens cannot be signed.', 1759046401);
        }

        return hash_hmac('sha256', $table . '|' . $uid . '|' . $payload, hash('sha256', $key . '|pbn_newskiatlas27_rate_token'));
    }
}
