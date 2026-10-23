<?php

use App\Assessment\Actions\Omr\CopyBookletVersionToDraft;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\GenerateBookletSets;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Assessment\Omr\Queries\BookletWorkspaceQuery;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Queries\ExamBookletDistributionQuery;
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
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new DatabaseSeeder)->run(DatabaseSeeder::testSeeders());

    foreach ([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
    ] as $capability) {
        Permission::findOrCreate($capability, 'web');
    }

    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->actor->givePermissionTo([
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
    ]);
});

/**
 * @return array{version: OmrAnswerSheetTemplateVersion, area: AptitudeArea}
 */
function secureBookletAnswerSheet(): array
{
    $area = AptitudeArea::query()->where('is_active', true)->orderBy('id')->firstOrFail();
    $template = OmrAnswerSheetTemplate::query()->create([
        'institution_id' => Institution::current()->id,
        'code' => 'SECURE-'.strtoupper(substr(uniqid(), -8)),
        'name' => 'Secure authoring sheet',
        'is_synthetic' => true,
    ]);
    $version = $template->versions()->create([
        'version' => 1,
        'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1',
        'native_bundle_json' => ['template' => [], 'config' => []],
        'native_bundle_hash' => hash('sha256', 'secure-booklet-native-bundle'),
        'page_width' => 1000,
        'page_height' => 1400,
        'identity_regions_json' => [],
        'provider_config_json' => [],
        'config_hash' => str_repeat('a', 64),
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

function secureQuestionContent(string $prefix): array
{
    return [
        'prompt' => $prefix.' prompt',
        'choices' => [
            'A' => $prefix.' choice A',
            'B' => $prefix.' choice B',
            'C' => $prefix.' choice C',
            'D' => $prefix.' choice D',
        ],
    ];
}

function enableSecureBookletAdvancedSetFixture(): void
{
    Institution::current()->update([
        'enable_seating_arrangements' => true,
        'enable_multi_set_booklets' => true,
    ]);
}

it('authors stored questions, generates deterministic Sets, and freezes finalization', function (): void {
    enableSecureBookletAdvancedSetFixture();
    ['version' => $sheet, 'area' => $area] = secureBookletAnswerSheet();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Stored secure demo',
        OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS,
        $this->actor,
        $sheet,
    );

    $items = [
        [
            'aptitude_area_id' => $area->id,
            'authored_position' => 1,
            'correct_option_code' => 'A',
            'content' => secureQuestionContent('First'),
        ],
        [
            'aptitude_area_id' => $area->id,
            'authored_position' => 2,
            'correct_option_code' => 'C',
            'content' => secureQuestionContent('Second'),
        ],
    ];

    $saved = app(SaveAuthoredBookletVersion::class)->handle($booklet, $items, $this->actor, 1, true);
    $saved = app(SetBookletSetsMode::class)->handle($saved, $this->actor, true, $saved->lock_version, false);
    $itemIds = $saved->items()->orderBy('authored_position')->pluck('id')->all();
    $generated = app(GenerateBookletSets::class)->handle($saved, $this->actor, $saved->lock_version, 'fixed-seed');
    $firstOrders = $generated->sets->mapWithKeys(
        fn ($set): array => [$set->set_code => $set->items->sortBy('global_position')->pluck('booklet_item_id')->all()],
    )->all();

    expect(fn () => app(GenerateBookletSets::class)->handle($generated, $this->actor, $saved->lock_version, 'fixed-seed'))
        ->toThrow(RuntimeException::class, 'changed elsewhere');

    $regenerated = app(GenerateBookletSets::class)->handle(
        $generated,
        $this->actor,
        $generated->lock_version,
        'fixed-seed',
    );
    $secondOrders = $regenerated->sets->mapWithKeys(
        fn ($set): array => [$set->set_code => $set->items->sortBy('global_position')->pluck('booklet_item_id')->all()],
    )->all();
    $contentHash = $regenerated->content_hash;
    $answerKeyHash = $regenerated->answer_key_hash;
    $finalized = app(FinalizeBookletVersion::class)->handle($regenerated, $this->actor);

    expect($firstOrders)->toBe($secondOrders)
        ->and($firstOrders['A'])->toBe($itemIds)
        ->and($finalized->status)->toBe(OmrQuestionBookletVersion::STATUS_FINALIZED)
        ->and($finalized->isFinalized())->toBeTrue()
        ->and($finalized->finalized_at)->not->toBeNull()
        ->and($finalized->activated_at)->toBeNull()
        ->and($finalized->content_hash)->toBe($contentHash)
        ->and($finalized->answer_key_hash)->toBe($answerKeyHash)
        ->and($finalized->sets()->count())->toBe(4)
        ->and($finalized->sets()->where('status', 'finalized')->count())->toBe(4)
        ->and($finalized->sets()->with('items')->get()->every(fn ($set): bool => $set->items->pluck('booklet_item_id')->filter()->sort()->values()->all() === collect($itemIds)->sort()->values()->all()))->toBeTrue();
});

it('redacts stored question content when the content capability is absent', function (): void {
    ['version' => $sheet, 'area' => $area] = secureBookletAnswerSheet();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Redacted secure demo',
        OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS,
        $this->actor,
        $sheet,
    );
    app(SaveAuthoredBookletVersion::class)->handle($booklet, [[
        'aptitude_area_id' => $area->id,
        'authored_position' => 1,
        'correct_option_code' => 'B',
        'content' => secureQuestionContent('Secret'),
    ]], $this->actor);

    $rawContent = (string) DB::table('omr_booklet_items')
        ->where('booklet_version_id', $booklet->id)
        ->value('encrypted_content');
    expect($rawContent)->not->toContain('Secret prompt')
        ->and($rawContent)->not->toContain('Secret choice');

    $viewer = User::factory()->create();
    $workspace = app(BookletWorkspaceQuery::class)->forBooklet($booklet->booklet, $viewer, false);

    expect($workspace['items'][0]['content'])->toBeNull()
        ->and($workspace['items'][0]['stem'])->toBeNull()
        ->and($workspace['items'][0]['choices'])->toBeNull();
});

it('persists exact external keys without creating stored question content', function (): void {
    enableSecureBookletAdvancedSetFixture();
    ['version' => $sheet, 'area' => $area] = secureBookletAnswerSheet();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Key-only secure demo',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        $sheet,
    );
    $keys = [
        ['aptitude_area_id' => $area->id, 'area_position' => 1, 'global_position' => 1, 'correct_option_code' => 'A'],
        ['aptitude_area_id' => $area->id, 'area_position' => 2, 'global_position' => 2, 'correct_option_code' => 'D'],
    ];
    $booklet = app(SetBookletSetsMode::class)->handle($booklet, $this->actor, true, $booklet->lock_version, false);

    foreach (['A', 'B', 'C', 'D'] as $setCode) {
        app(SaveBookletSetKeys::class)->handle($booklet, $setCode, $keys, $this->actor);
    }
    $finalized = app(FinalizeBookletVersion::class)->handle($booklet, $this->actor);

    $workspace = app(BookletWorkspaceQuery::class)->forBooklet($finalized->booklet, $this->actor, false);
    $firstSet = collect($workspace['sets'])->firstWhere('set_code', 'A');
    expect($finalized->isFinalized())->toBeTrue()
        ->and($finalized->items()->count())->toBe(2)
        ->and($finalized->sets()->where('status', 'finalized')->count())->toBe(4)
        ->and($finalized->sets()->firstOrFail()->answerKeyByPosition())->toBe([1 => 'A', 2 => 'D'])
        ->and($firstSet['items'])->toHaveCount(2)
        ->and(array_column($firstSet['items'], 'correct_option_code'))->toBe(['A', 'D']);
});

it('defaults authoring mode to answer_keys_only when omitted during booklet creation', function (): void {
    ['version' => $sheet] = secureBookletAnswerSheet();

    $response = $this->actingAs($this->actor)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $this->actor->authenticationState->session_version,
    ])->post(route('setup.exam-materials.booklets.store'), [
        'name' => 'Default mode booklet',
        'template_version_id' => $sheet->id,
    ]);
    $version = OmrQuestionBookletVersion::query()->latest('id')->firstOrFail();
    $response->assertRedirect(route('setup.exam-materials.booklets.show', $version->booklet_id))
        ->assertSessionHasNoErrors();

    expect($version->authoring_mode)->toBe(OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY);
});

it('preserves an existing advanced frozen snapshot when institution policy is disabled', function (): void {
    enableSecureBookletAdvancedSetFixture();
    ['version' => $sheet, 'area' => $area] = secureBookletAnswerSheet();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Previously published advanced material',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        $sheet,
    );
    $booklet = app(SetBookletSetsMode::class)->handle($booklet, $this->actor, true, $booklet->lock_version, false);
    $keys = [
        ['aptitude_area_id' => $area->id, 'area_position' => 1, 'global_position' => 1, 'correct_option_code' => 'A'],
        ['aptitude_area_id' => $area->id, 'area_position' => 2, 'global_position' => 2, 'correct_option_code' => 'D'],
    ];
    foreach (['A', 'B', 'C', 'D'] as $setCode) {
        app(SaveBookletSetKeys::class)->handle($booklet, $setCode, $keys, $this->actor);
    }
    $booklet = app(FinalizeBookletVersion::class)->handle($booklet, $this->actor);

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $firstSubmission = submitSyntheticApplicant('secure-booklet-session-a@example.test', 'Applicant A');
    $secondSubmission = submitSyntheticApplicant('secure-booklet-session-b@example.test', 'Applicant B');
    foreach ([$firstSubmission, $secondSubmission] as $submission) {
        $this->actingAs($registrar)->withSession([
            'auth.surface' => 'staff',
            'auth.session_version' => (int) $registrar->authenticationState->session_version,
        ])
            ->post(route('admissions.accept', $submission['attempt']), ['expected_version' => 1])
            ->assertRedirect();
    }

    $this->actingAs($this->actor)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => (int) $this->actor->authenticationState->session_version,
    ]);

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $arrangement = SeatingArrangement::factory()->create([
        'rows' => 5,
        'columns' => 6,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
    ]);
    $session = app(CreateExamSession::class)->handle(
        $cycle,
        $room,
        'EX-SECURE-MATERIAL',
        now()->addDay()->toDateTimeString(),
        now()->addDay()->addHours(2)->toDateTimeString(),
        $this->actor,
        ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        $booklet,
        $arrangement,
    );
    $firstAssignment = app(AssignApplicantToExamSession::class)->handle(
        $session,
        $firstSubmission['attempt']->fresh(),
        $this->actor,
    );
    $secondAssignment = app(AssignApplicantToExamSession::class)->handle(
        $session,
        $secondSubmission['attempt']->fresh(),
        $this->actor,
    );

    $sets = $booklet->sets->keyBy('set_code');
    $seatRows = [];
    $pattern = ['ABABAB', 'BABABA', 'ABABAB', 'BABABA', 'ABABAB'];
    foreach ($pattern as $rowIndex => $rowPattern) {
        foreach (str_split($rowPattern) as $columnIndex => $setCode) {
            $seatRows[] = [
                'ordinal' => ($rowIndex * 6) + $columnIndex + 1,
                'row_number' => $rowIndex + 1,
                'column_number' => $columnIndex + 1,
                'seat_label' => chr(65 + $rowIndex).($columnIndex + 1),
                'booklet_set_id' => $sets[$setCode]->id,
            ];
        }
    }
    $publishedAt = now();
    $institution = Institution::current();
    $seatManifestHash = hash('sha256', json_encode($seatRows, JSON_THROW_ON_ERROR));
    $materialHash = hash('sha256', json_encode([
        'fingerprint_version' => 2,
        'test_kit_version_id' => null,
        'manifest_sha256' => null,
        'acceptance_receipt_id' => null,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'booklet_content_hash' => $booklet->content_hash,
        'answer_key_hash' => $booklet->answer_key_hash,
        'sets' => [
            'A' => ['id' => $sets['A']->id, 'order_hash' => $sets['A']->order_hash, 'answer_key_hash' => $sets['A']->answer_key_hash],
            'B' => ['id' => $sets['B']->id, 'order_hash' => $sets['B']->order_hash, 'answer_key_hash' => $sets['B']->answer_key_hash],
        ],
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'capacity_limit' => $arrangement->desk_count,
        'required_set_codes' => ['A', 'B'],
        'policy_revision' => $institution->examFeaturePolicyVersion(),
        'seating_arrangement_id' => $arrangement->id,
        'arrangement_lock_version' => $arrangement->lock_version,
        'seat_manifest_hash' => $seatManifestHash,
    ], JSON_THROW_ON_ERROR));
    $snapshot = ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $session->id,
        'revision' => 1,
        'is_current' => true,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'test_instrument_version_id' => null,
        'test_kit_version_id' => null,
        'manifest_sha256' => null,
        'acceptance_receipt_id' => null,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'capacity_limit' => $arrangement->desk_count,
        'required_set_codes' => ['A', 'B'],
        'seating_arrangement_id' => $arrangement->id,
        'arrangement_lock_version' => $arrangement->lock_version,
        'arrangement_rows' => 5,
        'arrangement_columns' => 6,
        'arrangement_booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'seat_manifest_hash' => $seatManifestHash,
        'legacy_backfill_status' => ExamSessionMaterialSnapshot::LEGACY_BACKFILL_NOT_ATTEMPTED,
        'scoring_policy_identity' => 'grading.omr.session-seat.v2',
        'material_hash' => $materialHash,
        'published_by_user_id' => $this->actor->id,
        'published_at' => $publishedAt,
    ]);
    foreach (['A', 'B'] as $setCode) {
        $set = $sets[$setCode];
        $snapshot->sets()->create([
            'booklet_set_id' => $set->id,
            'set_code' => $setCode,
            'order_hash' => $set->order_hash,
            'answer_key_hash' => $set->answer_key_hash,
            'answer_key_json' => [1 => 'A', 2 => 'D'],
        ]);
    }
    foreach ($seatRows as $seatRow) {
        $snapshot->seats()->create($seatRow);
    }
    $session->update(['status' => ExamSession::STATUS_PUBLISHED]);
    foreach ([$firstAssignment, $secondAssignment] as $index => $assignment) {
        ExamAssignmentSeatAllocation::query()->create([
            'exam_assignment_id' => $assignment->id,
            'exam_session_seat_id' => $snapshot->seats()->where('ordinal', $index + 1)->value('id'),
            'effective_from' => $publishedAt,
            'reason' => 'Retained preexisting frozen advanced assignment',
            'assigned_by_user_id' => $this->actor->id,
        ]);
    }

    $before = [
        'snapshot' => $snapshot->fresh()->only([
            'id', 'revision', 'is_current', 'template_version_id', 'booklet_version_id',
            'test_instrument_version_id', 'test_kit_version_id', 'manifest_sha256',
            'acceptance_receipt_id', 'seating_arrangement_id', 'seating_mode',
            'capacity_limit', 'required_set_codes', 'arrangement_lock_version',
            'arrangement_rows', 'arrangement_columns', 'arrangement_booklet_pattern',
            'seat_manifest_hash', 'scoring_policy_identity', 'material_hash',
            'published_by_user_id', 'published_at',
        ]),
        'seats' => $snapshot->seats()->orderBy('ordinal')->get([
            'id', 'material_snapshot_id', 'ordinal', 'row_number', 'column_number', 'seat_label', 'booklet_set_id',
        ])->toArray(),
        'sets' => $snapshot->sets()->orderBy('set_code')->get([
            'set_code', 'booklet_set_id', 'order_hash', 'answer_key_hash', 'answer_key_json',
        ])->toArray(),
        'assignments' => ExamAssignmentSeatAllocation::query()
            ->whereIn('exam_assignment_id', [$firstAssignment->id, $secondAssignment->id])
            ->orderBy('exam_assignment_id')
            ->get(['id', 'exam_assignment_id', 'exam_session_seat_id', 'effective_from', 'effective_to'])
            ->toArray(),
    ];
    $institution->update(['enable_seating_arrangements' => false, 'enable_multi_set_booklets' => false]);
    $distribution = app(ExamBookletDistributionQuery::class)->handle($session, $this->actor, 1);

    expect($booklet->fresh()->sets()->count())->toBe(4)
        ->and($snapshot->fresh()->required_set_codes)->toBe(['A', 'B'])
        ->and($snapshot->seats()->count())->toBe(30)
        ->and($snapshot->test_kit_version_id)->toBeNull()
        ->and($snapshot->manifest_sha256)->toBeNull()
        ->and($snapshot->acceptance_receipt_id)->toBeNull()
        ->and($distribution['sets_summary'])->toBe(['A' => 1, 'B' => 1])
        ->and(collect($distribution['roster'])->pluck('set_code')->sort()->values()->all())->toBe(['A', 'B'])
        ->and($snapshot->fresh()->only(array_keys($before['snapshot'])))->toEqual($before['snapshot'])
        ->and($snapshot->seats()->orderBy('ordinal')->get([
            'id', 'material_snapshot_id', 'ordinal', 'row_number', 'column_number', 'seat_label', 'booklet_set_id',
        ])->toArray())->toBe($before['seats'])
        ->and($snapshot->sets()->orderBy('set_code')->get([
            'set_code', 'booklet_set_id', 'order_hash', 'answer_key_hash', 'answer_key_json',
        ])->toArray())->toBe($before['sets'])
        ->and(ExamAssignmentSeatAllocation::query()
            ->whereIn('exam_assignment_id', [$firstAssignment->id, $secondAssignment->id])
            ->orderBy('exam_assignment_id')
            ->get(['id', 'exam_assignment_id', 'exam_session_seat_id', 'effective_from', 'effective_to'])
            ->toArray())->toBe($before['assignments'])
        ->and(fn () => $snapshot->fresh()->update(['capacity_limit' => 1]))->toThrow(RuntimeException::class, 'immutable')
        ->and(fn () => $snapshot->fresh()->delete())->toThrow(RuntimeException::class, 'immutable');
});

it('allows a named draft without an Answer Sheet but blocks authoring', function (): void {
    $draft = app(CreateBookletDraft::class)->handle(
        'Waiting for Answer Sheet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        isSynthetic: true,
    );

    expect($draft->template_version_id)->toBeNull()
        ->and($draft->isDraft())->toBeTrue();

    expect(fn () => app(SaveBookletSetKeys::class)->handle(
        $draft,
        'A',
        [],
        $this->actor,
        $draft->lock_version,
        true,
    ))->toThrow(RuntimeException::class, 'Create intrinsic Booklet items before entering Set keys.');
});

it('copies a finalized stored-question Booklet only for protected-content authors', function (): void {
    enableSecureBookletAdvancedSetFixture();
    ['version' => $sheet, 'area' => $area] = secureBookletAnswerSheet();
    $booklet = app(CreateBookletDraft::class)->handle(
        'Stored copy source',
        OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS,
        $this->actor,
        $sheet,
    );
    $saved = app(SaveAuthoredBookletVersion::class)->handle($booklet, [
        [
            'aptitude_area_id' => $area->id,
            'authored_position' => 1,
            'correct_option_code' => 'A',
            'content' => secureQuestionContent('First'),
        ],
        [
            'aptitude_area_id' => $area->id,
            'authored_position' => 2,
            'correct_option_code' => 'D',
            'content' => secureQuestionContent('Second'),
        ],
    ], $this->actor, $booklet->lock_version, true);
    $saved = app(SetBookletSetsMode::class)->handle($saved, $this->actor, true, $saved->lock_version, false);
    $manager = User::factory()->create();
    $manager->givePermissionTo(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
    expect(fn () => app(GenerateBookletSets::class)->handle($saved, $manager, $saved->lock_version))
        ->toThrow(RuntimeException::class, 'Question content access is not authorized');

    $generated = app(GenerateBookletSets::class)->handle($saved, $this->actor, $saved->lock_version);
    expect(fn () => app(FinalizeBookletVersion::class)->handle($generated, $manager))
        ->toThrow(RuntimeException::class, 'Question content access is not authorized');
    $finalized = app(FinalizeBookletVersion::class)->handle($generated, $this->actor);
    expect(fn () => app(CopyBookletVersionToDraft::class)->handle($finalized, $manager))
        ->toThrow(RuntimeException::class, 'Question content access is not authorized');

    $copy = app(CopyBookletVersionToDraft::class)->handle($finalized, $this->actor);
    expect($copy->isDraft())->toBeTrue()
        ->and($copy->version)->toBe(2)
        ->and($copy->items()->count())->toBe(2)
        ->and($copy->sets()->count())->toBe(4)
        ->and($finalized->fresh()->isFinalized())->toBeTrue();
});

it('authors, saves, finalizes, and copies an intrinsic Booklet without sheet or profile inventory', function (): void {
    $booklet = app(CreateBookletDraft::class)->handle(
        'Independent Booklet',
        OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
        $this->actor,
        isSynthetic: true,
    );
    $sectionUid = (string) Str::uuid();
    $items = [
        [
            'booklet_item_uid' => (string) Str::uuid(),
            'booklet_section_uid' => $sectionUid,
            'section_name' => 'Verbal',
            'section_display_order' => 1,
            'section_expected_count' => 2,
            'option_tokens' => ['A-B', 'C-D'],
            'key_required' => true,
            'authored_position' => 1,
            'content' => ['prompt' => '', 'choices' => []],
        ],
        [
            'booklet_item_uid' => (string) Str::uuid(),
            'booklet_section_uid' => $sectionUid,
            'section_name' => 'Verbal',
            'section_display_order' => 1,
            'section_expected_count' => 2,
            'option_tokens' => ['A-B', 'C-D'],
            'key_required' => true,
            'authored_position' => 2,
            'content' => ['prompt' => '', 'choices' => []],
        ],
    ];
    $saved = app(SaveAuthoredBookletVersion::class)->handle($booklet, $items, $this->actor, $booklet->lock_version);
    expect($saved->template_version_id)->toBeNull()
        ->and($saved->test_instrument_version_id)->toBeNull()
        ->and($saved->items()->whereNull('aptitude_area_id')->count())->toBe(2)
        ->and($saved->items()->orderBy('authored_position')->get()->pluck('option_tokens')->all())
        ->toBe([['A-B', 'C-D'], ['A-B', 'C-D']]);

    $uidsBeforeEdit = $saved->items()->pluck('booklet_item_uid')->sort()->values()->all();
    $editedItems = $saved->items()->orderBy('authored_position')->get()->values()->map(
        fn ($item, int $index): array => [
            'id' => $item->id,
            'booklet_item_uid' => $item->booklet_item_uid,
            'booklet_section_uid' => $item->booklet_section_uid,
            'section_name' => 'Verbal renamed',
            'section_display_order' => 1,
            'section_expected_count' => 2,
            'option_tokens' => ['A-B', 'C-D'],
            'key_required' => true,
            'authored_position' => 2 - $index,
            'content' => [],
        ],
    )->all();
    $edited = app(SaveAuthoredBookletVersion::class)->handle($saved, $editedItems, $this->actor, $saved->lock_version);
    expect($edited->items()->pluck('booklet_item_uid')->sort()->values()->all())->toBe($uidsBeforeEdit)
        ->and($edited->sections()->firstOrFail()->name)->toBe('Verbal renamed');

    $generated = app(GenerateBookletSets::class)->handle($edited, $this->actor, $edited->lock_version);
    $set = $generated->sets()->where('set_code', 'A')->firstOrFail();
    $setItems = $set->items()->orderBy('global_position')->get();
    $currentItems = $generated->items()->get()->keyBy('id');
    expect($setItems->every(fn ($row): bool => $row->booklet_item_uid === $currentItems->get($row->booklet_item_id)?->booklet_item_uid
        && $row->booklet_section_uid === $currentItems->get($row->booklet_item_id)?->booklet_section_uid))->toBeTrue();
    $keys = $setItems->map(fn ($row): array => [
        'booklet_item_uid' => $row->booklet_item_uid,
        'booklet_section_uid' => $row->booklet_section_uid,
        'global_position' => $row->global_position,
        'selected_token' => $row->correct_option_code,
    ])->all();
    $keys[0]['selected_token'] = 'A-B';
    $keys[1]['selected_token'] = 'C-D';
    $savedKeys = app(SaveBookletSetKeys::class)->handle($generated, 'A', $keys, $this->actor, $generated->lock_version);
    $afterKeysVersion = $generated->fresh();
    $savedSetItems = $savedKeys->items()->orderBy('global_position')->get();
    expect($savedSetItems->every(fn ($row): bool => $row->booklet_item_uid === $currentItems->get($row->booklet_item_id)?->booklet_item_uid
        && $row->booklet_section_uid === $currentItems->get($row->booklet_item_id)?->booklet_section_uid))->toBeTrue();
    $beforeRows = DB::table('omr_booklet_set_items')
        ->where('booklet_set_id', $savedKeys->id)
        ->orderBy('global_position')
        ->get()
        ->toArray();
    $badKeys = $keys;
    $badKeys[0]['selected_token'] = 'A';
    expect(fn () => app(SaveBookletSetKeys::class)->handle($afterKeysVersion, 'A', $badKeys, $this->actor, $afterKeysVersion->lock_version))
        ->toThrow(RuntimeException::class, 'literal response domain');
    expect(DB::table('omr_booklet_set_items')->where('booklet_set_id', $savedKeys->id)->orderBy('global_position')->get()->toArray())
        ->toEqual($beforeRows);
    expect(fn () => app(SaveBookletSetKeys::class)->handle($afterKeysVersion, 'A', $keys, $this->actor, $afterKeysVersion->lock_version - 1))
        ->toThrow(RuntimeException::class, 'changed elsewhere');
    expect(DB::table('omr_booklet_set_items')->where('booklet_set_id', $savedKeys->id)->orderBy('global_position')->get()->toArray())
        ->toEqual($beforeRows);

    $finalized = app(FinalizeBookletVersion::class)->handle($afterKeysVersion, $this->actor, $afterKeysVersion->lock_version);
    $copy = app(CopyBookletVersionToDraft::class)->handle($finalized, $this->actor);
    expect($finalized->isFinalized())->toBeTrue()
        ->and($finalized->fresh()->template_version_id)->toBeNull()
        ->and($copy->isDraft())->toBeTrue()
        ->and($copy->template_version_id)->toBeNull()
        ->and($copy->items()->orderBy('authored_position')->pluck('booklet_item_uid')->all())
        ->toBe($finalized->items()->orderBy('authored_position')->pluck('booklet_item_uid')->all())
        ->and($copy->sections()->firstOrFail()->booklet_section_uid)->toBe($sectionUid);
});
