<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Assessment\Actions\Omr\CopyBookletVersionToDraft;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\GenerateBookletSets;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());
    $capabilities = [
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
    ];
    foreach ($capabilities as $capability) {
        Permission::findOrCreate($capability, 'web');
    }
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actor->givePermissionTo($capabilities);
});

/** @return array{version: OmrAnswerSheetTemplateVersion, area: AptitudeArea} */
function singleSetAnswerSheet(): array
{
    $area = AptitudeArea::query()->where('is_active', true)->orderBy('id')->firstOrFail();
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => Institution::current()->id,
        'code' => 'SINGLE-'.strtoupper(substr(hash('sha256', uniqid('', true)), 0, 10)),
        'name' => 'Synthetic single-set answer sheet',
        'is_synthetic' => true,
    ]);
    $version = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'page_width' => 1000,
        'page_height' => 1400,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => hash('sha256', 'single-set-config'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => ['template' => [], 'config' => []],
        'native_bundle_hash' => str_repeat('b', 64),
        'review_manifest_json' => ['pages' => []],
        'review_manifest_hash' => str_repeat('c', 64),
        'engine_name' => 'OMRChecker',
        'engine_commit' => 'de3f6982822895491be760325fd43e65e90fa293',
        'patch_identity' => 'nexiam-explicit-bubble-positions-v1',
    ]);
    $version->areas()->create([
        'aptitude_area_id' => $area->id,
        'display_order' => 1,
        'max_items' => 2,
        'region_x' => 0,
        'region_y' => 0,
        'region_w' => 1,
        'region_h' => 1,
    ]);
    $version->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_COMPILED]);

    return ['version' => $version->fresh('areas.aptitudeArea'), 'area' => $area];
}

/** @return list<array{aptitude_area_id: int, area_position: int, global_position: int, correct_option_code: string}> */
function singleSetKeys(AptitudeArea $area): array
{
    return [
        ['aptitude_area_id' => $area->id, 'area_position' => 1, 'global_position' => 1, 'correct_option_code' => 'A'],
        ['aptitude_area_id' => $area->id, 'area_position' => 2, 'global_position' => 2, 'correct_option_code' => 'D'],
    ];
}

function singleSetDraft(User $actor, OmrAnswerSheetTemplateVersion $sheet, string $name = 'Synthetic A-only key'): OmrQuestionBookletVersion
{
    return app(CreateBookletDraft::class)->handle($name, OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY, $actor, $sheet);
}

it('changes aggregate key identity when Set policy changes with only draft A keys', function (): void {
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $draft = singleSetDraft($this->actor, $sheet);
    app(SaveBookletSetKeys::class)->handle($draft, 'A', singleSetKeys($area), $this->actor);

    $singlePolicyHash = $draft->fresh()->answer_key_hash;
    expect($draft->sets()->pluck('set_code')->all())->toBe(['A']);

    $multiSetPolicy = app(SetBookletSetsMode::class)->handle(
        $draft->fresh(),
        $this->actor,
        true,
        (int) $draft->fresh()->lock_version,
        false,
    );
    expect($multiSetPolicy->sets()->pluck('set_code')->all())->toBe(['A'])
        ->and($multiSetPolicy->answer_key_hash)->not->toBe($singlePolicyHash);

    $singleSetPolicy = app(SetBookletSetsMode::class)->handle(
        $multiSetPolicy->fresh(),
        $this->actor,
        false,
        (int) $multiSetPolicy->fresh()->lock_version,
        false,
    );
    expect($singleSetPolicy->sets()->pluck('set_code')->all())->toBe(['A'])
        ->and($singleSetPolicy->answer_key_hash)->toBe($singlePolicyHash);
});

it('requires the dedicated Booklet management capability instead of assessment catalog access', function (): void {
    $catalogOnlyActor = User::factory()->create();
    $catalogOnlyActor->givePermissionTo(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG);

    expect(fn () => app(CreateBookletDraft::class)->handle(
        'Catalog-only actor must not manage Booklets',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $catalogOnlyActor,
    ))->toThrow(RuntimeException::class, 'You are not authorized to manage Booklets.');
});

it('rejects a draft Answer Sheet with bundle fields when preparing a Booklet key', function (): void {
    ['version' => $sheet] = singleSetAnswerSheet();
    $draftSheet = $sheet->template->versions()->create([
        'version' => 2,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'page_width' => $sheet->page_width,
        'page_height' => $sheet->page_height,
        'page_count' => $sheet->page_count,
        'identity_regions_json' => $sheet->identity_regions_json,
        'provider_config_json' => $sheet->provider_config_json,
        'config_hash' => $sheet->config_hash,
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => ['template' => [], 'config' => []],
        'native_bundle_hash' => hash('sha256', 'draft-bundle-fields'),
    ]);

    expect(fn () => singleSetDraft($this->actor, $draftSheet))
        ->toThrow(RuntimeException::class, 'Only a compiled immutable Answer Sheet version may be selected.');
});

it('defaults a new draft to Set A and finalizes a complete A without creating alternates', function (): void {
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $draft = singleSetDraft($this->actor, $sheet);

    expect($draft->allow_multiple_sets)->toBeFalse()
        ->and($draft->allowedSetCodes())->toBe(['A'])
        ->and($draft->requiredSetCodes())->toBe(['A']);

    app(SaveBookletSetKeys::class)->handle($draft, 'A', singleSetKeys($area), $this->actor);
    $answerKeyHash = $draft->fresh()->answer_key_hash;
    $finalized = app(FinalizeBookletVersion::class)->handle($draft->fresh(), $this->actor);

    $copy = app(CopyBookletVersionToDraft::class)->handle($finalized, $this->actor);
    expect($finalized->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($finalized->isFinalized())->toBeTrue()
        ->and($finalized->finalized_by_user_id)->toBe($this->actor->id)
        ->and($finalized->finalized_at)->not->toBeNull()
        ->and($finalized->activated_at)->toBeNull()
        ->and($finalized->answer_key_hash)->toBe($answerKeyHash)
        ->and($finalized->fresh()->allow_multiple_sets)->toBeFalse()
        ->and($finalized->sets()->pluck('set_code')->all())->toBe(['A'])
        ->and($finalized->sets()->firstOrFail()->answerKeyByPosition())->toBe([1 => 'A', 2 => 'D'])
        ->and($finalized->sets()->firstOrFail()->source_content_hash)->toBe($finalized->content_hash)
        ->and($copy->allow_multiple_sets)->toBeFalse()
        ->and($copy->allowedSetCodes())->toBe(['A']);
});

it('rejects an unattributed draft-to-finalized transition', function (): void {
    ['version' => $sheet] = singleSetAnswerSheet();
    $draft = singleSetDraft($this->actor, $sheet);
    $draft->forceFill([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'finalized_at' => now(),
        'content_hash' => hash('sha256', 'unattributed-content'),
        'answer_key_hash' => hash('sha256', 'unattributed-key'),
    ]);

    expect(fn () => $draft->save())->toThrow(RuntimeException::class, 'attributed finalizer')
        ->and($draft->fresh()->isDraft())->toBeTrue();
    $unattributedVersion = $draft->fresh()->replicate();
    $unattributedVersion->forceFill([
        'status' => OmrQuestionBookletVersion::STATUS_FINALIZED,
        'finalized_at' => now(),
        'content_hash' => hash('sha256', 'unattributed-constructor-content'),
        'answer_key_hash' => hash('sha256', 'unattributed-constructor-key'),
        'finalized_by_user_id' => null,
    ]);

    expect(fn () => $unattributedVersion->save())
        ->toThrow(RuntimeException::class, 'attributed finalizer');

});

it('blocks missing A positions and requires complete A through D when multi-set mode is enabled', function (): void {
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $draft = singleSetDraft($this->actor, $sheet);
    app(SaveBookletSetKeys::class)->handle($draft, 'A', [singleSetKeys($area)[0]], $this->actor);

    expect(fn () => app(FinalizeBookletVersion::class)->handle($draft->fresh(), $this->actor))
        ->toThrow(RuntimeException::class);
    expect($draft->fresh()->isDraft())->toBeTrue()
        ->and($draft->sets()->where('set_code', 'A')->firstOrFail()->answerKeyByPosition())->toBe([1 => 'A']);

    $multiSet = app(SetBookletSetsMode::class)->handle($draft->fresh(), $this->actor, true, $draft->fresh()->lock_version, false);
    app(SaveBookletSetKeys::class)->handle($multiSet, 'A', singleSetKeys($area), $this->actor);
    app(SaveBookletSetKeys::class)->handle($multiSet, 'B', singleSetKeys($area), $this->actor);
    expect($multiSet->requiredSetCodes())->toBe(['A', 'B', 'C', 'D'])
        ->and(fn () => app(FinalizeBookletVersion::class)->handle($multiSet->fresh(), $this->actor))
        ->toThrow(RuntimeException::class);
});

it('rejects direct alternate writes atomically and requires confirmation before removing populated alternates', function (): void {
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $draft = singleSetDraft($this->actor, $sheet);
    app(SaveBookletSetKeys::class)->handle($draft, 'A', singleSetKeys($area), $this->actor);
    $beforeRows = DB::table('omr_booklet_sets')->where('booklet_version_id', $draft->id)->orderBy('set_code')->get()->toArray();
    $beforeItems = DB::table('omr_booklet_set_items')
        ->whereIn('booklet_set_id', DB::table('omr_booklet_sets')->select('id')->where('booklet_version_id', $draft->id))
        ->orderBy('id')->get()->toArray();
    $beforeHash = $draft->fresh()->answer_key_hash;
    $beforeLock = $draft->fresh()->lock_version;

    foreach (['B', 'C', 'D'] as $setCode) {
        expect(fn () => app(SaveBookletSetKeys::class)->handle($draft->fresh(), $setCode, singleSetKeys($area), $this->actor))
            ->toThrow(RuntimeException::class);
    }
    expect(DB::table('omr_booklet_sets')->where('booklet_version_id', $draft->id)->orderBy('set_code')->get()->toArray())->toEqual($beforeRows)
        ->and(DB::table('omr_booklet_set_items')
            ->whereIn('booklet_set_id', DB::table('omr_booklet_sets')->select('id')->where('booklet_version_id', $draft->id))
            ->orderBy('id')->get()->toArray())->toEqual($beforeItems)
        ->and($draft->fresh()->answer_key_hash)->toBe($beforeHash)
        ->and($draft->fresh()->lock_version)->toBe($beforeLock);

    $multiSet = app(SetBookletSetsMode::class)->handle($draft->fresh(), $this->actor, true, $draft->fresh()->lock_version, false);
    foreach (['A', 'B', 'C', 'D'] as $code) {
        app(SaveBookletSetKeys::class)->handle($multiSet->fresh(), $code, singleSetKeys($area), $this->actor);
    }
    $aBefore = $multiSet->sets()->where('set_code', 'A')->firstOrFail()->answerKeyByPosition();
    expect(fn () => app(SetBookletSetsMode::class)->handle($multiSet, $this->actor, false, $multiSet->lock_version, false))
        ->toThrow(RuntimeException::class);
    expect($multiSet->fresh()->allow_multiple_sets)->toBeTrue()
        ->and($multiSet->fresh()->sets()->count())->toBe(4);

    $single = app(SetBookletSetsMode::class)->handle($multiSet->fresh(), $this->actor, false, $multiSet->fresh()->lock_version, true);
    expect($single->allow_multiple_sets)->toBeFalse()
        ->and($single->sets()->pluck('set_code')->all())->toBe(['A'])
        ->and($single->sets()->firstOrFail()->answerKeyByPosition())->toBe($aBefore);
});

it('preserves deterministic A through D whole-item generation and source policy on copy', function (): void {
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $draft = app(CreateBookletDraft::class)->handle(
        'Synthetic multi-set question source',
        OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS,
        $this->actor,
        $sheet,
    );
    $draft = app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false);
    $items = [
        ['aptitude_area_id' => $area->id, 'authored_position' => 1, 'correct_option_code' => 'B', 'content' => ['prompt' => 'Synthetic question one', 'choices' => ['A' => 'one A', 'B' => 'one B', 'C' => 'one C', 'D' => 'one D']]],
        ['aptitude_area_id' => $area->id, 'authored_position' => 2, 'correct_option_code' => 'D', 'content' => ['prompt' => 'Synthetic question two', 'choices' => ['A' => 'two A', 'B' => 'two B', 'C' => 'two C', 'D' => 'two D']]],
    ];
    $saved = app(SaveAuthoredBookletVersion::class)->handle($draft, $items, $this->actor, $draft->lock_version, true);
    $generated = app(GenerateBookletSets::class)->handle($saved, $this->actor, $saved->lock_version, 'whole-item-fixed-seed');
    $generatedOrders = $generated->sets->mapWithKeys(fn ($set): array => [$set->set_code => $set->items->sortBy('global_position')->pluck('booklet_item_id')->all()])->all();
    $regenerated = app(GenerateBookletSets::class)->handle($generated, $this->actor, $generated->lock_version, 'whole-item-fixed-seed');
    $regeneratedOrders = $regenerated->sets->mapWithKeys(fn ($set): array => [$set->set_code => $set->items->sortBy('global_position')->pluck('booklet_item_id')->all()])->all();
    $sourceIds = $saved->items()->orderBy('authored_position')->pluck('id')->all();
    $expectedByAuthoredPosition = [
        1 => ['prompt' => 'Synthetic question one', 'correct' => 'B', 'choices' => ['A' => 'one A', 'B' => 'one B', 'C' => 'one C', 'D' => 'one D']],
        2 => ['prompt' => 'Synthetic question two', 'correct' => 'D', 'choices' => ['A' => 'two A', 'B' => 'two B', 'C' => 'two C', 'D' => 'two D']],
    ];

    expect(array_keys($generatedOrders))->toBe(['A', 'B', 'C', 'D'])
        ->and($generatedOrders)->toBe($regeneratedOrders)
        ->and($generatedOrders['A'])->toBe($sourceIds);

    foreach ($regenerated->sets as $set) {
        $set->load('items.bookletItem');
        expect($set->items)->toHaveCount(2);
        foreach ($set->items as $setItem) {
            $item = $setItem->bookletItem;
            $expected = $expectedByAuthoredPosition[$item->authored_position];
            expect($item->aptitude_area_id)->toBe($area->id)
                ->and($item->correct_option_code)->toBe($expected['correct'])
                ->and($item->encrypted_content['prompt'])->toBe($expected['prompt'])
                ->and($item->encrypted_content['choices'])->toBe($expected['choices']);
        }
    }

    $complete = app(FinalizeBookletVersion::class)->handle($regenerated, $this->actor);
    $copy = app(CopyBookletVersionToDraft::class)->handle($complete, $this->actor);
    expect($complete->sets()->where('status', 'finalized')->count())->toBe(4)
        ->and($copy->allow_multiple_sets)->toBeTrue()
        ->and($copy->sets()->orderBy('set_code')->pluck('set_code')->all())->toBe(['A', 'B', 'C', 'D']);
});

it('denies stale and finalized policy changes without changing a finalized source', function (): void {
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $draft = singleSetDraft($this->actor, $sheet);
    $staleVersion = $draft->lock_version;
    $draft = app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false);
    expect(fn () => app(SetBookletSetsMode::class)->handle($draft, $this->actor, false, $staleVersion, true))
        ->toThrow(RuntimeException::class);

    foreach (['A', 'B', 'C', 'D'] as $code) {
        app(SaveBookletSetKeys::class)->handle($draft->fresh(), $code, singleSetKeys($area), $this->actor);
    }
    $finalized = app(FinalizeBookletVersion::class)->handle($draft, $this->actor);
    $sourceHash = $finalized->answer_key_hash;
    expect(fn () => app(SetBookletSetsMode::class)->handle($finalized, $this->actor, false, $finalized->lock_version, true))
        ->toThrow(RuntimeException::class);
    expect($finalized->fresh()->allow_multiple_sets)->toBeTrue()
        ->and($finalized->fresh()->answer_key_hash)->toBe($sourceHash)
        ->and($finalized->fresh()->sets()->orderBy('set_code')->pluck('set_code')->all())->toBe(['A', 'B', 'C', 'D']);

    $copy = app(CopyBookletVersionToDraft::class)->handle($finalized, $this->actor);
    expect($copy->allow_multiple_sets)->toBeTrue();
});

it('blocks enabling alternate Booklet Sets when the institution toggle is disabled', function (): void {
    ['version' => $sheet] = singleSetAnswerSheet();
    Institution::current()->update(['enable_multi_set_booklets' => false]);
    $draft = singleSetDraft($this->actor, $sheet);

    expect(fn () => app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false))
        ->toThrow(RuntimeException::class, 'Alternate Booklet Sets are not enabled for this institution.');
    expect($draft->fresh()->allow_multiple_sets)->toBeFalse();
});

it('blocks alternate key writes on a retained multi-set draft when the institution toggle is disabled', function (): void {
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    $draft = singleSetDraft($this->actor, $sheet);
    $draft = app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false);
    app(SaveBookletSetKeys::class)->handle($draft, 'B', singleSetKeys($area), $this->actor);
    $beforeHash = $draft->fresh()->answer_key_hash;
    $beforeLock = $draft->fresh()->lock_version;
    Institution::current()->update(['enable_multi_set_booklets' => false]);

    expect(fn () => app(SaveBookletSetKeys::class)->handle($draft->fresh(), 'B', singleSetKeys($area), $this->actor))
        ->toThrow(RuntimeException::class, 'Alternate Booklet Sets are not enabled for this institution.');
    expect($draft->fresh()->allow_multiple_sets)->toBeTrue()
        ->and($draft->sets()->where('set_code', 'B')->firstOrFail()->answerKeyByPosition())->toBe([1 => 'A', 2 => 'D'])
        ->and($draft->fresh()->answer_key_hash)->toBe($beforeHash)
        ->and($draft->fresh()->lock_version)->toBe($beforeLock);
});

it('blocks finalizing a complete retained multi-set draft when the institution toggle is disabled', function (): void {
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    $draft = singleSetDraft($this->actor, $sheet);
    $draft = app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false);
    foreach (['A', 'B', 'C', 'D'] as $code) {
        app(SaveBookletSetKeys::class)->handle($draft->fresh(), $code, singleSetKeys($area), $this->actor);
    }
    $beforeHash = $draft->fresh()->answer_key_hash;
    Institution::current()->update(['enable_multi_set_booklets' => false]);

    expect(fn () => app(FinalizeBookletVersion::class)->handle($draft->fresh(), $this->actor))
        ->toThrow(RuntimeException::class, 'Cannot finalize multi-set Booklet while alternate Booklet Sets are disabled.');
    expect($draft->fresh()->isDraft())->toBeTrue()
        ->and($draft->fresh()->answer_key_hash)->toBe($beforeHash)
        ->and($draft->sets()->pluck('set_code')->all())->toBe(['A', 'B', 'C', 'D']);
});

it('allows confirmed conversion of populated alternates to Set A while the institution toggle is disabled', function (): void {
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    $draft = singleSetDraft($this->actor, $sheet);
    $draft = app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false);
    foreach (['A', 'B', 'C', 'D'] as $code) {
        app(SaveBookletSetKeys::class)->handle($draft->fresh(), $code, singleSetKeys($area), $this->actor);
    }
    Institution::current()->update(['enable_multi_set_booklets' => false]);

    $single = app(SetBookletSetsMode::class)->handle($draft->fresh(), $this->actor, false, $draft->fresh()->lock_version, true);

    expect($single->allow_multiple_sets)->toBeFalse()
        ->and($single->sets()->pluck('set_code')->all())->toBe(['A'])
        ->and($single->sets()->firstOrFail()->answerKeyByPosition())->toBe([1 => 'A', 2 => 'D']);
    expect(app(FinalizeBookletVersion::class)->handle($single, $this->actor)->isFinalized())->toBeTrue();
});

it('copies finalized multi-set history as a retained-disabled draft without changing its source', function (): void {
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    $draft = singleSetDraft($this->actor, $sheet);
    $draft = app(SetBookletSetsMode::class)->handle($draft, $this->actor, true, $draft->lock_version, false);
    foreach (['A', 'B', 'C', 'D'] as $code) {
        app(SaveBookletSetKeys::class)->handle($draft->fresh(), $code, singleSetKeys($area), $this->actor);
    }
    $finalized = app(FinalizeBookletVersion::class)->handle($draft->fresh(), $this->actor);
    $sourceHash = $finalized->answer_key_hash;
    Institution::current()->update(['enable_multi_set_booklets' => false]);

    $copy = app(CopyBookletVersionToDraft::class)->handle($finalized, $this->actor);

    expect($copy->isDraft())->toBeTrue()
        ->and($copy->allow_multiple_sets)->toBeTrue()
        ->and($copy->sets()->orderBy('set_code')->pluck('set_code')->all())->toBe(['A', 'B', 'C', 'D'])
        ->and($finalized->fresh()->isFinalized())->toBeTrue()
        ->and($finalized->fresh()->answer_key_hash)->toBe($sourceHash);
    foreach ($copy->sets()->get() as $set) {
        expect($set->answerKeyByPosition())->toBe([1 => 'A', 2 => 'D']);
    }
    expect(fn () => app(SaveBookletSetKeys::class)->handle($copy->fresh(), 'B', singleSetKeys($area), $this->actor))
        ->toThrow(RuntimeException::class, 'Alternate Booklet Sets are not enabled for this institution.');
    expect(fn () => app(FinalizeBookletVersion::class)->handle($copy->fresh(), $this->actor))
        ->toThrow(RuntimeException::class, 'Cannot finalize multi-set Booklet while alternate Booklet Sets are disabled.');
});

it('publishes A-only material with uniform seating and blocks unavailable B through D before publication writes', function (): void {
    Institution::current()->update(['enable_seating_arrangements' => true]);
    ['version' => $sheet, 'area' => $area] = singleSetAnswerSheet();
    $booklet = singleSetDraft($this->actor, $sheet, 'Synthetic uniform A exam');
    app(SaveBookletSetKeys::class)->handle($booklet, 'A', singleSetKeys($area), $this->actor);
    $booklet = app(FinalizeBookletVersion::class)->handle($booklet, $this->actor);

    $uniform = singleSetSession($this->actor, $booklet, 'SINGLE-UNIFORM-A', SeatingArrangement::PATTERN_UNIFORM);
    $accepted = acceptSingleSetApplicant($this->actor, 'single-uniform@example.test', 'Uniform');
    $uniformAssignment = app(AssignApplicantToExamSession::class)->handle($uniform, $accepted, $this->actor);
    $published = app(PublishExamSession::class)->handle($uniform, $this->actor, 1);
    $snapshot = ExamSessionMaterialSnapshot::query()->where('exam_session_id', $published->id)->where('is_current', true)->firstOrFail();
    expect($snapshot->sets()->firstOrFail()->answer_key_json)->toBe([1 => 'A', 2 => 'D']);
    $seatSets = $snapshot->seats()->with('bookletSet')->orderBy('ordinal')->get()->map(fn ($seat): string => $seat->bookletSet->set_code)->unique()->values()->all();
    expect($snapshot->sets()->pluck('set_code')->all())->toBe(['A'])
        ->and($seatSets)->toBe(['A'])
        ->and($uniformAssignment->fresh()->booklet_set_id)->toBeNull();

    $checkerboard = singleSetSession($this->actor, $booklet, 'SINGLE-CHECKERBOARD-BLOCK', SeatingArrangement::PATTERN_CHECKERBOARD);
    $attempt = acceptSingleSetApplicant($this->actor, 'single-checkerboard@example.test', 'Blocked');
    $assignment = app(AssignApplicantToExamSession::class)->handle($checkerboard, $attempt, $this->actor);
    expect(fn () => app(PublishExamSession::class)->handle($checkerboard, $this->actor, 1))
        ->toThrow(RuntimeException::class);
    expect(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $checkerboard->id)->count())->toBe(0)
        ->and(ExamAssignmentSeatAllocation::query()->where('exam_assignment_id', $assignment->id)->count())->toBe(0)
        ->and($checkerboard->fresh()->status)->toBe('draft');
    $historical = singleSetDraft($this->actor, $sheet, 'Synthetic historical multi-set exam');
    Institution::current()->update(['enable_multi_set_booklets' => true]);
    $historical = app(SetBookletSetsMode::class)->handle($historical, $this->actor, true, $historical->lock_version, false);
    foreach (['A', 'B', 'C', 'D'] as $code) {
        app(SaveBookletSetKeys::class)->handle($historical->fresh(), $code, singleSetKeys($area), $this->actor);
    }
    $historical = app(FinalizeBookletVersion::class)->handle($historical, $this->actor);
    $historicalSession = singleSetSession($this->actor, $historical, 'HISTORIC-FOUR-SET-SESSION', SeatingArrangement::PATTERN_LATIN_FOUR);
    $historicalAttempt = acceptSingleSetApplicant($this->actor, 'historical-four-set@example.test', 'Historical');
    app(AssignApplicantToExamSession::class)->handle($historicalSession, $historicalAttempt, $this->actor);
    $publishedHistorical = app(PublishExamSession::class)->handle($historicalSession, $this->actor, 1);
    $historicalSnapshot = ExamSessionMaterialSnapshot::query()
        ->where('exam_session_id', $publishedHistorical->id)
        ->where('is_current', true)
        ->firstOrFail();
    expect($historicalSnapshot->sets()->orderBy('set_code')->pluck('set_code')->all())->toBe(['A', 'B', 'C'])
        ->and($historicalSnapshot->sets()->orderBy('set_code')->get()->mapWithKeys(fn ($set): array => [$set->set_code => $set->answer_key_json])->all())->toBe([
            'A' => [1 => 'A', 2 => 'D'],
            'B' => [1 => 'A', 2 => 'D'],
            'C' => [1 => 'A', 2 => 'D'],
        ])
        ->and($historicalSnapshot->seats()->with('bookletSet')->get()->pluck('bookletSet.set_code')->unique()->sort()->values()->all())->toBe(['A', 'B', 'C']);
});

function singleSetSession(User $actor, OmrQuestionBookletVersion $booklet, string $code, string $pattern): ExamSession
{
    $arrangement = SeatingArrangement::factory()->create([
        'name' => $code.' arrangement',
        'normalized_name' => mb_strtolower($code).' arrangement',
        'rows' => 2,
        'columns' => 2,
        'booklet_pattern' => $pattern,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
    ]);

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::factory()->create(['institution_id' => $cycle->institution_id]);

    return app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        $code,
        now()->addDay()->toDateTimeString(),
        now()->addDay()->addHours(2)->toDateTimeString(),
        $actor,
        ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        $booklet,
        $arrangement,
    );
}

function acceptSingleSetApplicant(User $actor, string $email, string $lastName): AdmissionAttempt
{
    $submission = submitSyntheticApplicant($email, $lastName);

    return app(AcceptAdmissionAttempt::class)->handle($submission['attempt'], $actor, 1);
}
