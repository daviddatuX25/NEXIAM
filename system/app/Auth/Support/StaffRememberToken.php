<?php

declare(strict_types=1);

namespace App\Auth\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class StaffRememberToken
{
    private const MAX_SESSION_VERSION = 9_999_999_999;

    private const MAX_ANCHOR_TIMESTAMP = 9_999_999_999;

    /**
     * @return array{session_version: int, factor_generation: string, interactive_authenticated_at: CarbonImmutable}|null
     */
    public function parse(string $token): ?array
    {
        if (preg_match('/^n2:([1-9][0-9]{0,9}):([0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}):([1-9][0-9]{0,9}):([A-Za-z0-9]{32})$/D', $token, $matches) !== 1) {
            return null;
        }

        $timestamp = (int) $matches[3];
        if ($timestamp > self::MAX_ANCHOR_TIMESTAMP) {
            return null;
        }

        return [
            'session_version' => (int) $matches[1],
            'factor_generation' => $matches[2],
            'interactive_authenticated_at' => CarbonImmutable::createFromTimestampUTC($timestamp),
        ];
    }

    public function issue(int $sessionVersion, string $factorGeneration, CarbonInterface $interactiveAuthenticatedAt): string
    {
        if ($sessionVersion < 1 || $sessionVersion > self::MAX_SESSION_VERSION) {
            throw new InvalidArgumentException('The staff authentication epoch cannot be represented in a remember token.');
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $factorGeneration) !== 1) {
            throw new InvalidArgumentException('A valid staff factor generation is required to issue a remember token.');
        }

        $timestamp = $interactiveAuthenticatedAt->getTimestamp();
        if ($timestamp < 1 || $timestamp > self::MAX_ANCHOR_TIMESTAMP) {
            throw new InvalidArgumentException('The interactive authentication time cannot be represented in a remember token.');
        }

        return 'n2:'.$sessionVersion.':'.$factorGeneration.':'.$timestamp.':'.Str::random(32);
    }
}
