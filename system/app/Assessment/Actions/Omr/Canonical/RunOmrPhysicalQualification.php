<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\Verification\OmrPhysicalQualificationDeriver;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function abs;
use function array_map;
use function array_values;
use function count;
use function in_array;
use function is_array;
use function max;
use function min;

/**
 * Verifies the full physical challenge and readiness prerequisites.
 *
 * The current capture workflow does not attest that submitted images came
 * from a genuine print on a known printer and capture device. Until that
 * provenance is independently verifiable, this action fails closed and does
 * not write a physical qualification manifest or success audit event.
 */
final class RunOmrPhysicalQualification
{
    public function __construct(
        private readonly OmrMarkerProfileService $profiles,
        private readonly OmrPhysicalQualificationDeriver $qualification,
    ) {}

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function handle(OmrTemplateVerificationRun $run, User $actor, array $evidence): OmrAnswerSheetTemplateVersion
    {
        if ($run->mode !== OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION) {
            throw new RuntimeException('physical_qualification_mode_invalid: Only a full-qualification run can qualify a template; quick rehearsals never qualify.');
        }
        if ($run->isDraftTargeted()) {
            throw new RuntimeException('physical_qualification_candidate_refused: Candidate (uncompiled) runs are rehearsal-only and cannot qualify a template.');
        }
        if ($run->status !== OmrTemplateVerificationRun::STATUS_PASSED) {
            throw new RuntimeException('physical_qualification_run_not_passed: Qualification requires a passed full-challenge run.');
        }

        $runId = (int) $run->id;
        $versionId = (int) $run->template_version_id;

        return DB::transaction(function () use ($runId, $versionId, $evidence): OmrAnswerSheetTemplateVersion {
            // Version creation/activation and challenge creation lock the target
            // version first. Keep that order, then follow the coordinator's
            // run -> method -> page -> capture order.
            $version = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->find($versionId);
            if ($version === null) {
                throw new RuntimeException('template_version_not_found');
            }
            $lockedRun = OmrTemplateVerificationRun::query()->lockForUpdate()->find($runId);
            if ($lockedRun === null) {
                throw new RuntimeException('verification_run_not_found');
            }
            if ($lockedRun->mode !== OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION) {
                throw new RuntimeException('physical_qualification_mode_invalid: Only a full-qualification run can qualify a template; quick rehearsals never qualify.');
            }
            if ($lockedRun->isDraftTargeted()) {
                throw new RuntimeException('physical_qualification_candidate_refused: Candidate (uncompiled) runs are rehearsal-only and cannot qualify a template.');
            }
            if ($lockedRun->status !== OmrTemplateVerificationRun::STATUS_PASSED) {
                throw new RuntimeException('physical_qualification_run_not_passed: Qualification requires a passed full-challenge run.');
            }
            if ((int) $lockedRun->template_version_id !== $versionId) {
                throw new RuntimeException('physical_qualification_target_changed');
            }
            if (! in_array($version->status, [
                OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
            ], true)) {
                throw new RuntimeException('template_version_not_compiled');
            }

            $target = new CompiledVersionDiagnosticTarget($version);
            if (! $target->qualificationCapable()) {
                throw new RuntimeException('physical_qualification_target_refused: The target is not qualification-capable.');
            }
            $manifest = (array) $version->authoring_manifest;
            if ($target->registrationMode() === OmrRegistrationMode::FiducialAnchored) {
                if (! $target->markerProfileQualified()) {
                    throw new RuntimeException('marker_profile_unqualified: Physical qualification requires an already-qualified marker profile.');
                }
                $profileId = $manifest['marker_profile']['id'] ?? null;
                $profile = $profileId ? OmrMarkerProfile::query()->find($profileId) : null;
                $this->profiles->assertQualified($profile);
            }

            $methods = OmrTemplateVerificationMethod::query()
                ->where('run_id', $lockedRun->id)
                ->orderBy('method')
                ->lockForUpdate()
                ->get();
            if ($methods->count() !== count(OmrTemplateVerificationMethod::METHODS)
                || $methods->pluck('method')->sort()->values()->all() !== collect(OmrTemplateVerificationMethod::METHODS)->sort()->values()->all()) {
                throw new RuntimeException('physical_methods_incomplete: Qualification requires both selected physical methods.');
            }
            $expectedPages = $target->compiledPages();
            $expectedPageNumbers = array_column($expectedPages, 'page_no');

            $selectedCaptureIds = [];
            $selectedCaptureRefs = [];
            foreach ($methods as $method) {
                $pages = OmrTemplateVerificationMethodPage::query()
                    ->where('method_id', $method->id)
                    ->where('required', true)
                    ->orderBy('page_no')
                    ->lockForUpdate()
                    ->get();
                if ($pages->pluck('page_no')->map(static fn ($pageNo): int => (int) $pageNo)->all() !== $expectedPageNumbers
                    || (int) $method->required_page_count !== count($expectedPageNumbers)) {
                    throw new RuntimeException('physical_pages_incomplete: Qualification requires every compiled page exactly once for each method.');
                }
                foreach ($pages as $page) {
                    if ($page->selected_attempt_id === null) {
                        throw new RuntimeException('physical_capture_incomplete: Qualification requires a selected passing attempt for every method/page.');
                    }
                    $captureId = (int) $page->selected_attempt_id;
                    $selectedCaptureIds[] = $captureId;
                    $selectedCaptureRefs[] = [
                        'method' => (string) $method->method,
                        'page_no' => (int) $page->page_no,
                        'capture_id' => $captureId,
                    ];
                }
            }
            $uniqueCaptureIds = array_values(array_unique($selectedCaptureIds));
            if (count($uniqueCaptureIds) !== count($selectedCaptureRefs)) {
                throw new RuntimeException('physical_capture_incomplete: Qualification requires a distinct selected passing attempt for every method/page.');
            }

            // Lock selected attempts only after all method/page pointers are
            // locked, matching the coordinator's deterministic lock order.
            $lockedCaptures = OmrTemplateVerificationCapture::query()
                ->where('run_id', $lockedRun->id)
                ->whereIn('id', $uniqueCaptureIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            foreach ($selectedCaptureRefs as $reference) {
                $capture = $lockedCaptures->get($reference['capture_id']);
                if ($capture === null
                    || (string) $capture->method !== $reference['method']
                    || (int) $capture->page_no !== $reference['page_no']) {
                    throw new RuntimeException('physical_capture_incomplete: Qualification requires a selected passing attempt for every method/page.');
                }
            }
            $captures = $lockedCaptures
                ->sortBy(static fn (OmrTemplateVerificationCapture $capture): string => sprintf('%s:%06d', $capture->method, (int) $capture->page_no))
                ->values();
            $this->assertExactRecovery($lockedRun, $version, $captures, count($uniqueCaptureIds));

            $qualificationEvidence = $evidence;
            if ($target->registrationMode() === OmrRegistrationMode::FiducialAnchored) {
                [$measured, $expected] = $this->measurements($evidence, $manifest);
                $this->assertMeasurements($measured, $expected);
                $qualificationEvidence['measured_marker_center_mm'] = $measured;
                $qualificationEvidence['expected_marker_center_mm'] = $expected;
            }
            $readiness = $this->qualification->derive($lockedRun, $version, $qualificationEvidence);
            if (! $readiness['ready']) {
                $blocker = $readiness['blockers'][0] ?? null;
                throw new RuntimeException(
                    (string) ($blocker['code'] ?? 'qualification_run_invalid').': '.(string) ($blocker['detail'] ?? 'The physical qualification checklist is not complete.'),
                );
            }

            throw new RuntimeException('physical_provenance_unavailable: The current capture workflow cannot verify genuine printer and device provenance.');
        });
    }

    /**
     * @param  Collection<int, OmrTemplateVerificationCapture>  $captures
     */
    private function assertExactRecovery(
        OmrTemplateVerificationRun $run,
        OmrAnswerSheetTemplateVersion $version,
        $captures,
        int $requiredCaptureCount,
    ): void {
        if ($captures->isEmpty()) {
            throw new RuntimeException('physical_capture_missing: The passed run has no capture evidence to derive qualification from.');
        }
        if ($captures->count() !== $requiredCaptureCount) {
            throw new RuntimeException('physical_capture_incomplete: Qualification requires a selected passing attempt for every method/page.');
        }
        foreach ($captures as $capture) {
            if ($capture->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
                || $capture->status !== OmrTemplateVerificationCapture::STATUS_PASS
                || $capture->vector_match !== true) {
                throw new RuntimeException('physical_exact_recovery_missing: Every qualifying capture must recover the expected vector exactly; reviewed/ambiguous evidence never qualifies.');
            }
            if ($capture->method === OmrTemplateVerificationCapture::METHOD_HAND_SHADED
                && ! in_array($capture->medium, OmrTemplateVerificationCapture::HAND_SHADED_MEDIA, true)) {
                throw new RuntimeException('physical_capture_hand_medium_required: Hand-shaded qualification requires a declared HB/#2 pencil or dark black pen.');
            }
            if ($capture->method === OmrTemplateVerificationCapture::METHOD_PRE_SHADED && $capture->medium !== null) {
                throw new RuntimeException('physical_capture_pre_medium_forbidden: Pre-shaded qualification cannot carry a hand-shading medium.');
            }
            if ($version->registration_mode === OmrRegistrationMode::FiducialAnchored) {
                $gates = is_array($capture->summary['gates'] ?? null) ? $capture->summary['gates'] : [];
                foreach (['marker_count', 'registration', 'dimensions', 'shifts'] as $gate) {
                    if (($gates[$gate] ?? 'fail') !== 'pass') {
                        throw new RuntimeException('physical_gates_failed: A qualifying capture failed a marker/dimension/shift gate.');
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @param  array<string, mixed>  $manifest
     * @return array{0: list<float>, 1: list<float>}
     */
    private function measurements(array $evidence, array $manifest): array
    {
        $paper = (array) ($manifest['paper'] ?? []);
        $insets = (array) ($manifest['marker_insets_mm'] ?? []);
        $expected = [
            (float) (($paper['width_mm'] ?? 0) - ($insets['left'] ?? 0) - ($insets['right'] ?? 0)),
            (float) (($paper['height_mm'] ?? 0) - ($insets['top'] ?? 0) - ($insets['bottom'] ?? 0)),
        ];
        $measured = array_map('floatval', array_values((array) ($evidence['measured_marker_center_mm'] ?? [])));

        return [$measured, $expected];
    }

    /**
     * @param  list<float>  $measured
     * @param  list<float>  $expected
     */
    private function assertMeasurements(array $measured, array $expected): void
    {
        if (count($measured) !== 2 || min($expected) <= 0) {
            throw new RuntimeException('physical_measurement_out_of_tolerance: Marker-center measurements must contain the width and height in mm.');
        }
        $tolerance = (float) config('omr.canonical.physical_measurement_tolerance_mm', 1.0);
        if (max(abs($measured[0] - $expected[0]), abs($measured[1] - $expected[1])) > $tolerance) {
            throw new RuntimeException('physical_measurement_out_of_tolerance: The measured printed marker centers deviate from the expected mm geometry.');
        }
    }
}
