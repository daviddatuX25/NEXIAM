<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\NativeBundle;
use RuntimeException;

use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function hash;
use function hexdec;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function str_starts_with;
use function substr;
use function strtoupper;

/**
 * One deterministic proof planner (OpenSpec 35.17, AD-3).
 *
 * Derives an {@see OmrProofPlan} from any {@see OmrDiagnosticTarget}: native
 * labels are expanded through {@see NativeBundle::expandLabel}, the seed is
 * the artifact/native-bundle hash plus purpose and plan version, declared
 * options are balanced across every expanded label, every page is covered by
 * a page instruction, and calibration digits are generated ONLY when the
 * target maps an applicant-number identity grid. The same target and purpose
 * always produce the same plan hash; no identity is ever fabricated.
 */
final class OmrProofPlanner
{
    public const PLAN_INSTRUCTION_DIGITAL = 'Shade the declared option on every labeled field.';
    public const PLAN_INSTRUCTION_QUICK = 'Quick rehearsal: shade the declared option on the listed fields only.';
    public const PLAN_INSTRUCTION_FULL = 'Full qualification: shade the declared option on every labeled field.';

    public function plan(OmrDiagnosticTarget $target, ProofPurpose $purpose, string $planVersion = 'v1'): OmrProofPlan
    {
        $seed = hash('sha256', implode('|', [
            $target->artifactHash(),
            $target->nativeBundleHash(),
            $purpose->value,
            $planVersion,
        ]));

        [$labelsByPage, $optionsByLabel] = $this->expand($target);

        $expected = [];
        $counter = 0;
        foreach ($labelsByPage as $pageLabels) {
            foreach ($pageLabels as $label) {
                if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                    continue;
                }
                $options = $optionsByLabel[$label] ?? null;
                if (! is_array($options) || $options === []) {
                    throw new RuntimeException('plan_options_missing: label '.$label.' has no declared options.');
                }
                $expected[$label] = (string) $options[$counter % count($options)];
                $counter++;
            }
        }

        $manifest = $target->reviewManifest();
        $identityMode = (string) (is_array($manifest['identity'] ?? null) ? ($manifest['identity']['mode'] ?? 'none') : 'none');
        $calibration = $this->calibrationDigits($labelsByPage, $seed, $identityMode === 'bubbled_applicant_number');

        $pageInstructions = [];
        for ($pageNo = 1; $pageNo <= $target->pageCount(); $pageNo++) {
            $labels = array_values(array_filter(
                $labelsByPage[$pageNo] ?? [],
                static fn (string $label): bool => ! str_starts_with($label, 'APPLICANT_NUMBER_'),
            ));
            if ($labels === []) {
                continue;
            }
            $pageInstructions[] = [
                'page_no' => $pageNo,
                'labels' => $labels,
                'instruction' => match ($purpose) {
                    ProofPurpose::Digital => self::PLAN_INSTRUCTION_DIGITAL,
                    ProofPurpose::QuickRehearsal => self::PLAN_INSTRUCTION_QUICK,
                    ProofPurpose::FullQualification => self::PLAN_INSTRUCTION_FULL,
                },
            ];
        }
        if ($pageInstructions === []) {
            throw new RuntimeException('plan_instructions_missing: the target declares no scorable fields to cover.');
        }

        return new OmrProofPlan(
            planVersion: $planVersion,
            purpose: $purpose,
            seed: $seed,
            challengeCode: 'CHAL-'.strtoupper(substr(hash('sha256', $seed), 0, 10)),
            expectedVector: $expected,
            calibrationDigits: $calibration,
            pageInstructions: $pageInstructions,
            targetHashes: [
                'artifact' => $target->artifactHash(),
                'native_bundle' => $target->nativeBundleHash(),
                'manifest' => $target->manifestHash(),
                'config' => $target->configHash(),
                'mapping' => $target->mappingHash(),
            ],
        );
    }

    /**
     * Expand every block declaration per page and resolve each label's
     * declared options from the review manifest (the manifest is the single
     * declared-option authority and is derived from the same native blocks).
     *
     * @return array{0: array<int, list<string>>, 1: array<string, list<string>>}
     */
    private function expand(OmrDiagnosticTarget $target): array
    {
        $labelsByPage = [];
        $optionsByLabel = [];
        $manifestFields = (array) ($target->reviewManifest()['fields'] ?? []);
        foreach ($manifestFields as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null) || ! is_string($field['option'] ?? null)) {
                continue;
            }
            $optionsByLabel[(string) $field['label']][] = (string) $field['option'];
        }

        for ($pageNo = 1; $pageNo <= $target->pageCount(); $pageNo++) {
            $template = $target->nativeTemplate($pageNo);
            $labels = [];
            foreach ((array) ($template['fieldBlocks'] ?? []) as $block) {
                if (! is_array($block)) {
                    continue;
                }
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    foreach (NativeBundle::expandLabel((string) $declaration) as $label) {
                        $labels[] = (string) $label;
                    }
                }
            }
            $labelsByPage[$pageNo] = array_values(array_unique($labels));
        }

        return [$labelsByPage, $optionsByLabel];
    }

    /**
     * Deterministic applicant-number digits for the target's mapped identity
     * grid. Empty unless an applicant-number identity grid is mapped — no
     * fabricated identity.
     *
     * @param  array<int, list<string>>  $labelsByPage
     * @return array<string, int>
     */
    private function calibrationDigits(array $labelsByPage, string $seed, bool $identityMapped): array
    {
        if (! $identityMapped) {
            return [];
        }
        $digits = [];
        foreach ($labelsByPage as $pageLabels) {
            foreach ($pageLabels as $label) {
                if (preg_match('/^APPLICANT_NUMBER_(\d+)$/', (string) $label, $matches) !== 1) {
                    continue;
                }
                $position = (int) $matches[1];
                $byte = hexdec(substr(hash('sha256', $seed.'|identity|'.$position), 0, 2));
                $digits[(string) $label] = $byte % 10;
            }
        }
        ksort($digits);

        return $digits;
    }
}
