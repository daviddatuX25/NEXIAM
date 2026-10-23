<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
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
use function strtoupper;
use function substr;

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
    public const CURRENT_PLAN_VERSION = 'v3';

    public const PLAN_VERSION_V1 = 'v1';

    public const PLAN_VERSION_V2 = 'v2';

    public const PLAN_VERSION_V3 = 'v3';

    public const PLAN_INSTRUCTION_DIGITAL = 'Shade the declared option on every labeled field.';

    public const PLAN_INSTRUCTION_QUICK = 'Quick rehearsal: shade the declared option on the listed fields only.';

    public const PLAN_INSTRUCTION_FULL = 'Full qualification: shade the declared option on every labeled field.';

    public function __construct(
        private readonly OmrProofQrReservationPlanner $qrReservations = new OmrProofQrReservationPlanner,
    ) {}

    public const PLAN_INSTRUCTION_HAND = 'Hand-shaded test: shade only the deterministic first/middle/last sample for each aptitude area (18 marks on the six-area profile).';

    /**
     * Build a deterministic proof plan.
     *
     * `$planVersionOrMethod` keeps the pre-36 positional API (`plan(...,
     * 'v1')`) working while allowing the method-bound API
     * (`plan(..., 'hand_shaded')`). New callers should prefer named arguments
     * when both a plan version and method are supplied.
     */
    public function plan(
        OmrDiagnosticTarget $target,
        ProofPurpose $purpose,
        string $planVersionOrMethod = self::CURRENT_PLAN_VERSION,
        ?string $method = null,
    ): OmrProofPlan {
        if ($method === null && in_array($planVersionOrMethod, OmrProofPlan::METHODS, true)) {
            $method = $planVersionOrMethod;
            $planVersion = self::CURRENT_PLAN_VERSION;
        } else {
            $planVersion = $planVersionOrMethod;
        }
        if ($purpose !== ProofPurpose::Digital && $method === null) {
            // Historical physical callers did not carry a method. Treat those
            // calls as the pre-shaded method while all new request paths pass
            // an explicit method.
            $method = OmrProofPlan::METHOD_PRE_SHADED;
        }
        if ($method !== null && ! in_array($method, OmrProofPlan::METHODS, true)) {
            throw new RuntimeException('plan_method_invalid: method must be pre_shaded or hand_shaded.');
        }
        $compiledPages = $target->compiledPages();
        if ($compiledPages === [] || count($compiledPages) !== $target->pageCount()) {
            throw new OmrDiagnosticTargetException('plan_compiled_pages_invalid', 'Proof planning requires the explicit immutable page list.');
        }
        $registrationMode = $target->registrationMode();
        $qrMode = $target->qrMode();
        $policyHash = hash('sha256', json_encode([
            'registration_mode' => $registrationMode->value,
            'qr_mode' => $qrMode->value,
            'compiled_pages' => $compiledPages,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $sourceKind = method_exists($target, 'sourceKind')
            ? (string) $target->sourceKind()
            : OmrSourcePolicy::SYSTEM_DESIGNED;
        $diagnosticQrReservation = $sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL
            ? []
            : $this->qrReservations->plan($target);

        $seedParts = $planVersion === self::PLAN_VERSION_V1
            ? [$target->artifactHash(), $target->nativeBundleHash(), $purpose->value, $planVersion, $policyHash]
            : [
                $target->artifactHash(),
                $target->nativeBundleHash(),
                $target->manifestHash(),
                $target->configHash(),
                $target->mappingHash(),
                $purpose->value,
                $planVersion,
                $policyHash,
            ];
        if ($method !== null) {
            $seedParts = [
                $target->artifactHash(),
                $target->nativeBundleHash(),
                $target->manifestHash(),
                $target->configHash(),
                $target->mappingHash(),
                $planVersion,
                $method,
                $purpose->value,
                $policyHash,
            ];
        }
        $seed = hash('sha256', implode('|', $seedParts));
        [$labelsByPage, $optionsByLabel] = $this->expand($target);
        $selectedByPage = $method === OmrProofPlan::METHOD_HAND_SHADED
            ? $this->handShadedLabels($target, $labelsByPage)
            : $labelsByPage;

        $expected = [];
        $isUploadedBaseline = $sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL && $method === OmrProofPlan::METHOD_PRE_SHADED;
        if (! $isUploadedBaseline) {
            $counter = 0;
            foreach ($selectedByPage as $pageLabels) {
                foreach ($pageLabels as $label) {
                    if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                        continue;
                    }
                    $options = $optionsByLabel[$label] ?? null;
                    if (! is_array($options) || $options === []) {
                        throw new RuntimeException('plan_options_missing: label '.$label.' has no declared options.');
                    }
                    $balanced = array_values(array_filter(
                        $options,
                        static fn (string $option): bool => in_array($option, ['A', 'B', 'C', 'D'], true),
                    ));
                    // Hand-shaded samples are deliberately A-D balanced even
                    // when the source row declares an additional distractor (for
                    // example MCQ5/E). Pre-shaded plans retain every declared
                    // option's normal deterministic cycle.
                    $choiceOptions = $method === OmrProofPlan::METHOD_HAND_SHADED
                        ? (count($balanced) === 4 ? $balanced : $options)
                        : (count($balanced) >= 4 ? $balanced : $options);
                    $expected[$label] = (string) $choiceOptions[$counter % count($choiceOptions)];
                    $counter++;
                }
            }
        }

        $manifest = $target->reviewManifest();
        $identityMode = (string) (is_array($manifest['identity'] ?? null) ? ($manifest['identity']['mode'] ?? 'none') : 'none');
        $calibration = $this->calibrationDigits($labelsByPage, $seed, $identityMode === 'bubbled_applicant_number');

        $pageInstructions = [];
        foreach ($compiledPages as $page) {
            $pageNo = (int) ($page['page_no'] ?? 0);
            $labels = array_values(array_filter(
                $selectedByPage[$pageNo] ?? [],
                static fn (string $label): bool => ! str_starts_with($label, 'APPLICANT_NUMBER_'),
            ));
            if ($labels === []) {
                throw new RuntimeException('plan_page_fields_missing: every compiled page requires scorable mapped fields.');
            }
            $pageInstructions[] = [
                'page_no' => $pageNo,
                'labels' => $labels,
                'instruction' => match ($purpose) {
                    ProofPurpose::Digital => self::PLAN_INSTRUCTION_DIGITAL,
                    ProofPurpose::QuickRehearsal => $sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL
                        ? ($method === OmrProofPlan::METHOD_HAND_SHADED
                            ? 'Scan or upload a sample sheet with answers filled using pencil or black pen.'
                            : 'Scan or upload a clean, unmarked sheet to verify paper alignment and check for false marks.')
                        : ($method === OmrProofPlan::METHOD_HAND_SHADED
                            ? self::PLAN_INSTRUCTION_HAND
                            : self::PLAN_INSTRUCTION_QUICK),
                    ProofPurpose::FullQualification => $sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL
                        ? ($method === OmrProofPlan::METHOD_HAND_SHADED
                            ? 'Scan or upload a sample sheet with answers filled using pencil or black pen.'
                            : 'Scan or upload a clean, unmarked sheet to verify paper alignment and check for false marks.')
                        : ($method === OmrProofPlan::METHOD_HAND_SHADED
                            ? self::PLAN_INSTRUCTION_HAND
                            : self::PLAN_INSTRUCTION_FULL),
                },
            ];
        }
        if ($pageInstructions === []) {
            throw new RuntimeException('plan_instructions_missing: the target declares no scorable fields to cover.');
        }

        $sampleManifest = [];
        $samplePosition = 1;
        foreach ($selectedByPage as $pageNo => $labels) {
            foreach ($labels as $label) {
                if (! array_key_exists($label, $expected)) {
                    continue;
                }
                $area = null;
                if (preg_match('/^([A-Z0-9]+(?:_[A-Z0-9]+)*)_Q_\d+$/', (string) $label, $matches) === 1) {
                    $area = $matches[1];
                }
                $sampleManifest[] = [
                    'page_no' => (int) $pageNo,
                    'label' => (string) $label,
                    'area' => $area,
                    'position' => $samplePosition++,
                ];
            }
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
            registrationMode: $registrationMode,
            qrMode: $qrMode,
            compiledPages: $compiledPages,
            diagnosticQrReservation: $diagnosticQrReservation,
            method: $method,
            sampleManifest: $sampleManifest,
            allowedMedia: $method === OmrProofPlan::METHOD_HAND_SHADED ? OmrProofPlan::HAND_SHADED_MEDIA : [],
            requiredPageCount: $target->pageCount(),
            expectedVectorHash: hash(
                'sha256',
                json_encode($expected, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ),
            sourceKind: $sourceKind,
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
     * Select the deterministic hand-shaded sample: first, middle, and last
     * item from each aptitude area. Manifests from older bundles do not carry
     * area metadata, so block names (then stable six-way buckets) are used as
     * the server-authored fallback; browser order never participates.
     *
     * @param  array<int, list<string>>  $labelsByPage
     * @return array<int, list<string>>
     */
    private function handShadedLabels(OmrDiagnosticTarget $target, array $labelsByPage): array
    {
        $areaByLabel = [];
        foreach ((array) ($target->reviewManifest()['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null)) {
                continue;
            }
            $area = $field['area_key'] ?? $field['area'] ?? $field['area_code'] ?? $field['section'] ?? null;
            if (! is_string($area) || $area === '') {
                $area = preg_match('/^([A-Z0-9]+(?:_[A-Z0-9]+)*)_Q_\\d+$/', (string) $field['label'], $matches) === 1
                    ? $matches[1]
                    : ($field['block'] ?? null);
            }
            if (is_string($area) && $area !== '') {
                $areaByLabel[(string) $field['label']] = $area;
            }
        }
        foreach ($labelsByPage as $pageNo => $labels) {
            $template = $target->nativeTemplate((int) $pageNo);
            foreach ((array) ($template['fieldBlocks'] ?? []) as $blockName => $block) {
                if (! is_array($block)) {
                    continue;
                }
                $area = $block['aptitudeAreaCode'] ?? $block['area_code'] ?? $block['area'] ?? (string) $blockName;
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    foreach (NativeBundle::expandLabel((string) $declaration) as $label) {
                        $areaByLabel[(string) $label] ??= (string) $area;
                    }
                }
            }
        }

        $groups = [];
        foreach ($labelsByPage as $pageNo => $labels) {
            foreach ($labels as $label) {
                if (str_starts_with($label, 'APPLICANT_NUMBER_') || ! isset($areaByLabel[$label])) {
                    continue;
                }
                $groups[$areaByLabel[$label]][] = ['page_no' => (int) $pageNo, 'label' => $label];
            }
        }
        $groups = array_values(array_map(
            static function (array $items): array {
                $seen = [];
                $unique = [];
                foreach ($items as $item) {
                    $key = ((int) ($item['page_no'] ?? 0)).'|'.((string) ($item['label'] ?? ''));
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $unique[] = $item;
                }

                return $unique;
            },
            $groups,
        ));
        if (count($groups) === 1 && count($groups[0]) >= 6) {
            $all = $groups[0];
            $groups = [];
            $bucketSize = (int) ceil(count($all) / 6);
            for ($offset = 0; $offset < count($all); $offset += max(1, $bucketSize)) {
                $groups[] = array_slice($all, $offset, max(1, $bucketSize));
            }
        }

        $selected = [];
        foreach ($groups as $group) {
            $items = array_values($group);
            if ($items === []) {
                continue;
            }
            $indices = array_values(array_unique([0, intdiv(count($items) - 1, 2), count($items) - 1]));
            foreach ($indices as $index) {
                $item = $items[$index] ?? null;
                if (is_array($item) && is_string($item['label'] ?? null)) {
                    $selected[(int) $item['page_no']][] = $item['label'];
                }
            }
        }
        if ($selected === []) {
            // A malformed/legacy manifest still receives a deterministic,
            // bounded sample rather than an empty unusable physical plan.
            $flat = [];
            foreach ($labelsByPage as $pageNo => $labels) {
                foreach ($labels as $label) {
                    if (! str_starts_with($label, 'APPLICANT_NUMBER_')) {
                        $flat[] = ['page_no' => (int) $pageNo, 'label' => $label];
                    }
                }
            }
            foreach ($flat as $index => $item) {
                if ($index % 3 === 0 || $index === count($flat) - 1) {
                    $selected[$item['page_no']][] = $item['label'];
                }
            }
        }

        foreach ($selected as $pageNo => $labels) {
            $selected[$pageNo] = array_values(array_unique($labels));
        }
        ksort($selected);

        return $selected;
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
