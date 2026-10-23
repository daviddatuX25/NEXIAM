<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitCommandReceipt;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ActivateTestKitVersion
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCommandReceipt $receipts,
        private readonly TestKitEligibility $eligibility,
        private readonly RecordAuditEvent $audit,
        private readonly KitCompositionContext $compositionContext,
    ) {}

    /**
     * @param  array{expected_revision?:int|string|null,idempotency_key?:string|null,academic_cycle_id?:int|string|null}|int  $input
     */
    public function handle(
        User $actor,
        int $testKitId,
        int $versionId,
        array|int $input,
        ?string $idempotencyKey = null,
        ?AcademicCycle $cycle = null,
    ): TestKitVersion {
        if (is_array($input)) {
            $rawRevision = $input['expected_revision'] ?? null;
            $rawKey = $input['idempotency_key'] ?? $idempotencyKey;
            $cycleInput = $input['academic_cycle_id'] ?? null;
        } else {
            $rawRevision = $input;
            $rawKey = $idempotencyKey;
            $cycleInput = null;
        }

        if ($rawRevision === null
            || (! is_int($rawRevision) && (! is_string($rawRevision) || preg_match('/^(0|[1-9][0-9]*)$/D', $rawRevision) !== 1))) {
            throw ValidationException::withMessages([
                'expected_revision' => 'A valid expected revision is required.',
            ]);
        }
        $expectedRevision = (int) $rawRevision;

        if ($rawKey === null || trim((string) $rawKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An idempotency key is required.',
            ]);
        }
        $key = trim((string) $rawKey);

        $resolvedCycle = $cycle;
        if ($resolvedCycle === null && $cycleInput !== null && is_numeric($cycleInput)) {
            $resolvedCycle = AcademicCycle::query()->find((int) $cycleInput);
        }

        return DB::transaction(function () use ($actor, $testKitId, $versionId, $expectedRevision, $key, $resolvedCycle): TestKitVersion {
            $kit = TestKit::query()->lockForUpdate()->findOrFail($testKitId);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $versions = TestKitVersion::query()
                ->where('test_kit_id', $kit->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $version = $versions->firstWhere('id', $versionId);
            if ($version === null) {
                throw (new ModelNotFoundException)->setModel(TestKitVersion::class, [$versionId]);
            }

            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.activate',
                (string) $kit->id,
                [
                    'test_kit_id' => (int) $kit->id,
                    'test_kit_version_id' => (int) $version->id,
                    'expected_revision' => $expectedRevision,
                ],
                $key,
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                return TestKitVersion::query()->where('test_kit_id', $kit->id)->findOrFail($result['test_kit_version_id']);
            }

            if ($version->status !== TestKitVersion::STATUS_CANDIDATE) {
                throw ValidationException::withMessages(['version' => 'Only a candidate Test Kit version can be activated.']);
            }
            if ($version->revoked_at !== null) {
                throw ValidationException::withMessages(['version' => 'A revoked Test Kit version cannot be activated.']);
            }
            if ((int) $version->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['expected_revision' => 'The Test Kit version changed. Reload it before activation.']);
            }
            $resolved = $this->compositionContext->resolveVersion($version);
            $requirements = (array) ($resolved['requirements'] ?? []);
            $needsOmr = (bool) ($requirements['omr_applicable'] ?? false);
            $selectedReceipt = null;
            if ($needsOmr) {
                if ($version->selected_acceptance_receipt_id === null) {
                    throw ValidationException::withMessages(['acceptance_receipt' => 'Select a passing acceptance receipt before activating OMR use.']);
                }
                $selectedReceipt = TestKitAcceptanceReceipt::query()
                    ->where('test_kit_version_id', $version->id)
                    ->whereKey($version->selected_acceptance_receipt_id)
                    ->lockForUpdate()
                    ->first();
                if ($selectedReceipt === null) {
                    throw ValidationException::withMessages(['acceptance_receipt' => 'The selected acceptance receipt does not belong to this version.']);
                }
            }
            $profile = TestInstrumentVersion::query()->lockForUpdate()->findOrFail($version->test_instrument_version_id);
            $template = $version->template_version_id === null
                ? null
                : OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->template_version_id);
            $booklet = $version->booklet_version_id === null
                ? null
                : OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($version->booklet_version_id);
            $set = $version->booklet_set_id === null
                ? null
                : OmrBookletSet::query()
                    ->where('booklet_version_id', $booklet?->id)
                    ->whereKey($version->booklet_set_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            $version->setRelation('selectedAcceptanceReceipt', $selectedReceipt);
            $version->setRelation('testKit', $kit);
            $version->setRelation('testInstrumentVersion', $profile);
            $version->setRelation('bookletVersion', $booklet);
            $version->setRelation('bookletSet', $set);
            $version->setRelation('templateVersion', $template);
            $eligibility = $this->eligibility->evaluate(
                $version,
                $resolvedCycle,
                TestKitEligibility::PURPOSE_ACTIVATION,
                operation: $profile->isFinalOnly() ? 'external_final' : 'manual',
            );
            if (! $eligibility['eligible']) {
                throw ValidationException::withMessages([
                    'eligibility' => array_column($eligibility['blockers'], 'message'),
                ]);
            }

            $predecessorIds = [];
            foreach ($versions as $predecessor) {
                if ((int) $predecessor->id === (int) $version->id || $predecessor->status !== TestKitVersion::STATUS_ACTIVE) {
                    continue;
                }
                $predecessor->forceFill([
                    'status' => TestKitVersion::STATUS_RETIRED,
                    'retired_at' => now(),
                    'retired_by_user_id' => (int) $principal->id,
                    'revision' => (int) $predecessor->revision + 1,
                ])->save();
                $predecessorIds[] = (int) $predecessor->id;
            }

            $version->forceFill([
                'status' => TestKitVersion::STATUS_ACTIVE,
                'activated_at' => now(),
                'activated_by_user_id' => (int) $principal->id,
                'revision' => (int) $version->revision + 1,
            ])->save();
            $version->refresh();
            $this->receipts->remember($receipt, $version);
            $this->audit->handle('assessment.test_kit.version_activated', [
                'test_kit_id' => (int) $kit->id,
                'test_kit_version_id' => (int) $version->id,
                'selected_acceptance_receipt_id' => $selectedReceipt?->id,
                'retired_predecessor_version_ids' => $predecessorIds,
            ], (string) $principal->id);

            return $version;
        });
    }
}
