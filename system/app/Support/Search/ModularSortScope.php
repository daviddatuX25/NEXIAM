<?php

namespace App\Support\Search;

use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ModularSortScope
{
    /**
     * Resolves and bounds sort parameters against an explicit allowlist.
     *
     * @param  array<string, string|Closure(EloquentBuilder|QueryBuilder, 'asc'|'desc'): void>  $allowedSorts
     * @return array{sort: string, dir: 'asc'|'desc'}
     */
    public static function resolve(
        ?string $sortKey,
        ?string $direction,
        array $allowedSorts,
        string $defaultKey,
        string $defaultDir = 'desc',
    ): array {
        $resolvedKey = ($sortKey !== null && array_key_exists($sortKey, $allowedSorts))
            ? $sortKey
            : $defaultKey;

        $dirLower = strtolower(trim((string) $direction));
        $fallbackDir = in_array(strtolower(trim($defaultDir)), ['asc', 'desc'], true)
            ? strtolower(trim($defaultDir))
            : 'desc';

        /** @var 'asc'|'desc' $resolvedDir */
        $resolvedDir = in_array($dirLower, ['asc', 'desc'], true)
            ? $dirLower
            : $fallbackDir;

        return [
            'sort' => $resolvedKey,
            'dir' => $resolvedDir,
        ];
    }

    /**
     * Resolves sort parameters, orders the query via column string or Closure,
     * and deterministically tie-breaks on an optional ID column.
     *
     * @param  array<string, string|Closure(EloquentBuilder|QueryBuilder, 'asc'|'desc'): void>  $allowedSorts
     * @return array{sort: string, dir: 'asc'|'desc'}
     */
    public static function apply(
        EloquentBuilder|QueryBuilder $query,
        ?string $sortKey,
        ?string $direction,
        array $allowedSorts,
        string $defaultKey,
        string $defaultDir = 'desc',
        ?string $tieBreakColumn = 'id',
    ): array {
        $resolved = self::resolve($sortKey, $direction, $allowedSorts, $defaultKey, $defaultDir);
        $key = $resolved['sort'];
        $dir = $resolved['dir'];

        $target = $allowedSorts[$key] ?? $allowedSorts[$defaultKey] ?? null;

        if ($target instanceof Closure) {
            $target($query, $dir);
        } elseif (is_string($target) && $target !== '') {
            $query->orderBy($target, $dir);
        }

        if ($tieBreakColumn !== null && (! is_string($target) || $target !== $tieBreakColumn)) {
            $query->orderBy($tieBreakColumn, $dir);
        }

        return $resolved;
    }
}
