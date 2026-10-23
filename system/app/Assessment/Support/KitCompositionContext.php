<?php

namespace App\Assessment\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
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
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;
use Throwable;

final class KitCompositionContext
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly PhysicalSheetInventory $physicalInventory = new PhysicalSheetInventory,
        private readonly ExactTestKitItemMap $itemMap = new ExactTestKitItemMap,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function resolvePreparation(TestKitPreparation $preparation): array
    {
        $preparation->loadMissing('testKit');
        if ($preparation->state !== TestKitPreparation::STATE_PREPARING) {
            throw new RuntimeException('Only the current open preparation has mutable Kit composition.');
        }

        $kit = $preparation->testKit;
        if (! $kit instanceof TestKit) {
            throw new RuntimeException('The Test Kit preparation has no owning Kit.');
        }

        $template = $preparation->template_version_id === null
            ? null
            : OmrAnswerSheetTemplateVersion::query()->with('template')->find((int) $preparation->template_version_id);
        $booklet = $preparation->booklet_version_id === null
            ? null
            : OmrQuestionBookletVersion::query()->with(['booklet', 'items.section', 'sets'])->find((int) $preparation->booklet_version_id);
        $set = $preparation->booklet_set_id === null
            ? null
            : OmrBookletSet::query()->with('items.bookletItem')->find((int) $preparation->booklet_set_id);
        $profile = $preparation->test_instrument_version_id === null
            ? null
            : TestInstrumentVersion::query()->with(['instrument', 'subtests.items', 'metrics.inputs'])->find((int) $preparation->test_instrument_version_id);

        $inventory = ['template_version_id' => $template?->id, 'inventory_digest' => null, 'groups' => [], 'items' => []];
        $issues = [];
        $requirements = $profile === null ? null : $this->requirementsForProfile($profile);
        $omrApplicable = is_array($requirements) ? $requirements['omr_applicable'] : null;
        $applicableResources = is_array($requirements) ? array_fill_keys(
            ['answer_sheet', 'booklet_form', 'key', 'physical_procedure'],
            false,
        ) : null;
        if (is_array($requirements)) {
            foreach ($requirements['required_resources'] as $resource) {
                $applicableResources[$resource] = true;
            }
        } else {
            $issues[] = [
                'code' => 'input_applicability_unresolved',
                'message' => 'Every selected Test Setup metric must have a valid approved input declaration before sealing.',
            ];
        }
        $hasOptionalAttachment = is_array($applicableResources)
            && ((! $applicableResources['answer_sheet'] && $template !== null)
                || (! $applicableResources['booklet_form'] && ($booklet !== null || $set !== null)));
        $mapApplicable = $omrApplicable === true || $hasOptionalAttachment;
        if ($template !== null && $mapApplicable) {
            try {
                $inventory = $this->physicalInventory->forVersion($template);
            } catch (Throwable $exception) {
                $issues[] = [
                    'code' => 'physical_inventory_unavailable',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $vector = $this->componentVector(
            $kit,
            $template,
            $booklet,
            $set,
            $profile,
            $inventory,
        );
        $vector = $this->conditionalizeComponentVector($vector, $requirements, $mapApplicable);
        $vectorSha256 = $this->componentVectorSha256($vector);
        $componentRevision = (int) $preparation->composition_revision;
        $revision = TestKitPreparationCompositionRevision::query()
            ->where('test_kit_preparation_id', $preparation->id)
            ->where('composition_revision', $componentRevision)
            ->first();
        $savedRows = $revision === null
            ? collect()
            : TestKitPreparationCompositionRow::query()
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', $componentRevision)
                ->orderBy('id')
                ->get();

        if ($profile === null) {
            $issues[] = [
                'code' => 'profile_selection_incomplete',
                'message' => 'Select the exact approved Test Setup before saving Kit composition.',
            ];
        }
        if (is_array($applicableResources)) {
            foreach ([
                'answer_sheet' => $template,
                'booklet_form' => $booklet,
            ] as $resource => $component) {
                if ($applicableResources[$resource] && $component === null) {
                    $issues[] = [
                        'code' => 'required_component_selection_mismatch',
                        'message' => 'The selected exact components do not match the approved Test Setup resource requirements.',
                    ];
                    break;
                }
            }
            if (($booklet !== null) !== ($set !== null)
                || ($applicableResources['key'] && ($booklet === null || $set === null))
                || ($set !== null && (
                    $booklet === null
                    || (int) $set->booklet_version_id !== (int) $booklet->id
                    || $set->set_code !== 'A'
                    || $set->status !== OmrBookletSet::STATUS_FINALIZED
                    || $set->finalized_at === null
                ))) {
                $issues[] = [
                    'code' => 'booklet_set_invalid',
                    'message' => 'The selected Booklet and Set must be finalized Set A belonging to the exact Booklet version.',
                ];
            }
            if ($hasOptionalAttachment && ($template === null || $booklet === null || $set === null)) {
                $issues[] = [
                    'code' => 'optional_attachment_incomplete',
                    'message' => 'An optional attachment requires a ready Answer Sheet, finalized Booklet, and finalized Set A.',
                ];
            }
            if ($omrApplicable === true
                && (! $applicableResources['answer_sheet']
                    || ! $applicableResources['booklet_form']
                    || ! $applicableResources['key'])) {
                $issues[] = [
                    'code' => 'omr_requirements_incomplete',
                    'message' => 'OMR requires an exact Answer Sheet, Booklet form, and key declaration.',
                ];
            }
        }
        if ($template !== null && ! in_array(
            $template->status,
            [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE],
            true,
        )) {
            $issues[] = [
                'code' => 'answer_sheet_not_compiled',
                'message' => 'The selected Answer Sheet version is not compiled for Kit composition.',
            ];
        }
        if ($booklet !== null
            && ($booklet->status !== OmrQuestionBookletVersion::STATUS_FINALIZED || ! $booklet->isFinalized())) {
            $issues[] = [
                'code' => 'booklet_not_finalized',
                'message' => 'The selected Booklet version is not finalized for Kit composition.',
            ];
        }
        if ($revision === null) {
            $issues[] = [
                'code' => 'composition_not_saved',
                'message' => 'Save the exact selected components and any applicable item mapping before sealing.',
            ];
        } elseif ($this->revisionVectorDiffersFromSelection($revision, $preparation, $vector)) {
            $issues[] = [
                'code' => 'component_vector_stale',
                'message' => 'The selected component versions changed after this composition revision was saved.',
            ];
        }

        $itemMap = null;
        $compositionSha256 = null;
        if ($mapApplicable && $issues === [] && $template !== null && $booklet !== null && $set !== null && $profile !== null) {
            try {
                $itemMap = $this->itemMap->build($template, $booklet, $set, $profile, $savedRows->all(), $vector);
                $compositionSha256 = $itemMap['composition_sha256'];
            } catch (Throwable $exception) {
                $issues[] = [
                    'code' => 'composition_incomplete_or_incompatible',
                    'message' => $exception->getMessage(),
                ];
            }
        } elseif (! $mapApplicable && $savedRows->isNotEmpty()) {
            $issues[] = [
                'code' => 'inapplicable_mapping_rows',
                'message' => 'A Test Setup without OMR or optional mapped materials cannot retain item-mapping rows.',
            ];
        }
        if (! $this->componentOwnersMatch($kit, $template, $booklet, $profile)) {
            $issues[] = [
                'code' => 'component_owner_mismatch',
                'message' => 'Every selected component must belong to this exact Kit institution and provenance.',
            ];
        }
        $ready = $issues === [];

        $physicalMappedCount = $savedRows->where('disposition', 'physical')->count();

        return [
            'state' => $ready ? 'ready' : 'incomplete',
            'test_kit_id' => (int) $kit->id,
            'institution_id' => (int) $kit->institution_id,
            'is_synthetic' => (bool) $kit->is_synthetic,
            'preparation_id' => (int) $preparation->id,
            'preparation_revision' => (int) $preparation->revision,
            'composition_revision' => $componentRevision,
            'omr_enabled' => (bool) $preparation->omr_enabled,
            'template_version_id' => $template === null ? null : (int) $template->id,
            'booklet_version_id' => $booklet === null ? null : (int) $booklet->id,
            'booklet_set_id' => $set === null ? null : (int) $set->id,
            'test_instrument_version_id' => $profile === null ? null : (int) $profile->id,
            'component_vector' => $vector,
            'component_vector_sha256' => $vectorSha256,
            'physical_inventory' => $inventory,
            'rows' => $savedRows->map(fn (TestKitPreparationCompositionRow $row): array => $row->toArray())->values()->all(),
            'item_map' => $itemMap,
            'composition_sha256' => $compositionSha256,
            'requirements' => $requirements,
            'applicable_resources' => $applicableResources,
            'omr_applicable' => $omrApplicable,
            'map_applicable' => $mapApplicable,
            'attachment_state' => ! $hasOptionalAttachment
                ? 'absent'
                : ($itemMap !== null ? 'complete' : 'incomplete'),
            'map_complete' => $itemMap !== null,
            'matched' => $physicalMappedCount,
            'expected' => count($inventory['items']),
            'issues' => $issues,
            'template' => $template,
            'booklet' => $booklet,
            'set' => $set,
            'profile' => $profile,
            'preparation' => $preparation,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveVersion(TestKitVersion $version): array
    {
        $version->loadMissing([
            'testKit', 'sourcePreparation', 'templateVersion.template', 'templateVersion.pages', 'templateVersion.canonicalAssets',
            'bookletVersion.booklet', 'bookletSet.items.bookletItem',
            'testInstrumentVersion.instrument', 'testInstrumentVersion.subtests.items', 'testInstrumentVersion.metrics.inputs',
            'compositionRows',
        ]);
        if ((int) $version->manifest_schema_version === TestKitManifestBuilder::MANIFEST_SCHEMA_VERSION) {
            return $this->resolveConditionalVersion($version);
        }

        if ($version->composition_revision === null
            || $version->composition_sha256 === null
            || $version->composition_rows_sealed_at === null) {
            throw new RuntimeException('This historical Test Kit version has no newly sealed Kit-owned composition map.');
        }

        $kit = $version->testKit;
        $template = $version->templateVersion;
        $booklet = $version->bookletVersion;
        $set = $version->bookletSet;
        $profile = $version->testInstrumentVersion;
        $preparation = $version->sourcePreparation;
        if (! $kit instanceof TestKit
            || ! $template instanceof OmrAnswerSheetTemplateVersion
            || ! $booklet instanceof OmrQuestionBookletVersion
            || ! $set instanceof OmrBookletSet
            || ! $profile instanceof TestInstrumentVersion
            || ! $preparation instanceof TestKitPreparation
            || $preparation->state !== TestKitPreparation::STATE_ASSEMBLED
            || (int) $preparation->id !== (int) $version->source_preparation_id
            || (int) $preparation->revision !== (int) $version->source_preparation_revision
            || (int) $preparation->composition_revision !== (int) $version->composition_revision
            || (int) $preparation->test_kit_id !== (int) $version->test_kit_id) {
            throw new RuntimeException('The sealed Kit composition source preparation does not match its immutable version pins.');
        }

        $inventory = $this->physicalInventory->forVersion($template);
        $vector = $this->componentVector($kit, $template, $booklet, $set, $profile, $inventory);
        if ((int) $template->id !== (int) $preparation->template_version_id
            || (int) $booklet->id !== (int) $preparation->booklet_version_id
            || (int) $set->id !== (int) $preparation->booklet_set_id
            || (int) $profile->id !== (int) $preparation->test_instrument_version_id) {
            throw new RuntimeException('The sealed Kit composition no longer matches its source preparation selections.');
        }

        $manifestRows = $version->manifest_json['composition']['rows'] ?? null;
        $rows = is_array($manifestRows)
            ? $manifestRows
            : $version->compositionRows->map(fn (TestKitVersionCompositionRow $row): array => $row->toArray())->values()->all();
        $historicalSchemaTwoRows = is_array($manifestRows)
            && $manifestRows !== []
            && ! array_key_exists('template_version_id', $manifestRows[0]);
        if ($historicalSchemaTwoRows) {
            foreach ($rows as &$row) {
                $row = [
                    'template_version_id' => (int) $vector['template_version_id'],
                    'booklet_version_id' => (int) $vector['booklet_version_id'],
                    'booklet_set_id' => (int) $vector['booklet_set_id'],
                    'test_instrument_version_id' => (int) $vector['test_instrument_version_id'],
                    ...$row,
                ];
            }
            unset($row);
        }
        $itemMap = $this->itemMap->build($template, $booklet, $set, $profile, $rows, $vector);
        if (! hash_equals((string) $version->composition_sha256, (string) $itemMap['composition_sha256'])) {
            throw new RuntimeException('The immutable Kit composition digest does not match its exact sealed rows.');
        }

        $manifestComposition = $version->manifest_json['composition'] ?? null;
        $manifestVector = is_array($manifestComposition) ? ($manifestComposition['component_vector'] ?? null) : null;
        if (! is_array($manifestComposition)
            || ! is_array($manifestVector)
            || ! hash_equals((string) ($manifestComposition['sha256'] ?? ''), (string) $version->composition_sha256)
            || ! hash_equals($this->componentVectorSha256($manifestVector), $this->componentVectorSha256($vector))) {
            throw new RuntimeException('The sealed Kit manifest does not identify the exact composition vector and digest.');
        }

        return [
            'state' => 'ready',
            'test_kit_id' => (int) $kit->id,
            'test_kit_version_id' => (int) $version->id,
            'institution_id' => (int) $kit->institution_id,
            'is_synthetic' => (bool) $kit->is_synthetic,
            'preparation_id' => (int) $preparation->id,
            'preparation_revision' => null,
            'composition_revision' => (int) $version->composition_revision,
            'template_version_id' => (int) $template->id,
            'booklet_version_id' => (int) $booklet->id,
            'booklet_set_id' => (int) $set->id,
            'test_instrument_version_id' => (int) $profile->id,
            'component_vector' => $vector,
            'component_vector_sha256' => $this->componentVectorSha256($vector),
            'physical_inventory' => $inventory,
            'rows' => $rows,
            'item_map' => $itemMap,
            'composition_sha256' => (string) $version->composition_sha256,
            'manifest_sha256' => (string) $version->manifest_sha256,
            'map_complete' => true,
            'matched' => count($inventory['items']),
            'expected' => count($inventory['items']),
            'issues' => [],
            'template' => $template,
            'booklet' => $booklet,
            'set' => $set,
            'profile' => $profile,
            'version' => $version,
            'preparation' => $preparation,
        ];
    }

    /**
     * Resolve a conditional schema-3 version without treating absent resources
     * as incomplete legacy paper pins.
     *
     * @return array<string, mixed>
     */
    private function resolveConditionalVersion(TestKitVersion $version): array
    {
        $kit = $version->testKit;
        $profile = $version->testInstrumentVersion;
        $preparation = $version->sourcePreparation;
        $template = $version->templateVersion;
        $booklet = $version->bookletVersion;
        $set = $version->bookletSet;
        if (! $kit instanceof TestKit
            || ! $profile instanceof TestInstrumentVersion
            || ! $preparation instanceof TestKitPreparation
            || $preparation->state !== TestKitPreparation::STATE_ASSEMBLED
            || (int) $preparation->id !== (int) $version->source_preparation_id
            || (int) $preparation->revision !== (int) $version->source_preparation_revision
            || (int) $preparation->test_kit_id !== (int) $version->test_kit_id) {
            throw new RuntimeException('The sealed Kit composition source preparation does not match its immutable version pins.');
        }
        $requirements = $this->requirementsForProfile($profile);
        $manifest = $version->manifest_json;
        if ($requirements === null
            || ! is_array($manifest)
            || $this->sortAssociativeKeys((array) ($manifest['requirements'] ?? [])) !== $this->sortAssociativeKeys($requirements)) {
            throw new RuntimeException('The sealed schema-3 requirements do not match the exact current approved profile declarations.');
        }
        $applicableResources = array_fill_keys(['answer_sheet', 'booklet_form', 'key', 'physical_procedure'], false);
        foreach ($requirements['required_resources'] as $resource) {
            $applicableResources[$resource] = true;
        }
        $omrApplicable = $requirements['omr_applicable'];
        $legacyV3 = ($manifest['canonicalizer_version'] ?? null) === TestKitManifestBuilder::LEGACY_V3_CANONICALIZER_VERSION;
        $hasOptionalAttachment = ! $legacyV3 && is_array($manifest['optional_attachment'] ?? null);
        $mapApplicable = $omrApplicable || $hasOptionalAttachment;
        if ($mapApplicable
            ? ($version->composition_revision === null
                || (int) $version->composition_revision !== (int) $preparation->composition_revision
                || $version->composition_rows_sealed_at === null)
            : ($version->composition_revision !== null
                || $version->composition_sha256 !== null
                || $version->composition_rows_sealed_at !== null)) {
            throw new RuntimeException('The schema-3 map pins must match declared OMR or a complete optional attachment.');
        }
        $selectedResourcesMatch = $legacyV3
            ? (($template !== null) === $applicableResources['answer_sheet']
                && ($booklet !== null) === $applicableResources['booklet_form']
                && ($set !== null) === $applicableResources['booklet_form'])
            : ((! $applicableResources['answer_sheet'] || $template !== null)
                && (! $applicableResources['booklet_form'] || ($booklet !== null && $set !== null))
                && (($booklet !== null) === ($set !== null))
                && (! $hasOptionalAttachment || ($template !== null && $booklet !== null && $set !== null)));
        if (! $selectedResourcesMatch
            || ($applicableResources['key'] && $set === null)
            || ($omrApplicable && ($template === null || $booklet === null || $set === null))
            || (int) $version->test_instrument_id !== (int) $profile->test_instrument_id
            || (int) $version->test_instrument_version_id !== (int) $profile->id
            || (int) $preparation->template_version_id !== (int) ($template?->id ?? 0)
            || (int) $preparation->booklet_version_id !== (int) ($booklet?->id ?? 0)
            || (int) $preparation->booklet_set_id !== (int) ($set?->id ?? 0)
            || (int) $preparation->test_instrument_version_id !== (int) $profile->id
            || (int) ($version->template_version_id ?? 0) !== (int) ($template?->id ?? 0)
            || (int) ($version->booklet_version_id ?? 0) !== (int) ($booklet?->id ?? 0)
            || (int) ($version->booklet_set_id ?? 0) !== (int) ($set?->id ?? 0)) {
            throw new RuntimeException('The schema-3 component pins do not match exact declared applicability and saved preparation selection.');
        }
        if (($template !== null && ! in_array(
            $template->status,
            [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE],
            true,
        ))
        || ($booklet !== null
            && ($booklet->status !== OmrQuestionBookletVersion::STATUS_FINALIZED || ! $booklet->isFinalized()))
        || ($set !== null
            && ((int) $set->booklet_version_id !== (int) ($booklet?->id ?? 0)
                || $set->set_code !== 'A'
                || $set->status !== OmrBookletSet::STATUS_FINALIZED
                || $set->finalized_at === null))) {
            throw new RuntimeException('The schema-3 selected physical/form components are not finalized exact material.');
        }
        if ((int) $version->institution_id !== (int) $kit->institution_id
            || $version->is_synthetic === null
            || (bool) $version->is_synthetic !== (bool) $kit->is_synthetic) {
            throw new RuntimeException('The schema-3 Test Kit owner and provenance do not match its family.');
        }
        if (! $this->componentOwnersMatch($kit, $template, $booklet, $profile)) {
            throw new RuntimeException('A sealed schema-3 component no longer matches exact Kit institution and provenance ownership.');
        }

        $inventory = ['template_version_id' => $template?->id, 'inventory_digest' => null, 'groups' => [], 'items' => []];
        if ($mapApplicable && $template !== null) {
            $inventory = $this->physicalInventory->forVersion($template);
        }
        $vector = $this->componentVector($kit, $template, $booklet, $set, $profile, $inventory);
        $vector = $this->conditionalizeComponentVector($vector, $requirements, $mapApplicable);
        if ($this->revisionVectorDiffersFromSelection(
            TestKitPreparationCompositionRevision::query()
                ->where('test_kit_preparation_id', $preparation->id)
                ->where('composition_revision', $preparation->composition_revision)
                ->firstOrFail(),
            $preparation,
            $vector,
        )) {
            throw new RuntimeException('The schema-3 saved component vector is stale or differs from its exact selected revisions.');
        }

        $composition = $manifest['composition'] ?? null;
        $storedVector = is_array($composition) ? ($composition['component_vector'] ?? null) : null;
        $manifestRows = is_array($composition) ? ($composition['rows'] ?? null) : null;
        if ($mapApplicable) {
            if (! is_array($composition)
                || ! is_array($storedVector)
                || ! is_array($manifestRows)
                || ! is_string($version->composition_sha256)) {
                throw new RuntimeException('The applicable schema-3 item map and its exact component digest are missing.');
            }
            $rows = $this->restoreComponentPins($manifestRows, $vector);
            $itemMap = $this->itemMap->build($template, $booklet, $set, $profile, $rows, $vector);
            if (! hash_equals((string) ($composition['sha256'] ?? ''), $version->composition_sha256)
                || ! hash_equals($version->composition_sha256, (string) $itemMap['composition_sha256'])
                || ! hash_equals($this->componentVectorSha256($storedVector), $this->componentVectorSha256($vector))) {
                throw new RuntimeException('The schema-3 map does not match its exact saved rows, component vector, and digest.');
            }
            if ($hasOptionalAttachment) {
                $attachment = $manifest['optional_attachment'];
                if ((int) ($attachment['version'] ?? 0) !== 1
                    || (int) ($attachment['answer_sheet_version_id'] ?? 0) !== (int) ($template?->id ?? 0)
                    || (int) ($attachment['booklet_version_id'] ?? 0) !== (int) ($booklet?->id ?? 0)
                    || (int) ($attachment['booklet_set_id'] ?? 0) !== (int) ($set?->id ?? 0)
                    || ! hash_equals((string) ($attachment['component_vector_sha256'] ?? ''), $this->componentVectorSha256($vector))
                    || ! hash_equals((string) ($attachment['composition_sha256'] ?? ''), (string) $version->composition_sha256)
                    || ! hash_equals((string) ($attachment['set_order_sha256'] ?? ''), (string) ($set?->order_hash ?? ''))
                    || ! hash_equals((string) ($attachment['set_key_sha256'] ?? ''), (string) ($set?->answer_key_hash ?? ''))) {
                    throw new RuntimeException('The optional attachment identity and hashes do not match the sealed exact materials.');
                }
            }
        } else {
            if ($composition !== null || ($manifest['item_map'] ?? null) !== null
                || $version->composition_sha256 !== null
                || $version->compositionRows->isNotEmpty()) {
                throw new RuntimeException('A no-map schema-3 version must not contain composition rows, map, or digest.');
            }
            $rows = [];
            $itemMap = null;
        }

        return [
            'state' => 'ready',
            'test_kit_id' => (int) $kit->id,
            'test_kit_version_id' => (int) $version->id,
            'institution_id' => (int) $kit->institution_id,
            'is_synthetic' => (bool) $kit->is_synthetic,
            'preparation_id' => (int) $preparation->id,
            'preparation_revision' => null,
            'composition_revision' => (int) $version->composition_revision,
            'template_version_id' => $template === null ? null : (int) $template->id,
            'booklet_version_id' => $booklet === null ? null : (int) $booklet->id,
            'booklet_set_id' => $set === null ? null : (int) $set->id,
            'test_instrument_version_id' => (int) $profile->id,
            'component_vector' => $vector,
            'component_vector_sha256' => $this->componentVectorSha256($vector),
            'physical_inventory' => $inventory,
            'rows' => $rows,
            'item_map' => $itemMap,
            'composition_sha256' => $version->composition_sha256,
            'manifest_sha256' => (string) $version->manifest_sha256,
            'requirements' => $requirements,
            'applicable_resources' => $applicableResources,
            'omr_applicable' => $omrApplicable,
            'map_applicable' => $mapApplicable,
            'attachment_state' => $hasOptionalAttachment ? 'complete' : 'absent',
            'map_complete' => $mapApplicable && $itemMap !== null,
            'matched' => $mapApplicable ? count($inventory['items']) : 0,
            'expected' => $mapApplicable ? count($inventory['items']) : 0,
            'issues' => [],
            'template' => $template,
            'booklet' => $booklet,
            'set' => $set,
            'profile' => $profile,
            'version' => $version,
            'preparation' => $preparation,
        ];
    }

    /**
     * Return validated answer-key tokens only after a fresh independent Kit, Sheet, and Booklet decision.
     *
     * @param  array<string, mixed>  $resolved
     * @return list<array{physical_item_uid:string,booklet_item_id:int,formal_item_id:int,correct_option_code:?string}>
     */
    public function readValidatedKeys(User $actor, array $resolved): array
    {
        $kitId = (int) ($resolved['test_kit_id'] ?? 0);
        $kit = TestKit::query()->find($kitId);
        $principal = User::query()->find($actor->getKey());
        if ($kit === null || $principal === null) {
            throw new AuthorizationException('A current Kit and authenticated staff authority are required to read composition keys.');
        }
        $scope = ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id];
        foreach ([
            CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
            CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        ] as $capability) {
            if (! $this->authorizer->decideFresh($principal, $capability, $scope)->allowed) {
                throw new AuthorizationException('Fresh Kit, Sheet, and Booklet key authority is required.');
            }
        }

        if (isset($resolved['test_kit_version_id'])) {
            $version = TestKitVersion::query()->where('test_kit_id', $kitId)->findOrFail((int) $resolved['test_kit_version_id']);
            $current = $this->resolveVersion($version);
            $this->assertUnchangedResolvedContext($resolved, $current, [
                'test_kit_version_id', 'composition_revision', 'composition_sha256', 'manifest_sha256',
            ]);
        } elseif (isset($resolved['preparation_id'])) {
            $preparation = TestKitPreparation::query()
                ->where('test_kit_id', $kitId)
                ->findOrFail((int) $resolved['preparation_id']);
            $current = $this->resolvePreparation($preparation);
            $this->assertUnchangedResolvedContext($resolved, $current, [
                'preparation_id', 'preparation_revision', 'composition_revision', 'component_vector_sha256',
            ]);
        } else {
            throw new RuntimeException('The protected composition context must identify an exact preparation or sealed version.');
        }
        if (($current['map_complete'] ?? false) !== true || ! is_array($current['item_map'] ?? null)) {
            return [];
        }

        $set = $current['set'];
        $set->loadMissing('items.bookletItem');
        $setRows = $set->items->keyBy('id');
        $keys = [];
        foreach ($current['item_map']['composition_rows'] as $row) {
            if (($row['disposition'] ?? null) !== 'physical') {
                continue;
            }
            $setItemId = (int) ($row['booklet_set_item_id'] ?? 0);
            $setItem = $setRows->get($setItemId);
            if ($setItem === null
                || (int) $setItem->booklet_item_id !== (int) $row['booklet_item_id']
                || (string) $setItem->booklet_item_uid !== (string) $row['booklet_item_uid']
                || (string) $setItem->booklet_section_uid !== (string) $row['booklet_section_uid']) {
                throw new RuntimeException('The protected Set A key row does not match its exact intrinsic composition identity.');
            }
            $token = $setItem->correct_option_code;
            if (($row['key_required'] ?? false) !== true
                || ! is_string($token)
                || ! in_array($token, $row['physical_option_tokens'], true)) {
                $token = null;
            }
            $keys[] = [
                'physical_item_uid' => (string) $row['physical_item_uid'],
                'booklet_item_id' => (int) $row['booklet_item_id'],
                'formal_item_id' => (int) $row['formal_item_id'],
                'correct_option_code' => $token,
            ];
        }

        return $keys;
    }

    /** @param list<string> $fields */
    private function assertUnchangedResolvedContext(array $provided, array $current, array $fields): void
    {
        foreach ($fields as $field) {
            if (($provided[$field] ?? null) !== ($current[$field] ?? null)) {
                throw new RuntimeException('The paired preview or key context is stale; refresh the exact current Kit composition.');
            }
        }
    }

    private function componentOwnersMatch(
        TestKit $kit,
        ?OmrAnswerSheetTemplateVersion $template,
        ?OmrQuestionBookletVersion $booklet,
        ?TestInstrumentVersion $profile,
    ): bool {
        if ($profile === null) {
            return false;
        }
        $expectedSynthetic = (bool) $kit->is_synthetic;
        $owners = [[$profile->instrument, $profile->test_instrument_id]];
        if ($template !== null) {
            $owners[] = [$template->template, $template->template_id];
        }
        if ($booklet !== null) {
            $owners[] = [$booklet->booklet, $booklet->booklet_id];
        }
        foreach ($owners as [$owner, $expectedId]) {
            if ($owner === null
                || $expectedId === null
                || (int) $owner->getKey() !== (int) $expectedId
                || $owner->getAttribute('institution_id') === null
                || (int) $owner->getAttribute('institution_id') !== (int) $kit->institution_id
                || $owner->getAttribute('is_synthetic') === null
                || (bool) $owner->getAttribute('is_synthetic') !== $expectedSynthetic) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $vector
     * @return list<array<string, mixed>>
     */
    private function restoreComponentPins(array $rows, array $vector): array
    {
        return array_map(static fn (array $row): array => [
            'template_version_id' => $vector['template_version_id'] ?? null,
            'booklet_version_id' => $vector['booklet_version_id'] ?? null,
            'booklet_set_id' => $vector['booklet_set_id'] ?? null,
            'test_instrument_version_id' => $vector['test_instrument_version_id'] ?? null,
            ...$row,
        ], $rows);
    }

    /** @param array<string, mixed>|null $requirements */
    private function conditionalizeComponentVector(array $vector, ?array $requirements, bool $mapApplicable): array
    {
        if ($requirements === null
            || $mapApplicable
            || in_array('key', $requirements['required_resources'] ?? [], true)
            || ! is_array($vector['booklet'] ?? null)) {
            return $vector;
        }

        $vector['booklet']['answer_key_hash'] = null;
        $vector['booklet']['set_answer_key_hash'] = null;

        return $vector;
    }

    /**
     * @return array{
     *     schema_version:int,
     *     metrics:list<array{metric_id:int,code:string,input_contract_json:array<string,mixed>}>,
     *     required_resources:list<string>,
     *     omr_applicable:bool
     * }|null
     */
    private function requirementsForProfile(TestInstrumentVersion $profile): ?array
    {
        if ($profile->status !== TestInstrumentVersion::STATUS_APPROVED) {
            return null;
        }
        try {
            $validator = new TestInstrumentProfileValidator;
            $profile->loadMissing(['metrics.inputs']);
            $normalized = $validator->normalizeVersion($profile);
            if (! is_string($profile->content_sha256)
                || ! hash_equals($validator->digest($normalized), strtolower($profile->content_sha256))) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }
        $contractsByCode = [];
        foreach ($normalized['metrics'] as $metric) {
            if (! is_string($metric['code'] ?? null)
                || ! is_array($metric['input_contract_json'] ?? null)) {
                return null;
            }
            $contractsByCode[$metric['code']] = $metric['input_contract_json'];
        }

        $metrics = [];
        $requiredResources = [];
        $omrApplicable = false;
        foreach ($profile->metrics->sortBy([['code', 'asc'], ['id', 'asc']])->values() as $metric) {
            $contract = $contractsByCode[(string) $metric->code] ?? null;
            if ((int) $metric->test_instrument_version_id !== (int) $profile->getKey()
                || ! is_array($contract)
                || $metric->input_contract_json === null) {
                return null;
            }
            $canonical = (new TestInstrumentProfileValidator)->normalizeInputContract(
                $metric->input_contract_json,
                (string) ($metric->unit ?? ''),
                (string) $metric->rule_kind,
                'metrics.'.$metric->code.'.input_contract_json',
            );
            if ($canonical !== $contract) {
                return null;
            }
            $metrics[] = [
                'metric_id' => (int) $metric->getKey(),
                'code' => (string) $metric->code,
                'input_contract_json' => $canonical,
            ];
            $requiredResources = [...$requiredResources, ...$canonical['required_resources']];
            $omrApplicable = $omrApplicable || in_array('omr', $canonical['allowed_sources'], true);
        }
        usort($metrics, static fn (array $left, array $right): int => $left['metric_id'] <=> $right['metric_id']);
        $requiredResources = array_values(array_unique($requiredResources));
        sort($requiredResources, SORT_STRING);

        return [
            'schema_version' => 1,
            'metrics' => $metrics,
            'required_resources' => $requiredResources,
            'omr_applicable' => $omrApplicable,
        ];
    }

    private function componentVector(
        TestKit $kit,
        ?OmrAnswerSheetTemplateVersion $template,
        ?OmrQuestionBookletVersion $booklet,
        ?OmrBookletSet $set,
        ?TestInstrumentVersion $profile,
        array $inventory,
    ): array {
        return [
            'test_kit_id' => (int) $kit->id,
            'institution_id' => (int) $kit->institution_id,
            'is_synthetic' => (bool) $kit->is_synthetic,
            'template_version_id' => $template === null ? null : (int) $template->id,
            'booklet_version_id' => $booklet === null ? null : (int) $booklet->id,
            'booklet_set_id' => $set === null ? null : (int) $set->id,
            'test_instrument_version_id' => $profile === null ? null : (int) $profile->id,
            'sheet' => $template === null ? null : [
                'template_id' => (int) $template->template_id,
                'version' => (int) $template->version,
                'native_bundle_hash' => $template->native_bundle_hash,
                'template_json_hash' => $template->template_json_hash,
                'config_hash' => $template->config_hash,
                'review_manifest_hash' => $template->review_manifest_hash,
                'layout_blueprint_hash' => $template->layout_blueprint_hash,
                'physical_inventory_sha256' => $inventory['inventory_digest'] ?? null,
            ],
            'booklet' => $booklet === null ? null : [
                'booklet_id' => (int) $booklet->booklet_id,
                'version' => (int) $booklet->version,
                'content_hash' => $booklet->content_hash,
                'answer_key_hash' => $booklet->answer_key_hash,
                'authoring_mode' => (string) $booklet->authoring_mode,
                'set_code' => $set?->set_code,
                'set_order_hash' => $set?->order_hash,
                'set_answer_key_hash' => $set?->answer_key_hash,
            ],
            'profile' => $profile === null ? null : [
                'instrument_id' => (int) $profile->test_instrument_id,
                'version_number' => (int) $profile->version_number,
                'content_sha256' => $profile->content_sha256,
            ],
        ];
    }

    public function componentVectorSha256(array $componentVector): string
    {
        $canonical = $this->sortAssociativeKeys($componentVector);

        return hash('sha256', json_encode(
            $canonical,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    private function revisionVectorDiffersFromSelection(
        TestKitPreparationCompositionRevision $revision,
        TestKitPreparation $preparation,
        array $currentVector,
    ): bool {
        $savedVector = $revision->component_vector_json;
        if (! is_array($savedVector)
            || (int) $revision->template_version_id !== (int) $preparation->template_version_id
            || (int) $revision->booklet_version_id !== (int) $preparation->booklet_version_id
            || (int) $revision->booklet_set_id !== (int) $preparation->booklet_set_id
            || (int) $revision->test_instrument_version_id !== (int) $preparation->test_instrument_version_id) {
            return true;
        }

        return ! hash_equals((string) $revision->component_vector_sha256, $this->componentVectorSha256($savedVector))
            || ! hash_equals((string) $revision->component_vector_sha256, $this->componentVectorSha256($currentVector));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortAssociativeKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }
        foreach ($value as $key => $entry) {
            if (is_array($entry)) {
                $value[$key] = $this->sortAssociativeKeys($entry);
            }
        }

        return $value;
    }
}
