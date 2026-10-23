<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Testing\PracticeFixtureSet;
use App\Testing\Queries\PracticeResearchAssignments;

function researchAssignmentSet(string $scenario, string $status = 'active'): PracticeFixtureSet
{
    return (new PracticeFixtureSet)->forceFill([
        'id' => 'f94f42ea-0344-47e9-bf51-c18ef33e3ee7',
        'institution_id' => 1, 'academic_cycle_id' => 1,
        'purpose' => 'research', 'scenario' => $scenario, 'status' => $status,
        'actor_bindings' => ['guidance' => 501, 'academic-leader' => 502],
        'material_snapshot' => [], 'credentials' => [],
    ]);
}

function researchAssignmentAttempt(PracticeFixtureSet $set, int $id): AdmissionAttempt
{
    $attempt = (new AdmissionAttempt)->forceFill([
        'id' => $id, 'institution_id' => $set->institution_id,
        'academic_cycle_id' => $set->academic_cycle_id,
        'practice_fixture_set_id' => $set->id,
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'current_result_document_id' => null,
    ]);
    $attempt->setRelation('identity', null);
    $attempt->setRelation('resultDocuments', collect());
    $attempt->setRelation('examAssignments', collect());

    return $attempt;
}

test('research descriptors cover every canonical task in its role and keep missing prerequisites unavailable', function (string $scenario, array $ids): void {
    $set = researchAssignmentSet($scenario);
    $set->actor_bindings = [];
    $result = app(PracticeResearchAssignments::class)->for($set, collect(), [], []);

    expect($result['designated_case_id'])->toBeNull()
        ->and(array_column($result['task_assignments'], 'task_id'))->toBe($ids);
    foreach ($result['task_assignments'] as $task) {
        expect($task['available'])->toBeFalse()->and($task['url'])->toBeNull()
            ->and($task['reason'])->not->toBeEmpty();
        expect($task)->not->toHaveKeys(['access_key', 'birthdate', 'participant_id', 'response', 'item_mapping']);
    }
})->with([
    'student' => ['student', ['A1', 'A2', 'A3', 'A4', 'A5', 'A6']],
    'registrar' => ['registrar', ['B1', 'B2', 'B3', 'B4', 'B5', 'B6']],
    'guidance and paired leader' => ['guidance', ['C1', 'C2', 'C3', 'C4', 'C5', 'C6', 'C7', 'C8', 'D4']],
    'leader and paired Guidance' => ['academic-leader', ['D1', 'D2', 'D3', 'D4', 'C5']],
]);

test('blocked paired tasks retain the exact same designated case and distinct assigned accounts without exposing a link', function (): void {
    $set = researchAssignmentSet('academic-leader');
    $attempt = researchAssignmentAttempt($set, 77);
    $result = app(PracticeResearchAssignments::class)->for($set, collect([$attempt]), [
        ['id' => 77, 'stage' => 'program', 'handoffs' => []],
    ], ['case_id' => 77, 'status' => 'missing', 'reason' => 'Current permitted thread is missing.']);
    $tasks = collect($result['task_assignments'])->keyBy('task_id');

    expect($tasks['C5']['case_id'])->toBe(77)->and($tasks['D4']['case_id'])->toBe(77)
        ->and($tasks['C5']['account_user_id'])->toBe(501)->and($tasks['D4']['account_user_id'])->toBe(502)
        ->and($tasks['C5']['available'])->toBeFalse()->and($tasks['D4']['available'])->toBeFalse()
        ->and($tasks['C5']['url'])->toBeNull()->and($tasks['D4']['url'])->toBeNull();
});

test('a foreign pair case cannot produce task links even when the supplied exchange claims availability', function (): void {
    $set = researchAssignmentSet('academic-leader');
    $foreign = researchAssignmentAttempt($set, 78);
    $foreign->practice_fixture_set_id = '3fc17e17-cb43-4c97-b0c5-943a02b8eddb';
    $result = app(PracticeResearchAssignments::class)->for($set, collect([$foreign]), [
        ['id' => 78, 'stage' => 'program', 'handoffs' => []],
    ], ['case_id' => 78, 'status' => 'available', 'url' => '/private-thread']);
    $tasks = collect($result['task_assignments'])->keyBy('task_id');

    expect($tasks['C5']['case_id'])->toBeNull()->and($tasks['D4']['case_id'])->toBeNull()
        ->and($tasks['C5']['available'])->toBeFalse()->and($tasks['D4']['available'])->toBeFalse()
        ->and($tasks['C5']['url'])->toBeNull()->and($tasks['D4']['url'])->toBeNull();
});
test('guidance C8 prefers an unmarked active examinee within the attendance window', function (): void {
    $set = researchAssignmentSet('guidance');
    $markedAttempt = researchAssignmentAttempt($set, 77);
    $unmarkedAttempt = researchAssignmentAttempt($set, 78);
    $session = fn (int $id): ExamSession => (new ExamSession)->forceFill([
        'id' => $id,
        'practice_fixture_set_id' => $set->id,
        'academic_cycle_id' => $set->academic_cycle_id,
        'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->subMinutes(10),
        'effective_end_at' => now()->addHour(),
    ]);
    $markedAssignment = (new ExamAssignment)->forceFill([
        'id' => 1, 'exam_session_id' => 101, 'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);
    $markedAssignment->setRelation('examSession', $session(101));
    $markedAssignment->setRelation('attendanceRecord', (new ExamAttendanceRecord)->forceFill([
        'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
    ]));
    $markedAssignment->setRelation('submissionRecord', null);
    $unmarkedAssignment = (new ExamAssignment)->forceFill([
        'id' => 2, 'exam_session_id' => 102, 'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);
    $unmarkedAssignment->setRelation('examSession', $session(102));
    $unmarkedAssignment->setRelation('attendanceRecord', null);
    $unmarkedAssignment->setRelation('submissionRecord', null);
    $markedAttempt->setRelation('examAssignments', collect([$markedAssignment]));
    $unmarkedAttempt->setRelation('examAssignments', collect([$unmarkedAssignment]));

    $result = app(PracticeResearchAssignments::class)->for($set, collect([$markedAttempt, $unmarkedAttempt]), [
        ['id' => 77, 'stage' => 'conduct', 'handoffs' => ['monitoring' => ['actor' => 'guidance', 'available' => true]]],
        ['id' => 78, 'stage' => 'conduct', 'handoffs' => ['monitoring' => ['actor' => 'guidance', 'available' => true]]],
    ], []);
    $c8 = collect($result['task_assignments'])->firstWhere('task_id', 'C8');

    expect($c8['case_id'])->toBe(78)
        ->and($c8['session_id'])->toBe(102)
        ->and($c8['available'])->toBeTrue();
});

test('guidance C8 rejects an already marked examinee when no unmarked assignment remains', function (): void {
    $set = researchAssignmentSet('guidance');
    $attempt = researchAssignmentAttempt($set, 77);
    $session = (new ExamSession)->forceFill([
        'id' => 101,
        'practice_fixture_set_id' => $set->id,
        'academic_cycle_id' => $set->academic_cycle_id,
        'status' => ExamSession::STATUS_PUBLISHED,
        'starts_at' => now()->subMinutes(10),
        'effective_end_at' => now()->addHour(),
    ]);
    $assignment = (new ExamAssignment)->forceFill([
        'id' => 1, 'exam_session_id' => 101, 'status' => ExamAssignment::STATUS_ASSIGNED,
    ]);
    $assignment->setRelation('examSession', $session);
    $assignment->setRelation('attendanceRecord', (new ExamAttendanceRecord)->forceFill([
        'outcome' => ExamAttendanceRecord::OUTCOME_ABSENT,
    ]));
    $assignment->setRelation('submissionRecord', null);
    $attempt->setRelation('examAssignments', collect([$assignment]));

    $result = app(PracticeResearchAssignments::class)->for($set, collect([$attempt]), [
        ['id' => 77, 'stage' => 'conduct', 'handoffs' => ['monitoring' => ['actor' => 'guidance', 'available' => true]]],
    ], []);
    $c8 = collect($result['task_assignments'])->firstWhere('task_id', 'C8');

    expect($c8['available'])->toBeFalse()
        ->and($c8['reason'])->toContain('already has an attendance or submission record');
});
