<?php

namespace Database\Seeders;

/**
 * Focused near-production OMR dataset for the default foundation seed.
 *
 * Founder decision 2026-08-17: the default developer dataset must look like
 * production — exactly ONE active answer-sheet template, ONE answer-key
 * booklet, and ONE active pair (the compact 150-item profile). The full
 * synthetic catalog (v1–v5 legacy pairs, draft booklet B, retired v1) remains
 * available for parity and state coverage via `OmrDemoSeeder`, but must not
 * ship in the default reset state.
 */
class OmrFocusedDemoSeeder extends OmrDemoSeeder
{
    public function run(): void
    {
        $this->seedCompactProfile();
    }
}
