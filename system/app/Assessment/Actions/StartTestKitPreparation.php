<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitPreparationReceipt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitPreparationCompositionRevision;
use App\Models\Assessment\TestKitPreparationCompositionRow;
use App\Models\Assessment\TestKitVersion;
use App\Models\Assessment\TestKitVersionCompositionRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class StartTestKitPreparation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitPreparationReceipt $receipts,
        private readonly KitCompositionContext $compositionContext,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{expected_family_version:int,idempotency_key:string}  $input
     */
    public function handle(User $actor, int $testKitId, array $input): TestKitPreparation
    {
        if (array_diff(['expected_family_version', 'idempotency_key'], array_keys($input)) !== []
            || array_diff(array_keys($input), ['expected_family_version', 'idempotency_key']) !== []) {
            throw ValidationException::withMessages(['preparation' => 'Only the expected family version and idempotency key are accepted.']);
        }
        $expectedVersion = $input['expected_family_version'];
        if ((! is_int($expectedVersion) && (! is_string($expectedVersion) || preg_match('/^(0|[1-9][0-9]*)$/D', $expectedVersion) !== 1))) {
            throw ValidationException::withMessages(['expected_family_version' => 'A non-negative family version is required.']);
        }
        if (! is_string($input['idempotency_key']) || ! Str::isUuid($input['idempotency_key'])) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency UUID is required.']);
        }
        $input['expected_family_version'] = (int) $expectedVersion;

        return DB::transaction(function () use ($actor, $testKitId, $input): TestKitPreparation {
            $kit = TestKit::query()->lockForUpdate()->findOrFail($testKitId);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $payload = [
                'test_kit_id' => (int) $kit->id,
                'expected_family_version' => (int) $input['expected_family_version'],
            ];
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.start_preparation',
                (string) $kit->id,
                $payload,
                (string) $input['idempotency_key'],
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                if ($result['test_kit_id'] !== (int) $kit->id) {
                    throw new \RuntimeException('The Test Kit preparation receipt belongs to another family.');
                }

                return $this->receipts->preparationSnapshot($result);
            }
            $open = TestKitPreparation::query()
                ->where('test_kit_id', $kit->id)
                ->where('state', TestKitPreparation::STATE_PREPARING)
                ->lockForUpdate()
                ->first();
            if ($open !== null) {
                $this->receipts->remember($receipt, $open);

                return $open;
            }

            $previousPreparations = TestKitPreparation::query()
                ->where('test_kit_id', $kit->id)
                ->where('state', TestKitPreparation::STATE_ASSEMBLED)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('assembled_test_kit_version_id');
            $latestVersion = TestKitVersion::query()
                ->where('test_kit_id', $kit->id)
                ->orderByDesc('version_number')
                ->lockForUpdate()
                ->first();

            $currentFamilyVersion = (int) ($latestVersion?->version_number ?? 0);
            if ((int) $input['expected_family_version'] !== $currentFamilyVersion) {
                throw ValidationException::withMessages([
                    'expected_family_version' => 'The Test Kit changed since it was opened. Refresh before starting a preparation.',
                ]);
            }

            $previousPreparation = $latestVersion === null
                ? null
                : $previousPreparations->get((int) $latestVersion->id);
            $preparation = TestKitPreparation::query()->create([
                'test_kit_id' => (int) $kit->id,
                'state' => TestKitPreparation::STATE_PREPARING,
                'revision' => 1,
                'test_instrument_version_id' => $latestVersion?->test_instrument_version_id,
                'norm_package_version_id' => $previousPreparation?->norm_package_version_id,
                'answer_sheet_draft_id' => $previousPreparation?->answer_sheet_draft_id,
                'template_version_id' => $latestVersion?->template_version_id,
                'booklet_version_id' => $latestVersion?->booklet_version_id,
                'booklet_set_id' => $latestVersion?->booklet_set_id,
                'source_context_json' => $previousPreparation?->assembly_context_json,
                'source_context_sha256' => $previousPreparation?->assembly_context_sha256,
                'created_by_user_id' => (int) $principal->id,
                'updated_by_user_id' => (int) $principal->id,
            ]);

            if ($latestVersion !== null && $preparation->test_instrument_version_id !== null) {
                $composition = $this->compositionContext->resolvePreparation($preparation);
                $revisionRow = TestKitPreparationCompositionRevision::query()->create([
                    'test_kit_preparation_id' => (int) $preparation->id,
                    'test_kit_id' => (int) $kit->id,
                    'composition_revision' => (int) $preparation->composition_revision,
                    'template_version_id' => $preparation->template_version_id,
                    'booklet_version_id' => $preparation->booklet_version_id,
                    'booklet_set_id' => $preparation->booklet_set_id,
                    'test_instrument_version_id' => $preparation->test_instrument_version_id,
                    'component_vector_json' => $composition['component_vector'],
                    'component_vector_sha256' => $composition['component_vector_sha256'],
                    'created_at' => now(),
                ]);

                if ($latestVersion->composition_sha256 !== null) {
                    $versionRows = TestKitVersionCompositionRow::query()
                        ->where('test_kit_version_id', (int) $latestVersion->id)
                        ->orderBy('id')
                        ->get();

                    foreach ($versionRows as $row) {
                        TestKitPreparationCompositionRow::query()->create([
                            'test_kit_preparation_id' => (int) $preparation->id,
                            'test_kit_id' => (int) $kit->id,
                            'composition_revision_id' => (int) $revisionRow->id,
                            'composition_revision' => (int) $preparation->composition_revision,
                            'template_version_id' => $row->template_version_id,
                            'booklet_version_id' => $row->booklet_version_id,
                            'booklet_set_id' => $row->booklet_set_id,
                            'test_instrument_version_id' => $row->test_instrument_version_id,
                            'component_vector_sha256' => $revisionRow->component_vector_sha256,
                            'disposition' => $row->disposition,
                            'physical_item_uid' => $row->physical_item_uid,
                            'sheet_item_id' => $row->sheet_item_id,
                            'booklet_set_item_id' => $row->booklet_set_item_id,
                            'booklet_item_id' => $row->booklet_item_id,
                            'booklet_item_uid' => $row->booklet_item_uid,
                            'booklet_section_uid' => $row->booklet_section_uid,
                            'test_instrument_subtest_id' => $row->test_instrument_subtest_id,
                            'test_instrument_item_id' => $row->test_instrument_item_id,
                            'created_at' => now(),
                        ]);
                    }
                }
            }
            $this->receipts->remember($receipt, $preparation);
            $this->audit->handle('assessment.test_kit.preparation_started', [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'revision' => (int) $preparation->revision,
                'expected_family_version' => $currentFamilyVersion,
            ], (string) $principal->id);

            return $preparation;
        });
    }
}
