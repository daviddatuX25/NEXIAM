<?php

declare(strict_types=1);

namespace App\Ai\Proposals;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Structural validator for normalized commands and browser review data. */
final class ProposalViewContract
{
    private const OPERATIONS = ['create', 'update', 'transition', 'bulk', 'send'];

    private const VALUE_TYPES = ['missing', 'null', 'text', 'code', 'integer', 'boolean', 'enum', 'date', 'instant', 'interval', 'duration', 'record', 'list'];

    private const MIN_SAFE_INTEGER = -9007199254740991;

    private const MAX_SAFE_INTEGER = 9007199254740991;

    /** @param array<string, mixed> $review
     * @param  array<string, mixed>  $commandInput
     */
    public static function validate(array $review, array $commandInput): void
    {
        self::keys($review, ['version', 'operation', 'title', 'sections', 'preflight', 'coverage']);
        if (($review['version'] ?? null) !== 1 || ! in_array($review['operation'] ?? null, self::OPERATIONS, true) || ! is_string($review['title'] ?? null) || $review['title'] === '') {
            self::invalid();
        }
        self::list($review['sections'] ?? null);
        self::keys($review['preflight'] ?? null, ['outcome', 'checks']);
        if (! in_array($review['preflight']['outcome'] ?? null, ['pass', 'fail', 'noop', 'unknown'], true)) {
            self::invalid();
        }
        self::list($review['preflight']['checks'] ?? null);
        foreach ($review['preflight']['checks'] as $check) {
            self::keys($check, ['key', 'label', 'status', 'detail']);
            self::string($check['key'] ?? null);
            self::string($check['label'] ?? null);
            if (! in_array($check['status'] ?? null, ['pass', 'fail', 'warn', 'noop', 'unknown'], true) || (! is_string($check['detail'] ?? null) && $check['detail'] !== null)) {
                self::invalid();
            }
        }

        $reviewed = [];
        foreach ($review['sections'] as $section) {
            if (! is_array($section) || ! is_string($section['kind'] ?? null)) {
                self::invalid();
            }
            if ($section['kind'] === 'fields') {
                self::keys($section, ['kind', 'key', 'label', 'fields']);
                self::string($section['key']);
                self::string($section['label']);
                self::fields($section['fields'], $reviewed);
            } elseif ($section['kind'] === 'groups') {
                self::keys($section, ['kind', 'key', 'label', 'groups']);
                self::string($section['key']);
                self::string($section['label']);
                self::list($section['groups']);
                foreach ($section['groups'] as $group) {
                    self::keys($group, ['key', 'label', 'context', 'fields', 'preflight']);
                    self::string($group['key']);
                    self::string($group['label']);
                    self::value($group['context']);
                    self::fields($group['fields'], $reviewed);
                    self::keys($group['preflight'], ['outcome', 'checks']);
                    if (! in_array($group['preflight']['outcome'], ['pass', 'fail', 'noop', 'unknown'], true)) {
                        self::invalid();
                    }
                    self::list($group['preflight']['checks']);
                    foreach ($group['preflight']['checks'] as $check) {
                        self::keys($check, ['key', 'label', 'status', 'detail']);
                        self::string($check['key']);
                        self::string($check['label']);
                        if (! in_array($check['status'], ['pass', 'fail', 'warn', 'noop', 'unknown'], true) || (! is_string($check['detail']) && $check['detail'] !== null)) {
                            self::invalid();
                        }
                    }
                }
            } else {
                self::invalid();
            }
        }

        self::coverage($review['coverage'] ?? null, $commandInput, $reviewed);
    }

    /** @return array{key:string,label:string,role:string,change:string,before:array<string,mixed>,after:array<string,mixed>} */
    public static function field(string $key, string $label, array $before, array $after, string $role = 'choice'): array
    {
        $field = ['key' => $key, 'label' => $label, 'role' => $role, 'change' => self::change($before, $after), 'before' => $before, 'after' => $after];
        $discard = [];
        self::fields([$field], $discard);

        return $field;
    }

    /** @param list<array<string, mixed>> $fields
     * @return array{kind:string,key:string,label:string,fields:list<array<string,mixed>>}
     */
    public static function fieldsSection(string $key, string $label, array $fields): array
    {
        $reviewed = [];
        self::fields($fields, $reviewed);

        return ['kind' => 'fields', 'key' => $key, 'label' => $label, 'fields' => $fields];
    }

    /** @param list<array<string, mixed>> $checks
     * @return array{outcome:string,checks:list<array<string,mixed>>}
     */
    public static function preflight(string $outcome, array $checks): array
    {
        $preflight = ['outcome' => $outcome, 'checks' => $checks];
        self::keys($preflight, ['outcome', 'checks']);
        if (! in_array($outcome, ['pass', 'fail', 'noop', 'unknown'], true)) {
            self::invalid();
        }
        self::list($checks);
        foreach ($checks as $check) {
            self::keys($check, ['key', 'label', 'status', 'detail']);
            self::string($check['key'] ?? null);
            self::string($check['label'] ?? null);
            if (! in_array($check['status'] ?? null, ['pass', 'fail', 'warn', 'noop', 'unknown'], true) || (! is_string($check['detail'] ?? null) && $check['detail'] !== null)) {
                self::invalid();
            }
        }

        return $preflight;
    }

    /** @param list<array<string, mixed>>|null $fields
     * @param  array<string, true>  $reviewed
     */
    private static function fields(mixed $fields, array &$reviewed): void
    {
        self::list($fields);
        foreach ($fields as $field) {
            self::keys($field, ['key', 'label', 'role', 'change', 'before', 'after']);
            self::string($field['key'] ?? null);
            self::string($field['label'] ?? null);
            if (! in_array($field['role'] ?? null, ['context', 'choice', 'effect'], true) || ! in_array($field['change'] ?? null, ['unchanged', 'set', 'clear'], true)) {
                self::invalid();
            }
            self::value($field['before'] ?? null);
            self::value($field['after'] ?? null);
            if ($field['change'] !== self::change($field['before'], $field['after'])) {
                self::invalid();
            }
            if (isset($reviewed[$field['key']])) {
                self::invalid();
            }
            $reviewed[$field['key']] = true;
        }
    }

    /** @param array<string, mixed>|null $coverage
     * @param  array<string, mixed>  $command
     * @param  array<string, true>  $reviewed
     */
    private static function coverage(mixed $coverage, array $command, array $reviewed): void
    {
        if (! is_array($coverage) || array_is_list($coverage) || $coverage === []) {
            self::invalid();
        }

        $leaves = array_fill_keys(self::leafPaths($command), true);
        foreach ($coverage as $path => $mapping) {
            if (! is_string($path) || ! isset($leaves[$path]) || ! self::pathExists($command, $path) || ! is_array($mapping) || array_is_list($mapping)) {
                self::invalid();
            }

            if (($mapping['classification'] ?? null) === 'display') {
                self::keys($mapping, ['classification', 'field']);
                if (! is_string($mapping['field'] ?? null) || ! isset($reviewed[$mapping['field']])) {
                    self::invalid();
                }
            } elseif (($mapping['classification'] ?? null) === 'internal') {
                self::keys($mapping, ['classification', 'reason']);
                if (! is_string($mapping['reason'] ?? null) || trim($mapping['reason']) === '') {
                    self::invalid();
                }
            } else {
                self::invalid();
            }
        }

        foreach ($leaves as $path => $_) {
            if (! array_key_exists($path, $coverage)) {
                self::invalid();
            }
        }
    }

    private static function pathExists(array $data, string $path): bool
    {
        if ($path === '' || str_contains($path, '..') || str_contains($path, '[')) {
            return false;
        }

        foreach (explode('.', $path) as $part) {
            if (! is_array($data) || ! array_key_exists($part, $data)) {
                return false;
            }
            $data = $data[$part];
        }

        return true;
    }

    /** @return list<string> */
    private static function leafPaths(array $data, string $prefix = ''): array
    {
        $paths = [];
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value) && ! array_is_list($value) && $value !== []) {
                array_push($paths, ...self::leafPaths($value, $path));
            } else {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /** @param array<string, mixed> $value */
    private static function value(mixed $value): void
    {
        if (! is_array($value) || array_is_list($value) || ! is_string($value['type'] ?? null) || ! in_array($value['type'], self::VALUE_TYPES, true)) {
            self::invalid();
        }
        $type = $value['type'];
        $shape = match ($type) {
            'missing', 'null' => ['type'],
            'text' => ['type', 'value', 'multiline'],
            'code' => ['type', 'value'],
            'integer' => ['type', 'value', 'semantic', 'unit'],
            'boolean' => ['type', 'value', 'true_label', 'false_label'],
            'enum' => ['type', 'code', 'label'],
            'date' => ['type', 'value'],
            'instant' => ['type', 'value', 'precision', 'display_zone'],
            'interval' => ['type', 'start', 'end'],
            'duration' => ['type', 'value', 'unit'],
            'record' => ['type', 'label', 'context'],
            'list' => ['type', 'items', 'empty_label'],
        };
        self::keys($value, $shape);
        match ($type) {
            'missing', 'null' => null,
            'text' => self::textValue($value),
            'code' => self::string($value['value'] ?? null),
            'integer' => self::integerValue($value),
            'boolean' => self::booleanValue($value),
            'enum' => self::enumValue($value),
            'date' => self::dateValue($value['value'] ?? null),
            'instant' => self::instantValue($value),
            'interval' => self::intervalValue($value),
            'duration' => self::durationValue($value),
            'record' => self::recordValue($value),
            'list' => self::listValue($value),
        };
    }

    private static function textValue(array $value): void
    {
        self::string($value['value'] ?? null);
        if (! is_bool($value['multiline'] ?? null)) {
            self::invalid();
        }
    }

    private static function integerValue(array $value): void
    {
        $val = $value['value'] ?? null;
        if (
            ! is_int($val)
            || $val < self::MIN_SAFE_INTEGER
            || $val > self::MAX_SAFE_INTEGER
            || ! in_array($value['semantic'] ?? null, ['count', 'raw_score', 'integer'], true)
            || (! is_string($value['unit'] ?? null) && $value['unit'] !== null)
        ) {
            self::invalid();
        }
    }

    private static function booleanValue(array $value): void
    {
        if (! is_bool($value['value'] ?? null)) {
            self::invalid();
        }
        self::string($value['true_label'] ?? null);
        self::string($value['false_label'] ?? null);
    }

    private static function enumValue(array $value): void
    {
        self::nonEmptyString($value['code'] ?? null);
        self::nonEmptyString($value['label'] ?? null);
    }

    private static function dateValue(mixed $value): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            self::invalid();
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            self::invalid();
        }
    }

    private static function instantValue(array $value): DateTimeImmutable
    {
        $instant = $value['value'] ?? null;
        if (! is_string($instant) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $instant) || ($value['precision'] ?? null) !== 'second' || ($value['display_zone'] ?? null) !== 'Asia/Manila') {
            self::invalid();
        }
        $normalizedInput = str_ends_with($instant, 'Z') ? substr($instant, 0, -1).'+00:00' : $instant;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $normalizedInput);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d\TH:i:sP') !== $normalizedInput) {
            self::invalid();
        }

        return $date;
    }

    private static function intervalValue(array $value): void
    {
        self::value($value['start'] ?? null);
        self::value($value['end'] ?? null);
        if (($value['start']['type'] ?? null) !== 'instant' || ($value['end']['type'] ?? null) !== 'instant') {
            self::invalid();
        }

        $start = self::instantValue($value['start']);
        $end = self::instantValue($value['end']);
        if ($start > $end) {
            self::invalid();
        }
    }

    private static function durationValue(array $value): void
    {
        $val = $value['value'] ?? null;
        if (! is_int($val) || $val < 0 || $val > self::MAX_SAFE_INTEGER || ($value['unit'] ?? null) !== 'minute') {
            self::invalid();
        }
    }

    private static function recordValue(array $value): void
    {
        self::nonEmptyString($value['label'] ?? null);
        self::list($value['context'] ?? null);
        foreach ($value['context'] as $part) {
            self::string($part);
        }
    }

    private static function listValue(array $value): void
    {
        self::list($value['items'] ?? null);
        self::string($value['empty_label'] ?? null);
        foreach ($value['items'] as $item) {
            self::value($item);
        }
    }

    private static function change(array $before, array $after): string
    {
        self::value($before);
        self::value($after);
        if ($before === $after) {
            return 'unchanged';
        }
        if (($before['type'] ?? null) === 'missing' && ($after['type'] ?? null) !== 'missing') {
            return 'set';
        }
        if (($after['type'] ?? null) === 'missing' || ($after['type'] ?? null) === 'null') {
            return 'clear';
        }

        return 'set';
    }

    private static function keys(mixed $value, array $required): void
    {
        if (! is_array($value) || array_is_list($value)) {
            self::invalid();
        }
        foreach ($required as $key) {
            if (! array_key_exists($key, $value)) {
                self::invalid();
            }
        }
        if (array_diff(array_keys($value), $required) !== []) {
            self::invalid();
        }
    }

    private static function list(mixed $value): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            self::invalid();
        }
    }

    private static function string(mixed $value): void
    {
        if (! is_string($value)) {
            self::invalid();
        }
    }

    private static function nonEmptyString(mixed $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            self::invalid();
        }
    }

    private static function invalid(): never
    {
        throw new InvalidArgumentException('proposal_view_contract_invalid');
    }
}
