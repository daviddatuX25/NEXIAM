<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitCommandReceipt;
use App\Assessment\Support\TestKitManifestBuilder;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitPreparationCompositionRevision;
use App\Models\Assessment\TestKitPreparationCompositionRow;
use App\Models\Assessment\TestKitVersion;
use App\Models\Assessment\TestKitVersionCompositionRow;
use App\Models\Foundation\Operation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AssembleTestKitVersion
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCommandReceipt $receipts,
        private readonly TestKitManifestBuilder $manifestBuilder,
        private readonly KitCompositionContext $compositionContext,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{template_version_id:int|null,booklet_version_id:int|null,booklet_set_id:int|null,test_instrument_version_id:int,expected_revision:int,idempotency_key:string,source_preparation_id:int,source_preparation_revision:int,composition_revision:int,composition_rows:list<array<string,mixed>>|null,component_vector:array<string,mixed>,component_vector_sha256:string,composition_sha256:string|null}  $input
     */
    public function handle(User $actor, int $testKitId, array $input, bool $deferAudit = false, ?Operation $claimedReceipt = null): TestKitVersion
    {
        return DB::transaction(function () use ($actor, $testKitId, $input, $deferAudit, $claimedReceipt): TestKitVersion {
            $kit = TestKit::query()->lockForUpdate()->findOrFail($testKitId);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }
            $latestVersion = TestKitVersion::query()->where('test_kit_id', $kit->id)
                ->orderByDesc('version_number')->lockForUpdate()->first();
            $compositionFields = [
                'source_preparation_id', 'source_preparation_revision', 'composition_revision',
                'composition_rows', 'component_vector', 'component_vector_sha256', 'composition_sha256',
            ];
            foreach ($compositionFields as $field) {
                if (! array_key_exists($field, $input)) {
                    throw ValidationException::withMessages([$field => 'Exact saved preparation composition is required.']);
                }
            }
            $payload = [
                'test_kit_id' => (int) $kit->id,
                'template_version_id' => $input['template_version_id'] === null ? null : (int) $input['template_version_id'],
                'booklet_version_id' => $input['booklet_version_id'] === null ? null : (int) $input['booklet_version_id'],
                'booklet_set_id' => $input['booklet_set_id'] === null ? null : (int) $input['booklet_set_id'],
                'test_instrument_version_id' => (int) $input['test_instrument_version_id'],
                'expected_revision' => (int) $input['expected_revision'],
                'source_preparation_id' => (int) $input['source_preparation_id'],
                'source_preparation_revision' => (int) $input['source_preparation_revision'],
                'composition_revision' => (int) $input['composition_revision'],
                'component_vector_sha256' => (string) $input['component_vector_sha256'],
                'composition_sha256' => $input['composition_sha256'],
            ];
            $receipt = $claimedReceipt ?? $this->receipts->claim(
                $principal, 'test_kit.assemble', (string) $kit->id, $payload, $input['idempotency_key'],
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                return TestKitVersion::query()->where('test_kit_id', $kit->id)->findOrFail($result['test_kit_version_id']);
            }
            if ((int) $input['expected_revision'] !== (int) ($latestVersion->version_number ?? 0)) {
                throw ValidationException::withMessages(['expected_revision' => 'The Test Kit changed since it was opened. Refresh before assembling another version.']);
            }
            $preparation = $kit->preparations()->whereKey((int) $input['source_preparation_id'])
                ->lockForUpdate()->firstOrFail();
            if ($preparation->state !== TestKitPreparation::STATE_PREPARING
                || (int) $preparation->revision !== (int) $input['source_preparation_revision']
                || (int) $preparation->composition_revision !== (int) $input['composition_revision']) {
                throw ValidationException::withMessages(['source_preparation_id' => 'The exact saved preparation changed or is no longer available.']);
            }
            foreach ([
                'template_version_id' => $input['template_version_id'],
                'booklet_version_id' => $input['booklet_version_id'],
                'booklet_set_id' => $input['booklet_set_id'],
                'test_instrument_version_id' => $input['test_instrument_version_id'],
            ] as $field => $value) {
                $selected = $preparation->{$field};
                if (($selected === null) !== ($value === null)
                    || ($selected !== null && (int) $selected !== (int) $value)) {
                    throw ValidationException::withMessages(['composition' => 'Saved preparation components do not match this assembly.']);
                }
            }
            $sourceRevision = TestKitPreparationCompositionRevision::query()
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', (int) $input['composition_revision'])
                ->lockForUpdate()->first();
            $sourceRows = TestKitPreparationCompositionRow::query()
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', (int) $input['composition_revision'])
                ->orderBy('id')->lockForUpdate()->get()
                ->map(fn (TestKitPreparationCompositionRow $row): array => $row->only([
                    'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id',
                    'disposition', 'physical_item_uid', 'sheet_item_id', 'booklet_set_item_id',
                    'booklet_item_id', 'booklet_item_uid', 'booklet_section_uid',
                    'test_instrument_subtest_id', 'test_instrument_item_id',
                ]))->all();
            $normalizedSourceRows = array_map(fn (array $row): array => $this->normalizeCompositionRow($row), $sourceRows);
            $resolvedComposition = $this->compositionContext->resolvePreparation($preparation);
            $resolvedRows = array_map(
                fn (array $row): array => $this->normalizeCompositionRow($row),
                $resolvedComposition['rows'],
            );
            $vector = $sourceRevision?->component_vector_json;
            $commonSnapshotMatches = $sourceRevision !== null
                && is_array($vector)
                && $resolvedComposition['requirements'] !== null
                && $resolvedComposition['state'] === 'ready'
                && is_array($input['component_vector'])
                && hash_equals((string) $sourceRevision->component_vector_sha256, $this->compositionContext->componentVectorSha256($vector))
                && hash_equals((string) $sourceRevision->component_vector_sha256, $this->compositionContext->componentVectorSha256($input['component_vector']))
                && hash_equals((string) $sourceRevision->component_vector_sha256, (string) $resolvedComposition['component_vector_sha256'])
                && hash_equals((string) $sourceRevision->component_vector_sha256, (string) $input['component_vector_sha256']);
            if (! $commonSnapshotMatches) {
                throw ValidationException::withMessages(['composition' => 'The exact saved preparation and component vector are missing, stale, or unresolved.']);
            }

            $requiresKey = in_array('key', $resolvedComposition['requirements']['required_resources'], true);
            if ($resolvedComposition['map_applicable'] === true) {
                $submittedRows = is_array($input['composition_rows']) && array_is_list($input['composition_rows'])
                    ? array_map(fn (mixed $row): array => $this->normalizeCompositionRow($row), $input['composition_rows'])
                    : [];
                if ($resolvedComposition['map_complete'] !== true
                    || $normalizedSourceRows === []
                    || $normalizedSourceRows !== $submittedRows
                    || $normalizedSourceRows !== $resolvedRows
                    || ! is_string($input['composition_sha256'])
                    || ! hash_equals((string) $input['composition_sha256'], (string) $resolvedComposition['composition_sha256'])) {
                    throw ValidationException::withMessages(['composition' => 'Exact saved OMR composition rows and digest are required.']);
                }
                $compositionRows = $normalizedSourceRows;
                $compositionSha256 = (string) $input['composition_sha256'];
                $compositionRevision = (int) $preparation->composition_revision;
            } else {
                if ($input['composition_rows'] !== null
                    || $input['composition_sha256'] !== null
                    || $resolvedComposition['map_complete'] !== false
                    || $resolvedComposition['item_map'] !== null
                    || $resolvedComposition['composition_sha256'] !== null
                    || $normalizedSourceRows !== []
                    || $resolvedRows !== []) {
                    throw ValidationException::withMessages(['composition' => 'No OMR map applies to this Test Setup; mapped rows or digests are not accepted.']);
                }
                $compositionRows = [];
                $compositionSha256 = null;
                $compositionRevision = null;
            }
            $componentVector = $vector;

            $profile = TestInstrumentVersion::query()->lockForUpdate()->findOrFail((int) $input['test_instrument_version_id']);
            $booklet = $input['booklet_version_id'] === null
                ? null
                : OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail((int) $input['booklet_version_id']);
            $set = $booklet === null
                ? null
                : OmrBookletSet::query()->where('booklet_version_id', $booklet->id)
                    ->whereKey((int) $input['booklet_set_id'])->lockForUpdate()->firstOrFail();
            $template = $input['template_version_id'] === null
                ? null
                : OmrAnswerSheetTemplateVersion::query()->whereKey((int) $input['template_version_id'])->lockForUpdate()->firstOrFail();
            $this->assertComponentOwnership($kit, $template, $booklet, $set, $profile);
            $preserveSelectedSetKey = $requiresKey || (($resolvedComposition['map_applicable'] ?? false) === true && $set !== null);
            try {
                $versionNumber = $latestVersion === null ? 1 : ((int) $latestVersion->version_number + 1);
                $manifest = $this->manifestBuilder->build(
                    $template, $booklet, $set, $profile, (int) $kit->institution_id,
                    [
                        'test_kit_id' => (int) $kit->id,
                        'public_id' => (string) $kit->public_id,
                        'version_number' => $versionNumber,
                        'provenance_class' => $kit->is_synthetic ? 'synthetic' : 'institutional',
                    ],
                    $compositionRevision === null ? null : $compositionRows,
                    $componentVector, (int) ($input['composition_revision'] ?? 0),
                );
                if ($compositionRevision === null
                    ? $manifest['composition_sha256'] !== null
                    : ! is_string($manifest['composition_sha256']) || ! hash_equals($compositionSha256, $manifest['composition_sha256'])) {
                    throw new \RuntimeException('The saved composition digest does not match the applicable mapping state.');
                }
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['materials' => 'The selected components and exact saved map are not coherent: '.$exception->getMessage()]);
            }

            $version = TestKitVersion::query()->create([
                'test_kit_id' => (int) $kit->id,
                'institution_id' => (int) $kit->institution_id,
                'is_synthetic' => (bool) $kit->is_synthetic,
                'version_number' => $versionNumber,
                'predecessor_version_id' => $latestVersion?->id,
                'template_id' => $template?->template_id,
                'template_version_id' => $template?->id,
                'booklet_id' => $booklet?->booklet_id,
                'booklet_version_id' => $booklet?->id,
                'booklet_set_id' => $set?->id,
                'test_instrument_id' => (int) $profile->test_instrument_id,
                'test_instrument_version_id' => (int) $profile->id,
                'booklet_set_code' => $set?->set_code,
                'booklet_set_order_hash' => $set?->order_hash,
                'booklet_set_answer_key_hash' => $preserveSelectedSetKey ? $set?->answer_key_hash : null,
                'manifest_schema_version' => TestKitManifestBuilder::MANIFEST_SCHEMA_VERSION,
                'canonicalizer_version' => TestKitManifestBuilder::CANONICALIZER_VERSION,
                'manifest_json' => $manifest['manifest'],
                'manifest_sha256' => $manifest['sha256'],
                'source_preparation_id' => (int) $preparation->id,
                'source_preparation_revision' => (int) $preparation->revision,
                'composition_revision' => $compositionRevision,
                'composition_sha256' => $compositionSha256,
                'composition_rows_sealed_at' => null,
                'status' => TestKitVersion::STATUS_CANDIDATE,
                'revision' => 1,
                'created_by_user_id' => (int) $principal->id,
            ]);
            $this->receipts->remember($receipt, $version);
            foreach ($compositionRows as $row) {
                TestKitVersionCompositionRow::query()->create([
                    'test_kit_version_id' => (int) $version->id,
                    'template_version_id' => (int) $template->id,
                    'booklet_version_id' => (int) $booklet->id,
                    'booklet_set_id' => (int) $set->id,
                    'test_instrument_version_id' => (int) $profile->id,
                    'composition_revision' => $compositionRevision,
                    'composition_sha256' => $compositionSha256,
                    ...$row,
                ]);
            }
            if ($compositionRevision !== null) {
                $version->update(['composition_rows_sealed_at' => now()]);
            }
            if (! $deferAudit) {
                $this->audit->handle('assessment.test_kit.version_assembled', [
                    'test_kit_id' => (int) $kit->id,
                    'test_kit_version_id' => (int) $version->id,
                    'version_number' => $versionNumber,
                    'component_ids' => [
                        'template_version_id' => $template?->id,
                        'booklet_version_id' => $booklet?->id,
                        'booklet_set_id' => $set?->id,
                        'test_instrument_version_id' => (int) $profile->id,
                    ],
                ], (string) $principal->id);
            }

            return $version;
        });
    }

    private function assertComponentOwnership(
        TestKit $kit,
        ?OmrAnswerSheetTemplateVersion $template,
        ?OmrQuestionBookletVersion $booklet,
        ?OmrBookletSet $set,
        TestInstrumentVersion $profile,
    ): void {
        $profile->loadMissing('instrument');
        if ($profile->status !== TestInstrumentVersion::STATUS_APPROVED) {
            throw ValidationException::withMessages(['test_instrument_version_id' => 'The selected Test Instrument profile must be approved.']);
        }
        $profileOwner = $profile->instrument;
        if ($profileOwner === null) {
            throw ValidationException::withMessages(['test_instrument_version_id' => 'The selected Test Setup has no owning profile.']);
        }

        $sheetOwner = null;
        if ($template !== null) {
            $template->loadMissing('template');
            $sheetOwner = $template->template;
            if (! in_array($template->status, [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE], true)
                || ! is_array($template->native_bundle_json) || $template->native_bundle_json === []
                || $template->native_bundle_hash === null || $template->template_json_hash === null
                || $template->review_manifest_hash === null || $sheetOwner === null
                || (int) $sheetOwner->id !== (int) $template->template_id) {
                throw ValidationException::withMessages(['template_version_id' => 'The selected Answer Sheet version has not been compiled or has no exact owner.']);
            }
        }

        $bookletOwner = null;
        if ($booklet !== null) {
            $booklet->loadMissing('booklet');
            $bookletOwner = $booklet->booklet;
            if ($booklet->status !== OmrQuestionBookletVersion::STATUS_FINALIZED
                || ! $booklet->isFinalized() || $bookletOwner === null
                || (int) $bookletOwner->id !== (int) $booklet->booklet_id
                || $set === null || $set->status !== OmrBookletSet::STATUS_FINALIZED
                || $set->finalized_at === null || (int) $set->booklet_version_id !== (int) $booklet->id
                || $set->set_code !== 'A') {
                throw ValidationException::withMessages(['booklet_version_id' => 'The selected finalized Booklet and exact Set A are required together.']);
            }
        } elseif ($set !== null) {
            throw ValidationException::withMessages(['booklet_set_id' => 'A Set cannot be selected without its exact Booklet.']);
        }

        foreach ([$sheetOwner, $bookletOwner, $profileOwner] as $owner) {
            if ($owner !== null
                && ($owner->getAttribute('institution_id') === null
                    || $owner->getAttribute('is_synthetic') === null
                    || (int) $owner->getAttribute('institution_id') !== (int) $kit->institution_id
                    || (bool) $owner->getAttribute('is_synthetic') !== (bool) $kit->is_synthetic)) {
                throw ValidationException::withMessages(['materials' => 'Selected Kit components must share institution and provenance.']);
            }
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function normalizeCompositionRow(mixed $row): array
    {
        if (! is_array($row)) {
            return [];
        }
        $fields = [
            'template_version_id', 'booklet_version_id', 'booklet_set_id', 'test_instrument_version_id',
            'disposition', 'physical_item_uid', 'sheet_item_id', 'booklet_set_item_id',
            'booklet_item_id', 'booklet_item_uid', 'booklet_section_uid',
            'test_instrument_subtest_id', 'test_instrument_item_id',
        ];
        $normalized = [];
        foreach ($fields as $field) {
            $value = $row[$field] ?? null;
            $normalized[$field] = str_ends_with($field, '_id') && $value !== null ? (int) $value : $value;
        }

        return $normalized;
    }
}
