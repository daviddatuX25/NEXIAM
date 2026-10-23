<?php

namespace App\AdmissionEngine\Support;

use InvalidArgumentException;

final class BenchmarkRangeAuthoring
{
    private const RANGE_GROUPS = ['honors', 'regular', 'cutoff'];

    /** @param array<string, mixed> $content @param array<string, string> $types @return array<string, mixed> */
    public static function apply(array $content, array $types): array
    {
        if (! array_key_exists('authoring', $content)) {
            return $content;
        }
        $authoring = $content['authoring'];
        $ranges = self::validate($authoring, $types);
        foreach (self::RANGE_GROUPS as $group) {
            $tier = ['honors' => '1', 'regular' => '2', 'cutoff' => '3'][$group];
            foreach ($ranges[$group] as $featureKey => [$from, $to]) {
                $content['tiers'][$tier]['values'][$featureKey] = $from === null || $to === null
                    ? null
                    : match ($tier) {
                        '1' => $to,
                        '2' => round(((float) $from + (float) $to) / 2, 2),
                        default => $from,
                    };
            }
        }

        return $content;
    }

    /** @param array<string, mixed> $content @param array<string, string> $types */
    public static function assertConsumable(array $content, array $types): void
    {
        if (! array_key_exists('authoring', $content)) {
            return;
        }
        self::assertCanonical($content, $types);
        foreach (self::validate($content['authoring'], $types) as $groupRanges) {
            foreach ($groupRanges as [$from, $to]) {
                if (($from === null) xor ($to === null)) {
                    throw new InvalidArgumentException('A benchmark range with only one endpoint cannot be previewed or published.');
                }
            }
        }
    }

    /** @param array<string, mixed> $content @param array<string, string> $types */
    public static function assertCanonical(array $content, array $types): void
    {
        if (! array_key_exists('authoring', $content)) {
            return;
        }
        $ranges = self::validate($content['authoring'], $types);
        foreach (self::RANGE_GROUPS as $group) {
            $tier = ['honors' => '1', 'regular' => '2', 'cutoff' => '3'][$group];
            foreach ($ranges[$group] as $featureKey => [$from, $to]) {
                $expected = $from === null || $to === null
                    ? null
                    : match ($tier) {
                        '1' => $to,
                        '2' => round(((float) $from + (float) $to) / 2, 2),
                        default => $from,
                    };
                $actual = $content['tiers'][$tier]['values'][$featureKey] ?? null;
                $matches = $expected === null
                    ? $actual === null
                    : is_numeric($actual) && is_finite((float) $actual) && (float) $actual === (float) $expected;
                if (! array_key_exists($featureKey, $content['tiers'][$tier]['values'] ?? []) || ! $matches) {
                    throw new InvalidArgumentException('Canonical benchmark cells no longer match their authored ranges.');
                }
            }
        }
    }

    /** @param array<string, mixed> $content */
    public static function assertPreparationBeforeState(array $content, ?array $previousContent): void
    {
        $preparation = $content['authoring']['preparation'] ?? [];
        if ($preparation === []) {
            return;
        }
        $previousRanges = $previousContent['authoring']['ranges'] ?? [];
        $previousPreparation = $previousContent['authoring']['preparation'] ?? [];
        foreach ($preparation as $featureKey => $entry) {
            if (($previousPreparation[$featureKey] ?? null) === $entry) {
                continue;
            }
            foreach (self::RANGE_GROUPS as $group) {
                $before = $entry['before'][$group];
                $prior = $previousRanges[$group][$featureKey] ?? null;
                if ($prior === [null, null]) {
                    $prior = null;
                }
                if ($before !== $prior) {
                    throw new InvalidArgumentException('Prepared before-values must match the previously saved authored ranges.');
                }
            }
        }
    }

    /** @param mixed $authoring @param array<string, string> $types @return array<string, array<string, array{0: int|float|string|null, 1: int|float|string|null}>> */
    private static function validate(mixed $authoring, array $types): array
    {
        $baseKeys = ['version', 'ranges', 'preferred_strands', 'interests'];
        $version = is_array($authoring) ? ($authoring['version'] ?? null) : null;
        $allowedKeys = $version === 1
            ? $baseKeys
            : [...$baseKeys, 'preparation', 'interest_activities', 'activities_reviewed'];
        if (! is_array($authoring)
            || ! in_array($version, [1, 2], true)
            || array_diff(array_keys($authoring), $allowedKeys) !== []
            || array_diff($baseKeys, array_keys($authoring)) !== []
            || ! is_array($authoring['ranges'] ?? null)
            || array_diff(array_keys($authoring['ranges']), self::RANGE_GROUPS) !== []
            || array_diff(self::RANGE_GROUPS, array_keys($authoring['ranges'])) !== []) {
            throw new InvalidArgumentException('Benchmark range authoring has an unsupported version or shape.');
        }
        foreach (['preferred_strands', 'interests'] as $metadataKey) {
            $values = $authoring[$metadataKey];
            if ($values !== null && (! is_array($values) || ! array_is_list($values))) {
                throw new InvalidArgumentException("Benchmark {$metadataKey} metadata must be null or a list.");
            }
            if (is_array($values) && count($values) !== count(array_unique($values, SORT_REGULAR))) {
                throw new InvalidArgumentException("Benchmark {$metadataKey} metadata must contain unique choices.");
            }
        }
        foreach (($authoring['preferred_strands'] ?? []) as $strand) {
            if (! is_string($strand) || $strand === '' || mb_strlen($strand) > 64) {
                throw new InvalidArgumentException('Preferred strand metadata contains an invalid choice.');
            }
        }
        foreach (($authoring['interests'] ?? []) as $interest) {
            if (! is_string($interest) || ! in_array($interest, ['riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c'], true)) {
                throw new InvalidArgumentException('Interest metadata must use a current RIASEC advisory key.');
            }
        }
        self::validateActivityMetadata($authoring);

        $normalized = [];
        foreach (self::RANGE_GROUPS as $group) {
            $entries = $authoring['ranges'][$group];
            if (! is_array($entries)) {
                throw new InvalidArgumentException('Benchmark range groups must be feature maps.');
            }
            foreach ($entries as $featureKey => $pair) {
                if (! is_string($featureKey) || ($types[$featureKey] ?? null) !== 'numeric'
                    || ! is_array($pair) || array_keys($pair) !== [0, 1]) {
                    throw new InvalidArgumentException('Benchmark ranges may only contain current numeric schema features and endpoint pairs.');
                }
                [$from, $to] = $pair;
                foreach ([$from, $to] as $endpoint) {
                    if ($endpoint !== null && ! self::isRangeNumber($endpoint)) {
                        throw new InvalidArgumentException('Benchmark range endpoints must be finite, within storage bounds, and have at most two decimals.');
                    }
                }
                if ($from !== null && $to !== null && (float) $from > (float) $to) {
                    throw new InvalidArgumentException('Benchmark range From must not exceed To.');
                }
                $normalized[$group][$featureKey] = [$from, $to];
            }
            $normalized[$group] ??= [];
            ksort($normalized[$group], SORT_STRING);
        }
        self::validatePreparation($authoring, $normalized, $types);

        return $normalized;
    }

    /** @param array<string,mixed> $authoring */
    private static function validateActivityMetadata(array $authoring): void
    {
        if (($authoring['version'] ?? null) !== 2) {
            return;
        }
        if (array_key_exists('activities_reviewed', $authoring) && ! is_bool($authoring['activities_reviewed'])) {
            throw new InvalidArgumentException('Activity review state must be an explicit boolean.');
        }
        if (array_key_exists('interest_activities', $authoring)) {
            $activities = $authoring['interest_activities'];
            if ($activities !== null && (! is_array($activities) || ! array_is_list($activities))) {
                throw new InvalidArgumentException('Programme interest activities must be a list or null.');
            }
            if (is_array($activities)) {
                RiasecActivityCatalog::validatePreferences($activities);
            }
        }
    }

    /** @param array<string,mixed> $authoring @param array<string,array<string,array{0:mixed,1:mixed}>> $ranges @param array<string,string> $types */
    private static function validatePreparation(array $authoring, array $ranges, array $types): void
    {
        $preparation = $authoring['preparation'] ?? [];
        if ($preparation === []) {
            return;
        }
        if (($authoring['version'] ?? null) !== 2 || ! is_array($preparation) || array_is_list($preparation)) {
            throw new InvalidArgumentException('Prepared model examples require authoring version 2 and a feature-keyed preparation map.');
        }
        foreach ($preparation as $featureKey => $entry) {
            if (! is_string($featureKey) || ($types[$featureKey] ?? null) !== 'numeric'
                || ! is_array($entry)
                || array_diff(array_keys($entry), ['version', 'common_range', 'before', 'applied_values', 'reviewed']) !== []
                || array_diff(['version', 'common_range', 'before', 'applied_values', 'reviewed'], array_keys($entry)) !== []
                || ($entry['version'] ?? null) !== 1 || ($entry['reviewed'] ?? null) !== true
                || ! is_array($entry['common_range'] ?? null) || array_keys($entry['common_range']) !== [0, 1]
                || ! is_array($entry['before'] ?? null) || array_diff(array_keys($entry['before']), self::RANGE_GROUPS) !== []
                || array_diff(self::RANGE_GROUPS, array_keys($entry['before'])) !== []
                || ! is_array($entry['applied_values'] ?? null) || array_diff(array_keys($entry['applied_values']), self::RANGE_GROUPS) !== []
                || array_diff(self::RANGE_GROUPS, array_keys($entry['applied_values'])) !== []) {
                throw new InvalidArgumentException('Prepared model example provenance has an unsupported shape.');
            }
            [$from, $to] = $entry['common_range'];
            if (! self::isRangeNumber($from) || ! self::isRangeNumber($to) || (float) $from > (float) $to) {
                throw new InvalidArgumentException('Prepared common ranges require finite, ordered endpoints.');
            }
            $expected = ['honors' => $to, 'regular' => round(((float) $from + (float) $to) / 2, 2), 'cutoff' => $from];
            foreach (self::RANGE_GROUPS as $group) {
                $before = $entry['before'][$group];
                if ($before !== null && (! is_array($before) || array_keys($before) !== [0, 1]
                    || ($before[0] !== null && ! self::isRangeNumber($before[0]))
                    || ($before[1] !== null && ! self::isRangeNumber($before[1])))) {
                    throw new InvalidArgumentException('Prepared before-values must be a prior range or explicitly Missing.');
                }
                $applied = $entry['applied_values'][$group];
                if (! self::isRangeNumber($applied) || (float) $applied !== (float) $expected[$group]) {
                    throw new InvalidArgumentException('Prepared values must retain the reviewed proposal values for audit.');
                }
            }
        }
    }

    private static function isRangeNumber(mixed $value): bool
    {
        return (is_int($value) || is_float($value) || is_string($value))
            && is_numeric($value)
            && is_finite((float) $value)
            && abs((float) $value) <= 9999.99
            && (float) number_format((float) $value, 2, '.', '') === (float) $value;
    }
}
