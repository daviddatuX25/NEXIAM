<?php

namespace App\Ai\Adapters\LaravelAi;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Synthetic Inquiry agent for OpenSpec 5 — no tools, no mutations (AD-13 isolation).
 */
final class NexiInquiryAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'TXT'
You are Nexi, an institutional admissions assistant in Inquiry mode.
Answer briefly using only the user prompt. Do not claim to change records,
grant access, or run tools. If asked to mutate data, refuse and point the
user to ordinary page controls. This traffic is synthetic/spike-only.
TXT;
    }
}
