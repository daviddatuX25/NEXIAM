<?php

namespace App\Ai\Orchestration;

use App\Ai\Policy\InputMinimizer;

/**
 * Sanitizes and validates bounded text releases (D-XIAM-03).
 */
final class OutputFirewall
{
    public const MAX_BUFFER_BYTES = 16_384;

    private const MAX_OUTPUT_CHARACTERS = 4_000;

    public function __construct(
        private readonly InputMinimizer $minimizer = new InputMinimizer,
    ) {}

    public function assembleAssistantText(string $raw): ?string
    {
        if (strlen($raw) > self::MAX_BUFFER_BYTES || ! mb_check_encoding($raw, 'UTF-8')) {
            return null;
        }

        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{80}-\x{9F}\p{Cf}]/u', '', $raw);
        if ($clean === null) {
            return null;
        }

        $clean = trim($clean);
        if ($clean === '' || mb_strlen($clean, 'UTF-8') > self::MAX_OUTPUT_CHARACTERS) {
            return null;
        }

        return $clean;
    }

    /**
     * Validates full protected response for grounded/own-data turns.
     * Ensures bounds, UTF-8 validity, and absence of leaked credentials or raw PII.
     */
    public function validateProtectedResponse(string $text): ?string
    {
        $clean = $this->assembleAssistantText($text);
        if ($clean === null) {
            return null;
        }

        if ($this->containsCodeBlockOrScript($clean)) {
            return null;
        }

        if ($this->minimizer->containsCredentials($clean)) {
            return null;
        }

        if ($this->minimizer->containsRawPii($clean)) {
            return null;
        }

        return $clean;
    }

    public function containsCodeBlockOrScript(string $text): bool
    {
        return preg_match('/```\s*(?:php|python|py|javascript|js|typescript|ts|sql|bash|sh|shell|html|css|json|xml|c|cpp|java|go|rust)\b/i', $text) === 1
            || preg_match('/```[\s\S]*?```/', $text) === 1
            || preg_match('/<script\b/i', $text) === 1;
    }
}
