<?php

namespace App\Assessment\Omr\Verification;

use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrDocumentRectification;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;

use function abs;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_finite;
use function is_numeric;
use function is_string;
use function json_encode;
use function max;

/**
 * Read-only source of truth for physical qualification readiness.
 * Candidate and quick-rehearsal evidence is always rehearsal-only.
 */
final class OmrPhysicalQualificationDeriver
{
    public function __construct(
        private readonly ?CurrentOmrDigitalVerification $digitalVerification = null,
    ) {}

    public const CHECK_KEYS = [
        'digital_passed',
        'marker_profile_approved',
        'final_sheet_compiled',
        'pre_shaded_passed',
        'hand_shaded_passed',
        'measurements_recorded',
    ];

    private const CHECK_LABELS = [
        'digital_passed' => 'digital passed',
        'marker_profile_approved' => 'marker profile approved',
        'final_sheet_compiled' => 'final sheet compiled',
        'pre_shaded_passed' => 'pre-shaded passed',
        'hand_shaded_passed' => 'hand-shaded passed',
        'measurements_recorded' => 'physical provenance and measurements',
    ];

    /**
     * Derive a bounded checklist without mutating workflow, assets, attempts,
     * marker profiles, or audit rows.
     *
     * @param  array<string,mixed>  $measurementEvidence
     * @return array<string,mixed>
     */
    public function derive(
        ?OmrTemplateVerificationRun $run,
        ?OmrAnswerSheetTemplateVersion $version = null,
        array $measurementEvidence = [],
    ): array {
        $run = $this->reloadRun($run);
        $version ??= $run?->template_version_id !== null
            ? OmrAnswerSheetTemplateVersion::query()->find($run->template_version_id)
            : null;

        $candidate = $run?->draft_id !== null || ($run !== null && $version === null);
        $registrationMode = $this->registrationMode($run, $version);
        $isUploaded = ($version !== null && OmrSourcePolicy::sourceKindForVersion($version) === OmrSourcePolicy::UPLOADED_ORIGINAL)
            || ($run !== null && OmrSourcePolicy::sourceKindForRun($run) === OmrSourcePolicy::UPLOADED_ORIGINAL);
        $methods = $this->methods($run);
        $selected = $this->selectedCaptures($methods);
        $target = $this->targetSummary($run, $version);
        $measurementsRecorded = $this->measurementsRecorded($version, $selected, $measurementEvidence, $registrationMode);
        $physicalProvenanceVerified = false;
        $measurementBlockerCode = $measurementsRecorded
            ? 'physical_provenance_unavailable'
            : 'physical_measurement_out_of_tolerance';
        $values = [
            'digital_passed' => $this->digitalPassed($run, $version),
            'marker_profile_approved' => $registrationMode === OmrRegistrationMode::DocumentBound
                || ($registrationMode === OmrRegistrationMode::FiducialAnchored && $this->markerProfileApproved($version)),
            'final_sheet_compiled' => $this->finalSheetCompiled($run, $version),
            'pre_shaded_passed' => $this->methodPassed($methods['pre_shaded'] ?? null, $selected['pre_shaded'] ?? [], $registrationMode),
            'hand_shaded_passed' => $this->methodPassed($methods['hand_shaded'] ?? null, $selected['hand_shaded'] ?? [], $registrationMode),
            'measurements_recorded' => $measurementsRecorded && $physicalProvenanceVerified,
        ];
        $details = [
            'digital_passed' => 'A current passed digital verification is required.',
            'marker_profile_approved' => $registrationMode === OmrRegistrationMode::DocumentBound
                ? 'Document-bound registration does not use a marker profile.'
                : 'Qualify the marker profile independently before template qualification.',
            'final_sheet_compiled' => 'Candidate or draft-targeted evidence is rehearsal-only; compile an immutable version first.',
            'pre_shaded_passed' => $isUploaded
                ? 'Capture a genuine supplier-issued unmarked sheet (supplier_blank) for every compiled page.'
                : 'Select an exact passing pre-shaded capture for every required page.',
            'hand_shaded_passed' => $isUploaded
                ? 'Capture a genuine supplier-issued hand-marked sheet (supplier_hand_marked) for every compiled page.'
                : 'Select an exact passing hand-shaded capture for every required page.',
            'measurements_recorded' => $measurementsRecorded
                ? 'The current capture workflow cannot verify genuine printer and device provenance.'
                : ($registrationMode === OmrRegistrationMode::DocumentBound
                    ? 'Record printer, device, and paper details; document-bound registration does not use marker-center measurements.'
                    : 'Record printer, device, paper, and marker measurements within tolerance.'),
        ];
        $codes = [
            'digital_passed' => 'digital_pass_required',
            'marker_profile_approved' => 'marker_profile_unqualified',
            'final_sheet_compiled' => $candidate ? 'candidate_not_qualification_capable' : 'compiled_version_required',
            'pre_shaded_passed' => $isUploaded ? 'supplier_physical_capture_required' : 'physical_capture_incomplete',
            'hand_shaded_passed' => $isUploaded ? 'supplier_physical_capture_required' : 'physical_capture_incomplete',
            'measurements_recorded' => $measurementBlockerCode,
        ];
        $checklist = array_map(
            fn (string $key): array => [
                'key' => $key,
                'label' => $key === 'marker_profile_approved' && $registrationMode === OmrRegistrationMode::DocumentBound
                    ? 'marker profile not required'
                    : ($key === 'pre_shaded_passed' && $isUploaded
                        ? 'supplier unmarked blank passed'
                        : ($key === 'hand_shaded_passed' && $isUploaded
                            ? 'supplier hand-marked passed'
                            : self::CHECK_LABELS[$key])),
                'passed' => (bool) $values[$key],
                'state' => $values[$key] ? 'passed' : 'blocked',
                'code' => $values[$key] ? null : $codes[$key],
                'detail' => $values[$key] ? $this->passedDetail($key, $registrationMode) : $details[$key],
            ],
            self::CHECK_KEYS,
        );
        $blockers = array_values(array_filter($checklist, static fn (array $check): bool => ! $check['passed']));
        if ($candidate) {
            $blockers = [[
                'key' => 'final_sheet_compiled',
                'label' => self::CHECK_LABELS['final_sheet_compiled'],
                'passed' => false,
                'state' => 'blocked',
                'code' => 'candidate_not_qualification_capable',
                'detail' => 'Candidate and draft-targeted evidence can never qualify a template.',
            ], ...array_values(array_filter($blockers, static fn (array $check): bool => $check['key'] !== 'final_sheet_compiled'))];
        }

        $ready = ! $candidate
            && $run?->mode === OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION
            && $run?->status === OmrTemplateVerificationRun::STATUS_PASSED
            && count($blockers) === 0;
        $qualified = $ready && $this->manifestAlreadyQualified($version, $run);
        if ($candidate) {
            $status = 'rehearsal_only';
            $label = 'Rehearsal only';
            $nextAction = 'Compile an immutable version, then rerun both physical methods.';
        } elseif ($qualified) {
            $status = 'physical_qualified';
            $label = 'Qualified';
            $nextAction = 'Activate the qualified version.';
        } elseif ($ready) {
            $status = 'ready';
            $label = 'Ready to qualify';
            $nextAction = 'Record qualification measurements and derive the physical gate.';
        } else {
            $status = 'blocked';
            $label = 'Blocked';
            $nextAction = $this->nextAction($checklist, $registrationMode);
        }

        return [
            'status' => $status,
            'label' => $label,
            'ready' => $ready,
            'qualified' => $qualified,
            'software_verification_status' => $values['digital_passed'] ? 'passed' : 'held',
            'physical_status' => $qualified ? 'qualified' : 'held',
            'checklist' => $checklist,
            'checks' => $checklist,
            'blockers' => $blockers,
            'next_action' => $nextAction,
            'target' => $target,
            'run_id' => $run?->id !== null ? (int) $run->id : null,
            'mode' => $run?->mode,
        ];
    }

    /** @return array<string,mixed> */
    public function checklist(?OmrTemplateVerificationRun $run, ?OmrAnswerSheetTemplateVersion $version = null, array $measurementEvidence = []): array
    {
        return $this->derive($run, $version, $measurementEvidence);
    }

    /** @return array<string,mixed> */
    public function get(?OmrTemplateVerificationRun $run, ?OmrAnswerSheetTemplateVersion $version = null, array $measurementEvidence = []): array
    {
        return $this->derive($run, $version, $measurementEvidence);
    }

    private function reloadRun(?OmrTemplateVerificationRun $run): ?OmrTemplateVerificationRun
    {
        if ($run === null || $run->id === null) {
            return null;
        }

        return OmrTemplateVerificationRun::query()
            ->with([
                'draft.markerProfile',
                'templateVersion',
                'methods.challenge',
                'methods.pages.selectedAttempt',
                'methods.captures.challenge',
            ])
            ->find($run->id);
    }

    /** @return array<string,OmrTemplateVerificationMethod> */
    private function methods(?OmrTemplateVerificationRun $run): array
    {
        if ($run === null) {
            return [];
        }
        $result = [];
        foreach ($run->methods as $method) {
            $result[(string) $method->method] = $method;
        }

        return $result;
    }

    /** @param array<string,OmrTemplateVerificationMethod> $methods
     * @return array<string,array<int,OmrTemplateVerificationCapture>>
     */
    private function selectedCaptures(array $methods): array
    {
        $selected = [];
        foreach ($methods as $methodName => $method) {
            $selected[$methodName] = [];
            foreach ($method->pages as $page) {
                if (! $page->required || $page->selected_attempt_id === null || $page->selectedAttempt === null) {
                    continue;
                }
                $selected[$methodName][(int) $page->page_no] = $page->selectedAttempt;
            }
        }

        return $selected;
    }

    private function methodPassed(
        ?OmrTemplateVerificationMethod $method,
        array $selected,
        ?OmrRegistrationMode $registrationMode,
    ): bool {
        if ($method === null || $method->method_state !== OmrTemplateVerificationMethod::STATE_PASSED || $registrationMode === null) {
            return false;
        }
        $requiredPages = $method->pages->filter(static fn (OmrTemplateVerificationMethodPage $page): bool => $page->required)->values();
        if ($requiredPages->isEmpty() || $requiredPages->count() !== count($selected)) {
            return false;
        }
        foreach ($requiredPages as $page) {
            $capture = $selected[(int) $page->page_no] ?? null;
            if (! $capture instanceof OmrTemplateVerificationCapture
                || $capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
                || $capture->status !== OmrTemplateVerificationCapture::STATUS_PASS
                || $capture->vector_match !== true
                || ! $this->captureGatesPass($capture, $registrationMode, $method, $page)
                || ! $this->captureBindingMatches($method, $capture)) {
                return false;
            }
        }

        return true;
    }

    private function captureGatesPass(
        OmrTemplateVerificationCapture $capture,
        OmrRegistrationMode $registrationMode,
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
    ): bool {
        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            return $this->documentBoundCaptureEvidencePasses($capture, $method, $page);
        }

        if ($registrationMode !== OmrRegistrationMode::FiducialAnchored) {
            return false;
        }

        $gates = is_array($capture->summary) && is_array($capture->summary['gates'] ?? null)
            ? $capture->summary['gates']
            : (is_array($capture->gate_result) ? $capture->gate_result : []);
        foreach (['marker_count', 'registration', 'dimensions', 'shifts'] as $key) {
            if (($gates[$key] ?? null) !== 'pass') {
                return false;
            }
        }

        return true;
    }

    private function documentBoundCaptureEvidencePasses(
        OmrTemplateVerificationCapture $capture,
        OmrTemplateVerificationMethod $method,
        OmrTemplateVerificationMethodPage $page,
    ): bool {
        $summary = is_array($capture->summary) ? $capture->summary : [];
        $prepared = is_array($summary['prepared_capture'] ?? null) ? $summary['prepared_capture'] : [];
        $diagnostic = is_array($prepared['diagnostic'] ?? null) ? $prepared['diagnostic'] : [];
        $target = is_array($prepared['target'] ?? null) ? $prepared['target'] : [];
        $targetHashes = is_array($target['hashes'] ?? null) ? $target['hashes'] : [];
        $compiledPages = $target['compiled_pages'] ?? null;
        $gates = is_array($prepared['gates'] ?? null) ? $prepared['gates'] : null;
        $rectificationEvidence = is_array($summary['document_rectification_evidence'] ?? null)
            ? $summary['document_rectification_evidence']
            : [];
        $layoutRectification = $rectificationEvidence['layout'] ?? null;
        $detectionRectification = $rectificationEvidence['detection'] ?? null;
        $rectificationHash = $rectificationEvidence['sha256'] ?? null;
        $rectificationHashInput = [
            'layout' => $layoutRectification,
            'detection' => $detectionRectification,
        ];

        if ($gates === null
            || ($summary['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
            || $capture->gate_result !== $gates
            || ($summary['gates'] ?? null) !== $gates
            || ($diagnostic['gates'] ?? null) !== $gates
            || ($diagnostic['target'] ?? null) !== $target
            || (int) ($prepared['page_no'] ?? 0) !== (int) $page->page_no
            || (int) $capture->page_no !== (int) $page->page_no
            || (string) ($prepared['input_hash'] ?? '') !== (string) $capture->input_hash
            || ($target['registration_mode'] ?? null) !== OmrRegistrationMode::DocumentBound->value
            || ($target['kind'] ?? null) !== $method->target_kind
            || (string) ($target['id'] ?? '') !== (string) $method->target_id
            || (int) ($target['page_no'] ?? 0) !== (int) $page->page_no
            || ! is_array($compiledPages)
            || ! in_array(['page_no' => (int) $page->page_no], $compiledPages, true)
            || ! is_string($targetHashes['artifact'] ?? null)
            || ! hash_equals((string) $method->target_hash, $targetHashes['artifact'])
            || (string) ($prepared['expected_vector_hash'] ?? '') !== (string) $method->expected_vector_hash
            || ! is_array($prepared['hashes'] ?? null)
            || ! is_string($prepared['hashes']['plan'] ?? null)
            || ! hash_equals((string) $method->plan_hash, $prepared['hashes']['plan'])
            || ! is_array($prepared['layout'] ?? null)
            || ($prepared['rectification'] ?? null) !== $layoutRectification
            || ($prepared['layout']['rectification'] ?? null) !== $layoutRectification
            || ($summary['rectification'] ?? null) !== $layoutRectification
            || (is_array($capture->layout_result) ? ($capture->layout_result['rectification'] ?? null) : null) !== $layoutRectification
            || ! is_string($rectificationHash)
            || ! hash_equals(
                $rectificationHash,
                hash('sha256', json_encode($rectificationHashInput, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            )
            || ! OmrDocumentRectification::sameSelectedWarp($layoutRectification, $detectionRectification)
            || ! is_string($prepared['evidence_hash'] ?? null)
            || ! is_string($capture->evidence_hash)
            || ! hash_equals((string) $capture->evidence_hash, (string) $prepared['evidence_hash'])) {
            return false;
        }

        $requiredGates = [
            'marker_count' => 'not_applicable',
            'registration' => 'pass',
            'document' => 'pass',
            'content' => 'pass',
            'dimensions' => 'pass',
            'shifts' => 'pass',
            'rectification_consistency' => 'pass',
        ];
        foreach ($requiredGates as $name => $requiredStatus) {
            if (($gates[$name] ?? null) !== $requiredStatus) {
                return false;
            }
        }
        if (! array_key_exists('unsafe_blocks', $gates) || $gates['unsafe_blocks'] !== []) {
            return false;
        }
        foreach (array_keys($gates) as $name) {
            if (! array_key_exists($name, $requiredGates) && $name !== 'unsafe_blocks') {
                return false;
            }
        }

        unset($prepared['evidence_hash']);

        return hash_equals(
            (string) $capture->evidence_hash,
            hash('sha256', json_encode($prepared, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
        );
    }

    private function captureBindingMatches(OmrTemplateVerificationMethod $method, OmrTemplateVerificationCapture $capture): bool
    {
        if ((int) $capture->method_id !== (int) $method->id
            || (string) $capture->method !== (string) $method->method
            || (string) $capture->target_kind !== (string) $method->target_kind
            || (int) $capture->target_id !== (int) $method->target_id
            || ! hash_equals((string) $capture->target_hash, (string) $method->target_hash)
            || ! hash_equals((string) $capture->plan_hash, (string) $method->plan_hash)
            || ! hash_equals((string) $capture->expected_vector_hash, (string) $method->expected_vector_hash)
            || $capture->challenge === null) {
            return false;
        }
        $challenge = $capture->challenge;

        return (int) $challenge->id === (int) $method->challenge_id
            && hash_equals((string) $capture->renderer_hash, (string) $challenge->renderer_hash)
            && (string) $capture->renderer_version === (string) $challenge->renderer_version
            && (string) $capture->page_manifest_hash === (string) $challenge->page_manifest_hash
            && (int) $capture->marker_profile_id === (int) $challenge->marker_profile_id
            && (int) $capture->marker_profile_version === (int) $challenge->marker_profile_version
            && (string) $capture->marker_profile_hash === (string) $challenge->marker_profile_hash
            && in_array((string) $capture->method, OmrTemplateVerificationCapture::METHODS, true)
            && ($capture->method !== OmrTemplateVerificationCapture::METHOD_HAND_SHADED || in_array((string) $capture->medium, OmrTemplateVerificationCapture::HAND_SHADED_MEDIA, true))
            && ($capture->method !== OmrTemplateVerificationCapture::METHOD_PRE_SHADED || $capture->medium === null);
    }

    private function registrationMode(
        ?OmrTemplateVerificationRun $run,
        ?OmrAnswerSheetTemplateVersion $version,
    ): ?OmrRegistrationMode {
        if ($run === null) {
            return $version?->registration_mode instanceof OmrRegistrationMode
                ? $version->registration_mode
                : null;
        }
        if ($run->draft_id !== null) {
            $mode = $run->draft?->registration_mode;

            return $mode instanceof OmrRegistrationMode ? $mode : null;
        }

        $boundVersion = $run->templateVersion;
        if ($run->template_version_id === null
            || $version === null
            || (int) $version->id !== (int) $run->template_version_id
            || $boundVersion === null
            || $boundVersion->registration_mode !== $version->registration_mode) {
            return null;
        }

        $mode = $boundVersion->registration_mode;

        return $mode instanceof OmrRegistrationMode ? $mode : null;
    }

    private function digitalPassed(?OmrTemplateVerificationRun $run, ?OmrAnswerSheetTemplateVersion $version): bool
    {
        if ($run !== null
            && $version !== null
            && $run->draft_id === null
            && (int) $run->template_version_id === (int) $version->id) {
            $manifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
            $hashes = is_array($manifest['verification_hashes'] ?? null) ? $manifest['verification_hashes'] : [];

            return $this->nonEmptyHashSet($hashes, ['digital_verification', 'matrix_hash', 'proof_hash']);
        }

        $draft = $run?->draft;
        if ($draft === null) {
            return false;
        }

        return ($this->digitalVerification ?? app(CurrentOmrDigitalVerification::class))->find($draft) !== null;
    }

    private function markerProfileApproved(?OmrAnswerSheetTemplateVersion $version): bool
    {
        if ($version === null) {
            return false;
        }
        $native = is_array($version->native_bundle_json) ? $version->native_bundle_json : [];
        $authoring = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
        $manifest = is_array($native['manifest']['marker_profile'] ?? null) ? $native['manifest']['marker_profile'] : [];
        $authoringProfile = is_array($authoring['marker_profile'] ?? null) ? $authoring['marker_profile'] : [];
        $profileId = $manifest['id'] ?? ($authoringProfile['id'] ?? null);
        if ((string) ($manifest['status'] ?? '') === OmrMarkerProfile::STATUS_QUALIFIED) {
            return true;
        }

        return $profileId !== null
            && OmrMarkerProfile::query()->whereKey($profileId)->where('status', OmrMarkerProfile::STATUS_QUALIFIED)->exists();
    }

    private function finalSheetCompiled(?OmrTemplateVerificationRun $run, ?OmrAnswerSheetTemplateVersion $version): bool
    {
        return $run !== null
            && $version !== null
            && $run->draft_id === null
            && (int) $run->template_version_id === (int) $version->id
            && in_array((string) $version->status, [
                OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
                OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                OmrAnswerSheetTemplateVersion::STATUS_RETIRED,
            ], true)
            && is_array($version->native_bundle_json)
            && $version->native_bundle_json !== []
            && is_array($version->review_manifest_json)
            && $version->review_manifest_json !== [];
    }

    /** @param array<string,array<int,OmrTemplateVerificationCapture>> $selected
     * @param  array<string,mixed>  $evidence
     */
    private function measurementsRecorded(
        ?OmrAnswerSheetTemplateVersion $version,
        array $selected,
        array $evidence,
        ?OmrRegistrationMode $registrationMode,
    ): bool {
        $manifest = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
        $stored = is_array($manifest['physical_qualification'] ?? null) ? $manifest['physical_qualification'] : [];
        $candidate = $evidence !== [] ? $evidence : $stored;
        $printer = trim((string) ($candidate['printer'] ?? ''));
        $device = trim((string) ($candidate['device'] ?? ''));
        $paper = trim((string) ($candidate['paper'] ?? ''));
        if ($printer === '' || $device === '' || $paper === '') {
            return false;
        }
        if ($registrationMode === OmrRegistrationMode::DocumentBound) {
            return true;
        }
        if ($registrationMode !== OmrRegistrationMode::FiducialAnchored) {
            return false;
        }

        $measured = array_values((array) ($candidate['measured_marker_center_mm'] ?? []));
        if (count($measured) !== 2 || ! is_numeric($measured[0]) || ! is_numeric($measured[1])) {
            return false;
        }
        if ((float) $measured[0] <= 0 || (float) $measured[1] <= 0
            || ! is_finite((float) $measured[0]) || ! is_finite((float) $measured[1])) {
            return false;
        }
        $expectedValue = $candidate['expected_marker_center_mm'] ?? $this->expectedMarkerCenters($version);
        if (! is_array($expectedValue)) {
            return false;
        }
        $expected = array_values($expectedValue);
        if (count($expected) !== 2 || ! is_numeric($expected[0]) || ! is_numeric($expected[1])
            || (float) $expected[0] <= 0 || (float) $expected[1] <= 0
            || ! is_finite((float) $expected[0]) || ! is_finite((float) $expected[1])) {
            return false;
        }
        $tolerance = (float) config('omr.canonical.physical_measurement_tolerance_mm', 1.0);

        return max(abs((float) $measured[0] - (float) $expected[0]), abs((float) $measured[1] - (float) $expected[1])) <= $tolerance;
    }

    /** @return list<float> */
    private function expectedMarkerCenters(?OmrAnswerSheetTemplateVersion $version): array
    {
        $manifest = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
        $paper = is_array($manifest['paper'] ?? null) ? $manifest['paper'] : [];
        $insets = is_array($manifest['marker_insets_mm'] ?? null) ? $manifest['marker_insets_mm'] : [];
        $paperWidth = $paper['width_mm'] ?? null;
        $paperHeight = $paper['height_mm'] ?? null;
        if (! is_numeric($paperWidth) || ! is_numeric($paperHeight)
            || (float) $paperWidth <= 0 || (float) $paperHeight <= 0
            || ! is_finite((float) $paperWidth) || ! is_finite((float) $paperHeight)) {
            return [];
        }
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $inset = $insets[$side] ?? null;
            if (! is_numeric($inset) || (float) $inset < 0 || ! is_finite((float) $inset)) {
                return [];
            }
        }
        $centers = [
            (float) $paperWidth - (float) $insets['left'] - (float) $insets['right'],
            (float) $paperHeight - (float) $insets['top'] - (float) $insets['bottom'],
        ];
        if ($centers[0] <= 0 || $centers[1] <= 0) {
            return [];
        }

        return $centers;
    }

    /** @param array<string,mixed> $hashes */
    private function nonEmptyHashSet(array $hashes, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! is_string($hashes[$key] ?? null) || trim($hashes[$key]) === '') {
                return false;
            }
        }

        return true;
    }

    private function manifestAlreadyQualified(?OmrAnswerSheetTemplateVersion $version, ?OmrTemplateVerificationRun $run): bool
    {
        $manifest = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
        $qualification = is_array($manifest['physical_qualification'] ?? null) ? $manifest['physical_qualification'] : [];

        return ($qualification['status'] ?? null) === 'physical_qualified'
            && (int) ($qualification['run_id'] ?? 0) === (int) ($run?->id ?? 0);
    }

    /** @param list<array<string,mixed>> $checklist */
    private function nextAction(array $checklist, ?OmrRegistrationMode $registrationMode): string
    {
        foreach ($checklist as $check) {
            if (! $check['passed']) {
                return match ($check['key']) {
                    'digital_passed' => 'Complete digital verification.',
                    'marker_profile_approved' => 'Qualify the selected marker profile.',
                    'final_sheet_compiled' => 'Compile the digitally verified draft.',
                    'pre_shaded_passed' => 'Complete and select the pre-shaded method pages.',
                    'hand_shaded_passed' => 'Complete and select the hand-shaded method pages.',
                    'measurements_recorded' => ($check['code'] ?? null) === 'physical_provenance_unavailable'
                        ? 'Use a physical capture workflow that verifies genuine printer and device provenance.'
                        : ($registrationMode === OmrRegistrationMode::DocumentBound
                            ? 'Record printer, device, and paper details.'
                            : 'Record printer, device, paper, and marker measurements.'),
                    default => 'Resolve the blocked qualification check.',
                };
            }
        }

        return 'Activate the qualified version.';
    }

    /** @return array{kind:string,id:int|null,hashes:array<string,mixed>} */
    private function targetSummary(?OmrTemplateVerificationRun $run, ?OmrAnswerSheetTemplateVersion $version): array
    {
        return [
            'kind' => $run?->draft_id !== null ? OmrTemplateVerificationMethod::TARGET_CANDIDATE : OmrTemplateVerificationMethod::TARGET_COMPILED_VERSION,
            'id' => $run?->draft_id !== null ? $run->draft_id : $run?->template_version_id,
            'hashes' => $run?->pinnedTargetHashes() ?? [],
            'version_id' => $version?->id !== null ? (int) $version->id : null,
        ];
    }

    private function passedDetail(string $key, ?OmrRegistrationMode $registrationMode): string
    {
        return match ($key) {
            'digital_passed' => 'The current digital verification passed.',
            'marker_profile_approved' => $registrationMode === OmrRegistrationMode::DocumentBound
                ? 'Document-bound registration does not use a marker profile.'
                : 'The marker profile is already qualified.',
            'final_sheet_compiled' => 'The run is pinned to one immutable compiled version.',
            'pre_shaded_passed' => 'Every required pre-shaded page has a selected exact passing capture.',
            'hand_shaded_passed' => 'Every required hand-shaded page has a selected exact passing capture.',
            'measurements_recorded' => 'Genuine printer/device provenance and measurements are verified.',
            default => 'Check passed.',
        };
    }
}
