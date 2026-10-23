<?php

use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
    $this->kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => (int) $this->institution->id,
        'code' => 'PREP-'.Str::upper(Str::random(8)),
        'name' => 'Preparation selection test',
        'is_synthetic' => false,
        'created_by_user_id' => (int) $this->actor->id,
    ]);
});

/** @return array<string, mixed> */
function testKitPreparationSelectionPayload(int $preparationId, int $revision, string $key): array
{
    return [
        'preparation_id' => $preparationId,
        'expected_revision' => $revision,
        'idempotency_key' => $key,
        'test_instrument_version_id' => null,
        'norm_package_version_id' => null,
        'answer_sheet_draft_id' => null,
        'template_version_id' => null,
        'booklet_version_id' => null,
        'booklet_set_id' => null,
    ];
}

function testKitPreparationProfile(User $actor, int $institutionId, bool $synthetic): TestInstrumentVersion
{
    $instrument = new TestInstrument;
    $instrument->forceFill([
        'institution_id' => $institutionId,
        'code' => 'PROFILE-'.Str::upper(Str::random(8)),
        'is_synthetic' => $synthetic,
    ])->save();

    return TestInstrumentVersion::query()->create([
        'test_instrument_id' => (int) $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'display_name' => 'Selection fixture profile',
        'lock_version' => 1,
        'created_by_user_id' => (int) $actor->id,
    ]);
}

test('starts an empty kit without a candidate and saves exact nullable selections with replay and conflict fences', function (): void {
    $key = (string) Str::uuid();
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => $key,
    ]);

    expect($preparation->state)->toBe(TestKitPreparation::STATE_PREPARING)
        ->and($preparation->revision)->toBe(1)
        ->and($preparation->test_instrument_version_id)->toBeNull()
        ->and(TestKitVersion::query()->where('test_kit_id', $this->kit->id)->count())->toBe(0);

    $replayedStart = app(StartTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => $key,
    ]);
    expect($replayedStart->id)->toBe($preparation->id);

    $saveKey = (string) Str::uuid();
    $selection = testKitPreparationSelectionPayload((int) $preparation->id, 1, $saveKey);
    $saved = app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $selection);
    expect($saved->revision)->toBe(2)
        ->and($saved->fresh()->test_instrument_version_id)->toBeNull();

    $replayedSave = app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $selection);
    expect($replayedSave->revision)->toBe(2);

    $profileB = testKitPreparationProfile($this->actor, (int) $this->institution->id, false);
    $selectionB = testKitPreparationSelectionPayload((int) $preparation->id, 2, (string) Str::uuid());
    $selectionB['test_instrument_version_id'] = (int) $profileB->id;
    $savedB = app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $selectionB);
    $replayedSave = app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $selection);
    expect($replayedSave->revision)->toBe(2)
        ->and($replayedSave->test_instrument_version_id)->toBeNull()
        ->and($savedB->fresh()->revision)->toBe(3)
        ->and($savedB->fresh()->test_instrument_version_id)->toBe($profileB->id);

    $stalePayload = testKitPreparationSelectionPayload((int) $preparation->id, 1, (string) Str::uuid());
    expect(fn () => app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $stalePayload))
        ->toThrow(ValidationException::class);

    expect(fn () => app(AssembleTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => 2,
        'idempotency_key' => (string) Str::uuid(),
    ]))->toThrow(ValidationException::class);

    expect($preparation->fresh()->state)->toBe(TestKitPreparation::STATE_PREPARING)
        ->and(TestKitVersion::query()->where('test_kit_id', $this->kit->id)->count())->toBe(0);
});

test('rejects preparation mutations without fresh Test Kit catalog authority', function (): void {
    $unauthorized = User::factory()->create();

    expect(fn () => app(StartTestKitPreparation::class)->handle($unauthorized, (int) $this->kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]))->toThrow(AuthorizationException::class);
});

test('rejects selected profiles from another institution or provenance class', function (): void {
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $foreignInstitution = Institution::query()->create([
        'code' => 'OTHER-'.Str::upper(Str::random(8)),
        'name' => 'Other institution fixture',
    ]);
    foreach ([
        testKitPreparationProfile($this->actor, (int) $foreignInstitution->id, false),
        testKitPreparationProfile($this->actor, (int) $this->institution->id, true),
    ] as $profile) {
        $selection = testKitPreparationSelectionPayload((int) $preparation->id, (int) $preparation->revision, (string) Str::uuid());
        $selection['test_instrument_version_id'] = (int) $profile->id;
        expect(fn () => app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $selection))
            ->toThrow(ValidationException::class);
        expect($preparation->fresh()->revision)->toBe(1)
            ->and($preparation->fresh()->test_instrument_version_id)->toBeNull();
    }
});

test('an assembled preparation cannot be edited or deleted as history', function (): void {
    $preparation = TestKitPreparation::query()->create([
        'test_kit_id' => (int) $this->kit->id,
        'state' => TestKitPreparation::STATE_PREPARING,
        'revision' => 1,
        'created_by_user_id' => (int) $this->actor->id,
        'updated_by_user_id' => (int) $this->actor->id,
    ]);
    $historical = new TestKitPreparation;
    $historical->setRawAttributes([
        'id' => (int) $preparation->id,
        'test_kit_id' => (int) $this->kit->id,
        'state' => TestKitPreparation::STATE_ASSEMBLED,
        'revision' => 1,
        'created_by_user_id' => (int) $this->actor->id,
        'updated_by_user_id' => (int) $this->actor->id,
    ], true);
    $historical->exists = true;

    expect(fn () => $historical->forceFill(['revision' => 2])->save())
        ->toThrow(RuntimeException::class, 'assembled_test_kit_preparations_are_immutable_history');
    expect(fn () => $historical->delete())
        ->toThrow(RuntimeException::class, 'assembled_test_kit_preparations_are_immutable_history');
});
test('a selected non-A Set remains an incompatible draft and cannot create a candidate', function (): void {
    $profile = testKitPreparationProfile($this->actor, (int) $this->institution->id, false);
    $templateFamily = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => (int) $this->institution->id,
        'is_synthetic' => false,
        'code' => 'SHEET-'.Str::upper(Str::random(8)),
        'name' => 'Set selection fixture sheet',
    ]);
    $template = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => (int) $templateFamily->id,
        'test_instrument_version_id' => (int) $profile->id,
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'page_width' => 100,
        'page_height' => 200,
        'page_count' => 1,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'config'),
    ]);
    $bookletFamily = OmrQuestionBooklet::query()->create([
        'institution_id' => (int) $this->institution->id,
        'is_synthetic' => false,
        'code' => 'BOOKLET-'.Str::upper(Str::random(8)),
        'name' => 'Set selection fixture Booklet',
    ]);
    $booklet = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => (int) $bookletFamily->id,
        'template_version_id' => (int) $template->id,
        'test_instrument_version_id' => (int) $profile->id,
        'version' => 1,
        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS,
        'allow_multiple_sets' => false,
        'answer_key_hash' => hash('sha256', 'answer-key'),
    ]);
    $set = OmrBookletSet::query()->create([
        'booklet_version_id' => (int) $booklet->id,
        'set_code' => 'B',
        'status' => OmrBookletSet::STATUS_DRAFT,
    ]);
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, [
        'expected_family_version' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $selection = testKitPreparationSelectionPayload((int) $preparation->id, 1, (string) Str::uuid());
    $selection['test_instrument_version_id'] = (int) $profile->id;
    $selection['template_version_id'] = (int) $template->id;
    $selection['booklet_version_id'] = (int) $booklet->id;
    $selection['booklet_set_id'] = (int) $set->id;
    $saved = app(SaveTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, $selection);

    expect($saved->revision)->toBe(2)
        ->and($saved->booklet_set_id)->toBe($set->id);
    expect(fn () => app(AssembleTestKitPreparation::class)->handle($this->actor, (int) $this->kit->id, [
        'preparation_id' => (int) $saved->id,
        'expected_revision' => 2,
        'idempotency_key' => (string) Str::uuid(),
    ]))->toThrow(ValidationException::class);

    expect($saved->fresh()->state)->toBe(TestKitPreparation::STATE_PREPARING)
        ->and(TestKitVersion::query()->where('test_kit_id', $this->kit->id)->count())->toBe(0);
});
test('assembles a successor from exact preparation pins and seals immutable history', function (): void {
    require_once __DIR__.'/../../Support/TestKitHelpers.php';
    $fixture = testKitAcceptanceFixture($this->actor, $this->institution);
    $versionOneManifest = $fixture['version']->manifest_json;
    $versionOneManifestHash = (string) $fixture['version']->manifest_sha256;
    $preparation = app(StartTestKitPreparation::class)->handle($this->actor, (int) $fixture['kit']->id, [
        'expected_family_version' => 1,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    expect($preparation->test_instrument_version_id)->toBe($fixture['version']->test_instrument_version_id)
        ->and($preparation->template_version_id)->toBe($fixture['version']->template_version_id)
        ->and($preparation->booklet_version_id)->toBe($fixture['version']->booklet_version_id)
        ->and($preparation->booklet_set_id)->toBe($fixture['version']->booklet_set_id);
    $unchangedSave = testKitPreparationSelectionPayload(
        (int) $preparation->id,
        (int) $preparation->revision,
        (string) Str::uuid(),
    );
    foreach ([
        'test_instrument_version_id', 'norm_package_version_id', 'answer_sheet_draft_id',
        'template_version_id', 'booklet_version_id', 'booklet_set_id',
    ] as $field) {
        $unchangedSave[$field] = $preparation->{$field} === null ? null : (int) $preparation->{$field};
    }
    $preparation = app(SaveTestKitPreparation::class)->handle($this->actor, (int) $fixture['kit']->id, $unchangedSave);
    $request = [
        'preparation_id' => (int) $preparation->id,
        'expected_revision' => (int) $preparation->revision,
        'idempotency_key' => (string) Str::uuid(),
    ];
    $version = app(AssembleTestKitPreparation::class)->handle($this->actor, (int) $fixture['kit']->id, $request);
    $sealed = $preparation->fresh();

    expect($version->version_number)->toBe(2)
        ->and($fixture['version']->fresh()->manifest_json)->toBe($versionOneManifest)
        ->and($fixture['version']->fresh()->manifest_sha256)->toBe($versionOneManifestHash)
        ->and($sealed->state)->toBe(TestKitPreparation::STATE_ASSEMBLED)
        ->and($sealed->assembled_test_kit_version_id)->toBe($version->id)
        ->and($sealed->assembly_context_json['selection']['booklet_set_id'])->toBe($fixture['set']->id)
        ->and($sealed->assembly_context_json['norm'])->toBeNull()
        ->and($sealed->assembly_context_sha256)->toBe(hash(
            'sha256',
            json_encode($sealed->assembly_context_json, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ));

    $replayed = app(AssembleTestKitPreparation::class)->handle($this->actor, (int) $fixture['kit']->id, $request);
    expect($replayed->id)->toBe($version->id)
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->count())->toBe(2);
    expect(fn () => $sealed->forceFill(['revision' => (int) $sealed->revision + 1])->save())
        ->toThrow(RuntimeException::class, 'assembled_test_kit_preparations_are_immutable_history');
    expect(fn () => $sealed->delete())
        ->toThrow(RuntimeException::class, 'assembled_test_kit_preparations_are_immutable_history');
});
