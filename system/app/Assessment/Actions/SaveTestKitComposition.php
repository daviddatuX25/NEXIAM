<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitPreparationReceipt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
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
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SaveTestKitComposition
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitPreparationReceipt $receipts,
        private readonly KitCompositionContext $compositionContext,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $input */
    public function handle(User $actor, int $testKitId, array $input): TestKitPreparation
    {
        $fields = ['preparation_id', 'expected_revision', 'component_vector', 'rows', 'idempotency_key'];
        if (array_diff($fields, array_keys($input)) !== [] || array_diff(array_keys($input), $fields) !== []) {
            throw ValidationException::withMessages(['composition' => 'Submit the complete composition snapshot and expected preparation revision.']);
        }
        foreach (['preparation_id', 'expected_revision'] as $field) {
            if ((! is_int($input[$field]) && (! is_string($input[$field]) || preg_match('/^(0|[1-9][0-9]*)$/D', $input[$field]) !== 1))
                || (int) $input[$field] < ($field === 'preparation_id' ? 1 : 0)) {
                throw ValidationException::withMessages([$field => 'A valid non-negative integer is required.']);
            }
        }
        if (! is_string($input['idempotency_key']) || ! Str::isUuid($input['idempotency_key'])) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency UUID is required.']);
        }
        if (! is_array($input['component_vector']) || ! is_array($input['rows']) || ! array_is_list($input['rows'])) {
            throw ValidationException::withMessages(['rows' => 'Submit a complete ordered composition snapshot.']);
        }
        $this->assertInputRows($input['rows']);

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
            $preparation = TestKitPreparation::query()->where('test_kit_id', $kit->id)
                ->lockForUpdate()->findOrFail((int) $input['preparation_id']);
            $payload = [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'expected_revision' => (int) $input['expected_revision'],
                'component_vector' => $input['component_vector'],
                'rows' => $input['rows'],
            ];
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.save_composition',
                (string) $preparation->id,
                $payload,
                $input['idempotency_key'],
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                if ($result['test_kit_id'] !== (int) $kit->id || $result['preparation_id'] !== (int) $preparation->id) {
                    throw new RuntimeException('The composition receipt belongs to another Test Kit preparation.');
                }

                return $this->receipts->preparationSnapshot($result);
            }
            if ($preparation->state !== TestKitPreparation::STATE_PREPARING) {
                throw ValidationException::withMessages(['preparation_id' => 'Assembled Test Kit preparations are immutable.']);
            }
            if ((int) $preparation->revision !== (int) $input['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'This preparation changed. Refresh before saving.']);
            }
            $this->lockSelectedComponents($kit, $preparation);
            $resolved = $this->compositionContext->resolvePreparation($preparation);
            if (! hash_equals(
                (string) $resolved['component_vector_sha256'],
                $this->compositionContext->componentVectorSha256($input['component_vector']),
            )) {
                throw ValidationException::withMessages(['component_vector' => 'The exact selected component versions or hashes changed. Refresh before saving.']);
            }
            if (($resolved['map_applicable'] ?? false) !== true) {
                if ($input['rows'] !== []) {
                    throw ValidationException::withMessages(['rows' => 'This composition has no applicable item map; remove the submitted mapping rows.']);
                }
                throw ValidationException::withMessages(['composition' => 'No item map applies to this Test Setup. Save its exact selections without mapping rows.']);
            }
            $selectionComplete = $resolved['template'] !== null && $resolved['booklet'] !== null
                && $resolved['set'] !== null && $resolved['profile'] !== null;
            if (! $selectionComplete) {
                $issue = $resolved['issues'][0]['message'] ?? 'Select the complete exact Answer Sheet, Booklet, and finalized Set A before saving the item map.';
                throw ValidationException::withMessages(['composition' => $issue]);
            }
            $normalizedRows = $this->normalizeRows($input['rows'], $preparation, $resolved);
            $nextCompositionRevision = (int) $preparation->composition_revision + 1;
            $vector = $resolved['component_vector'];
            $vectorSha256 = (string) $resolved['component_vector_sha256'];
            $preparation->composition_revision = $nextCompositionRevision;
            $preparation->revision = (int) $preparation->revision + 1;
            $preparation->updated_by_user_id = (int) $principal->id;
            $preparation->save();
            $revision = TestKitPreparationCompositionRevision::query()->create([
                'test_kit_preparation_id' => (int) $preparation->id,
                'test_kit_id' => (int) $kit->id,
                'composition_revision' => $nextCompositionRevision,
                'template_version_id' => (int) $preparation->template_version_id,
                'booklet_version_id' => (int) $preparation->booklet_version_id,
                'booklet_set_id' => (int) $preparation->booklet_set_id,
                'test_instrument_version_id' => (int) $preparation->test_instrument_version_id,
                'component_vector_json' => $vector,
                'component_vector_sha256' => $vectorSha256,
                'created_at' => now(),
            ]);
            $insertRows = array_map(fn (array $row): array => [
                'test_kit_preparation_id' => (int) $preparation->id,
                'test_kit_id' => (int) $kit->id,
                'composition_revision_id' => (int) $revision->id,
                'composition_revision' => $nextCompositionRevision,
                'template_version_id' => (int) $preparation->template_version_id,
                'booklet_version_id' => (int) $preparation->booklet_version_id,
                'booklet_set_id' => (int) $preparation->booklet_set_id,
                'test_instrument_version_id' => (int) $preparation->test_instrument_version_id,
                'component_vector_sha256' => $vectorSha256,
                ...$row,
                'created_at' => now(),
                'updated_at' => now(),
            ], $normalizedRows);
            if ($insertRows !== []) {
                TestKitPreparationCompositionRow::query()->insert($insertRows);
            }
            $this->receipts->remember($receipt, $preparation);
            $this->audit->handle('assessment.test_kit.composition_saved', [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'preparation_revision' => (int) $preparation->revision,
                'composition_revision' => (int) $preparation->composition_revision,
                'mapped_row_count' => count($normalizedRows),
                'component_vector_sha256' => $vectorSha256,
            ], (string) $principal->id);

            return $preparation;
        });
    }

    /** @param list<array<string, mixed>> $rows */
    private function assertInputRows(array $rows): void
    {
        if (! array_is_list($rows)) {
            throw ValidationException::withMessages(['rows' => 'Composition rows must be an ordered list.']);
        }
        $allowed = [
            'disposition', 'physical_item_uid', 'booklet_item_id', 'formal_subtest_id',
            'formal_item_id', 'option_tokens',
        ];
        foreach ($rows as $index => $row) {
            if (! is_array($row) || array_diff(array_keys($row), $allowed) !== [] || array_diff($allowed, array_keys($row)) !== []) {
                throw ValidationException::withMessages(["rows.{$index}" => 'Each composition row must contain exactly the reviewed source identities and option tokens.']);
            }
            if (! is_array($row['option_tokens']) || ! array_is_list($row['option_tokens'])) {
                throw ValidationException::withMessages(["rows.{$index}.option_tokens" => 'Response tokens must retain their exact ordered list.']);
            }
        }
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function normalizeRows(array $rows, TestKitPreparation $preparation, array $resolved): array
    {
        $physicalByUid = [];
        foreach ($resolved['physical_inventory']['items'] as $location) {
            $physicalByUid[(string) $location['physical_item_uid']] = $location;
        }
        $bookletItems = $resolved['booklet']->items->keyBy('id');
        $setItems = $resolved['set']->items->groupBy('booklet_item_id');
        $formalItems = [];
        foreach ($resolved['profile']->subtests as $subtest) {
            foreach ($subtest->items as $item) {
                $formalItems[(int) $item->id] = $item;
            }
        }

        $seenPhysical = [];
        $seenBooklet = [];
        $seenFormal = [];
        $normalized = [];
        foreach ($rows as $index => $row) {
            $disposition = (string) $row['disposition'];
            $formalItemId = $this->positiveId($row['formal_item_id'], "rows.{$index}.formal_item_id");
            $formalSubtestId = $this->positiveId($row['formal_subtest_id'], "rows.{$index}.formal_subtest_id");
            $formal = $formalItems[$formalItemId] ?? null;
            if ($formal === null || (int) $formal->test_instrument_subtest_id !== $formalSubtestId
                || isset($seenFormal[$formalItemId])) {
                throw ValidationException::withMessages(["rows.{$index}.formal_item_id" => 'Choose one exact item from the selected approved Test Setup.']);
            }
            $seenFormal[$formalItemId] = true;

            if ($disposition === 'nonphysical_practice') {
                if ((bool) $formal->is_scored
                    || $row['physical_item_uid'] !== null
                    || $row['booklet_item_id'] !== null
                    || $row['option_tokens'] !== []) {
                    throw ValidationException::withMessages(["rows.{$index}.disposition" => 'Only an explicitly unscored practice item can be recorded as nonphysical.']);
                }
                $normalized[] = [
                    'disposition' => 'nonphysical_practice',
                    'physical_item_uid' => null,
                    'sheet_item_id' => null,
                    'booklet_set_item_id' => null,
                    'booklet_item_id' => null,
                    'booklet_item_uid' => null,
                    'booklet_section_uid' => null,
                    'test_instrument_subtest_id' => $formalSubtestId,
                    'test_instrument_item_id' => $formalItemId,
                ];

                continue;
            }

            $physicalUid = $row['physical_item_uid'];
            $bookletItemId = $this->positiveId($row['booklet_item_id'], "rows.{$index}.booklet_item_id");
            if (! is_string($physicalUid) || $physicalUid === '' || isset($seenPhysical[$physicalUid]) || isset($seenBooklet[$bookletItemId])) {
                throw ValidationException::withMessages(["rows.{$index}" => 'A physical location and intrinsic Booklet item may each be mapped only once.']);
            }
            $location = $physicalByUid[$physicalUid] ?? null;
            $bookletItem = $bookletItems->get($bookletItemId);
            $setOccurrences = $setItems->get($bookletItemId, collect());
            if (! is_array($location) || $bookletItem === null || $setOccurrences->count() !== 1) {
                throw ValidationException::withMessages(["rows.{$index}" => 'Choose one exact physical location and one exact Set A Booklet item.']);
            }
            $tokens = array_values(array_map(static fn (mixed $token): string => (string) $token, $row['option_tokens']));
            if ($tokens !== array_values((array) $location['option_tokens'])) {
                throw ValidationException::withMessages(["rows.{$index}.option_tokens" => 'The displayed physical response tokens changed. Refresh before saving.']);
            }
            $setItem = $setOccurrences->first();
            if ((int) $bookletItem->booklet_version_id !== (int) $preparation->booklet_version_id
                || (int) $setItem->booklet_set_id !== (int) $preparation->booklet_set_id
                || (int) ($setItem->booklet_version_id ?? 0) !== (int) $preparation->booklet_version_id
                || (string) $setItem->booklet_item_uid !== (string) $bookletItem->booklet_item_uid
                || (string) $setItem->booklet_section_uid !== (string) $bookletItem->booklet_section_uid) {
                throw ValidationException::withMessages(["rows.{$index}.booklet_item_id" => 'The selected Booklet item is not the exact item in this finalized Set A.']);
            }
            $seenPhysical[$physicalUid] = true;
            $seenBooklet[$bookletItemId] = true;
            $normalized[] = [
                'disposition' => 'physical',
                'physical_item_uid' => $physicalUid,
                'sheet_item_id' => (int) $location['sheet_item_id'],
                'booklet_set_item_id' => (int) $setItem->id,
                'booklet_item_id' => $bookletItemId,
                'booklet_item_uid' => (string) $bookletItem->booklet_item_uid,
                'booklet_section_uid' => (string) $bookletItem->booklet_section_uid,
                'test_instrument_subtest_id' => $formalSubtestId,
                'test_instrument_item_id' => $formalItemId,
            ];
        }

        return $normalized;
    }

    private function lockSelectedComponents(TestKit $kit, TestKitPreparation $preparation): void
    {
        if ($preparation->test_instrument_version_id !== null) {
            $candidate = TestInstrumentVersion::query()->findOrFail((int) $preparation->test_instrument_version_id);
            $owner = TestInstrument::query()->lockForUpdate()->findOrFail((int) $candidate->test_instrument_id);
            $profile = TestInstrumentVersion::query()->lockForUpdate()->findOrFail((int) $candidate->id);
            $this->assertOwner($kit, $owner, 'test_instrument_version_id');
            if ((int) $owner->id !== (int) $profile->test_instrument_id) {
                throw ValidationException::withMessages(['test_instrument_version_id' => 'The exact profile version owner changed.']);
            }
        }
        if ($preparation->booklet_version_id !== null) {
            $candidate = OmrQuestionBookletVersion::query()->findOrFail((int) $preparation->booklet_version_id);
            $owner = OmrQuestionBooklet::query()->lockForUpdate()->findOrFail((int) $candidate->booklet_id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail((int) $candidate->id);
            $this->assertOwner($kit, $owner, 'booklet_version_id');
            if ((int) $owner->id !== (int) $booklet->booklet_id) {
                throw ValidationException::withMessages(['booklet_version_id' => 'The exact Booklet version owner changed.']);
            }
        }
        if ($preparation->booklet_set_id !== null) {
            $setCandidate = OmrBookletSet::query()->findOrFail((int) $preparation->booklet_set_id);
            $set = OmrBookletSet::query()->where('booklet_version_id', $setCandidate->booklet_version_id)
                ->lockForUpdate()->findOrFail((int) $setCandidate->id);
            if ($set->set_code !== 'A' || (int) $set->booklet_version_id !== (int) $preparation->booklet_version_id) {
                throw ValidationException::withMessages(['booklet_set_id' => 'Only the exact selected finalized Set A can be composed.']);
            }
        }
        if ($preparation->template_version_id !== null) {
            $candidate = OmrAnswerSheetTemplateVersion::query()->findOrFail((int) $preparation->template_version_id);
            $owner = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail((int) $candidate->template_id);
            $template = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail((int) $candidate->id);
            $this->assertOwner($kit, $owner, 'template_version_id');
            if ((int) $owner->id !== (int) $template->template_id) {
                throw ValidationException::withMessages(['template_version_id' => 'The exact Answer Sheet version owner changed.']);
            }
        }
    }

    private function assertOwner(TestKit $kit, TestInstrument|OmrQuestionBooklet|OmrAnswerSheetTemplate $owner, string $field): void
    {
        if ((int) $owner->getAttribute('institution_id') !== (int) $kit->institution_id
            || $owner->getAttribute('is_synthetic') === null
            || (bool) $owner->getAttribute('is_synthetic') !== (bool) $kit->is_synthetic) {
            throw ValidationException::withMessages([$field => 'The selected component belongs to another institution or provenance class.']);
        }
    }

    private function positiveId(mixed $value, string $field): int
    {
        if ((! is_int($value) && (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1)) || (int) $value < 1) {
            throw ValidationException::withMessages([$field => 'Select a valid exact component identity.']);
        }

        return (int) $value;
    }
}
