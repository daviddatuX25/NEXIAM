<?php

namespace App\Ai\Policy;

use App\Admission\Support\ApplicantAccessKey;
use RuntimeException;

/**
 * First-party detector and redactor for PII and credentials (D-XIAM-03).
 * Redacts raw LRN, phone, and email into safe placeholders while preserving
 * safe domain identifiers (EX-*, APP-*, SYN-*, RM-*).
 * Refuses applicant access keys and raw credentials with credential_input_refused.
 */
final class InputMinimizer
{
    public const REDACTED_LRN = '[REDACTED_LRN]';

    public const REDACTED_PHONE = '[REDACTED_PHONE]';

    public const REDACTED_EMAIL = '[REDACTED_EMAIL]';

    /**
     * @throws RuntimeException
     */
    public function assertNoCredentials(string $text): void
    {
        if ($this->containsCredentials($text)) {
            throw new RuntimeException('credential_input_refused');
        }
    }

    public function containsCredentials(string $text): bool
    {
        $text = preg_replace('/\p{Cf}+/u', '', $text) ?? $text;

        if (preg_match('/\bapp_key_[A-Za-z0-9_\-]+\b/i', $text) === 1) {
            return true;
        }

        if (preg_match('/\b(?:password|passwd|pwd|access[_\s-]?key|access[_\s-]?token|api[_\s-]?key|auth[_\s-]?token|secret[_\s-]?key)\s*[:=]\s*\S+/i', $text) === 1) {
            return true;
        }

        if (preg_match('/\bmy\s+(?:access[_\s-]?key|access[_\s-]?token|password)\s+(?:is|=)\s*\S+/i', $text) === 1) {
            return true;
        }

        if (preg_match('/\b(?:sk|pk)_(?:live|test)_[0-9a-zA-Z]+\b/', $text) === 1) {
            return true;
        }

        if (preg_match('/\bBearer\s+[A-Za-z0-9_\-\.]{16,}\b/i', $text) === 1) {
            return true;
        }

        if (preg_match_all('/(?<![A-Za-z0-9])[A-HJ-NP-Z2-9]{4}\s*[-_\/]\s*[A-HJ-NP-Z2-9]{4}(?![A-Za-z0-9])/i', $text, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $upper = strtoupper((string) preg_replace('/\s+/', '', (string) $match));
                if (
                    str_starts_with($upper, 'EX-')
                    || str_starts_with($upper, 'APP-')
                    || str_starts_with($upper, 'SYN-')
                    || str_starts_with($upper, 'RM-')
                ) {
                    continue;
                }
                $normalized = (string) preg_replace('/[^A-Za-z0-9]/', '', $upper);
                $hasDigit = preg_match('/[2-9]/', $normalized) === 1;
                $isExplicitUppercase = (string) $match === strtoupper((string) $match);
                if (($hasDigit || $isExplicitUppercase) && ApplicantAccessKey::isValidShape($normalized)) {
                    return true;
                }
            }
        }

        if (preg_match('/\b(?:access[_\s-]?key|key|code|token|login)\b(?:\s+(?:is|equals))?[\s:=#-]{0,12}([A-HJ-NP-Z2-9]{8})\b/i', $text, $keyMatch) === 1) {
            $candidate = strtoupper((string) ($keyMatch[1] ?? ''));
            if (preg_match('/[2-9]/', $candidate) === 1 && ApplicantAccessKey::isValidShape($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact raw PII while preserving safe domain codes.
     */
    public function minimize(string $text): string
    {
        $text = preg_replace('/\p{Cf}+/u', '', $text) ?? $text;

        // 1. Redact email addresses
        $minimized = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', self::REDACTED_EMAIL, $text) ?? $text;

        // 2. Redact Philippine mobile numbers (09XXXXXXXXX or +639XXXXXXXXX, with optional separators)
        $minimized = preg_replace('/(?<!\w)(?:\+63\s*9\d{2}[-\s]?\d{3}[-\s]?\d{4}|\+63[-\s]?9\d{9}|09\d{2}[-\s]?\d{3}[-\s]?\d{4}|09\d{9})\b/', self::REDACTED_PHONE, $minimized) ?? $minimized;

        // 3. Redact raw 12-digit LRN (not part of hyphenated domain codes)
        $minimized = preg_replace('/(?<![A-Za-z0-9_-])\d{12}(?![A-Za-z0-9_-])/', self::REDACTED_LRN, $minimized) ?? $minimized;

        return $minimized;
    }

    /**
     * Check if text contains raw unredacted PII (12-digit LRN, phone, or email).
     */
    public function containsRawPii(string $text): bool
    {
        $text = preg_replace('/\p{Cf}+/u', '', $text) ?? $text;

        if (preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '', $text) !== $text) {
            return true;
        }

        if (preg_replace('/(?<!\w)(?:\+63\s*9\d{2}[-\s]?\d{3}[-\s]?\d{4}|\+63[-\s]?9\d{9}|09\d{2}[-\s]?\d{3}[-\s]?\d{4}|09\d{9})\b/', '', $text) !== $text) {
            return true;
        }

        if (preg_replace('/(?<![A-Za-z0-9_-])\d{12}(?![A-Za-z0-9_-])/', '', $text) !== $text) {
            return true;
        }

        return false;
    }
}
