<?php

namespace App\ResultSheet\Support;

use Normalizer;

/**
 * Deterministic presentation-only abbreviation for applicant academic history.
 */
final class TemplateShortValue
{
    public const MAP_VERSION = 1;

    /**
     * @return array{value: string, basis: string}
     */
    public static function resolve(?string $text, string $category = 'history'): array
    {
        return self::resolveUsing($text, self::mapping($category));
    }

    /**
     * @param  list<array{key: string, value: string}>  $mapping
     * @return array{value: string, basis: string}
     */
    public static function resolveUsing(?string $text, array $mapping): array
    {
        $raw = trim((string) $text);
        if ($raw === '') {
            return ['value' => '', 'basis' => 'empty'];
        }
        $normalized = self::normalize($raw);
        $matches = [];
        foreach ($mapping as $entry) {
            if (self::normalize($entry['key']) === $normalized) {
                $matches[$entry['value']] = true;
            }
        }
        if (count($matches) === 1) {
            return ['value' => (string) array_key_first($matches), 'basis' => 'mapped'];
        }

        if (preg_match('/^\p{L}{2,16}$/u', $raw) === 1) {
            return ['value' => mb_strtoupper($raw), 'basis' => 'preserved'];
        }

        if (preg_match('/^(?:\p{L}[.-]?)(?:[ .-]+\p{L}[.-]?)+$/u', $raw) === 1) {
            $letters = preg_replace('/[ .-]/u', '', $raw);
            if (is_string($letters) && preg_match('/^\p{L}{2,16}$/u', $letters) === 1) {
                return ['value' => mb_strtoupper($letters), 'basis' => 'preserved'];
            }
        }

        $withoutPossessive = preg_replace('/(?<=\p{L})[’\']s\b/iu', '', $raw) ?? $raw;
        preg_match_all('/[\p{L}\p{N}]+/u', $withoutPossessive, $tokens);
        $words = $tokens[0] ?? [];
        if ($words === []) {
            return ['value' => '', 'basis' => 'empty'];
        }

        $connectives = ['and', 'of', 'the', 'in', 'for'];
        $substantive = array_values(array_filter(
            $words,
            static fn (string $word): bool => ! in_array(mb_strtolower($word), $connectives, true),
        ));
        $words = $substantive === [] ? $words : $substantive;
        $initials = '';
        for ($index = 0, $count = count($words); $index < $count; $index++) {
            $word = $words[$index];
            if (in_array(mb_strtolower($word), ['nc', 'coc'], true)
                && isset($words[$index + 1])
                && preg_match('/^[ivxlcdm]+$/i', $words[$index + 1]) === 1) {
                $initials .= mb_strtoupper($word.$words[++$index]);
            } else {
                $initials .= mb_substr($word, 0, 1);
            }
        }

        return ['value' => mb_strtoupper($initials), 'basis' => 'derived'];
    }

    /**
     * @return array{last_school_enrolled: string, last_school_short: string, strand_or_prev_course_short: string, school_short: string, history_short: string}
     */
    public static function fields(?string $school, ?string $history, string $category = 'history'): array
    {
        $school = (string) $school;
        $history = (string) $history;
        $schoolShort = self::resolve($school, 'school')['value'];
        $historyShort = self::resolve($history, $category)['value'];

        return [
            'last_school_enrolled' => $school,
            'last_school_short' => $schoolShort,
            'strand_or_prev_course_short' => $historyShort,
            'school_short' => $schoolShort,
            'history_short' => $historyShort,
        ];
    }

    /** @return list<array{key: string, value: string}> */
    private static function mapping(string $category): array
    {
        if ($category === 'school') {
            return [
                ['key' => 'ispsc', 'value' => 'ISPSC'],
                ['key' => 'Ilocos Sur Polytechnic State College', 'value' => 'ISPSC'],
            ];
        }

        if (! in_array($category, ['history', 'previous-course'], true)) {
            return [];
        }

        return [
            ['key' => 'Humanities and Social Sciences', 'value' => 'HUMSS'],
            ['key' => 'Humanities and Social Sciences (legacy SHS strand)', 'value' => 'HUMSS'],
            ['key' => 'HUMSS', 'value' => 'HUMSS'],
            ['key' => 'Accountancy, Business and Management', 'value' => 'ABM'],
            ['key' => 'Accountancy, Business and Management (legacy SHS strand)', 'value' => 'ABM'],
            ['key' => 'ABM', 'value' => 'ABM'],
            ['key' => 'Science, Technology, Engineering and Mathematics', 'value' => 'STEM'],
            ['key' => 'Science, Technology, Engineering and Mathematics (legacy SHS strand)', 'value' => 'STEM'],
            ['key' => 'STEM', 'value' => 'STEM'],
            ['key' => 'Bachelor of Science in Computer Engineering', 'value' => 'BSCpE'],
            ['key' => 'BSCpE', 'value' => 'BSCpE'],
        ];
    }

    private static function normalize(string $value): string
    {
        if (class_exists(Normalizer::class)) {
            $value = Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
        }

        return mb_strtolower(preg_replace('/[\s\p{Z}]+/u', ' ', trim($value)) ?? trim($value));
    }
}
