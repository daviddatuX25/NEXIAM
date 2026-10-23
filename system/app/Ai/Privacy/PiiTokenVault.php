<?php

namespace App\Ai\Privacy;

use Illuminate\Support\Facades\Cache;

final class PiiTokenVault
{
    private const TTL_SECONDS = 86_400;

    /**
     * Replace known identities with stable candidate surrogates for this conversation epoch.
     *
     * @param  array<int|string, mixed>  $entityMap  A list of identity strings or a map keyed by identity string.
     */
    public function tokenize(
        string $text,
        string $actorId,
        string $conversationId,
        string $contextEpoch,
        array $entityMap = [],
    ): string {
        $cacheKey = $this->cacheKey($conversationId, $contextEpoch);
        $vault = Cache::get($cacheKey, []);
        $vault = is_array($vault) ? $vault : [];

        $identities = array_is_list($entityMap) ? $entityMap : array_keys($entityMap);
        $identities = array_values(array_unique(array_filter(
            $identities,
            static fn (mixed $identity): bool => is_string($identity) && $identity !== '',
        )));
        usort($identities, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        $identityTokens = array_flip($vault);
        $nextTokenNumber = count($vault) + 1;

        foreach ($identities as $identity) {
            if (str_contains($text, $identity)) {
                $token = $identityTokens[$identity] ?? '[CANDIDATE_'.$nextTokenNumber++.']';
                $vault[$token] = $identity;
                $identityTokens[$identity] = $token;
                $text = str_replace($identity, $token, $text);
            }
        }

        Cache::put($cacheKey, $vault, self::TTL_SECONDS);

        return $text;
    }

    public function detokenize(string $text, string $conversationId, string $contextEpoch): string
    {
        $vault = Cache::get($this->cacheKey($conversationId, $contextEpoch), []);

        if (! is_array($vault) || $vault === []) {
            return $text;
        }

        $surrogates = array_keys($vault);
        usort($surrogates, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($surrogates as $surrogate) {
            $original = $vault[$surrogate];

            if (is_string($original)) {
                $text = str_replace($surrogate, $original, $text);
            }
        }

        return $text;
    }

    public function clear(string $conversationId, string $contextEpoch): void
    {
        Cache::forget($this->cacheKey($conversationId, $contextEpoch));
    }

    private function cacheKey(string $conversationId, string $contextEpoch): string
    {
        return "xiam:pii_vault:{$conversationId}:{$contextEpoch}";
    }
}
