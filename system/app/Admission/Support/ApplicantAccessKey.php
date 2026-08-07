<?php

namespace App\Admission\Support;

/**
 * Paper-friendly applicant access keys (D-085).
 * Format display: XXXX-XXXX (8 unambiguous chars). Hash uses normalized form (no hyphen).
 */
final class ApplicantAccessKey
{
    public const DISPLAY_LENGTH = 9; // including hyphen

    public const RAW_LENGTH = 8;

    /** Crockford-like alphabet: no 0/O/1/I/L. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function mint(): string
    {
        $raw = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < self::RAW_LENGTH; $i++) {
            $raw .= self::ALPHABET[random_int(0, $max)];
        }

        return self::format($raw);
    }

    public static function normalize(string $key): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '');

        return $normalized;
    }

    public static function format(string $rawOrDisplay): string
    {
        $raw = self::normalize($rawOrDisplay);
        if (strlen($raw) !== self::RAW_LENGTH) {
            return $raw;
        }

        return substr($raw, 0, 4).'-'.substr($raw, 4, 4);
    }

    public static function hash(string $key): string
    {
        return hash('sha256', self::normalize($key));
    }

    public static function isValidShape(string $key): bool
    {
        $normalized = self::normalize($key);

        if (strlen($normalized) !== self::RAW_LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[A-Z2-9]+$/', $normalized);
    }
}
