<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\KitCompositionContext;
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
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitPreparationCompositionRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SaveTestKitPreparation
{
    private const SELECTION_FIELDS = [
        'test_instrument_version_id',
        'norm_package_version_id',
        'answer_sheet_draft_id',
        'template_version_id',
        'booklet_version_id',
        'booklet_set_id',
    ];

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitPreparationReceipt $receipts,
        private readonly KitCompositionContext $compositionContext,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, int $testKitId, array $input): TestKitPreparation
    {
        $required = ['preparation_id', 'expected_revision', 'idempotency_key', ...self::SELECTION_FIELDS];
        if (array_diff($required, array_keys($input)) !== [] || array_diff(array_keys($input), $required) !== []) {
            throw ValidationException::withMessages(['preparation' => 'Submit every selection field explicitly; use null to clear a selection.']);
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
        $selection = [];
        foreach (self::SELECTION_FIELDS as $field) {
            $value = $input[$field];
            if ($value !== null && ((! is_int($value) && (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1)) || (int) $value < 1)) {
                throw ValidationException::withMessages([$field => 'Select a valid resource or clear this selection.']);
            }
            $selection[$field] = $value === null ? null : (int) $value;
        }

        return DB::transaction(function () use ($actor, $testKitId, $input, $selection): TestKitPreparation {
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
                ...$selection,
            ];
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.save_preparation',
                (string) $preparation->id,
                $payload,
                $input['idempotency_key'],
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                if ($result['test_kit_id'] !== (int) $kit->id || $result['preparation_id'] !== (int) $preparation->id) {
                    throw new \RuntimeException('The Test Kit preparation receipt belongs to another preparation.');
                }

                return $this->receipts->preparationSnapshot($result);
            }
            if ($preparation->state !== TestKitPreparation::STATE_PREPARING) {
                throw ValidationException::withMessages(['preparation_id' => 'Assembled Test Kit preparations are immutable.']);
            }
            if ((int) $preparation->revision !== (int) $input['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'This preparation changed. Refresh before saving selections.']);
            }

            $this->lockAndAssertOwnership($kit, $selection);
            $selectionChanged = false;
            foreach ($selection as $field => $value) {
                $current = $preparation->{$field};
                $current = $current === null ? null : (int) $current;
                $selectionChanged = $selectionChanged || ($field !== 'norm_package_version_id' && $current !== $value);
                $preparation->{$field} = $value;
            }
            if ($selectionChanged) {
                $preparation->composition_revision = (int) $preparation->composition_revision + 1;
            }
            $composition = $this->compositionContext->resolvePreparation($preparation);
            $requirements = $composition['requirements'];
            if ($requirements !== null && $selection['booklet_version_id'] === null && $selection['booklet_set_id'] !== null) {
                throw ValidationException::withMessages(['booklet_set_id' => 'A Set cannot be selected without its exact Booklet.']);
            }

            $preparation->revision = (int) $preparation->revision + 1;
            $preparation->updated_by_user_id = (int) $principal->id;
            $preparation->save();

            $hasRevisionRow = TestKitPreparationCompositionRevision::query()
                ->where('test_kit_preparation_id', (int) $preparation->id)
                ->where('composition_revision', (int) $preparation->composition_revision)
                ->exists();

            $compositionIssues = array_column($composition['issues'], 'code');
            $canPersistVector = $requirements !== null
                && array_diff($compositionIssues, ['composition_not_saved']) === [];
            if (! $hasRevisionRow && $selection['test_instrument_version_id'] !== null && $canPersistVector) {
                TestKitPreparationCompositionRevision::query()->create([
                    'test_kit_preparation_id' => (int) $preparation->id,
                    'test_kit_id' => (int) $kit->id,
                    'composition_revision' => (int) $preparation->composition_revision,
                    'template_version_id' => $selection['template_version_id'],
                    'booklet_version_id' => $selection['booklet_version_id'],
                    'booklet_set_id' => $selection['booklet_set_id'],
                    'test_instrument_version_id' => $selection['test_instrument_version_id'],
                    'component_vector_json' => $composition['component_vector'],
                    'component_vector_sha256' => $composition['component_vector_sha256'],
                    'created_at' => now(),
                ]);
            }

            $this->receipts->remember($receipt, $preparation);
            $this->audit->handle('assessment.test_kit.preparation_saved', [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'revision' => (int) $preparation->revision,
                'composition_revision' => (int) $preparation->composition_revision,
                'selection_digest' => hash('sha256', json_encode($selection, JSON_THROW_ON_ERROR)),
            ], (string) $principal->id);

            return $preparation;
        });
    }

    /**
     * @param  array<string, int|null>  $selection
     */
    private function lockAndAssertOwnership(TestKit $kit, array $selection): void
    {
        if ($selection['test_instrument_version_id'] !== null) {
            $candidate = TestInstrumentVersion::query()->findOrFail($selection['test_instrument_version_id']);
            TestInstrument::query()->lockForUpdate()->findOrFail((int) $candidate->test_instrument_id);
            TestInstrumentVersion::query()->lockForUpdate()->findOrFail($candidate->id);
            $this->assertFamilyOwner(
                $kit,
                TestInstrument::query()->findOrFail((int) $candidate->test_instrument_id),
                'test_instrument_version_id',
            );
        }

        if ($selection['norm_package_version_id'] !== null) {
            $candidate = AssessmentNormPackageVersion::query()->findOrFail($selection['norm_package_version_id']);
            AssessmentNormPackage::query()->lockForUpdate()->findOrFail((int) $candidate->norm_package_id);
            $norm = AssessmentNormPackageVersion::query()->lockForUpdate()->findOrFail($candidate->id);
            $this->assertFamilyOwner($kit, AssessmentNormPackage::query()->findOrFail((int) $norm->norm_package_id), 'norm_package_version_id');
        }

        if ($selection['booklet_version_id'] !== null) {
            $candidate = OmrQuestionBookletVersion::query()->findOrFail($selection['booklet_version_id']);
            OmrQuestionBooklet::query()->lockForUpdate()->findOrFail((int) $candidate->booklet_id);
            $booklet = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($candidate->id);
            $this->assertFamilyOwner($kit, OmrQuestionBooklet::query()->findOrFail((int) $booklet->booklet_id), 'booklet_version_id');
        }

        if ($selection['booklet_set_id'] !== null) {
            $setCandidate = OmrBookletSet::query()->findOrFail($selection['booklet_set_id']);
            $bookletCandidate = OmrQuestionBookletVersion::query()->findOrFail((int) $setCandidate->booklet_version_id);
            $this->assertFamilyOwner(
                $kit,
                OmrQuestionBooklet::query()->findOrFail((int) $bookletCandidate->booklet_id),
                'booklet_set_id',
            );
            $set = OmrBookletSet::query()->where('booklet_version_id', $bookletCandidate->id)
                ->lockForUpdate()->findOrFail($setCandidate->id);
        }

        if ($selection['answer_sheet_draft_id'] !== null) {
            $draft = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($selection['answer_sheet_draft_id']);
            if ((int) $draft->institution_id !== (int) $kit->institution_id
                || $draft->is_synthetic === null
                || (bool) $draft->is_synthetic !== (bool) $kit->is_synthetic) {
                throw ValidationException::withMessages(['answer_sheet_draft_id' => 'The selected answer-sheet draft belongs to a different institution or provenance class.']);
            }
        }

        if ($selection['template_version_id'] !== null) {
            $candidate = OmrAnswerSheetTemplateVersion::query()->findOrFail($selection['template_version_id']);
            OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail((int) $candidate->template_id);
            $template = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($candidate->id);
            $owner = OmrAnswerSheetTemplate::query()->findOrFail((int) $template->template_id);
            $this->assertFamilyOwner($kit, $owner, 'template_version_id');
        }
    }

    private function assertFamilyOwner(TestKit $kit, TestInstrument|AssessmentNormPackage|OmrAnswerSheetTemplate|OmrQuestionBooklet $owner, string $field): void
    {
        if ((int) $owner->getAttribute('institution_id') !== (int) $kit->institution_id
            || $owner->getAttribute('is_synthetic') === null
            || (bool) $owner->getAttribute('is_synthetic') !== (bool) $kit->is_synthetic) {
            throw ValidationException::withMessages([$field => 'The selected resource belongs to a different institution or provenance class.']);
        }
    }
}
