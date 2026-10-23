<?php

use App\Authorization\CapabilityCatalog;
use App\Consultation\Queries\ApplicantConsultationCalendar;
use App\Examination\Queries\ApplicantSlotCalendar;
use App\Foundation\Actions\SaveSeatingArrangement;
use App\Foundation\Actions\SetRoomDefaultSeatingArrangement;
use App\Foundation\Actions\UpdateRoom;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Foundation\SpaceKind;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function setupStaffActor(array $capabilities = [CapabilityCatalog::SETUP_INSTITUTION_MANAGE, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG]): User
{
    Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution'],
    );

    $actor = User::factory()->create();
    foreach ($capabilities as $cap) {
        Permission::findOrCreate($cap, 'web');
        $actor->givePermissionTo($cap);
    }

    return $actor;
}

test('creates rooms with different space kinds', function (): void {
    $institution = Institution::query()->create([
        'code' => 'TEST-INST',
        'name' => 'Test Institution',
    ]);

    $examRoom = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'ITBR-106',
        'name' => 'ITBR 106 (Computer Lab)',
        'space_kind' => SpaceKind::Room,
        'capacity' => 40,
        'is_active' => true,
    ]);

    $venue = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CC-01',
        'name' => 'Covered Court',
        'space_kind' => SpaceKind::AssemblyVenue,
        'capacity' => 150,
        'is_active' => true,
    ]);

    $counter = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'WIN-01',
        'name' => 'Registrar Window 1',
        'space_kind' => SpaceKind::ServicePoint,
        'capacity' => 1,
        'is_active' => true,
    ]);

    $office = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'DEAN-01',
        'name' => 'Dean Office',
        'space_kind' => SpaceKind::Office,
        'capacity' => 10,
        'is_active' => true,
    ]);

    expect($examRoom->isExamRoom())->toBeTrue()
        ->and($examRoom->allowsSeatingArrangement())->toBeTrue()
        ->and($venue->isExamRoom())->toBeFalse()
        ->and($venue->allowsSeatingArrangement())->toBeFalse()
        ->and($counter->isExamRoom())->toBeFalse()
        ->and($counter->allowsSeatingArrangement())->toBeFalse()
        ->and($office->isExamRoom())->toBeFalse()
        ->and($office->allowsSeatingArrangement())->toBeFalse()
        ->and($office->space_kind->label())->toBe('Office / Other Room')
        ->and(SpaceKind::values())->toBe(['room', 'assembly_venue', 'service_point', 'office']);

    $catalog = SpaceKind::catalog();
    expect($catalog)->toHaveCount(4);

    $counts = SpaceKind::countsForInstitution((int) $institution->id);
    expect($counts)->toMatchArray([
        'all' => 4,
        'room' => 1,
        'assembly_venue' => 1,
        'service_point' => 1,
        'office' => 1,
    ]);
});

test('rejects seating arrangement assignment to assembly venues or service points', function (): void {
    $institution = Institution::query()->create([
        'code' => 'TEST-INST',
        'name' => 'Test Institution',
    ]);
    $venue = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CC-01',
        'name' => 'Covered Court',
        'space_kind' => SpaceKind::AssemblyVenue,
        'capacity' => 150,
        'is_active' => true,
    ]);
    $actor = setupStaffActor();

    expect(fn () => app(SaveSeatingArrangement::class)->handle(null, [
        'name' => 'Covered Court Grid',
        'rows' => 5,
        'columns' => 6,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
        'room_ids' => [$venue->id],
        'is_active' => true,
    ], $actor))->toThrow(ValidationException::class);
});

test('rejects setting default seating arrangement on assembly venues or service points', function (): void {
    $institution = Institution::query()->create([
        'code' => 'TEST-INST',
        'name' => 'Test Institution',
    ]);
    $examRoom = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-101',
        'name' => 'Room 101',
        'space_kind' => SpaceKind::Room,
        'capacity' => 40,
        'is_active' => true,
    ]);
    $venue = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CC-01',
        'name' => 'Covered Court',
        'space_kind' => SpaceKind::AssemblyVenue,
        'capacity' => 150,
        'is_active' => true,
    ]);
    $actor = setupStaffActor();

    $arrangement = app(SaveSeatingArrangement::class)->handle(null, [
        'name' => 'Exam Layout',
        'rows' => 5,
        'columns' => 6,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
    ], $actor);

    // Allowed on exam room
    $updated = app(SetRoomDefaultSeatingArrangement::class)->handle($examRoom, $arrangement, $actor);
    expect($updated->default_seating_arrangement_id)->toBe($arrangement->id);

    // Rejected on assembly venue
    expect(fn () => app(SetRoomDefaultSeatingArrangement::class)->handle($venue, $arrangement, $actor))
        ->toThrow(ValidationException::class);
});

test('switching room kind from room to assembly venue clears default seating arrangement', function (): void {
    $institution = Institution::query()->create([
        'code' => 'TEST-INST',
        'name' => 'Test Institution',
    ]);
    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-101',
        'name' => 'Multi-purpose Hall',
        'space_kind' => SpaceKind::Room,
        'capacity' => 50,
        'is_active' => true,
    ]);
    $actor = setupStaffActor();

    $arrangement = app(SaveSeatingArrangement::class)->handle(null, [
        'name' => 'Hall Layout',
        'rows' => 5,
        'columns' => 6,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
    ], $actor);

    app(SetRoomDefaultSeatingArrangement::class)->handle($room, $arrangement, $actor);
    expect($room->fresh()->default_seating_arrangement_id)->toBe($arrangement->id);

    // Update to assembly venue
    $updated = app(UpdateRoom::class)->handle($room, [
        'name' => 'Multi-purpose Hall',
        'space_kind' => SpaceKind::AssemblyVenue,
        'capacity' => 150,
        'is_active' => true,
    ], $actor);
    expect($updated->space_kind)->toBe(SpaceKind::AssemblyVenue)
        ->and($updated->default_seating_arrangement_id)->toBeNull();
});

test('readiness checks evaluate exam rooms only and include document requirements', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic NEXIAM Institution'],
    );

    // Create an exam room with a default seating arrangement
    $examRoom = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-101',
        'name' => 'Room 101',
        'space_kind' => SpaceKind::Room,
        'capacity' => 40,
        'is_active' => true,
    ]);
    $actor = setupStaffActor();
    $arrangement = app(SaveSeatingArrangement::class)->handle(null, [
        'name' => 'Readiness Layout',
        'rows' => 5,
        'columns' => 6,
        'booklet_pattern' => SeatingArrangement::PATTERN_CHECKERBOARD,
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
        'room_ids' => [],
        'is_active' => true,
    ], $actor);
    app(SetRoomDefaultSeatingArrangement::class)->handle($examRoom, $arrangement, $actor);

    // Create an assembly venue without a seating arrangement
    Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CC-01',
        'name' => 'Covered Court',
        'space_kind' => SpaceKind::AssemblyVenue,
        'capacity' => 300,
        'is_active' => true,
    ]);

    // Create requirements
    IntakeRequirement::query()->create([
        'institution_id' => $institution->id,
        'code' => 'form_138',
        'label' => 'Report Card Form 138',
        'applicant_track' => IntakeRequirement::TRACK_FRESHMAN,
        'item_kind' => IntakeRequirement::KIND_DOCUMENT,
        'is_mandatory' => true,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();
    $roomsCategory = collect($readiness['groups'])
        ->flatMap(fn ($g) => $g['categories'])
        ->firstWhere('key', 'rooms');

    expect($roomsCategory)->not->toBeNull();
    // Seating arrangement check passed because 1 active exam room has default arrangement
    $seatingCheck = collect($roomsCategory['checks'])->first(fn ($c) => str_contains($c['label'], 'seating arrangement'));
    expect($seatingCheck['passed'])->toBeTrue();

    // Check requirements category exists and passes
    $requirementsCategory = collect($readiness['groups'])
        ->flatMap(fn ($g) => $g['categories'])
        ->firstWhere('key', 'requirements');
    expect($requirementsCategory)->not->toBeNull();
    expect(collect($requirementsCategory['checks'])->every(fn ($c) => $c['passed']))->toBeTrue();
});

test('manages intake requirements via http endpoints', function (): void {
    $actor = setupStaffActor();
    $this->actingAs($actor);

    $response = $this->get(route('setup.requirements'));
    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/Requirements/Index')
            ->has('requirements.data')
            ->has('filters')
            ->has('capabilities')
        );

    $postResponse = $this->post(route('setup.requirements.store'), [
        'code' => 'psa_birth',
        'label' => 'PSA Birth Certificate',
        'applicant_track' => 'freshman',
        'item_kind' => 'document',
        'is_mandatory' => true,
        'sort_order' => 20,
    ]);
    $postResponse->assertRedirect();

    $req = IntakeRequirement::query()->where('code', 'psa_birth')->first();
    expect($req)->not->toBeNull()
        ->and($req->label)->toBe('PSA Birth Certificate')
        ->and($req->is_mandatory)->toBeTrue()
        ->and($req->is_active)->toBeTrue();

    // Deactivate requirement
    $patchResponse = $this->patch(route('setup.requirements.toggle-active', $req), [
        'is_active' => false,
        'expected_version' => (int) $req->version,
    ]);
    $patchResponse->assertRedirect();
    expect($req->fresh()->is_active)->toBeFalse();
});

test('new institutions default advanced exam features off and scoped versioned settings update independently', function (): void {
    $actor = setupStaffActor([
        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
    ]);
    $institution = Institution::current();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'mfa_required' => false,
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $this->actingAs($actor);

    expect($institution->usesSeatingArrangements())->toBeFalse()
        ->and($institution->usesMultiSetBooklets())->toBeFalse()
        ->and($institution->examFeaturePolicyVersion())->toBe(1);

    $this->get(route('setup.rooms'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Rooms/Index')
            ->where('enable_seating_arrangements', false)
            ->where('exam_feature_policy_version', 1));

    $this->get(route('setup.exam-materials.booklets.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/BookletsIndex')
            ->where('enable_multi_set_booklets', false)
            ->where('exam_feature_policy_version', 1));

    Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-SIMPLE-1',
        'name' => 'Simple Room 1',
        'space_kind' => SpaceKind::Room,
        'capacity' => 50,
        'is_active' => true,
    ]);

    $readinessBefore = app(ComputeSetupReadinessQuery::class)->handle();
    $roomsBefore = collect($readinessBefore['groups'])
        ->flatMap(fn ($group) => $group['categories'])
        ->firstWhere('key', 'rooms');
    expect(collect($roomsBefore['checks'])->every(fn ($check) => $check['passed']))->toBeTrue();

    $this->post(route('setup.rooms.seating-policy'), [
        'enable_seating_arrangements' => true,
        'expected_version' => 1,
    ])->assertRedirect();

    $institution->refresh();

    expect($institution->usesSeatingArrangements())->toBeTrue()
        ->and($institution->usesMultiSetBooklets())->toBeFalse()
        ->and($institution->examFeaturePolicyVersion())->toBe(2);

    $this->post(route('setup.exam-materials.booklets.set-policy'), [
        'enable_multi_set_booklets' => true,
        'expected_version' => 2,
    ])->assertRedirect();

    $institution->refresh();
    expect($institution->usesSeatingArrangements())->toBeTrue()
        ->and($institution->usesMultiSetBooklets())->toBeTrue()
        ->and($institution->examFeaturePolicyVersion())->toBe(3);

    $this->post(route('setup.exam-materials.booklets.set-policy'), [
        'enable_multi_set_booklets' => true,
        'expected_version' => 3,
    ])->assertRedirect();

    expect($institution->fresh()->examFeaturePolicyVersion())->toBe(3)
        ->and(AuditEvent::query()->where('event_type', 'setup.exam_feature_policy_updated')->count())->toBe(2);

    $this->post(route('setup.rooms.seating-policy'), [
        'enable_seating_arrangements' => false,
        'expected_version' => 2,
    ])->assertSessionHasErrors('policy');

    expect($institution->fresh()->usesSeatingArrangements())->toBeTrue()
        ->and($institution->fresh()->usesMultiSetBooklets())->toBeTrue()
        ->and($institution->fresh()->examFeaturePolicyVersion())->toBe(3)
        ->and(AuditEvent::query()->where('event_type', 'setup.exam_feature_policy_updated')->count())->toBe(2);

    $this->post(route('setup.rooms.seating-policy'), [
        'enable_seating_arrangements' => false,
        'expected_version' => 3,
    ])->assertRedirect();

    $institution->refresh();
    expect($institution->usesSeatingArrangements())->toBeFalse()
        ->and($institution->usesMultiSetBooklets())->toBeTrue()
        ->and($institution->examFeaturePolicyVersion())->toBe(4);

    $this->post(route('setup.rooms.seating-policy'), [
        'enable_seating_arrangements' => 'sometimes',
        'expected_version' => 4,
    ])->assertSessionHasErrors('enable_seating_arrangements');

    expect($institution->fresh()->usesSeatingArrangements())->toBeFalse()
        ->and($institution->fresh()->examFeaturePolicyVersion())->toBe(4)
        ->and(AuditEvent::query()->where('event_type', 'setup.exam_feature_policy_updated')->count())->toBe(3);

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)
        ->post(route('setup.exam-materials.booklets.set-policy'), [
            'enable_multi_set_booklets' => false,
            'expected_version' => 4,
        ])
        ->assertForbidden();

    expect($institution->fresh()->usesSeatingArrangements())->toBeFalse()
        ->and($institution->fresh()->usesMultiSetBooklets())->toBeTrue()
        ->and($institution->fresh()->examFeaturePolicyVersion())->toBe(4)
        ->and(AuditEvent::query()->where('event_type', 'setup.exam_feature_policy_updated')->count())->toBe(3);

    $readinessAfter = app(ComputeSetupReadinessQuery::class)->handle();
    $roomsAfter = collect($readinessAfter['groups'])
        ->flatMap(fn ($group) => $group['categories'])
        ->firstWhere('key', 'rooms');
    expect(collect($roomsAfter['checks'])->every(fn ($check) => $check['passed']))->toBeTrue();
});

test('exam feature availability changes preserve exam and consultation reservation state and policy', function (): void {
    seedE2Foundation();
    $actor = setupStaffActor([
        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
        CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
    ]);
    $institution = Institution::current();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);

    StaffAuthenticationState::query()->create([
        'user_id' => $actor->id,
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'session_version' => 1,
        'mfa_required' => false,
        'two_factor_generation' => (string) Str::uuid(),
    ]);
    $institution->forceFill([
        'enable_seating_arrangements' => false,
        'enable_multi_set_booklets' => false,
    ])->save();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $attempt = submitSyntheticApplicant('setup-booking-isolation@example.test', 'Isolation')['attempt'];
    $exam = ExamSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
        'reservation_opened_at' => now()->subHours(2),
        'reservation_closed_at' => null,
        'starts_at' => now()->addDays(10)->startOfHour(),
    ]);
    $consultation = ConsultationSession::factory()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
        'reservation_opened_at' => now()->subHour(),
        'reservation_closed_at' => null,
        'scheduled_at' => now()->addDays(12)->startOfHour(),
    ]);
    $policy = ConsultationPolicy::query()->create([
        'institution_id' => $institution->id,
        'academic_cycle_id' => $cycle->id,
        'normal_method' => ConsultationPolicy::METHOD_F2F,
        'allow_online_exceptions' => false,
        'configured_by_user_id' => $actor->id,
        'version' => 4,
    ]);
    $bookingColumns = ['booking_mode', 'reservation_opened_at', 'reservation_closed_at'];
    $examBooking = $exam->fresh()->only($bookingColumns);
    $consultationBooking = $consultation->fresh()->only($bookingColumns);
    $policyBefore = $policy->fresh()->getRawOriginal();
    $examCalendar = app(ApplicantSlotCalendar::class);
    $consultationCalendar = app(ApplicantConsultationCalendar::class);
    $examSlots = $examCalendar->flatSlots($attempt);
    $consultationSlots = $consultationCalendar->openSessions($attempt)
        ->map(fn (ConsultationSession $session): array => $consultationCalendar->slotPayload($session))
        ->all();

    expect(array_column($examSlots, 'id'))->toBe([$exam->id])
        ->and($examSlots[0]['remaining'])->toBe((int) $room->capacity)
        ->and($examSlots[0]['full'])->toBeFalse()
        ->and(array_column($consultationSlots, 'id'))->toBe([$consultation->id])
        ->and($consultationSlots[0]['remaining'])->toBe((int) $room->capacity)
        ->and($consultationSlots[0]['full'])->toBeFalse()
        ->and($policy->allowsOnlineConsultation())->toBeFalse();

    foreach ([
        ['setup.rooms.seating-policy', 'enable_seating_arrangements', true],
        ['setup.exam-materials.booklets.set-policy', 'enable_multi_set_booklets', true],
        ['setup.rooms.seating-policy', 'enable_seating_arrangements', false],
        ['setup.exam-materials.booklets.set-policy', 'enable_multi_set_booklets', false],
    ] as [$route, $setting, $enabled]) {
        $this->actingAs($actor)->post(route($route), [
            $setting => $enabled,
            'expected_version' => $institution->fresh()->examFeaturePolicyVersion(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($institution->fresh()->getAttribute($setting))->toBe($enabled)
            ->and($exam->fresh()->only($bookingColumns))->toEqual($examBooking)
            ->and($consultation->fresh()->only($bookingColumns))->toEqual($consultationBooking)
            ->and($policy->fresh()->getRawOriginal())->toBe($policyBefore)
            ->and($policy->fresh()->allowsOnlineConsultation())->toBeFalse()
            ->and($examCalendar->flatSlots($attempt))->toBe($examSlots)
            ->and($consultationCalendar->openSessions($attempt)
                ->map(fn (ConsultationSession $session): array => $consultationCalendar->slotPayload($session))
                ->all())->toBe($consultationSlots);
    }

    $exam->update([
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'reservation_closed_at' => now(),
    ]);
    expect($examCalendar->flatSlots($attempt))->toBe([])
        ->and($consultationCalendar->openSessions($attempt)->modelKeys())->toBe([$consultation->id]);

    $consultation->update([
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'reservation_closed_at' => now(),
    ]);
    expect($consultationCalendar->openSessions($attempt))->toBeEmpty()
        ->and($policy->fresh()->getRawOriginal())->toBe($policyBefore);

    $closedExamBooking = $exam->fresh()->only($bookingColumns);
    $closedConsultationBooking = $consultation->fresh()->only($bookingColumns);
    foreach ([
        ['setup.rooms.seating-policy', 'enable_seating_arrangements'],
        ['setup.exam-materials.booklets.set-policy', 'enable_multi_set_booklets'],
    ] as [$route, $setting]) {
        $this->actingAs($actor)->post(route($route), [
            $setting => true,
            'expected_version' => $institution->fresh()->examFeaturePolicyVersion(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        expect($institution->fresh()->getAttribute($setting))->toBeTrue()
            ->and($exam->fresh()->only($bookingColumns))->toEqual($closedExamBooking)
            ->and($consultation->fresh()->only($bookingColumns))->toEqual($closedConsultationBooking)
            ->and($examCalendar->flatSlots($attempt))->toBe([])
            ->and($consultationCalendar->openSessions($attempt))->toBeEmpty()
            ->and($policy->fresh()->getRawOriginal())->toBe($policyBefore);
    }
});
