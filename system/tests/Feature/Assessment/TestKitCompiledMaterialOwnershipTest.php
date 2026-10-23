<?php

use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** @return array{actor: User, institution: Institution} */
function compiledOwnershipGuidanceActor(): array
{
    $institution = Institution::query()->create([
        'code' => 'COMP-'.Str::upper(Str::random(10)),
        'name' => 'Compiled material ownership fixture',
    ]);
    $actor = User::factory()->create();
    $cycleCode = 'COMP-CYCLE-'.Str::upper(Str::random(8));
    DB::table('academic_cycles')->insertGetId([
        'institution_id' => $institution->id,
        'code' => $cycleCode,
        'name' => 'Compiled material ownership cycle',
        'opens_on' => now()->subDay()->toDateString(),
        'closes_on' => now()->addYear()->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $actor->staffProfile()->create([
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'active_cycle_code' => $cycleCode,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    foreach ([
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
        $actor->givePermissionTo($capability);
    }

    return ['actor' => $actor, 'institution' => $institution];
}

test('booklet producers keep institution-local codes and explicit synthetic provenance', function (): void {
    $first = compiledOwnershipGuidanceActor();
    $second = compiledOwnershipGuidanceActor();

    $draft = app(CreateBookletDraft::class)->handle(
        'Shared no-sheet booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $first['actor'],
        code: 'SHARED-BOOKLET',
        isSynthetic: true,
    );
    $otherDraft = app(CreateBookletDraft::class)->handle(
        'Shared no-sheet booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $second['actor'],
        code: 'SHARED-BOOKLET',
        isSynthetic: true,
    );

    expect($draft->booklet->institution_id)->toBe($first['institution']->id)
        ->and($otherDraft->booklet->institution_id)->toBe($second['institution']->id)
        ->and($draft->booklet->code)->toBe($otherDraft->booklet->code)
        ->and($draft->booklet->is_synthetic)->toBeTrue()
        ->and($otherDraft->booklet->is_synthetic)->toBeTrue();

    $area = AptitudeArea::query()->create([
        'code' => 'COMP-GENERAL',
        'name' => 'Compiled ownership general',
        'max_items' => 1,
        'display_order' => 1,
        'is_active' => true,
    ]);
    $legacyDraft = app(SaveAuthoredBookletVersion::class)->handle(
        'SHARED-LEGACY',
        'Shared legacy-style synthetic booklet',
        [['position' => 1, 'area_id' => $area->id, 'correct_option_code' => 'A']],
        $first['actor'],
    );
    $otherLegacyDraft = app(SaveAuthoredBookletVersion::class)->handle(
        'SHARED-LEGACY',
        'Shared legacy-style synthetic booklet',
        [['position' => 1, 'area_id' => $area->id, 'correct_option_code' => 'A']],
        $second['actor'],
    );

    expect($legacyDraft->booklet->institution_id)->toBe($first['institution']->id)
        ->and($legacyDraft->booklet->is_synthetic)->toBeTrue()
        ->and($otherLegacyDraft->booklet->is_synthetic)->toBeTrue();

    $institutionalBooklet = OmrQuestionBooklet::query()->create([
        'institution_id' => $first['institution']->id,
        'code' => 'LOCKED-BOOKLET',
        'name' => 'Institutional booklet',
        'is_synthetic' => false,
    ]);
    expect(fn () => app(SaveAuthoredBookletVersion::class)->handle(
        'LOCKED-BOOKLET',
        'Synthetic replacement attempt',
        [['position' => 1, 'area_id' => $area->id, 'correct_option_code' => 'A']],
        $first['actor'],
    ))->toThrow(RuntimeException::class, 'cannot relabel')
        ->and($institutionalBooklet->fresh()->is_synthetic)->toBeFalse();
});

test('explicit compiled inactive sheet selection carries coherent profile provenance into booklet draft', function (): void {
    $scope = compiledOwnershipGuidanceActor();
    $institution = $scope['institution'];
    $actor = $scope['actor'];
    $instrument = TestInstrument::query()->create([
        'institution_id' => $institution->id,
        'code' => 'COMP-PROFILE-'.Str::upper(Str::random(8)),
    ]);
    $instrument->forceFill(['is_synthetic' => true])->save();
    $profile = TestInstrumentVersion::query()->create([
        'test_instrument_id' => $instrument->id,
        'version_number' => 1,
        'status' => TestInstrumentVersion::STATUS_DRAFT,
        'display_name' => 'Protected synthetic profile',
        'created_by_user_id' => $actor->id,
    ]);
    $sheetFamily = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $institution->id,
        'code' => 'COMP-SHEET-'.Str::upper(Str::random(8)),
        'name' => 'Compiled inactive synthetic sheet',
        'is_synthetic' => true,
    ]);
    $sheetVersion = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $sheetFamily->id,
        'test_instrument_version_id' => $profile->id,
        'native_bundle_hash' => hash('sha256', 'compiled-inactive-sheet'),
        'native_bundle_json' => ['template' => [], 'config' => [], 'manifest' => [], 'files' => []],
    ]);
    $sheetVersion->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED]);

    $booklet = app(CreateBookletDraft::class)->handle(
        'Explicit compiled sheet booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $actor,
        $sheetVersion,
        'EXPLICIT-SHEET',
    );

    expect($booklet->template_version_id)->toBe($sheetVersion->id)
        ->and($booklet->test_instrument_version_id)->toBe($profile->id)
        ->and($booklet->booklet->institution_id)->toBe($institution->id)
        ->and($booklet->booklet->is_synthetic)->toBeTrue()
        ->and($sheetVersion->isCompiled())->toBeTrue()
        ->and($sheetVersion->status)->toBe(OmrAnswerSheetTemplateVersion::STATUS_COMPILED)
        ->and(fn () => $sheetVersion->forceFill([
            'native_bundle_json' => ['tampered' => true],
        ])->save())->toThrow(RuntimeException::class, 'Compiled answer-sheet versions are immutable.')
        ->and(fn () => $sheetVersion->delete())->toThrow(RuntimeException::class, 'Compiled answer-sheet versions are immutable.');
});

test('booklet creation rejects omitted no-sheet provenance and cross-institution compiled sheets', function (): void {
    $scope = compiledOwnershipGuidanceActor();
    $foreignScope = compiledOwnershipGuidanceActor();
    $draft = fn (?OmrAnswerSheetTemplateVersion $version, ?bool $synthetic = null) => app(CreateBookletDraft::class)->handle(
        'Bounded booklet draft',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $scope['actor'],
        $version,
        null,
        $synthetic,
    );

    expect(fn () => $draft(null))->toThrow(RuntimeException::class, 'explicit provenance');

    $foreignFamily = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => $foreignScope['institution']->id,
        'code' => 'FOREIGN-SHEET',
        'name' => 'Foreign compiled sheet',
        'is_synthetic' => true,
    ]);
    $foreignVersion = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $foreignFamily->id,
        'native_bundle_hash' => hash('sha256', 'foreign-compiled-sheet'),
        'native_bundle_json' => ['template' => [], 'config' => [], 'manifest' => [], 'files' => []],
    ]);
    $foreignVersion->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED]);

    expect(fn () => $draft($foreignVersion, true))->toThrow(RuntimeException::class, 'another institution');
});
test('finalized booklet material is immutable while its draft version remains editable', function (): void {
    $scope = compiledOwnershipGuidanceActor();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Editable before finalization',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $scope['actor'],
        isSynthetic: true,
    );

    $booklet->forceFill(['content_hash' => hash('sha256', 'editable-draft')])->save();
    expect($booklet->fresh()->isDraft())->toBeTrue();

    $booklet->forceFill([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'content_hash' => hash('sha256', 'finalized-content'),
        'answer_key_hash' => hash('sha256', 'finalized-key'),
        'finalized_at' => now(),
        'finalized_by_user_id' => $scope['actor']->id,
    ])->save();

    $finalized = $booklet->fresh();
    $finalized->forceFill(['archived_at' => now()])->save();
    $finalized->refresh();

    expect($finalized->isFinalized())->toBeTrue()
        ->and(fn () => $finalized->forceFill([
            'content_hash' => hash('sha256', 'tampered-content'),
        ])->save())->toThrow(RuntimeException::class, 'Finalized Booklet versions are immutable.')
        ->and(fn () => $finalized->delete())->toThrow(RuntimeException::class, 'Finalized Booklet versions are immutable.');
});
