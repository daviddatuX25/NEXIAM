<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Models\Admission\RecordThread;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

test('department entity associates programs and resolves automatically in dean actor scope', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

    $ccit = Department::query()->create([
        'institution_id' => $institution->id,
        'code' => 'CCIT_TEST',
        'name' => 'College of Computing & IT Test',
        'is_active' => true,
    ]);

    $bsit = Program::query()->where('code', 'BSIT')->first()
        ?? Program::query()->create([
            'institution_id' => $institution->id,
            'code' => 'BSIT',
            'name' => 'BS Information Technology',
            'is_active' => true,
            'department_id' => $ccit->id,
        ]);
    $bsit->update(['department_id' => $ccit->id]);

    $baPsych = Program::query()->where('code', 'BA-Psych')->first()
        ?? Program::query()->create([
            'institution_id' => $institution->id,
            'code' => 'BA-Psych',
            'name' => 'Bachelor of Arts in Psychology',
            'is_active' => true,
            'department_id' => $ccit->id,
        ]);
    $baPsych->update(['department_id' => $ccit->id]);

    $dean = User::factory()->create([
        'email' => 'test.dean@nexiam.test',
        'name' => 'Test Dean CCIT',
    ]);
    $dean->assignRole('program_head');

    StaffProfile::query()->create([
        'user_id' => $dean->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => $ccit->id,
        'program_codes' => [],
        'active_cycle_code' => 'SYN-CYCLE-2026',
    ]);

    $ccit->update(['dean_user_id' => $dean->id]);

    expect($ccit->programs->pluck('code')->all())->toContain('BSIT', 'BA-Psych')
        ->and($ccit->dean->id)->toBe($dean->id);

    // Actor scope automatically resolves all programs from department
    $scope = $dean->actorScope();
    expect($scope->departmentId)->toBe($ccit->id)
        ->and($scope->programCodes)->toContain('BSIT', 'BA-Psych');
});

test('program head receives a forbidden denied state for a foreign program', function (): void {
    seedE2Foundation();

    $programHead = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $foreignProgram = Program::query()->where('code', 'BA-Psych')->firstOrFail();

    actingAsMessagingActor($this, $programHead)
        ->get(route('program-head.show', $foreignProgram->code))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('ProgramHead/ProgramShow')
            ->where('page_state', 'denied'));
});

test('consultation admission support query enriches recommendations with real-time quota headroom', function (): void {
    seedE2Foundation();
    $this->seed(AdmissionBenchmarkSeeder::class);

    [$attempt, $applicant, $guidance, $registrar] = buildReleasedAdvisedAttempt($this, 'quota-check@example.test');

    $bsit = Program::query()->where('code', 'BSIT')->firstOrFail();
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $bsit->id, 'academic_cycle_id' => $attempt->academic_cycle_id],
        ['slots' => 15],
    );

    $query = app(ConsultationAdmissionSupportQuery::class);
    $data = $query->for($attempt);

    expect($data)->toHaveKey('models');
    $tabfm = $data['models'][0] ?? null;
    expect($tabfm)->not->toBeNull();

    $recs = $tabfm['recommendations'] ?? [];
    expect($recs)->not->toBeEmpty();

    $bsitRec = collect($recs)->firstWhere('program_code', 'BSIT');
    expect($bsitRec)->not->toBeNull()
        ->and($bsitRec)->toHaveKey('quota')
        ->and($bsitRec['quota']['configured'])->toBeTrue()
        ->and($bsitRec['quota']['capacity'])->toBe(15)
        ->and($bsitRec['quota']['status'])->toBeIn(['available', 'limited', 'full']);
});

test('official internal case thread can be opened and messages exchanged between guidance and program head', function (): void {
    seedE2Foundation();

    [$attempt, $applicant, $guidance, $registrar] = buildMessagingReadyAttempt($this, 'thread-test@example.test');
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'official-thread-pool');
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'official-thread-selection');

    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();

    // Guidance opens the internal thread with its first message and stable operation identity.
    actingAsMessagingActor($this, $guidance)
        ->post(route('program-head.threads.open', ['program' => $program->code, 'attempt' => $attempt->id]), [
            'subject' => 'Borderline Math Aptitude Review',
            'body' => 'Review the borderline Math aptitude result against the advising record.',
            'idempotency_key' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Internal case thread opened.');

    $thread = RecordThread::query()
        ->where('admission_attempt_id', $attempt->id)
        ->where('thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE)
        ->firstOrFail();

    expect($thread->subject)->toBe('Borderline Math Aptitude Review')
        ->and($thread->status)->toBe(RecordThread::STATUS_OPEN)
        ->and($thread->messages)->toHaveCount(1)
        ->and($thread->messages->first()->body)->toBe('Review the borderline Math aptitude result against the advising record.');

    // Program Head sends a deliberation response
    actingAsMessagingActor($this, $phUser)
        ->post(route('threads.messages.store', $thread->id), [
            'body' => 'Approved subject to completing Math 101 bridging course.',
            'idempotency_key' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Message sent.');

    expect($thread->fresh()->messages)->toHaveCount(2)
        ->and($thread->fresh()->messages->last()->body)->toBe('Approved subject to completing Math 101 bridging course.');

    // Audit event recorded
    $event = AuditEvent::query()->where('event_type', 'thread.message_sent')->latest('id')->first();
    expect($event)->not->toBeNull()
        ->and($event->payload['thread_id'])->toBe($thread->id);
});
