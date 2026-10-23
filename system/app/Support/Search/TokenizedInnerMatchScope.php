<?php

namespace App\Support\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class TokenizedInnerMatchScope
{
    /**
     * Normalizes Unicode, collapses whitespace, deduplicates case-insensitively,
     * preserves original token ordering, and caps total token count.
     *
     * @return list<string>
     */
    public static function tokenize(?string $rawSearch, int $maxTokens = 8): array
    {
        if ($rawSearch === null) {
            return [];
        }

        $normalized = class_exists(\Normalizer::class)
            ? (\Normalizer::normalize($rawSearch, \Normalizer::FORM_C) ?: $rawSearch)
            : $rawSearch;

        $trimmed = trim($normalized);
        if ($trimmed === '') {
            return [];
        }

        $splits = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
        if ($splits === false || $splits === []) {
            return [];
        }

        $seen = [];
        $tokens = [];

        foreach ($splits as $split) {
            $token = trim($split);
            if ($token === '') {
                continue;
            }

            $lower = mb_strtolower($token, 'UTF-8');
            if (isset($seen[$lower])) {
                continue;
            }

            $seen[$lower] = true;
            $tokens[] = $token;

            if (count($tokens) >= $maxTokens) {
                break;
            }
        }

        return $tokens;
    }

    /**
     * Escapes standard SQL LIKE wildcard metacharacters.
     */
    public static function escapeLike(string $token): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $token);
    }

    /**
     * Emits a null-safe, case-insensitive LIKE predicate on a qualified column expression (AND).
     */
    public static function whereColumnLike(EloquentBuilder|QueryBuilder $sub, string $qualifiedColumn, string $likePattern): void
    {
        $sub->whereRaw('LOWER(COALESCE('.$qualifiedColumn.', \'\')) LIKE LOWER(?)', [$likePattern]);
    }

    /**
     * Emits a null-safe, case-insensitive LIKE predicate on a qualified column expression.
     */
    public static function orWhereColumnLike(EloquentBuilder|QueryBuilder $sub, string $qualifiedColumn, string $likePattern): void
    {
        $sub->orWhereRaw('LOWER(COALESCE('.$qualifiedColumn.', \'\')) LIKE LOWER(?)', [$likePattern]);
    }

    /**
     * Applies an inner-match tokenized filter:
     * (token_1 matches field_A OR field_B OR ...) AND (token_2 matches field_A OR field_B OR ...)
     *
     * @param  list<string|Closure(EloquentBuilder|QueryBuilder, string): void>  $fieldMatchers
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        ?string $rawSearch,
        array $fieldMatchers,
        int $maxTokens = 8,
    ): EloquentBuilder|QueryBuilder {
        $tokens = self::tokenize($rawSearch, $maxTokens);
        if ($tokens === [] || $fieldMatchers === []) {
            return $query;
        }

        foreach ($tokens as $token) {
            $pattern = '%'.self::escapeLike($token).'%';

            $query->where(function (EloquentBuilder|QueryBuilder $sub) use ($fieldMatchers, $pattern): void {
                foreach ($fieldMatchers as $matcher) {
                    if ($matcher instanceof Closure) {
                        $matcher($sub, $pattern);
                    } elseif (is_string($matcher) && $matcher !== '') {
                        self::orWhereColumnLike($sub, $matcher, $pattern);
                    }
                }
            });
        }

        return $query;
    }
}
