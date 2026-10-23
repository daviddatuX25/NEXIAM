<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Support\ExamSessionPresenter;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use Database\Seeders\CapabilityCatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\InstitutionProfileSeeder;
use Database\Seeders\ReferenceCatalogSeeder;
use Database\Seeders\ResultSheetTemplateSeeder;
use Database\Seeders\RoomSeatingProfileSeeder;
use Database\Seeders\StaffIdentitySeeder;
use Database\Seeders\StaffPlatformOverhaulDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/../../Support/TestKitHelpers.php';
require_once __DIR__.'/../../Support/NormConversionFixtures.php';
uses(RefreshDatabase::class);

/** @return array{kit: TestKit, version: TestKitVersion, profile: TestInstrumentVersion, sheet: OmrAnswerSheetTemplateVersion, booklet: OmrQuestionBookletVersion} */
function examSessionCandidateMaterials(User $actor, Institution $institution, string $sheetStatus = 'compiled'): array
{
    $suffix = Str::upper(Str::random(10));
    $instrument = TestInstrument::query()->forceCreate([
        'institution_id' => $institution->id, 'code' => 'SESSION-PROFILE-'.$suffix, 'is_synthetic' => true,
    ]);
    $profile = TestInstrumentVersion::query()->create([
        'test_instrument_id' => $instrument->id, 'version_number' => 1,
        'display_name' => 'Synthetic session profile', 'status' => 'approved',
        'material_requirement' => 'paper_backed', 'administration_procedure_reference' => 'Synthetic session fixture only',
        'content_sha256' => hash('sha256', 'profile-'.$suffix), 'created_by_user_id' => $actor->id,
        'publisher_name' => 'Synthetic fixture', 'form_code' => 'SYN-'.$suffix, 'edition' => 'Synthetic', 'language' => 'en',
        'source_reference' => 'Synthetic session-boundary fixture only',
        'permitted_use_reference' => 'Tests only',
        'source_reviewed_by_user_id' => $actor->id, 'source_reviewed_at' => now(),
        'approved_by_user_id' => $actor->id, 'approved_at' => now(),
    ]);
    $template = OmrAnswerSheetTemplate::factory()->create([
        'institution_id' => $institution->id, 'is_synthetic' => true,
    ]);
    $sheet = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $template->id, 'status' => $sheetStatus,
        'test_instrument_version_id' => $profile->id,
        'native_bundle_json' => ['fixture' => 'synthetic-session-selection-only'],
        'native_bundle_hash' => hash('sha256', 'native-'.$suffix),
    ]);
    $bookletFamily = OmrQuestionBooklet::query()->create([
        'institution_id' => $institution->id, 'code' => 'SESSION-BOOKLET-'.$suffix,
        'name' => 'Synthetic independent session booklet', 'is_synthetic' => true,
    ]);
    $booklet = OmrQuestionBookletVersion::query()->create([
        'booklet_id' => $bookletFamily->id,
        'template_version_id' => DB::getDriverName() === 'sqlite' ? $sheet->id : null,
        'test_instrument_version_id' => $profile->id,
        'version' => 1, 'status' => 'draft', 'authoring_mode' => 'answer_keys_only',
        'allow_multiple_sets' => false, 'lock_version' => 1,
        'content_hash' => hash('sha256', 'content-'.$suffix), 'answer_key_hash' => hash('sha256', 'keys-'.$suffix),
    ]);
    $set = OmrBookletSet::query()->create([
        'booklet_version_id' => $booklet->id, 'set_code' => 'A', 'status' => 'finalized',
        'generation_version' => 'synthetic-session-fixture',
        'order_hash' => hash('sha256', 'order-'.$suffix), 'answer_key_hash' => hash('sha256', 'keys-'.$suffix),
        'finalized_at' => now(),
    ]);
    $booklet->update(['status' => 'finalized', 'finalized_at' => now(), 'finalized_by_user_id' => $actor->id]);
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(), 'institution_id' => $institution->id,
        'code' => 'SESSION-KIT-'.$suffix, 'name' => 'Synthetic draft session kit', 'is_synthetic' => true,
        'created_by_user_id' => $actor->id,
    ]);
    $version = TestKitVersion::query()->create([
        'test_kit_id' => $kit->id, 'institution_id' => $institution->id, 'is_synthetic' => true,
        'version_number' => 1, 'template_id' => $template->id, 'template_version_id' => $sheet->id,
        'booklet_id' => $bookletFamily->id, 'booklet_version_id' => $booklet->id,
        'booklet_set_id' => $set->id, 'booklet_set_code' => 'A',
        'booklet_set_order_hash' => $set->order_hash, 'booklet_set_answer_key_hash' => $set->answer_key_hash,
        'test_instrument_id' => $instrument->id, 'test_instrument_version_id' => $profile->id,
        'manifest_schema_version' => 1, 'canonicalizer_version' => 'synthetic-draft-session',
        'manifest_json' => ['institution_id' => (int) $institution->id, 'kit' => ['provenance_class' => 'synthetic']],
        'manifest_sha256' => hash('sha256', 'manifest-'.$suffix),
        'created_by_user_id' => $actor->id,
    ]);

    return compact('kit', 'version', 'profile', 'sheet', 'booklet');
}

beforeEach(function (): void {
    app(DatabaseSeeder::class)->run([
        ReferenceCatalogSeeder::class,
        InstitutionProfileSeeder::class,
        RoomSeatingProfileSeeder::class,
        CapabilityCatalogSeeder::class,
        StaffIdentitySeeder::class,
        DepartmentSeeder::class,
        StaffPlatformOverhaulDemoSeeder::class,
        ResultSheetTemplateSeeder::class,
    ]);

    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE, 'web');
    Permission::findOrCreate(CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT, 'web');
    $this->actor->givePermissionTo([
        CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
        CapabilityCatalog::GUIDANCE_BOOKLET_CONTENT,
    ]);
    $this->cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $this->room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $this->materials = examSessionCandidateMaterials($this->actor, Institution::current());
});

function examSessionKitRequest(ExamSession $session, array $overrides = []): array
{
    $startsAt = now()->addDays(31)->startOfDay()->addHours(8);

    return array_replace([
        'academic_cycle_id' => $session->academic_cycle_id,
        'room_id' => $session->room_id,
        'code' => $session->code,
        'starts_at' => $startsAt->toDateTimeString(),
        'effective_end_at' => $startsAt->copy()->addHours(2)->toDateTimeString(),
        'expected_version' => $session->version,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'seating_arrangement_id' => null,
    ], $overrides);
}
function examSessionKitDraft(string $code): ExamSession
{
    return ExamSession::query()->create([
        'academic_cycle_id' => AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->value('id'),
        'room_id' => Room::query()->where('code', 'RM-101')->value('id'),
        'code' => $code,
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(30)->startOfDay()->addHours(8),
        'effective_end_at' => now()->addDays(30)->startOfDay()->addHours(10),
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'version' => 1,
    ]);
}

test('Setup-only Kit intent creates and updates sessions without paper materials while no-Kit drafts remain supported', function (): void {
    $profile = createTestApprovedProfileVersion($this, $this->actor, 'SESSION-NO-PAPER-'.Str::upper(Str::random(8)));
    $setupOnly = testKitSetupOnlyFixture($this->actor, $profile);
    $createdDraft = examSessionKitDraft('SETUP-ONLY-CREATE-'.uniqid());

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), examSessionKitRequest($createdDraft, [
            'code' => 'SETUP-ONLY-CREATE-'.uniqid(),
            'test_kit_version_id' => $setupOnly['version']->id,
            'starts_at' => now()->addDays(31)->startOfDay()->addHours(8)->toDateTimeString(),
            'effective_end_at' => now()->addDays(31)->startOfDay()->addHours(10)->toDateTimeString(),
        ]))
        ->assertSessionHasNoErrors();

    $created = ExamSession::query()->where('test_kit_version_id', $setupOnly['version']->id)->firstOrFail();
    expect($created->test_kit_version_id)->toBe($setupOnly['version']->id)
        ->and($created->test_instrument_version_id)->toBe($profile->id)
        ->and($created->booklet_version_id)->toBeNull();

    $updatedDraft = examSessionKitDraft('SETUP-ONLY-UPDATE-'.uniqid());
    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $updatedDraft), examSessionKitRequest($updatedDraft, [
            'test_kit_version_id' => $setupOnly['version']->id,
            'starts_at' => now()->addDays(33)->startOfDay()->addHours(8)->toDateTimeString(),
            'effective_end_at' => now()->addDays(33)->startOfDay()->addHours(10)->toDateTimeString(),
        ]))
        ->assertSessionHasNoErrors();

    expect($updatedDraft->fresh()->test_kit_version_id)->toBe($setupOnly['version']->id)
        ->and($updatedDraft->fresh()->test_instrument_version_id)->toBe($profile->id)
        ->and($updatedDraft->fresh()->booklet_version_id)->toBeNull();

    $noKitDraft = examSessionKitDraft('NO-KIT-UPDATE-'.uniqid());
    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $noKitDraft), examSessionKitRequest($noKitDraft, [
            'starts_at' => now()->addDays(35)->startOfDay()->addHours(8)->toDateTimeString(),
            'effective_end_at' => now()->addDays(35)->startOfDay()->addHours(10)->toDateTimeString(),
        ]))
        ->assertSessionHasNoErrors();

    expect($noKitDraft->fresh()->test_kit_version_id)->toBeNull()
        ->and($noKitDraft->fresh()->test_instrument_version_id)->toBeNull()
        ->and($noKitDraft->fresh()->booklet_version_id)->toBeNull();
});
test('HTTP session kit intent persists the exact Booklet and profile without Booklet sheet ownership', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Independent Booklet component FKs require the production PostgreSQL schema; SQLite retains the predecessor composite material FK.');
    }
    expect($this->materials['booklet']->template_version_id)->toBeNull();

    $session = examSessionKitDraft('KIT-DERIVED-'.uniqid());
    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), examSessionKitRequest($session, [
            'code' => 'KIT-DERIVED-'.uniqid(),
            'test_kit_version_id' => $this->materials['version']->id,
        ]))
        ->assertSessionHasNoErrors();

    $created = ExamSession::query()->where('test_kit_version_id', $this->materials['version']->id)->firstOrFail();
    expect($created->booklet_version_id)->toBe($this->materials['booklet']->id)
        ->and($created->test_instrument_version_id)->toBe($this->materials['profile']->id)
        ->and($created->test_kit_version_id)->toBe($this->materials['version']->id);
    $display = app(ExamSessionPresenter::class)->detailPayload($created);
    expect($display['material']['booklet']['answer_sheet'])->toBe(
        $this->materials['sheet']->template->name.' v'.$this->materials['sheet']->version,
    );

    $application = submitSyntheticApplicant('session-kit-'.uniqid().'@example.test', 'Kit'.uniqid());
    $acceptedAttempt = app(AcceptAdmissionAttempt::class)->handle($application['attempt'], $this->actor, 1);
    app(AssignApplicantToExamSession::class)->handle($created, $acceptedAttempt, $this->actor);
    expect($preflight['ok'])->toBeTrue()
        ->and($preflight['reason'])->toBeNull()
        ->and($preflight['roster_material_fingerprint'])->toBeString()->not->toBeEmpty()
        ->and($preflight['checks'])->not->toContain('materials_selected');
});

test('draft sessions can save and clear a kit without retaining a separately submitted Booklet', function (): void {
    $empty = examSessionKitDraft('KIT-NULL-'.uniqid());
    $createPayload = examSessionKitRequest($empty, [
        'code' => 'KIT-NULL-CREATE-'.uniqid(),
    ]);
    unset($createPayload['seating_mode']);
    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), $createPayload)
        ->assertSessionHasNoErrors();

    $created = ExamSession::query()->where('code', 'like', 'KIT-NULL-CREATE-%')->firstOrFail();
    expect($created->test_kit_version_id)->toBeNull()
        ->and($created->booklet_version_id)->toBeNull()
        ->and($created->test_instrument_version_id)->toBeNull();

    $pinned = examSessionKitDraft('KIT-CLEAR-'.uniqid());
    $pinned->update([
        'test_kit_version_id' => $this->materials['version']->id,
        'booklet_version_id' => $this->materials['booklet']->id,
        'test_instrument_version_id' => $this->materials['profile']->id,
    ]);
    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $pinned), examSessionKitRequest($pinned, [
            'test_kit_version_id' => null,
            'expected_version' => $pinned->version,
            'starts_at' => now()->addDays(32)->startOfDay()->addHours(8)->toDateTimeString(),
            'effective_end_at' => now()->addDays(32)->startOfDay()->addHours(10)->toDateTimeString(),
        ]))
        ->assertSessionHasNoErrors();

    expect($pinned->fresh()->test_kit_version_id)->toBeNull()
        ->and($pinned->fresh()->booklet_version_id)->toBeNull()
        ->and($pinned->fresh()->test_instrument_version_id)->toBeNull();
});

test('unavailable and foreign Test Kits are rejected without writing a session', function (): void {
    $sessionCount = ExamSession::query()->count();
    $payload = [
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'KIT-INVALID-'.uniqid(),
        'starts_at' => now()->addDays(40)->startOfDay()->addHours(8)->toDateTimeString(),
        'effective_end_at' => now()->addDays(40)->startOfDay()->addHours(10)->toDateTimeString(),
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    ];

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), array_replace($payload, ['test_kit_version_id' => 999999999]))
        ->assertSessionHasErrors('test_kit_version_id');
    expect(ExamSession::query()->count())->toBe($sessionCount);

    $foreignInstitution = Institution::query()->create([
        'code' => 'KIT-FOREIGN-'.uniqid(),
        'name' => 'Foreign test institution',
    ]);
    $foreignMaterials = examSessionCandidateMaterials($this->actor, $foreignInstitution);
    $foreignVersion = $foreignMaterials['version'];
    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), array_replace($payload, [
            'test_kit_version_id' => $foreignVersion->id,
        ]))
        ->assertSessionHasErrors('schedule');

    expect(ExamSession::query()->count())->toBe($sessionCount);
});

test('retired Test Kit versions cannot be newly selected in a draft', function (): void {
    $session = examSessionKitDraft('KIT-RETIRED-'.uniqid());
    $this->materials['version']->update([
        'status' => TestKitVersion::STATUS_RETIRED,
        'retired_at' => now(),
        'retired_by_user_id' => $this->actor->id,
    ]);
    $originalVersion = $session->fresh()->version;

    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $session), examSessionKitRequest($session, [
            'test_kit_version_id' => $this->materials['version']->id,
        ]))
        ->assertSessionHasErrors('schedule');

    expect($session->fresh()->version)->toBe($originalVersion)
        ->and($session->fresh()->test_kit_version_id)->toBeNull();
});

test('session create and edit expose candidate kit choices without booklet catalogs', function (): void {
    $candidate = $this->materials['version'];
    $this->actingAs($this->actor)
        ->get(route('exams.sessions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('test_kits', 1)
            ->missing('booklets')
            ->where('test_kits.0.status', TestKitVersion::STATUS_CANDIDATE));

    $session = examSessionKitDraft('KIT-EDIT-'.uniqid());
    $session->update([
        'test_kit_version_id' => $candidate->id,
        'booklet_version_id' => $this->materials['booklet']->id,
        'test_instrument_version_id' => $this->materials['profile']->id,
    ]);
    $this->actingAs($this->actor)
        ->get(route('exams.sessions.edit', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('test_kits')
            ->missing('booklets'));
});

test('assigned seating remains independent of optional kit selection', function (): void {
    Permission::findOrCreate(CapabilityCatalog::SETUP_INSTITUTION_MANAGE, 'web');
    $settingsAuthority = User::factory()->create();
    $settingsAuthority->staffProfile()->create([
        'institution_id' => Institution::current()->id,
        'office' => 'registrar',
        'active_cycle_code' => $this->cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $settingsAuthority->givePermissionTo(CapabilityCatalog::SETUP_INSTITUTION_MANAGE);
    app(UpdateInstitutionExamFeaturePolicy::class)->updateSeatingAvailability(
        $settingsAuthority,
        true,
        Institution::current()->examFeaturePolicyVersion(),
    );
    $arrangement = SeatingArrangement::query()->where('normalized_name', 'main examination layout')->firstOrFail();
    $draft = examSessionKitDraft('KIT-SEATING-'.uniqid());

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), examSessionKitRequest($draft, [
            'code' => 'KIT-SEATING-CREATE-'.uniqid(),
            'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
            'seating_arrangement_id' => $arrangement->id,
        ]))
        ->assertSessionHasNoErrors();

    $created = ExamSession::query()->where('code', 'like', 'KIT-SEATING-CREATE-%')->firstOrFail();
    expect($created->seating_mode)->toBe(ExamSession::SEATING_MODE_ASSIGNED_SEATING)
        ->and($created->seating_arrangement_id)->toBe($arrangement->id)
        ->and($created->test_kit_version_id)->toBeNull()
        ->and($created->booklet_version_id)->toBeNull();
});

test('HTTP session writes reject an independent Booklet selection without changing draft state', function (): void {
    $draft = examSessionKitDraft('KIT-STANDALONE-'.uniqid());
    $beforeCount = ExamSession::query()->count();
    $payload = examSessionKitRequest($draft, [
        'test_kit_version_id' => $this->materials['version']->id,
        'booklet_version_id' => $this->materials['booklet']->id,
    ]);

    $this->actingAs($this->actor)
        ->post(route('exams.sessions.store'), $payload)
        ->assertSessionHasErrors('booklet_version_id');
    expect(ExamSession::query()->count())->toBe($beforeCount);

    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $draft), $payload)
        ->assertSessionHasErrors('booklet_version_id');
    expect($draft->fresh()->version)->toBe(1)
        ->and($draft->fresh()->test_kit_version_id)->toBeNull()
        ->and($draft->fresh()->booklet_version_id)->toBeNull();
});
test('test kit with active answer sheet template version can be attached to session', function (): void {
    $materials = examSessionCandidateMaterials($this->actor, Institution::current(), OmrAnswerSheetTemplateVersion::STATUS_ACTIVE);
    $draft = examSessionKitDraft('KIT-ACTIVE-SHEET-'.uniqid());

    $this->actingAs($this->actor)
        ->put(route('exams.sessions.update', $draft), examSessionKitRequest($draft, [
            'test_kit_version_id' => $materials['version']->id,
        ]))
        ->assertSessionHasNoErrors();

    expect($draft->fresh()->test_kit_version_id)->toBe($materials['version']->id)
        ->and($draft->fresh()->booklet_version_id)->toBe($materials['booklet']->id);
});
