<?php

use App\Models\Assessment\OmrBookletSet;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAssignmentSeatAllocation;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('persists global and selected-room applicability with a room default relation', function (): void {
    $globalRoom = Room::factory()->create(['code' => 'EMR-GLOBAL-ROOM']);
    $selectedRoom = Room::factory()->create(['code' => 'EMR-SELECTED-ROOM']);

    $global = SeatingArrangement::factory()->create([
        'name' => 'Global layout',
        'normalized_name' => 'global layout',
        'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
    ]);
    $selected = SeatingArrangement::factory()->create([
        'name' => 'Selected layout',
        'normalized_name' => 'selected layout',
        'availability_mode' => SeatingArrangement::AVAILABILITY_SELECTED_ROOMS,
    ]);

    $selected->rooms()->attach($selectedRoom);
    $selectedRoom->forceFill(['default_seating_arrangement_id' => $selected->id])->save();

    expect($global->fresh()->availability_mode)->toBe(SeatingArrangement::AVAILABILITY_ALL_ROOMS)
        ->and($global->fresh('rooms')->rooms)->toBeEmpty()
        ->and($selected->fresh('rooms')->rooms->modelKeys())->toBe([$selectedRoom->id])
        ->and($selectedRoom->fresh('seatingArrangements')->seatingArrangements->modelKeys())->toBe([$selected->id])
        ->and($selectedRoom->fresh('defaultSeatingArrangement')->defaultSeatingArrangement->is($selected))->toBeTrue()
        ->and($globalRoom->fresh('defaultSeatingArrangement')->defaultSeatingArrangement)->toBeNull();
});

test('rejects duplicate normalized arrangement names across rooms', function (): void {
    SeatingArrangement::factory()->create([
        'name' => 'Main Layout',
        'normalized_name' => 'main layout',
    ]);

    expect(fn () => DB::transaction(fn () => SeatingArrangement::factory()->create([
        'name' => ' main   layout ',
        'normalized_name' => 'main layout',
    ])))->toThrow(QueryException::class);
});

test('allows one current snapshot per session while retaining superseded revisions', function (): void {
    $fixture = emrSeatingPersistenceFixture();
    $snapshot = $fixture['snapshot'];
    $supersededAt = now();

    $supersededId = DB::table('exam_session_material_snapshots')->insertGetId([
        'exam_session_id' => $fixture['session']->id,
        'revision' => 2,
        'is_current' => false,
        'template_version_id' => $fixture['template_version_id'],
        'booklet_version_id' => $fixture['booklet_version_id'],
        'scoring_policy_identity' => 'emr-policy-v1',
        'material_hash' => str_repeat('s', 64),
        'published_at' => $fixture['published_at'],
        'superseded_at' => $supersededAt,
        'created_at' => $supersededAt,
        'updated_at' => $supersededAt,
    ]);

    expect(fn () => DB::transaction(fn () => DB::table('exam_session_material_snapshots')->insert([
        'exam_session_id' => $fixture['session']->id,
        'revision' => 3,
        'is_current' => true,
        'template_version_id' => $fixture['template_version_id'],
        'booklet_version_id' => $fixture['booklet_version_id'],
        'scoring_policy_identity' => 'emr-policy-v1',
        'material_hash' => str_repeat('t', 64),
        'published_at' => $fixture['published_at'],
        'created_at' => now(),
        'updated_at' => now(),
    ])))->toThrow(QueryException::class);

    expect(ExamSessionMaterialSnapshot::query()->where('exam_session_id', $fixture['session']->id)->where('is_current', true)->count())->toBe(1)
        ->and(ExamSessionMaterialSnapshot::query()->findOrFail($supersededId)->is_current)->toBeFalse()
        ->and(ExamSessionMaterialSnapshot::query()->findOrFail($snapshot->id)->revision)->toBe(1);
});

test('enforces unique ordinal and label within one frozen session snapshot', function (): void {
    $fixture = emrSeatingPersistenceFixture();
    $seat = emrCreateSessionSeat($fixture['snapshot'], $fixture['booklet_set_id'], 1, 'A1');

    expect($seat->fresh(['materialSnapshot', 'bookletSet'])->materialSnapshot->is($fixture['snapshot']))->toBeTrue()
        ->and($seat->fresh('bookletSet')->bookletSet->is($fixture['booklet']))->toBeTrue();

    expect(fn () => DB::transaction(fn () => emrCreateSessionSeat($fixture['snapshot'], $fixture['booklet_set_id'], 1, 'B1')))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => emrCreateSessionSeat($fixture['snapshot'], $fixture['booklet_set_id'], 2, 'A1')))
        ->toThrow(QueryException::class);
});

test('keeps closed allocation history, limits active assignment and occupant, and freezes submission reference', function (): void {
    $fixture = emrSeatingPersistenceFixture();
    $seatOne = emrCreateSessionSeat($fixture['snapshot'], $fixture['booklet_set_id'], 1, 'A1');
    $seatTwo = emrCreateSessionSeat($fixture['snapshot'], $fixture['booklet_set_id'], 2, 'A2');
    $seatThree = emrCreateSessionSeat($fixture['snapshot'], $fixture['booklet_set_id'], 3, 'A3');
    $assignmentOne = emrCreateAssignment($fixture['session']->id, $fixture['attempt_ids'][1], $fixture['snapshot']->id, $fixture['booklet_set_id']);
    $assignmentTwo = emrCreateAssignment($fixture['session']->id, $fixture['attempt_ids'][2], $fixture['snapshot']->id, $fixture['booklet_set_id']);
    $assignmentThree = emrCreateAssignment($fixture['session']->id, $fixture['attempt_ids'][3], $fixture['snapshot']->id, $fixture['booklet_set_id']);
    $closedFrom = now()->subHours(2);
    $closedTo = now()->subHour();

    $closed = emrCreateAllocation($assignmentOne, $seatOne, $closedFrom, $closedTo);
    $active = emrCreateAllocation($assignmentOne, $seatTwo, now()->subMinutes(30));
    $occupied = emrCreateAllocation($assignmentTwo, $seatOne, now()->subMinutes(20));

    expect($closed->effective_to)->not->toBeNull()
        ->and($active->fresh(['assignment', 'sessionSeat'])->assignment->is($assignmentOne))->toBeTrue()
        ->and($active->fresh('sessionSeat')->sessionSeat->is($seatTwo))->toBeTrue()
        ->and($occupied->fresh('sessionSeat')->sessionSeat->is($seatOne))->toBeTrue();

    expect(fn () => DB::transaction(fn () => emrCreateAllocation($assignmentOne, $seatThree, now()->subMinutes(10))))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => emrCreateAllocation($assignmentThree, $seatOne, now()->subMinutes(5))))
        ->toThrow(QueryException::class);

    $submissionId = DB::table('exam_submission_records')->insertGetId([
        'exam_assignment_id' => $assignmentOne->id,
        'seat_allocation_id' => $active->id,
        'legacy_seat_allocation_status' => 'backfilled',
        'idempotency_key' => 'emr-submission-reference',
        'submitted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($active->fresh('submission')->submission->getKey())->toBe($submissionId)
        ->and((int) DB::table('exam_submission_records')->where('id', $submissionId)->value('seat_allocation_id'))->toBe($active->id);
});

/**
 * @return array{session: ExamSession, snapshot: ExamSessionMaterialSnapshot, booklet: OmrBookletSet, booklet_set_id: int, template_version_id: int, booklet_version_id: int, published_at: Carbon, attempt_ids: array<int, int>}
 */
function emrSeatingPersistenceFixture(): array
{
    $now = now();
    $room = Room::factory()->create(['code' => 'EMR-PERSISTENCE-ROOM']);
    $institutionId = (int) $room->institution_id;
    $cycleId = (int) DB::table('academic_cycles')->insertGetId([
        'institution_id' => $institutionId,
        'code' => 'EMR-CYCLE-'.strtoupper(substr((string) $room->id, 0, 8)),
        'name' => 'EMR cycle',
        'opens_on' => $now->toDateString(),
        'closes_on' => $now->copy()->addYear()->toDateString(),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $templateId = (int) DB::table('omr_answer_sheet_templates')->insertGetId([
        'code' => 'EMR-TEMPLATE-'.strtoupper(substr((string) $room->id, 0, 8)),
        'name' => 'EMR template',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $templateVersionId = (int) DB::table('omr_answer_sheet_template_versions')->insertGetId([
        'template_id' => $templateId,
        'version' => 1,
        'status' => 'draft',
        'registration_mode' => 'fiducial_anchored',
        'qr_mode' => 'qr_signed',
        'page_count' => 1,
        'page_width' => 100,
        'page_height' => 100,
        'identity_regions_json' => '[]',
        'provider_config_json' => '[]',
        'config_hash' => str_repeat('a', 64),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bookletId = (int) DB::table('omr_question_booklets')->insertGetId([
        'code' => 'EMR-BOOKLET-'.strtoupper(substr((string) $room->id, 0, 8)),
        'name' => 'EMR booklet',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bookletVersionId = (int) DB::table('omr_question_booklet_versions')->insertGetId([
        'booklet_id' => $bookletId,
        'version' => 1,
        'status' => 'draft',
        'answer_key_hash' => str_repeat('b', 64),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bookletSetId = (int) DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersionId,
        'set_code' => 'A',
        'status' => 'draft',
        'generation_version' => 'emr-test-v1',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $sessionId = (int) DB::table('exam_sessions')->insertGetId([
        'academic_cycle_id' => $cycleId,
        'room_id' => $room->id,
        'booklet_version_id' => $bookletVersionId,
        'code' => 'EMR-SESSION-'.strtoupper(substr((string) $room->id, 0, 8)),
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => $now->copy()->addDay(),
        'effective_end_at' => $now->copy()->addDay()->addHours(2),
        'version' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $snapshotId = (int) DB::table('exam_session_material_snapshots')->insertGetId([
        'exam_session_id' => $sessionId,
        'revision' => 1,
        'is_current' => true,
        'template_version_id' => $templateVersionId,
        'booklet_version_id' => $bookletVersionId,
        'scoring_policy_identity' => 'emr-policy-v1',
        'material_hash' => str_repeat('c', 64),
        'published_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $attemptIds = [];
    for ($number = 1; $number <= 3; $number++) {
        $identityId = (int) DB::table('applicant_identities')->insertGetId([
            'institution_id' => $institutionId,
            'normalized_key' => 'EMR-APPLICANT-'.$number.'-'.$room->id,
            'first_name' => 'EMR',
            'last_name' => 'Applicant '.$number,
            'birthdate' => '2001-01-01',
            'sex' => 'male',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attemptIds[$number] = (int) DB::table('admission_attempts')->insertGetId([
            'applicant_identity_id' => $identityId,
            'institution_id' => $institutionId,
            'academic_cycle_id' => $cycleId,
            'reference_code' => 'EMR-ATTEMPT-'.$number.'-'.$room->id,
            'status' => 'accepted',
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    return [
        'session' => ExamSession::query()->findOrFail($sessionId),
        'snapshot' => ExamSessionMaterialSnapshot::query()->findOrFail($snapshotId),
        'booklet' => OmrBookletSet::query()->findOrFail($bookletSetId),
        'booklet_set_id' => $bookletSetId,
        'template_version_id' => $templateVersionId,
        'booklet_version_id' => $bookletVersionId,
        'published_at' => $now,
        'attempt_ids' => $attemptIds,
    ];
}

function emrCreateSessionSeat(ExamSessionMaterialSnapshot $snapshot, int $bookletSetId, int $ordinal, string $label): ExamSessionSeat
{
    return ExamSessionSeat::query()->create([
        'material_snapshot_id' => $snapshot->id,
        'ordinal' => $ordinal,
        'row_number' => 1,
        'column_number' => $ordinal,
        'seat_label' => $label,
        'booklet_set_id' => $bookletSetId,
    ]);
}

function emrCreateAssignment(int $sessionId, int $attemptId, int $_snapshotId, int $_bookletSetId): ExamAssignment
{
    $id = (int) DB::table('exam_assignments')->insertGetId([
        'exam_session_id' => $sessionId,
        'admission_attempt_id' => $attemptId,
        'status' => ExamAssignment::STATUS_ASSIGNED,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ExamAssignment::query()->findOrFail($id);
}

function emrCreateAllocation(ExamAssignment $assignment, ExamSessionSeat $seat, Carbon $effectiveFrom, ?Carbon $effectiveTo = null): ExamAssignmentSeatAllocation
{
    return ExamAssignmentSeatAllocation::query()->create([
        'exam_assignment_id' => $assignment->id,
        'exam_session_seat_id' => $seat->id,
        'effective_from' => $effectiveFrom,
        'effective_to' => $effectiveTo,
    ]);
}
