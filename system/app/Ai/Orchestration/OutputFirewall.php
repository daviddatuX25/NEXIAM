<?php

namespace App\Ai\Orchestration;

/**
 * AD-11 defense-in-depth assembler — strips control characters / trims; never authorizes.
 */
final class OutputFirewall
{
    public function assembleAssistantText(string $raw): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? '';
        $clean = trim($clean);

        if ($clean === '') {
            return 'I could not produce a safe reply. Use the ordinary page controls, or try again.';
        }

        return mb_substr($clean, 0, 4000);
    }
}
