<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitCommandReceipt;
use App\Assessment\Support\TestKitPreparationReceipt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitPreparationCompositionRevision;
use App\Models\Assessment\TestKitPreparationCompositionRow;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssembleTestKitPreparation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitPreparationReceipt $receipts,
        private readonly TestKitCommandReceipt $kitReceipts,
        private readonly AssembleTestKitVersion $assembler,
        private readonly KitCompositionContext $compositionContext,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{preparation_id:int,expected_revision:int,idempotency_key:string}  $input
     */
    public function handle(User $actor, int $testKitId, array $input): TestKitVersion
    {
        $fields = ['preparation_id', 'expected_revision', 'idempotency_key'];
        if (array_diff($fields, array_keys($input)) !== [] || array_diff(array_keys($input), $fields) !== []) {
            throw ValidationException::withMessages(['preparation' => 'Only preparation identity, expected revision, and idempotency key are accepted for assembly.']);
        }
        foreach (['preparation_id', 'expected_revision'] as $field) {
            $value = $input[$field] ?? null;
            if ((! is_int($value) && (! is_string($value) || preg_match('/^(0|[1-9][0-9]*)$/D', $value) !== 1))
                || (int) $value < ($field === 'preparation_id' ? 1 : 0)) {
                throw ValidationException::withMessages([$field => 'A valid non-negative integer is required.']);
            }
            $input[$field] = (int) $value;
        }
        if (! is_string($input['idempotency_key'] ?? null) || ! Str::isUuid($input['idempotency_key'])) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency UUID is required.']);
        }

        return DB::transaction(function () use ($actor, $testKitId, $input): TestKitVersion {
            $kit = TestKit::query()->lockForUpdate()->findOrFail($testKitId);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $preparation = TestKitPreparation::query()->where('test_kit_id', $kit->id)
                ->lockForUpdate()->findOrFail($input['preparation_id']);
            $latestVersion = TestKitVersion::query()->where('test_kit_id', $kit->id)
                ->orderByDesc('version_number')->lockForUpdate()->first();
            $familyVersion = (int) ($latestVersion?->version_number ?? 0);
            $payload = [
                'test_kit_id' => (int) $kit->id,
                'expected_revision' => $input['expected_revision'],
            ];
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.assemble_preparation',
                (string) $preparation->id,
                $payload,
                $input['idempotency_key'],
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                if ($result['test_kit_id'] !== (int) $kit->id || $result['preparation_id'] !== (int) $preparation->id
                    || $result['assembled_test_kit_version_id'] === null) {
                    throw new \RuntimeException('The Test Kit assembly receipt has an invalid result identity.');
                }

                return TestKitVersion::query()->where('test_kit_id', $kit->id)->findOrFail($result['assembled_test_kit_version_id']);
            }
            if ($preparation->state !== TestKitPreparation::STATE_PREPARING) {
                throw ValidationException::withMessages(['preparation_id' => 'This preparation is already assembled.']);
            }
            if ((int) $preparation->revision !== $input['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'This preparation changed. Refresh before assembling.']);
            }

            if ($preparation->test_instrument_version_id === null) {
                throw ValidationException::withMessages(['test_instrument_version_id' => 'Select an approved Test Setup with complete input declarations before assembly.']);
            }
            $sourceRevision = TestKitPreparationCompositionRevision::query()
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', (int) $preparation->composition_revision)
                ->lockForUpdate()
                ->first();
            if ($sourceRevision === null) {
                throw ValidationException::withMessages(['composition' => 'Save the exact current component selections before assembly.']);
            }
            $assemblyKey = (string) Str::uuid();
            $selection = [
                'template_version_id' => $preparation->template_version_id === null ? null : (int) $preparation->template_version_id,
                'booklet_version_id' => $preparation->booklet_version_id === null ? null : (int) $preparation->booklet_version_id,
                'booklet_set_id' => $preparation->booklet_set_id === null ? null : (int) $preparation->booklet_set_id,
                'test_instrument_version_id' => (int) $preparation->test_instrument_version_id,
                'source_preparation_id' => (int) $preparation->id,
                'source_preparation_revision' => (int) $preparation->revision,
                'composition_revision' => (int) $preparation->composition_revision,
                'component_vector' => $sourceRevision->component_vector_json,
                'component_vector_sha256' => (string) $sourceRevision->component_vector_sha256,
                'expected_revision' => $familyVersion,
                'idempotency_key' => $assemblyKey,
            ];
            $kitReceipt = $this->kitReceipts->claim(
                $principal,
                'test_kit.assemble',
                (string) $kit->id,
                [
                    'test_kit_id' => (int) $kit->id,
                    'template_version_id' => $selection['template_version_id'],
                    'booklet_version_id' => $selection['booklet_version_id'],
                    'booklet_set_id' => $selection['booklet_set_id'],
                    'test_instrument_version_id' => $selection['test_instrument_version_id'],
                    'source_preparation_id' => $selection['source_preparation_id'],
                    'source_preparation_revision' => $selection['source_preparation_revision'],
                    'composition_revision' => $selection['composition_revision'],
                    'component_vector_sha256' => $selection['component_vector_sha256'],
                    'expected_revision' => $selection['expected_revision'],
                ],
                $assemblyKey,
            );

            $profileCandidate = TestInstrumentVersion::query()->findOrFail((int) $preparation->test_instrument_version_id);
            TestInstrument::query()->lockForUpdate()->findOrFail((int) $profileCandidate->test_instrument_id);
            $profile = TestInstrumentVersion::query()->lockForUpdate()->findOrFail($profileCandidate->id);
            $profileOwner = TestInstrument::query()->findOrFail((int) $profile->test_instrument_id);
            $this->assertOwner($kit, $profileOwner, 'test_instrument_version_id');

            $norm = null;
            if ($preparation->norm_package_version_id !== null) {
                $normCandidate = AssessmentNormPackageVersion::query()->findOrFail((int) $preparation->norm_package_version_id);
                AssessmentNormPackage::query()->lockForUpdate()->findOrFail((int) $normCandidate->norm_package_id);
                $norm = AssessmentNormPackageVersion::query()->lockForUpdate()->findOrFail($normCandidate->id);
                $normOwner = AssessmentNormPackage::query()->findOrFail((int) $norm->norm_package_id);
                $this->assertOwner($kit, $normOwner, 'norm_package_version_id');
            }

            $booklet = null;
            $set = null;
            if ($preparation->booklet_version_id !== null) {
                $bookletCandidate = OmrQuestionBookletVersion::query()->findOrFail((int) $preparation->booklet_version_id);
                OmrQuestionBooklet::query()->lockForUpdate()->findOrFail((int) $bookletCandidate->booklet_id);
                $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($bookletCandidate->id);
                $this->assertOwner($kit, OmrQuestionBooklet::query()->findOrFail((int) $booklet->booklet_id), 'booklet_version_id');
                $set = OmrBookletSet::query()->where('booklet_version_id', $booklet->id)
                    ->lockForUpdate()->findOrFail((int) $preparation->booklet_set_id);
                if ($set->set_code !== 'A') {
                    throw ValidationException::withMessages(['booklet_set_id' => 'Only finalized Set A can be assembled into a Test Kit.']);
                }
            } elseif ($preparation->booklet_set_id !== null) {
                throw ValidationException::withMessages(['booklet_set_id' => 'A Set cannot be selected without its exact Booklet.']);
            }
            $template = null;
            if ($preparation->template_version_id !== null) {
                $templateCandidate = OmrAnswerSheetTemplateVersion::query()->findOrFail((int) $preparation->template_version_id);
                $templateOwner = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail((int) $templateCandidate->template_id);
                $template = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail((int) $templateCandidate->id);
                $this->assertOwner($kit, $templateOwner, 'template_version_id');
            }
            $revision = TestKitPreparationCompositionRevision::query()
                ->whereKey($sourceRevision->id)
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', (int) $preparation->composition_revision)
                ->lockForUpdate()
                ->first();
            if ($revision === null
                || ! hash_equals((string) $sourceRevision->component_vector_sha256, (string) $revision->component_vector_sha256)) {
                throw ValidationException::withMessages(['composition' => 'The exact saved component revision changed during assembly.']);
            }
            $savedRows = TestKitPreparationCompositionRow::query()
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', (int) $preparation->composition_revision)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $resolvedComposition = $this->compositionContext->resolvePreparation($preparation);
            if ($resolvedComposition['requirements'] === null
                || $resolvedComposition['state'] !== 'ready'
                || ! hash_equals((string) $revision->component_vector_sha256, (string) $resolvedComposition['component_vector_sha256'])
                || count($savedRows) !== count($resolvedComposition['rows'])) {
                $firstIssue = $resolvedComposition['issues'][0]['message'] ?? 'The exact saved component selections are incomplete or stale.';
                throw ValidationException::withMessages(['composition' => $firstIssue]);
            }
            if ($resolvedComposition['map_applicable'] === true) {
                if ($resolvedComposition['map_complete'] !== true || ! is_string($resolvedComposition['composition_sha256'])) {
                    throw ValidationException::withMessages(['composition' => 'The exact applicable OMR item map is incomplete or stale.']);
                }
                $selection['composition_rows'] = $resolvedComposition['rows'];
                $selection['composition_sha256'] = $resolvedComposition['composition_sha256'];
            } else {
                if ($resolvedComposition['map_complete'] !== false
                    || $resolvedComposition['item_map'] !== null
                    || $resolvedComposition['composition_sha256'] !== null
                    || $resolvedComposition['rows'] !== []
                    || $savedRows->isNotEmpty()) {
                    throw ValidationException::withMessages(['composition' => 'This Test Setup does not permit an OMR map or mapped rows.']);
                }
                $selection['composition_rows'] = null;
                $selection['composition_sha256'] = null;
            }
            $assemblyContext = $this->assemblyContext($preparation, $profile, $norm, $resolvedComposition);
            $version = $this->assembler->handle($principal, (int) $kit->id, $selection, deferAudit: true, claimedReceipt: $kitReceipt);

            $preparation->state = TestKitPreparation::STATE_ASSEMBLED;
            $preparation->assembled_test_kit_version_id = (int) $version->id;
            $preparation->assembly_context_json = $assemblyContext;
            $preparation->assembly_context_sha256 = hash('sha256', json_encode($assemblyContext, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $preparation->updated_by_user_id = (int) $principal->id;
            $preparation->save();
            $this->receipts->remember($receipt, $preparation, $version);
            $this->audit->handle('assessment.test_kit.preparation_assembled', [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'preparation_revision' => (int) $preparation->revision,
                'test_kit_version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'assembly_context_sha256' => (string) $preparation->assembly_context_sha256,
                'manifest_sha256' => (string) $version->manifest_sha256,
            ], (string) $principal->id);

            return $version;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function assemblyContext(
        TestKitPreparation $preparation,
        TestInstrumentVersion $profile,
        ?AssessmentNormPackageVersion $norm,
        array $composition,
    ): array {
        $context = [
            'preparation_id' => (int) $preparation->id,
            'preparation_revision' => (int) $preparation->revision,
            'composition' => [
                'revision' => (int) $preparation->composition_revision,
                'component_vector_sha256' => (string) $composition['component_vector_sha256'],
                'composition_sha256' => $composition['composition_sha256'],
                'matched' => (int) $composition['matched'],
                'expected' => (int) $composition['expected'],
                'omr_applicable' => $composition['omr_applicable'],
                'applicable_resources' => $composition['applicable_resources'],
                'map_complete' => $composition['map_complete'],
            ],
            'selection' => [
                'test_instrument_version_id' => (int) $profile->id,
                'norm_package_version_id' => $norm === null ? null : (int) $norm->id,
                'answer_sheet_draft_id' => $preparation->answer_sheet_draft_id === null ? null : (int) $preparation->answer_sheet_draft_id,
                'template_version_id' => $preparation->template_version_id === null ? null : (int) $preparation->template_version_id,
                'booklet_version_id' => $preparation->booklet_version_id === null ? null : (int) $preparation->booklet_version_id,
                'booklet_set_id' => $preparation->booklet_set_id === null ? null : (int) $preparation->booklet_set_id,
            ],
            'profile' => [
                'id' => (int) $profile->id,
                'lock_version' => (int) $profile->lock_version,
                'status' => (string) $profile->status,
                'content_sha256' => $profile->content_sha256,
                'display_name' => (string) $profile->display_name,
                'publisher_name' => (string) $profile->publisher_name,
                'form_code' => (string) $profile->form_code,
                'edition' => (string) $profile->edition,
                'language' => (string) $profile->language,
                'source_reference' => $profile->source_reference,
                'permitted_use_reference' => $profile->permitted_use_reference,
                'source_reviewed_by_user_id' => $profile->source_reviewed_by_user_id === null ? null : (int) $profile->source_reviewed_by_user_id,
                'source_reviewed_at' => $profile->source_reviewed_at?->toISOString(),
                'approved_by_user_id' => $profile->approved_by_user_id === null ? null : (int) $profile->approved_by_user_id,
                'approved_at' => $profile->approved_at?->toISOString(),
            ],
            'norm' => null,
        ];
        if ($norm !== null) {
            $norm->loadMissing('package');
            $tables = $norm->tables()->get()->map(static function ($table): array {
                return [
                    'metric_code' => (string) $table->metric_code,
                    'output_scale' => (string) $table->output_scale,
                    'intervals' => $table->intervals_json,
                ];
            })->all();
            $normalized = $this->sortAssociativeKeys($tables);
            $tableDigest = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $context['norm'] = [
                'id' => (int) $norm->id,
                'package_id' => (int) $norm->norm_package_id,
                'lock_version' => (int) $norm->lock_version,
                'content_sha256' => $norm->content_sha256,
                'tables_sha256' => $tableDigest,
                'status' => (string) $norm->status,
                'metadata' => [
                    'code' => (string) $norm->package->code,
                    'display_name' => $norm->display_name,
                    'publisher_name' => $norm->publisher_name,
                    'form_code' => $norm->form_code,
                    'edition' => $norm->edition,
                    'language' => $norm->language,
                    'applicable_population' => $norm->applicable_population,
                    'source_reference' => $norm->source_reference,
                    'permitted_use_reference' => $norm->permitted_use_reference,
                    'source_reviewed_by_user_id' => $norm->source_reviewed_by_user_id === null ? null : (int) $norm->source_reviewed_by_user_id,
                    'approved_by_user_id' => $norm->approved_by_user_id === null ? null : (int) $norm->approved_by_user_id,
                ],
                'source_reviewed_at' => $norm->source_reviewed_at?->toISOString(),
                'approved_at' => $norm->approved_at?->toISOString(),
                'tables' => $normalized,
            ];
        }

        return $context;
    }

    private function assertOwner(TestKit $kit, TestInstrument|AssessmentNormPackage|OmrQuestionBooklet|OmrAnswerSheetTemplate $owner, string $field): void
    {
        if ((int) $owner->getAttribute('institution_id') !== (int) $kit->institution_id
            || $owner->getAttribute('is_synthetic') === null
            || (bool) $owner->getAttribute('is_synthetic') !== (bool) $kit->is_synthetic) {
            throw ValidationException::withMessages([$field => 'The selected resource belongs to a different institution or provenance class.']);
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortAssociativeKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortAssociativeKeys($item);
            }
        }

        return $value;
    }
}
