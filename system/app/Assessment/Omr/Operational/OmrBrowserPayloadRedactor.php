<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Operational;

final class OmrBrowserPayloadRedactor
{
    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function redact(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && self::forbiddenKey($key)) {
                continue;
            }

            $safe[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $safe;
    }

    private static function forbiddenKey(string $key): bool
    {
        $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($key));
        if (! is_string($normalized) || $normalized === '') {
            return false;
        }

        return str_contains($normalized, 'answerkey')
            || str_contains($normalized, 'correctoption')
            || str_contains($normalized, 'expectedoption')
            || str_contains($normalized, 'expectedset')
            || str_contains($normalized, 'qualification')
            || str_contains($normalized, 'projectedscore')
            || in_array($normalized, ['setcode', 'observedsetcode', 'materialsetid', 'bookletsetid', 'score'], true);
    }
}
