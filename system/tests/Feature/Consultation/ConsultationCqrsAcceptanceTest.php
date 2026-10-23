<?php

use App\Consultation\Queries\ConsultationSessionDetailQuery;
use App\Consultation\Queries\ConsultationSessionListQuery;
use App\Models\Consultation\ConsultationSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Scheduling\CandidatePoolQuery;

beforeEach(function (): void {
    seedE2Foundation();

    $this->institution = Institution::query()->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('institution_id', $this->institution->id)->firstOrFail();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->room = Room::query()->where('institution_id', $this->institution->id)->where('is_active', true)->firstOrFail();
});

test('consultation session list query calculates authoritative SQL summary and supports dual projections', function (): void {
    // Create consultation sessions in the active cycle
    ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CS-CQRS-01',
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(5)->setTime(10, 0),
        'version' => 1,
    ]);

    ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CS-CQRS-02',
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(6)->setTime(14, 0),
        'version' => 1,
    ]);

    $listQuery = app(ConsultationSessionListQuery::class);
    $result = $listQuery->query([], (int) $this->institution->id, limit: 1);

    // SQL Summary reflects all matching sessions
    expect($result->summary['total_count'])->toBeGreaterThanOrEqual(2)
        ->and($result->summary['capacity_count'])->toBeGreaterThanOrEqual((int) $this->room->capacity)
        ->and($result->summary['by_status'])->toHaveKey('published')
        ->and($result->summary['by_status'])->toHaveKey('draft');

    // Inertia projection returns full presentation rows
    $inertiaData = $result->forInertia();
    expect($inertiaData)->toHaveKeys(['sessions', 'filters', 'summary'])
        ->and(count($inertiaData['sessions']))->toBe(1); // Capped to limit

    // Xiam-safe projection returns summary and minimized sample
    $xiamData = $result->forXiamSafe(sampleLimit: 1);
    expect($xiamData)->toHaveKeys(['summary', 'sessions'])
        ->and($xiamData['summary']['total_count'])->toBe($result->summary['total_count'])
        ->and(count($xiamData['sessions']))->toBe(1)
        ->and($xiamData['sessions'][0])->not->toHaveKey('applicants')
        ->and($xiamData['sessions'][0])->toHaveKeys(['id', 'code', 'status', 'scheduled_at', 'assigned_count']);
});

test('consultation session list query enforces relational tenant fencing and fails closed on foreign cycle', function (): void {
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOR-CONS-99',
        'name' => 'Foreign Consultation University',
        'slug' => 'foreign-consultation-university',
        'is_active' => true,
    ]);

    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'FOR-CONS-CYCLE',
        'name' => 'Foreign Cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'school_year' => '2026-2027',
        'semester' => '1',
        'is_active' => true,
    ]);

    $listQuery = app(ConsultationSessionListQuery::class);

    // Querying under actor's institution with a foreign cycle must fail closed
    $result = $listQuery->query(['academic_cycle_id' => $foreignCycle->id], (int) $this->institution->id);

    expect($result->summary['total_count'])->toBe(0)
        ->and($result->sessions)->toBeEmpty();
});

test('candidate pool query forXiamSafe exposes aggregate metrics only with zero applicant PII', function (): void {
    $candidatePoolQuery = app(CandidatePoolQuery::class);

    $xiamPayload = $candidatePoolQuery->forXiamSafe((int) $this->cycle->id, (int) $this->institution->id, stage: 'exam');

    expect($xiamPayload)->toHaveKeys(['pool_count', 'available_capacity'])
        ->and($xiamPayload)->not->toHaveKey('academic_cycle_id')
        ->and($xiamPayload)->not->toHaveKey('by_program_strand')
        ->and($xiamPayload)->not->toHaveKey('first_name')
        ->and($xiamPayload)->not->toHaveKey('last_name')
        ->and($xiamPayload)->not->toHaveKey('email')
        ->and($xiamPayload)->not->toHaveKey('reference_code');
});

test('consultation session detail query enforces tenant fencing and dual projections', function (): void {
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $this->cycle->id,
        'room_id' => $this->room->id,
        'code' => 'CS-DETAIL-01',
        'status' => ConsultationSession::STATUS_DRAFT,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(7)->setTime(9, 0),
        'version' => 1,
    ]);

    $detailQuery = app(ConsultationSessionDetailQuery::class);
    $detail = $detailQuery->query($session->id, (int) $this->institution->id);

    expect($detail['session']->id)->toBe($session->id);

    $xiamSafe = ($detail['forXiamSafe'])();
    expect($xiamSafe)->toHaveKeys(['id', 'code', 'status', 'version', 'assignments'])
        ->and($xiamSafe)->not->toHaveKey('contact_email');

    $inertiaShow = ($detail['forInertia'])();
    expect($inertiaShow)->toHaveKeys(['session', 'assigned', 'ready_pool', 'reassign_targets', 'publish_block_reason']);
});
