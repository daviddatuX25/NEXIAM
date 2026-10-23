<?php

use App\Ai\Adapters\LaravelAi\XiamProjectorRegistry;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Queries\ExamSessionListQuery;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\User;
use Database\Seeders\CohortDistributionDemoSeeder;
use Database\Seeders\ExamPublicationAuthorityDemoSeeder;

beforeEach(function (): void {
    seedE2Foundation();
    (new CohortDistributionDemoSeeder)->run();
    (new ExamPublicationAuthorityDemoSeeder)->run();

    $this->institution = Institution::query()->firstOrFail();
    $this->cycle = AcademicCycle::query()->where('institution_id', $this->institution->id)->firstOrFail();
    $this->guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->room = Room::query()->where('institution_id', $this->institution->id)->where('is_active', true)->firstOrFail();
});

test('preflight evaluation on simple capacity session is pure read and creates zero seating arrangements', function (): void {
    $initialArrangementCount = SeatingArrangement::query()->count();

    // Create a draft session without an arrangement
    $session = app(CreateExamSession::class)->handle(
        cycle: $this->cycle,
        room: $this->room,
        code: 'CQRS-PURE-01',
        startsAt: now()->addDays(120)->setTime(9, 0)->toDateTimeString(),
        actor: $this->guidance,
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
        bookletVersion: null,
        seatingArrangement: null,
    );

    expect($session->seating_arrangement_id)->toBeNull();

    // Run preflight evaluation multiple times
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session);

    // Assert zero database rows created in seating_arrangements
    expect(SeatingArrangement::query()->count())->toBe($initialArrangementCount);
});

test('stable layout fingerprint allows confirmation and publication without hash conflict', function (): void {
    $pubReady = ExamSession::query()
        ->where('code', ExamPublicationAuthorityDemoSeeder::READY_SESSION_CODE)
        ->firstOrFail();

    // Compute preflight in memory
    $preflight = app(ExamSessionPublishPreflight::class)->evaluate($pubReady);
    expect($preflight['ok'])->toBeTrue()
        ->and($preflight['roster_material_fingerprint'])->toBeString()->not->toBeEmpty();

    $expectedFingerprint = $preflight['roster_material_fingerprint'];

    // Publish using this fingerprint succeeds without stale_plan_conflict
    $published = app(PublishExamSession::class)->handle(
        session: $pubReady->fresh(),
        actor: $this->guidance,
        expectedVersion: (int) $pubReady->version,
        expectedFingerprint: $expectedFingerprint,
    );

    expect($published->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($published->version)->toBe((int) $pubReady->version + 1);
});

test('authoritative SQL summary reports true totals regardless of capped sample size', function (): void {
    $listQuery = app(ExamSessionListQuery::class);
    $result = $listQuery->query([], (int) $this->institution->id, limit: 2);

    expect($result->summary['total_count'])->toBeGreaterThanOrEqual(3)
        ->and($result->summary['assigned_count'])->toBeGreaterThanOrEqual(1);

    // Even with a sample limit of 2, the summary reflects all matching records
    $xiamSafe = $result->forXiamSafe(sampleLimit: 2);
    expect($xiamSafe['summary']['total_count'])->toBe($result->summary['total_count'])
        ->and(count($xiamSafe['sessions']))->toBeLessThanOrEqual(2);
});

test('multi-tenant relational fencing excludes foreign academic cycles fail-closed', function (): void {
    $foreignInstitution = Institution::query()->create([
        'code' => 'FOR-999',
        'name' => 'Foreign University',
        'slug' => 'foreign-university',
        'is_active' => true,
    ]);

    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $foreignInstitution->id,
        'code' => 'FOREIGN-CYCLE-999',
        'name' => 'Foreign Academic Cycle',
        'opens_on' => '2026-01-01',
        'closes_on' => '2026-12-31',
        'school_year' => '2026-2027',
        'semester' => '1',
        'is_active' => true,
    ]);

    $listQuery = app(ExamSessionListQuery::class);

    // Querying with foreign cycle under actor's institution must return empty fail-closed
    $result = $listQuery->query(['academic_cycle_id' => $foreignCycle->id], (int) $this->institution->id);

    expect($result->summary['total_count'])->toBe(0)
        ->and($result->sessions)->toBeEmpty();
});

test('xiam projector registry scrubs applicant PII from tool payloads', function (): void {
    $registry = app(XiamProjectorRegistry::class);

    $unscrubbedData = [
        'session_id' => 123,
        'code' => 'EX-TEST-01',
        'contact_email' => 'applicant.leak@example.com',
        'email' => 'direct.leak@example.com',
        'birthdate' => '2000-01-01',
        'access_key' => 'SECRET-KEY-1234',
        'raw_password' => 'plaintext_secret',
        'safe_nested' => [
            'status' => 'accepted',
            'phone' => '+639123456789', // PII
            'code' => 'ROOM-101',
        ],
    ];

    $scrubbed = $registry->project('test.capability@1', $unscrubbedData);

    expect($scrubbed)->toHaveKey('session_id')
        ->and($scrubbed)->toHaveKey('code')
        ->and($scrubbed)->not->toHaveKey('contact_email')
        ->and($scrubbed)->not->toHaveKey('email')
        ->and($scrubbed)->not->toHaveKey('birthdate')
        ->and($scrubbed)->not->toHaveKey('access_key')
        ->and($scrubbed)->not->toHaveKey('raw_password')
        ->and($scrubbed['safe_nested'])->not->toHaveKey('phone')
        ->and($scrubbed['safe_nested'])->toHaveKey('status')
        ->and($scrubbed['safe_nested'])->toHaveKey('code');
});
test('xiam projector registry fails closed on unregistered capabilities', function (): void {
    $registry = app(XiamProjectorRegistry::class);

    expect(fn () => $registry->project('unregistered.capability@1', ['foo' => 'bar']))
        ->toThrow(RuntimeException::class, 'Unregistered capability [unregistered.capability@1] refused');
});

test('exam session list query sums room capacity once per session for shared rooms', function (): void {
    $session1 = app(CreateExamSession::class)->handle(
        cycle: $this->cycle,
        room: $this->room,
        code: 'CQRS-SHARED-01',
        startsAt: now()->addDays(130)->setTime(9, 0)->toDateTimeString(),
        effectiveEndAt: now()->addDays(130)->setTime(11, 0)->toDateTimeString(),
        actor: $this->guidance,
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );

    $session2 = app(CreateExamSession::class)->handle(
        cycle: $this->cycle,
        room: $this->room,
        code: 'CQRS-SHARED-02',
        startsAt: now()->addDays(131)->setTime(9, 0)->toDateTimeString(),
        effectiveEndAt: now()->addDays(131)->setTime(11, 0)->toDateTimeString(),
        seatingMode: ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
    );

    $listQuery = app(ExamSessionListQuery::class);
    $result = $listQuery->query(['search' => 'CQRS-SHARED'], (int) $this->institution->id);

    expect($result->summary['total_count'])->toBe(2)
        ->and($result->summary['capacity_count'])->toBe((int) $this->room->capacity * 2);
});
