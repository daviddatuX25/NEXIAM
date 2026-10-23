<?php

namespace App\Assessment\Support;

use App\Assessment\Omr\Verification\OmrPhysicalQualificationDeriver;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestKitEligibility
{
    public function __construct(private readonly TestKitCurrentAcceptanceStatus $acceptanceStatus) {}

    public function allowsReacceptance(TestKitVersion $version): bool
    {
        if ($version->revoked_at !== null || ! in_array($version->status, [
            TestKitVersion::STATUS_CANDIDATE,
            TestKitVersion::STATUS_ACTIVE,
            TestKitVersion::STATUS_RETIRED,
        ], true)) {
            return false;
        }

        return $version->status !== TestKitVersion::STATUS_RETIRED
            || $this->hasFrozenOperationalUsePin($version);
    }

    private function hasFrozenOperationalUsePin(TestKitVersion $version): bool
    {
        $versionId = (int) $version->getKey();
        $manifest = (string) $version->manifest_sha256;
        $snapshotPin = DB::table('exam_session_material_snapshots as snapshot')
            ->join('exam_sessions as session', 'session.id', '=', 'snapshot.exam_session_id')
            ->join('academic_cycles as cycle', 'cycle.id', '=', 'session.academic_cycle_id')
            ->join('test_kit_versions as kit_version', 'kit_version.id', '=', 'snapshot.test_kit_version_id')
            ->join('test_kits as kit', 'kit.id', '=', 'kit_version.test_kit_id')
            ->join('test_kit_acceptance_receipts as receipt', 'receipt.id', '=', 'snapshot.acceptance_receipt_id')
            ->where('snapshot.test_kit_version_id', $versionId)
            ->where('snapshot.manifest_sha256', $manifest)
            ->where('kit_version.test_kit_id', (int) $version->test_kit_id)
            ->whereColumn('cycle.institution_id', 'kit.institution_id')
            ->whereNotNull('snapshot.published_by_user_id')
            ->whereNotNull('snapshot.published_at')
            ->where('receipt.test_kit_version_id', $versionId)
            ->where('receipt.manifest_sha256', $manifest)
            ->where('receipt.result', TestKitAcceptanceReceipt::RESULT_PASSED)
            ->exists();

        if ($snapshotPin) {
            return true;
        }

        return DB::table('assessment_administrations as administration')
            ->join('exam_assignments as assignment', 'assignment.id', '=', 'administration.exam_assignment_id')
            ->join('exam_sessions as session', 'session.id', '=', 'assignment.exam_session_id')
            ->join('academic_cycles as cycle', 'cycle.id', '=', 'session.academic_cycle_id')
            ->join('admission_attempts as attempt', 'attempt.id', '=', 'assignment.admission_attempt_id')
            ->join('test_kit_versions as kit_version', 'kit_version.id', '=', 'administration.test_kit_version_id')
            ->join('test_kits as kit', 'kit.id', '=', 'kit_version.test_kit_id')
            ->join('test_kit_acceptance_receipts as receipt', 'receipt.id', '=', 'administration.acceptance_receipt_id')
            ->where('administration.test_kit_version_id', $versionId)
            ->where('administration.manifest_sha256', $manifest)
            ->where('administration.test_instrument_version_id', (int) $version->test_instrument_version_id)
            ->where('kit_version.test_kit_id', (int) $version->test_kit_id)
            ->whereColumn('administration.admission_attempt_id', 'attempt.id')
            ->whereColumn('attempt.academic_cycle_id', 'cycle.id')
            ->whereColumn('attempt.institution_id', 'kit.institution_id')
            ->whereColumn('cycle.institution_id', 'kit.institution_id')
            ->whereNotNull('administration.bound_by_user_id')
            ->whereNotNull('administration.bound_at')
            ->whereNull('administration.superseded_at')
            ->where('receipt.test_kit_version_id', $versionId)
            ->where('receipt.manifest_sha256', $manifest)
            ->where('receipt.result', TestKitAcceptanceReceipt::RESULT_PASSED)
            ->exists();
    }

    public const PURPOSE_REHEARSAL = 'rehearsal';

    public const PURPOSE_ACTIVATION = 'activation';

    public const PURPOSE_NEW_USE = 'new_use';

    public const PURPOSE_PINNED_USE = 'pinned_use';

    /**
     * @return array{eligible:bool,blockers:list<array{code:string,message:string}>,pins:array<string,mixed>}
     */
    public function evaluate(
        TestKitVersion $version,
        ?AcademicCycle $cycle,
        string $purpose,
        ?TestKitAcceptanceReceipt $frozenReceipt = null,
        ?TestKitAcceptanceReceipt $operationReceipt = null,
        string $operation = 'omr',
    ): array {
        $blockers = [];
        $pins = [
            'test_kit_id' => (int) $version->test_kit_id,
            'test_kit_version_id' => (int) $version->getKey(),
            'manifest_sha256' => $version->manifest_sha256,
            'selected_acceptance_receipt_id' => $version->selected_acceptance_receipt_id === null
                ? null
                : (int) $version->selected_acceptance_receipt_id,
            'academic_cycle_id' => $cycle?->getKey() === null ? null : (int) $cycle->getKey(),
        ];
        $allowedPurposes = [self::PURPOSE_REHEARSAL, self::PURPOSE_ACTIVATION, self::PURPOSE_NEW_USE, self::PURPOSE_PINNED_USE];
        if (! in_array($purpose, $allowedPurposes, true)) {
            $this->block($blockers, 'purpose_not_supported', 'The requested Test Kit eligibility purpose is not supported.');

            return ['eligible' => false, 'blockers' => $blockers, 'pins' => $pins];
        }
        if (! in_array($operation, ['manual', 'import', 'omr', 'external_final', 'rehearsal'], true)) {
            $this->block($blockers, 'operation_not_supported', 'The requested assessment operation is not supported.');
        }
        if ($version->testInstrumentVersion?->isFinalOnly() === true
            && $operation !== 'external_final') {
            $this->block($blockers, 'external_final_operation_required', 'A final-only Test Setup can be used only through the external-final scoring operation.');
        }

        $version->loadMissing(['testKit', 'testInstrumentVersion', 'selectedAcceptanceReceipt']);
        if ($version->testKit === null || $version->testInstrumentVersion === null
            || (int) $version->institution_id !== (int) $version->testKit->institution_id
            || (bool) $version->is_synthetic !== (bool) $version->testKit->is_synthetic) {
            $this->block($blockers, 'kit_identity_incoherent', 'The exact Test Kit family and version ownership pins do not match.');
        }

        $resolved = null;
        try {
            $resolved = app(KitCompositionContext::class)->resolveVersion($version);
        } catch (Throwable) {
            $this->block($blockers, 'configuration_incoherent', 'The exact locked Kit configuration or approved Setup declaration is stale or incoherent.');
        }
        $requirements = is_array($resolved) ? ($resolved['requirements'] ?? null) : null;
        if (! is_array($requirements) || ! is_array($requirements['metrics'] ?? null)) {
            $this->block($blockers, 'requirements_unavailable', 'The exact approved Setup requirements cannot be verified.');
            $requirements = ['metrics' => [], 'required_resources' => [], 'omr_applicable' => false];
        }

        $requiredResources = (array) ($requirements['required_resources'] ?? []);
        $isPaperApplicable = array_intersect($requiredResources, ['answer_sheet', 'booklet_form', 'physical_procedure']) !== [];
        if ($purpose !== self::PURPOSE_ACTIVATION
            && $operation === 'omr'
            && ! ($requirements['omr_applicable'] ?? false)) {
            $this->block($blockers, 'omr_not_applicable', 'The selected Setup does not declare OMR as an applicable input source.');
        }

        if ($purpose === self::PURPOSE_REHEARSAL && ! $this->allowsReacceptance($version)) {
            $this->block($blockers, 'version_reacceptance_unavailable', 'Only Candidate, Active, or operationally pinned Retired versions may continue acceptance rehearsal; revoked versions are unavailable.');
        }
        if ($purpose === self::PURPOSE_ACTIVATION) {
            if ($version->revoked_at !== null) {
                $this->block($blockers, 'version_revoked', 'A revoked Test Kit version cannot be activated.');
            }
            if ($version->testInstrumentVersion?->status !== 'approved') {
                $this->block($blockers, 'approved_profile_required', 'Activation requires the exact approved profile version.');
            }
            if ((bool) $version->testKit?->is_synthetic) {
                $this->block($blockers, 'institutional_provenance_required', 'Synthetic materials cannot be activated for institutional use.');
            }
            if ($requirements['omr_applicable'] ?? false) {
                $receipt = $version->selectedAcceptanceReceipt;
                if ($receipt === null || $receipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED
                    || ! hash_equals((string) $version->manifest_sha256, (string) $receipt->manifest_sha256)
                    || ! $this->acceptanceStatus->forReceipt($version, $receipt)['current']) {
                    $this->block($blockers, 'passing_receipt_required', 'Current passing acceptance evidence for the exact OMR Kit is required.');
                }
                $physical = app(OmrPhysicalQualificationDeriver::class)->derive(null, $version->templateVersion);
                if (! ($physical['qualified'] ?? false)) {
                    $this->block($blockers, 'physical_qualification_required', 'Current physical qualification evidence is required.');
                }
                $this->block($blockers, 'source_evidence_unavailable', 'Lawful institutional source, rights, and independent expected-truth evidence are not currently verifiable.');
            }
            if ($isPaperApplicable && ! ($requirements['omr_applicable'] ?? false)) {
                $this->block($blockers, 'source_evidence_unavailable', 'Lawful paper-material source, rights, and reproduction permissions are not currently verifiable.');
            }
            if ($cycle !== null && (int) $cycle->institution_id !== (int) $version->testKit?->institution_id) {
                $this->block($blockers, 'cycle_scope_mismatch', 'The academic cycle must belong to the Test Kit institution.');
            }
        }

        if (in_array($purpose, [self::PURPOSE_NEW_USE, self::PURPOSE_PINNED_USE], true)) {
            if ($purpose === self::PURPOSE_NEW_USE && $version->status !== TestKitVersion::STATUS_ACTIVE) {
                $this->block($blockers, 'active_version_required', 'New unbound use requires an Active Test Kit version.');
            }
            if ($version->revoked_at !== null) {
                $this->block($blockers, 'version_revoked', 'A revoked Test Kit version cannot authorize use.');
            }
            if ($purpose === self::PURPOSE_PINNED_USE
                && ! in_array($version->status, [TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_RETIRED], true)) {
                $this->block($blockers, 'frozen_version_unavailable', 'Pinned use requires the exact version to remain Active or ordinarily Retired.');
            }
            if ($cycle === null || (int) $cycle->institution_id !== (int) $version->testKit?->institution_id) {
                $this->block($blockers, 'cycle_scope_mismatch', 'The academic cycle must belong to the Test Kit institution.');
            }
            if ($operation === 'omr') {
                $receipt = $purpose === self::PURPOSE_PINNED_USE
                    ? $frozenReceipt
                    : $version->selectedAcceptanceReceipt;
                if ($purpose === self::PURPOSE_NEW_USE) {
                    if ($receipt === null || (int) $receipt->test_kit_version_id !== (int) $version->getKey()
                        || $receipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED
                        || ! hash_equals((string) $version->manifest_sha256, (string) $receipt->manifest_sha256)) {
                        $this->block($blockers, 'passing_receipt_required', 'A passing receipt for the exact Kit and manifest is required for new OMR use.');
                    } else {
                        $pins['acceptance_receipt_id'] = (int) $receipt->getKey();
                        $pins['acceptance_protocol_sha256'] = $receipt->acceptance_protocol_sha256;
                        $pins['acceptance_plan_sha256'] = $receipt->acceptance_plan_sha256;
                        if (! $this->acceptanceStatus->forReceipt($version, $receipt)['current']) {
                            $this->block($blockers, 'acceptance_evidence_stale', 'Current complete acceptance evidence for the exact OMR Kit is required.');
                        }
                    }
                } elseif ($receipt !== null) {
                    if ((int) $receipt->test_kit_version_id !== (int) $version->getKey()
                        || $receipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED
                        || ! hash_equals((string) $version->manifest_sha256, (string) $receipt->manifest_sha256)) {
                        $this->block($blockers, 'frozen_receipt_mismatch', 'The original OMR receipt does not match this exact pinned Kit.');
                    } else {
                        $pins['acceptance_receipt_id'] = (int) $receipt->getKey();
                        $pins['acceptance_protocol_sha256'] = $receipt->acceptance_protocol_sha256;
                        $pins['acceptance_plan_sha256'] = $receipt->acceptance_plan_sha256;
                    }
                }

                if ($purpose === self::PURPOSE_PINNED_USE) {
                    if ($operationReceipt === null) {
                        $this->block($blockers, 'operation_acceptance_receipt_required', 'This OMR operation requires its own current acceptance receipt.');
                    } else {
                        $pins['operation_acceptance_receipt_id'] = (int) $operationReceipt->getKey();
                        if ((int) $operationReceipt->test_kit_version_id !== (int) $version->getKey()
                            || $operationReceipt->result !== TestKitAcceptanceReceipt::RESULT_PASSED
                            || ! hash_equals((string) $version->manifest_sha256, (string) $operationReceipt->manifest_sha256)
                            || ! $this->acceptanceStatus->forReceipt($version, $operationReceipt, lock: true)['current']) {
                            $this->block($blockers, 'operation_acceptance_evidence_stale', 'Current complete acceptance evidence for this OMR operation is required.');
                        }
                    }
                }
            }
            if ($purpose === self::PURPOSE_PINNED_USE && $operation === 'omr'
                && $frozenReceipt !== null && (int) $frozenReceipt->test_kit_version_id !== (int) $version->getKey()) {
                $this->block($blockers, 'frozen_receipt_mismatch', 'The original OMR receipt does not belong to this exact pinned Kit.');
            }
            if ($isPaperApplicable) {
                $this->block($blockers, 'source_evidence_unavailable', 'Lawful source, rights, and applicable material-use permissions are not currently verifiable.');
            }
        }

        return ['eligible' => $blockers === [], 'blockers' => $blockers, 'pins' => $pins];
    }

    /** @param list<array{code:string,message:string}> $blockers */
    private function block(array &$blockers, string $code, string $message): void
    {
        foreach ($blockers as $blocker) {
            if ($blocker['code'] === $code) {
                return;
            }
        }

        $blockers[] = ['code' => $code, 'message' => $message];
    }
}
