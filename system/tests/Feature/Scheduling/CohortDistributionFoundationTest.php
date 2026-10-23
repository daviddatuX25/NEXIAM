<?php

use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $institution = Institution::query()->create([
        'code' => 'DIST-INST',
        'name' => 'Distribution Test Institution',
    ]);

    $this->cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'DIST-CYCLE',
        'name' => 'Distribution Test Cycle',
        'school_year' => '2026-2027',
        'semester' => '1',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'is_active' => true,
    ]);

    $this->room = Room::factory()->for($institution)->create([
        'code' => 'DIST-ROOM',
    ]);
});

test('distribution fields default safely and expose constrained provenance values', function (): void {
    $cycle = $this->cycle;
    $room = $this->room;

    $examSession = ExamSession::factory()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create(['code' => 'EX-DIST-FOUNDATION']);
    $consultationSession = ConsultationSession::factory()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create(['code' => 'CS-DIST-FOUNDATION']);

    expect(Schema::hasColumns('exam_sessions', ['auto_assign_enabled']))->toBeTrue()
        ->and(Schema::hasColumns('consultation_sessions', ['auto_assign_enabled']))->toBeTrue()
        ->and(Schema::hasColumns('exam_assignments', ['origin']))->toBeTrue()
        ->and(Schema::hasColumns('consultation_assignments', ['origin']))->toBeTrue()
        ->and($examSession->auto_assign_enabled)->toBeFalse()
        ->and($consultationSession->auto_assign_enabled)->toBeFalse()
        ->and(ExamAssignment::ORIGINS)->toBe([
            ExamAssignment::ORIGIN_MANUAL,
            ExamAssignment::ORIGIN_BATCH,
            ExamAssignment::ORIGIN_CONTINUOUS,
            ExamAssignment::ORIGIN_RESERVATION,
        ])
        ->and(ConsultationAssignment::ORIGINS)->toBe([
            ConsultationAssignment::ORIGIN_MANUAL,
            ConsultationAssignment::ORIGIN_BATCH,
            ConsultationAssignment::ORIGIN_CONTINUOUS,
            ConsultationAssignment::ORIGIN_RESERVATION,
        ])
        ->and(ExamAssignment::factory()->make([
            'exam_session_id' => $examSession->id,
            'admission_attempt_id' => 1,
        ])->origin)->toBe(ExamAssignment::ORIGIN_MANUAL)
        ->and(ConsultationAssignment::factory()->reservation()->make([
            'consultation_session_id' => $consultationSession->id,
            'admission_attempt_id' => 1,
        ])->origin)->toBe(ConsultationAssignment::ORIGIN_RESERVATION);
});

test('distribution target scopes exclude published and reservation sessions', function (): void {
    $cycle = $this->cycle;
    $room = $this->room;
    $eligibleExam = ExamSession::factory()
        ->autoAssignEnabled()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create(['code' => 'EX-DIST-ELIGIBLE']);
    ExamSession::factory()
        ->autoAssignEnabled()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create([
            'code' => 'EX-DIST-PUBLISHED',
            'status' => ExamSession::STATUS_PUBLISHED,
        ]);
    ExamSession::factory()
        ->autoAssignEnabled()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create([
            'code' => 'EX-DIST-RESERVATION',
            'booking_mode' => ExamSession::BOOKING_OPEN_FOR_RESERVATION,
        ]);

    $eligibleConsultation = ConsultationSession::factory()
        ->autoAssignEnabled()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create(['code' => 'CS-DIST-ELIGIBLE']);
    ConsultationSession::factory()
        ->autoAssignEnabled()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create([
            'code' => 'CS-DIST-PUBLISHED',
            'status' => ConsultationSession::STATUS_PUBLISHED,
        ]);
    ConsultationSession::factory()
        ->autoAssignEnabled()
        ->for($cycle, 'academicCycle')
        ->for($room)
        ->create([
            'code' => 'CS-DIST-RESERVATION',
            'booking_mode' => ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
        ]);

    expect(ExamSession::query()->autoAssignEligible($cycle->id)->pluck('id')->all())
        ->toBe([$eligibleExam->id])
        ->and(ConsultationSession::query()->autoAssignEligible($cycle->id)->pluck('id')->all())
        ->toBe([$eligibleConsultation->id]);
});

test('planning mutex runs work inside one domain and cycle transaction', function (): void {
    $mutex = app(AssignmentPlanningMutex::class);

    $result = $mutex->run(
        AssignmentPlanningMutex::DOMAIN_EXAM,
        $this->cycle->id,
        fn (): array => [
            'cycle_id' => $this->cycle->id,
            'transaction_level' => DB::connection()->transactionLevel(),
        ],
    );

    expect($result['cycle_id'])->toBe($this->cycle->id)
        ->and($result['transaction_level'])->toBeGreaterThan(0);
});

test('planning mutex rejects unsupported domains before executing work', function (): void {
    $executed = false;

    expect(fn () => app(AssignmentPlanningMutex::class)->run(
        'unsupported',
        $this->cycle->id,
        function () use (&$executed): void {
            $executed = true;
        },
    ))->toThrow(InvalidArgumentException::class)
        ->and($executed)->toBeFalse();
});
