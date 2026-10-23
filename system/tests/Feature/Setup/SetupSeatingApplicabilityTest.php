<?php

use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use App\Setup\Queries\SetupSeatingApplicabilityQuery;

function createTestMaterialVersions(Institution $institution): array
{
    $tpl = OmrAnswerSheetTemplate::query()->firstOrCreate(
        ['code' => 'TPL-SEAT-TEST-'.$institution->id],
        ['name' => 'Seating Test Template', 'institution_id' => $institution->id, 'is_synthetic' => true]
    );
    $sheet = OmrAnswerSheetTemplateVersion::query()->firstOrCreate(
        ['template_id' => $tpl->id, 'version' => 1],
        [
            'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
            'page_width' => 100,
            'page_height' => 100,
            'identity_regions_json' => [],
            'provider_config_json' => [],
            'config_hash' => hash('sha256', 'c'),
            'template_json_hash' => hash('sha256', 't'),
        ]
    );

    $booklet = OmrQuestionBooklet::query()->firstOrCreate(
        ['code' => 'BKL-SEAT-TEST-'.$institution->id],
        ['name' => 'Seating Test Booklet', 'institution_id' => $institution->id, 'is_synthetic' => true]
    );
    $bookletVersion = OmrQuestionBookletVersion::query()->firstOrCreate(
        ['booklet_id' => $booklet->id, 'version' => 1],
        [
            'status' => OmrQuestionBookletVersion::STATUS_ACTIVE,
            'answer_key_hash' => hash('sha256', 'k'),
        ]
    );

    return [$sheet, $bookletVersion];
}

test('returns false when institution disables seating arrangements', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-01',
        'name' => 'Seating Test Inst 01',
        'enable_seating_arrangements' => false,
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeFalse()
        ->and(SetupSeatingApplicabilityQuery::applicable($institution))->toBeFalse();
});

test('returns false when no active academic cycle exists', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-02',
        'name' => 'Seating Test Inst 02',
        'enable_seating_arrangements' => true,
    ]);

    // Ensure no active cycle exists
    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeFalse();
});

test('returns false when active academic cycle has no sessions', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-03',
        'name' => 'Seating Test Inst 03',
        'enable_seating_arrangements' => true,
    ]);

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-NO-SESS',
        'name' => 'Cycle Without Sessions',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeFalse();
});

test('returns false when all sessions in cycle use simple capacity mode', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-04',
        'name' => 'Seating Test Inst 04',
        'enable_seating_arrangements' => true,
    ]);

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-SIMPLE-CAP',
        'name' => 'Cycle Simple Cap',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-SC-01',
        'name' => 'Simple Cap Room',
        'space_kind' => SpaceKind::Room,
        'capacity' => 30,
        'is_active' => true,
    ]);

    // Draft session with simple_capacity
    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-SC-DRAFT',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    // Published session with simple_capacity snapshot
    $pubSession = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-SC-PUB',
        'status' => ExamSession::STATUS_PUBLISHED,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(3),
        'effective_end_at' => now()->addDays(3)->addHours(2),
    ]);

    [$sheet, $booklet] = createTestMaterialVersions($institution);

    ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $pubSession->id,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'scoring_policy_identity' => 'grading.omr.session-seat.v2',
        'material_hash' => hash('sha256', 'hash-sc'),
        'published_at' => now(),
        'revision' => 1,
        'is_current' => true,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeFalse();
});

test('returns true when cycle has draft session with assigned seating', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-05',
        'name' => 'Seating Test Inst 05',
        'enable_seating_arrangements' => true,
    ]);

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-ASSIGNED-DRAFT',
        'name' => 'Cycle Assigned Draft',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-AS-01',
        'name' => 'Assigned Room',
        'space_kind' => SpaceKind::Room,
        'capacity' => 30,
        'is_active' => true,
    ]);

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-AS-DRAFT',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeTrue();
});

test('returns false when assigned seating session belongs to an inactive academic cycle', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-08',
        'name' => 'Seating Test Inst 08',
        'enable_seating_arrangements' => true,
    ]);

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $activeCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-ACT-08',
        'name' => 'Active Cycle 08',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $inactiveCycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-INACT-08',
        'name' => 'Inactive Cycle 08',
        'opens_on' => now()->subYear()->startOfYear(),
        'closes_on' => now()->subYear()->endOfYear(),
        'is_active' => false,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-AS-08',
        'name' => 'Room 08',
        'space_kind' => SpaceKind::Room,
        'capacity' => 30,
        'is_active' => true,
    ]);

    // Active cycle has a simple capacity session
    ExamSession::query()->create([
        'academic_cycle_id' => $activeCycle->id,
        'room_id' => $room->id,
        'code' => 'EX-SC-08',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    // Inactive cycle has an assigned seating session
    ExamSession::query()->create([
        'academic_cycle_id' => $inactiveCycle->id,
        'room_id' => $room->id,
        'code' => 'EX-AS-INACTIVE',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'starts_at' => now()->subYear()->addDays(2),
        'effective_end_at' => now()->subYear()->addDays(2)->addHours(2),
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeFalse();
});

test('returns true when cycle has published session with assigned seating snapshot', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-06',
        'name' => 'Seating Test Inst 06',
        'enable_seating_arrangements' => true,
    ]);

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-ASSIGNED-PUB',
        'name' => 'Cycle Assigned Pub',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-AS-02',
        'name' => 'Assigned Room 2',
        'space_kind' => SpaceKind::Room,
        'capacity' => 30,
        'is_active' => true,
    ]);

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-AS-PUB',
        'status' => ExamSession::STATUS_PUBLISHED,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    [$sheet, $booklet] = createTestMaterialVersions($institution);

    ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $session->id,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'scoring_policy_identity' => 'grading.omr.session-seat.v2',
        'material_hash' => hash('sha256', 'hash-as'),
        'published_at' => now(),
        'revision' => 1,
        'is_current' => true,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeTrue();
});

test('returns true when cycle has in_progress session with assigned seating snapshot', function (): void {
    $institution = Institution::query()->create([
        'code' => 'SEAT-TEST-07',
        'name' => 'Seating Test Inst 07',
        'enable_seating_arrangements' => true,
    ]);

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-ASSIGNED-PROG',
        'name' => 'Cycle Assigned In Progress',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-AS-03',
        'name' => 'Assigned Room 3',
        'space_kind' => SpaceKind::Room,
        'capacity' => 30,
        'is_active' => true,
    ]);

    $session = ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-AS-PROG',
        'status' => ExamSession::STATUS_IN_PROGRESS,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->subHour(),
        'effective_end_at' => now()->addHour(),
    ]);

    [$sheet, $booklet] = createTestMaterialVersions($institution);

    ExamSessionMaterialSnapshot::query()->create([
        'exam_session_id' => $session->id,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $booklet->id,
        'scoring_policy_identity' => 'grading.omr.session-seat.v2',
        'material_hash' => hash('sha256', 'hash-prog'),
        'published_at' => now()->subHour(),
        'revision' => 1,
        'is_current' => true,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
    ]);

    $query = app(SetupSeatingApplicabilityQuery::class);
    expect($query->isApplicable($institution))->toBeTrue();
});

test('ComputeSetupReadinessQuery omits seating check when seating is not applicable', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution', 'enable_seating_arrangements' => true],
    );
    $institution->forceFill(['enable_seating_arrangements' => true])->save();

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-NO-ASSIGNED',
        'name' => 'Cycle Without Assigned',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-READY-01',
        'name' => 'Ready Room',
        'space_kind' => SpaceKind::Room,
        'capacity' => 25,
        'is_active' => true,
    ]);

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-READY-SC',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();
    $roomsCategory = collect($readiness['groups'])
        ->flatMap(fn ($group) => $group['categories'])
        ->firstWhere('key', 'rooms');

    expect($roomsCategory)->not->toBeNull();
    $seatingCheck = collect($roomsCategory['checks'])->first(fn ($c) => str_contains($c['label'], 'seating arrangement'));
    expect($seatingCheck)->toBeNull();
});

test('ComputeSetupReadinessQuery includes seating check when seating is applicable', function (): void {
    $institution = Institution::query()->firstOrCreate(
        ['code' => 'SYN-INST-001'],
        ['name' => 'Synthetic Institution', 'enable_seating_arrangements' => true],
    );
    $institution->forceFill(['enable_seating_arrangements' => true])->save();

    AcademicCycle::query()->where('is_active', true)->update(['is_active' => false]);

    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CYCLE-WITH-ASSIGNED',
        'name' => 'Cycle With Assigned',
        'opens_on' => now()->startOfYear(),
        'closes_on' => now()->endOfYear(),
        'is_active' => true,
    ]);

    $room = Room::query()->create([
        'institution_id' => $institution->id,
        'code' => 'RM-READY-02',
        'name' => 'Ready Room 2',
        'space_kind' => SpaceKind::Room,
        'capacity' => 25,
        'is_active' => true,
    ]);

    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-READY-AS',
        'status' => ExamSession::STATUS_DRAFT,
        'seating_mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
    ]);

    $readiness = app(ComputeSetupReadinessQuery::class)->handle();
    $roomsCategory = collect($readiness['groups'])
        ->flatMap(fn ($group) => $group['categories'])
        ->firstWhere('key', 'rooms');

    expect($roomsCategory)->not->toBeNull();
    $seatingCheck = collect($roomsCategory['checks'])->first(fn ($c) => str_contains($c['label'], 'seating arrangement'));
    expect($seatingCheck)->not->toBeNull()
        ->and($seatingCheck['label'])->toBe('At least one active exam room has a default seating arrangement');
});
