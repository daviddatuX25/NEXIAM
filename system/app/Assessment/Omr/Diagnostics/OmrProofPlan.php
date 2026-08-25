<?php

namespace App\Assessment\Omr\Diagnostics;

use RuntimeException;

use function array_key_exists;
use function array_keys;
use function array_values;
use function hash;
use function in_array;
use function is_int;
use function is_string;
use function json_encode;
use function ksort;
use function str_starts_with;
use function strtoupper;

/**
 * Immutable deterministic proof plan (OpenSpec 35.13, AD-3).
 *
 * Binds one target's hashes, purpose, and plan version into a canonical plan
 * hash so digital proof, quick rehearsal, and full qualification share one
 * expected vector and cannot drift. `expectedVector` maps expanded native
 * field labels to their declared option value; `calibrationDigits` carries
 * expected applicant-number digits ONLY when the target maps an identity
 * grid. No applicant identity is fabricated by this object.
 */
final readonly class OmrProofPlan
{
    /**
     * @param  array<string, string>  $expectedVector  native label => declared option value
     * @param  array<string, int>  $calibrationDigits  APPLICANT_NUMBER_<position> => digit (empty when no identity grid)
     * @param  list<array{page_no: int, labels: list<string>, instruction: string}>  $pageInstructions
     * @param  array<string, string>  $targetHashes  artifact|native_bundle|manifest|config|mapping hashes the plan pins
     */
    public function __construct(
        public string $planVersion,
        public ProofPurpose $purpose,
        public string $seed,
        public string $challengeCode,
        public array $expectedVector,
        public array $calibrationDigits,
        public array $pageInstructions,
        public array $targetHashes,
    ) {
        $this->validateShape();
    }

    /** Canonical plan hash over every plan field (assets excluded). */
    public function planHash(): string
    {
        return hash('sha256', json_encode($this->canonicalPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Expected vector restricted to the labels declared for one page.
     *
     * @return array<string, string>
     */
    public function expectedVectorForPage(int $pageNo): array
    {
        $labels = [];
        foreach ($this->pageInstructions as $instruction) {
            if ((int) ($instruction['page_no'] ?? 0) === $pageNo) {
                foreach ((array) ($instruction['labels'] ?? []) as $label) {
                    if (is_string($label)) {
                        $labels[] = $label;
                    }
                }
            }
        }

        return array_filter($this->expectedVector, static fn (string $label): bool => in_array($label, $labels, true), ARRAY_FILTER_USE_KEY);
    }

    /**
     * True when every pinned target hash equals the given target hashes —
     * the binding check before a run may consume this plan.
     *
     * @param  array<string, string>  $targetHashes
     */
    public function bindsTo(array $targetHashes): bool
    {
        foreach ($this->targetHashes as $name => $hash) {
            if (! array_key_exists($name, $targetHashes) || $targetHashes[$name] !== $hash) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function canonicalPayload(): array
    {
        $instructions = array_map(
            static fn (array $instruction): array => [
                'page_no' => (int) ($instruction['page_no'] ?? 0),
                'labels' => array_values(array_map('strval', (array) ($instruction['labels'] ?? []))),
                'instruction' => (string) ($instruction['instruction'] ?? ''),
            ],
            $this->pageInstructions,
        );
        $expected = $this->expectedVector;
        ksort($expected);
        $digits = $this->calibrationDigits;
        ksort($digits);
        $targetHashes = $this->targetHashes;
        ksort($targetHashes);

        return [
            'plan_version' => $this->planVersion,
            'purpose' => $this->purpose->value,
            'seed' => $this->seed,
            'challenge_code' => $this->challengeCode,
            'expected_vector' => $expected,
            'calibration_digits' => $digits,
            'page_instructions' => $instructions,
            'target_hashes' => $targetHashes,
        ];
    }

    private function validateShape(): void
    {
        if ($this->planVersion === '' || $this->seed === '' || $this->challengeCode === '') {
            throw new RuntimeException('plan_incomplete: plan version, seed, and challenge code are required.');
        }
        foreach ($this->expectedVector as $label => $value) {
            if (! is_string($label) || ! is_string($value) || $label === '' || $value === '') {
                throw new RuntimeException('plan_expected_vector_invalid: expected vector must map labels to option values.');
            }
        }
        foreach ($this->calibrationDigits as $label => $digit) {
            if (! is_string($label) || ! is_int($digit) || $digit < 0 || $digit > 9) {
                throw new RuntimeException('plan_calibration_digits_invalid: calibration digits must be 0-9 per label.');
            }
            if (strtoupper($label) !== $label || ! str_starts_with($label, 'APPLICANT_NUMBER_')) {
                throw new RuntimeException('plan_calibration_digits_invalid: calibration digits apply only to applicant-number labels.');
            }
        }
        if ($this->pageInstructions === []) {
            throw new RuntimeException('plan_instructions_missing: at least one page instruction is required.');
        }
        $pages = [];
        $covered = [];
        foreach ($this->pageInstructions as $instruction) {
            $pageNo = (int) ($instruction['page_no'] ?? 0);
            if ($pageNo < 1 || isset($pages[$pageNo])) {
                throw new RuntimeException('plan_instructions_invalid: page instructions must be unique, positive page numbers.');
            }
            $pages[$pageNo] = true;
            $labels = (array) ($instruction['labels'] ?? []);
            if ($labels === []) {
                throw new RuntimeException('plan_instructions_invalid: every page instruction must declare its labels.');
            }
            foreach ($labels as $label) {
                if (! is_string($label) || $label === '') {
                    throw new RuntimeException('plan_instructions_invalid: instruction labels must be non-empty strings.');
                }
                if (! array_key_exists($label, $this->expectedVector)) {
                    throw new RuntimeException('plan_instructions_invalid: instruction label is not in the expected vector.');
                }
                $covered[] = $label;
            }
        }
        foreach (array_keys($this->expectedVector) as $label) {
            if (! in_array($label, $covered, true)) {
                throw new RuntimeException('plan_instructions_invalid: expected label is not covered by any page instruction.');
            }
        }
        foreach (['artifact', 'native_bundle', 'manifest', 'config', 'mapping'] as $name) {
            if (! is_string($this->targetHashes[$name] ?? null) || $this->targetHashes[$name] === '') {
                throw new RuntimeException('plan_target_hashes_invalid: artifact/native_bundle/manifest/config/mapping hashes are required.');
            }
        }
    }
}
