<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionIssue;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrDocumentRectification;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrTemplateGeometryProjector;

use function array_filter;
use function array_map;
use function count;
use function hash;
use function is_array;
use function strtoupper;

/**
 * The one diagnostic kernel (OpenSpec 35.15, AD-1).
 *
 * Verifies one capture against any {@see OmrDiagnosticTarget} by delegating
 * ONLY to the shared {@see OmrDiagnosticRequestBuilder} (via the target
 * adapter), {@see OmrLayoutVerificationPort}, and {@see OmrDetectionPort}.
 * Response hash/HMAC validation is inherited from the real worker seam
 * (`OmrCheckerDetection`); unsafe-shift gates reuse the builder's pitch math;
 * no detector, threshold, or geometry implementation lives here.
 *
 * Qualification refuses early: a full-qualification plan against a
 * non-qualification-capable (candidate) target is a typed failure before any
 * worker call, and a plan whose pinned hashes differ from the target is
 * rejected as `plan_target_mismatch` (AD-2/AD-7).
 */
final class OmrTemplateDiagnosticRunner
{
    public function __construct(
        private readonly OmrDiagnosticRequestBuilder $requests,
        private readonly OmrLayoutVerificationPort $layout,
        private readonly OmrDetectionPort $detector,
    ) {}

    public function verify(
        OmrDiagnosticTarget $target,
        string $bytes,
        string $mime,
        ?OmrProofPlan $plan = null,
        int $pageNo = 1,
        ?OmrLayoutVerificationResult $verifiedLayout = null,
        ?OmrDetectionRequest $preparedRequest = null,
    ): OmrDiagnosticEvidence {
        if ($pageNo < 1 || $pageNo > $target->pageCount()) {
            throw new OmrDiagnosticTargetException(
                'diagnostic_page_out_of_range',
                "Target {$target->kind()}/{$target->targetId()} has no page {$pageNo}.",
            );
        }
        $inputHash = hash('sha256', $bytes);

        if ($plan !== null && $plan->purpose->isQualification() && ! $target->qualificationCapable()) {
            return OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: 'candidate_qualification_refused',
                detail: 'Candidate targets are rehearsal-only; full qualification requires a compiled immutable version target.',
                inputHash: $inputHash,
                plan: $plan,
            );
        }
        if ($plan !== null && $plan->targetHashes !== [
            'artifact' => $target->artifactHash(),
            'native_bundle' => $target->nativeBundleHash(),
            'manifest' => $target->manifestHash(),
            'config' => $target->configHash(),
            'mapping' => $target->mappingHash(),
        ]) {
            return OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: 'plan_target_mismatch',
                detail: 'The proof plan pins different target hashes; plan against this exact target before verifying.',
                inputHash: $inputHash,
                plan: $plan,
            );
        }

        $request = $preparedRequest ?? $target->buildRequest($this->requests, $bytes, $mime, $pageNo);
        $layoutResult = $verifiedLayout ?? $this->layout->verifyLayout($request);
        $timings = [];
        if ($layoutResult->preprocessMs !== null) {
            $timings['preprocess_ms'] = $layoutResult->preprocessMs;
        }
        if ($layoutResult->verificationMs !== null) {
            $timings['layout_verification_ms'] = $layoutResult->verificationMs;
        }
        if ($layoutResult->succeeded()
            && $target->registrationMode() === OmrRegistrationMode::DocumentBound
            && ! OmrDocumentRectification::isAccepted($layoutResult->rectification)) {
            return OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: 'document_rectification_rejected',
                detail: 'The server did not accept one document boundary and warp.',
                inputHash: $inputHash,
                capturedMime: $mime,
                capturedBytes: $bytes,
                timings: $timings,
                plan: $plan,
                layout: $layoutResult,
                gates: [
                    'registration' => 'fail',
                    'document' => 'fail',
                    'content' => (($layoutResult->rectification['content_gate']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                ],
            );
        }
        if (! $layoutResult->succeeded()) {
            return OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: $layoutResult->code ?? 'layout_verification_failed',
                detail: $layoutResult->detail,
                inputHash: $inputHash,
                capturedMime: $mime,
                capturedBytes: $bytes,
                timings: $timings,
                plan: $plan,
                layout: $layoutResult,
                issues: $layoutResult->code !== null ? [[
                    'code' => $layoutResult->code,
                    'severity' => OmrDetectionIssue::SEVERITY_TERMINAL,
                ]] : [],
            );
        }

        $unsafe = $this->requests->unsafeBlocks($request->nativeTemplate, $layoutResult->fieldBlockShifts ?? []);
        $gates = $this->gates($request, $layoutResult, $unsafe);

        $detection = $this->detector->detect($request);
        if ($detection->timings !== null) {
            $timings['detection_ms'] = $detection->timings->detectionMs;
        }
        if ($detection->outcome !== OmrDetectionOutcome::Succeeded || $detection->success === null) {
            return OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: $detection->safeDiagnosticCode ?? 'detection_failed',
                detail: $detection->safeDiagnosticMessage,
                inputHash: $inputHash,
                capturedMime: $mime,
                capturedBytes: $bytes,
                timings: $timings,
                plan: $plan,
                layout: $layoutResult,
                gates: $gates,
                issues: $this->safeIssues([], $gates, $target->registrationMode(), $plan, $pageNo),
                providerResult: $detection,
            );
        }

        if ($target->registrationMode() === OmrRegistrationMode::DocumentBound
            && ! OmrDocumentRectification::sameSelectedWarp($layoutResult->rectification, $detection->success->rectification)) {
            return OmrDiagnosticEvidence::failed(
                target: $target,
                pageNo: $pageNo,
                code: 'rectification_mismatch',
                detail: 'Layout verification and detection selected different document rectifications.',
                inputHash: $inputHash,
                capturedMime: $mime,
                capturedBytes: $bytes,
                timings: $timings,
                plan: $plan,
                layout: $layoutResult,
                gates: [
                    'registration' => 'fail',
                    'document' => 'fail',
                    'rectification_consistency' => 'fail',
                ],
                issues: [[
                    'code' => 'rectification_mismatch',
                    'severity' => OmrDetectionIssue::SEVERITY_TERMINAL,
                ]],
                providerResult: $detection,
            );
        }
        if ($target->registrationMode() === OmrRegistrationMode::DocumentBound) {
            $gates['rectification_consistency'] = 'pass';
        }

        $items = $this->itemEvidence($request, $layoutResult, $detection->success, $plan, $pageNo);
        $identity = $this->identityEvidence($request, $detection->success, $plan, $pageNo);

        return OmrDiagnosticEvidence::successful(
            target: $target,
            pageNo: $pageNo,
            inputHash: $inputHash,
            capturedMime: $mime,
            capturedBytes: $bytes,
            layout: $layoutResult,
            gates: $gates,
            items: $items,
            identity: $identity,
            timings: $timings,
            issues: $this->safeIssues($detection->success->issues, $gates, $target->registrationMode(), $plan, $pageNo),
            plan: $plan,
            calibrationStatus: $detection->success->calibrationStatus,
            providerResult: $detection,
        );
    }

    /**
     * NEXIAM-side gates over worker diagnostics — the same marker-count,
     * dimensions, and unsafe-shift rules Test capture uses; never a new
     * detector or threshold.
     *
     * @return array<string, mixed>
     */
    private function gates(OmrDetectionRequest $request, OmrLayoutVerificationResult $result, array $unsafe): array
    {
        $frame = is_array($request->nativeTemplate['pageDimensions'] ?? null) ? $request->nativeTemplate['pageDimensions'] : [0, 0];
        $aligned = is_array($result->alignedImage) ? $result->alignedImage : [];
        $dimensionsPass = (int) ($aligned['width'] ?? 0) === (int) ($frame[0] ?? 0)
            && (int) ($aligned['height'] ?? 0) === (int) ($frame[1] ?? 0);
        if ($request->registrationMode === OmrRegistrationMode::DocumentBound) {
            $rectification = $result->rectification;
            $rectificationPass = OmrDocumentRectification::isAccepted($rectification);
            $keepOriginal = ($rectification['crop_policy'] ?? null) === 'keep_original';

            return [
                'marker_count' => 'not_applicable',
                'registration' => $rectificationPass ? 'pass' : 'fail',
                'document' => $keepOriginal || (($rectification['document_gates']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                'content' => $keepOriginal || (($rectification['content_gate']['accepted'] ?? null) === true) ? 'pass' : 'fail',
                'dimensions' => $dimensionsPass ? 'pass' : 'fail',
                'shifts' => $unsafe === [] ? 'pass' : 'fail',
                'rectification_consistency' => 'pending',
                'unsafe_blocks' => $unsafe,
            ];
        }

        $markerCountPass = is_array($result->markerMatches) && count($result->markerMatches) === 4;

        return [
            'marker_count' => $markerCountPass ? 'pass' : 'fail',
            'registration' => 'pass',
            'dimensions' => $dimensionsPass ? 'pass' : 'fail',
            'shifts' => $unsafe === [] ? 'pass' : 'fail',
            'unsafe_blocks' => $unsafe,
        ];
    }

    /**
     * Declared-option item evidence: every item the review manifest declared
     * on this page, with option regions, bubble means, threshold status,
     * the detected observation, and the plan's expected value.
     *
     * @param  list<array<string, mixed>>  $bubbleMeans
     * @return list<array<string, mixed>>
     */
    private function itemEvidence(OmrDetectionRequest $request, OmrLayoutVerificationResult $layout, OmrDetectionSuccess $success, ?OmrProofPlan $plan, int $pageNo): array
    {
        $expected = $plan !== null ? $plan->expectedVectorForPage($pageNo) : [];
        $detectedByKey = [];
        foreach ($success->items as $item) {
            $detectedByKey[strtoupper((string) $item->areaCode).'_Q_'.(int) $item->questionNo] = $item;
        }
        $means = [];
        foreach ((array) ($layout->bubbleMeans ?? []) as $entries) {
            foreach ((array) $entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $key = (string) ($entry['label'] ?? '').'|'.(string) ($entry['option'] ?? '');
                if (array_key_exists('mean', $entry)) {
                    $means[$key] = $entry['mean'] === null ? null : (float) $entry['mean'];
                }
            }
        }
        $registeredRegions = OmrTemplateGeometryProjector::registeredOptionRegions($layout);

        $items = [];
        foreach ($request->items as $geometry) {
            $detected = $detectedByKey[strtoupper($geometry->areaCode).'_Q_'.(int) $geometry->questionNo] ?? null;
            $expectedValue = $expected[$geometry->nativeLabel] ?? null;
            $options = [];
            $hasRegisteredRegion = false;
            foreach ($geometry->options as $option) {
                $meanKey = $geometry->nativeLabel.'|'.$option->code;
                $regionKey = $geometry->nativeLabel.'|'.$option->code;
                $hasRegisteredRegion = $hasRegisteredRegion || isset($registeredRegions[$regionKey]);
                $options[] = [
                    'code' => $option->code,
                    'region' => $registeredRegions[$regionKey] ?? $option->region->toArray(),
                    'display_order' => $option->displayOrder,
                    'mean' => $means[$meanKey] ?? null,
                    'threshold_status' => 'unqualified',
                ];
            }
            $itemRegion = $hasRegisteredRegion
                ? OmrTemplateGeometryProjector::unionRegions(array_map(
                    static fn (array $option): array => $option['region'],
                    $options,
                ))
                : $geometry->region->toArray();
            $items[] = [
                'position' => $geometry->position,
                'area_code' => $geometry->areaCode,
                'question_no' => $geometry->questionNo,
                'native_label' => $geometry->nativeLabel,
                'page_no' => $pageNo,
                'region' => $itemRegion,
                'options' => $options,
                'detected' => $detected !== null ? [
                    'state' => $detected->state,
                    'selected_option' => $detected->selectedOption,
                    'confidence' => $detected->confidence,
                    'observed_options' => $detected->observedOptions,
                    'option_observations' => array_map(
                        static fn ($observation): array => $observation->toArray(),
                        $detected->optionObservations,
                    ),
                    'issue_codes' => $detected->issueCodes,
                ] : null,
                'expected' => $expectedValue,
                'exact' => $detected !== null && $expectedValue !== null
                    ? ($detected->state === OmrDetectedItem::STATE_MARKED && $detected->selectedOption === $expectedValue)
                    : null,
            ];
        }

        return $items;
    }

    /**
     * Declared-option ID evidence: observed applicant-number digits with the
     * plan's expected digits and the same pinned option geometry used for
     * answer bubbles. Advisory only — never binds an applicant.
     *
     * @return list<array<string, mixed>>
     */
    private function identityEvidence(
        OmrDetectionRequest $request,
        OmrDetectionSuccess $success,
        ?OmrProofPlan $plan,
        int $pageNo,
    ): array {
        $optionsByLabel = [];
        foreach ((array) ($request->reviewManifest['fields'] ?? []) as $manifestField) {
            if (! is_array($manifestField) || (int) ($manifestField['page'] ?? 0) !== $pageNo) {
                continue;
            }
            $label = is_string($manifestField['label'] ?? null) ? $manifestField['label'] : null;
            $option = is_scalar($manifestField['option'] ?? null) ? (string) $manifestField['option'] : null;
            $region = is_array($manifestField['region'] ?? null) ? $manifestField['region'] : null;
            if ($label === null || ! str_starts_with($label, 'APPLICANT_NUMBER_') || $option === null || $region === null) {
                continue;
            }
            $optionsByLabel[$label][] = [
                'code' => $option,
                'region' => $region,
            ];
        }

        $identity = [];
        $calibration = $plan !== null ? $plan->calibrationDigits : [];
        foreach ($success->identityFields as $index => $field) {
            if ($field->code !== OmrIdentityField::CODE_APPLICANT_NUMBER) {
                continue;
            }
            $position = $index + 1;
            $label = 'APPLICANT_NUMBER_'.$position;
            $identity[] = [
                'code' => $field->code,
                'position' => $position,
                'page_no' => $pageNo,
                'region' => $field->region->toArray(),
                'options' => array_map(
                    static fn (array $option): array => [
                        ...$option,
                        'observed' => $field->value !== null && (string) $option['code'] === (string) $field->value,
                        'classification' => $field->value !== null && (string) $option['code'] === (string) $field->value
                            ? 'marked'
                            : 'unmarked',
                    ],
                    $optionsByLabel[$label] ?? [],
                ),
                'confidence' => $field->confidence,
                'detected_value' => $field->value,
                'expected_digit' => $calibration[$label] ?? null,
                'exact' => isset($calibration[$label]) && $field->value !== null
                    ? (string) $field->value === (string) $calibration[$label]
                    : null,
            ];
        }

        return $identity;
    }

    /**
     * Safe issues: the worker's typed issues plus a sheet-misalignment
     * warning whenever a NEXIAM gate fails. Never raw provider internals.
     *
     * @param  list<OmrDetectionIssue>  $detectionIssues
     * @param  array<string, mixed>  $gates
     * @return list<array<string, mixed>>
     */
    private function safeIssues(
        array $detectionIssues,
        array $gates,
        OmrRegistrationMode $mode,
        ?OmrProofPlan $plan,
        int $pageNo,
    ): array {
        $blankNegative = $plan !== null
            && $plan->purpose !== ProofPurpose::Digital
            && $plan->sourceKind === OmrSourcePolicy::UPLOADED_ORIGINAL
            && $plan->method === OmrProofPlan::METHOD_PRE_SHADED
            && $plan->expectedVectorForPage($pageNo) === [];
        $issues = [];
        foreach ($detectionIssues as $issue) {
            $severity = $blankNegative && $issue->code === OmrDetectionIssue::CODE_IDENTITY_UNRESOLVED
                ? OmrDetectionIssue::SEVERITY_WARNING
                : $issue->severity;
            $issues[] = [
                'code' => $issue->code,
                'severity' => $severity,
                'area_code' => $issue->areaCode,
                'question_no' => $issue->questionNo,
            ];
        }
        $failed = array_filter(
            $gates,
            static fn (mixed $status, string $name): bool => $name !== 'unsafe_blocks'
                && ! ($mode === OmrRegistrationMode::DocumentBound && $name === 'marker_count' && $status === 'not_applicable')
                && $status !== 'pass',
            ARRAY_FILTER_USE_BOTH,
        );
        if ($failed !== []) {
            $issues[] = [
                'code' => OmrDetectionIssue::CODE_SHEET_MISALIGNED,
                'severity' => OmrDetectionIssue::SEVERITY_WARNING,
                'area_code' => null,
                'question_no' => null,
            ];
        }

        return $issues;
    }
}
