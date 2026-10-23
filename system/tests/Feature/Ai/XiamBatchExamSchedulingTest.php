<?php

declare(strict_types=1);

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Contracts\AiGateway;
use App\Application\Capabilities\CapabilityDefinitions;
use App\Application\Capabilities\Schema\CapabilityInputValidator;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config([
        'xiam.process_inline' => true,
        'xiam.ai.gateway' => 'laravel',
        'xiam.enabled' => true,
    ]);
});

test('batch assignment proposals with legacy candidate arrays are rejected', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()
        ->where('institution_id', $guidance->actorScope()->institutionId)
        ->where('is_active', true)
        ->firstOrFail();
    $room = Room::query()
        ->where('institution_id', $cycle->institution_id)
        ->where('is_active', true)
        ->orderBy('id')
        ->firstOrFail();
    $session = ExamSession::factory()->for($cycle, 'academicCycle')->for($room)->create([
        'code' => 'EX-PUB-READY',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
    ]);

    $candidateRefs = ['APP-2026-99501', 'APP-2026-99502'];
    foreach ($candidateRefs as $index => $candidateRef) {
        $identity = ApplicantIdentity::query()->create([
            'institution_id' => $cycle->institution_id,
            'normalized_key' => 'ID-'.$candidateRef,
            'first_name' => 'Private'.($index + 1),
            'last_name' => 'Candidate',
            'birthdate' => '2006-01-0'.($index + 1),
            'sex' => 'female',
            'email' => 'private'.($index + 1).'@example.test',
            'phone' => '+63917123456'.($index + 1),
        ]);
        AdmissionAttempt::query()->create([
            'applicant_identity_id' => $identity->id,
            'institution_id' => $cycle->institution_id,
            'academic_cycle_id' => $cycle->id,
            'reference_code' => $candidateRef,
            'status' => AdmissionAttempt::STATUS_ACCEPTED,
            'preferred_program_code' => 'BSCS',
            'strand' => 'STEM',
            'accepted_at' => now()->subDays(2 - $index),
            'version' => 1,
        ]);
    }
    $proposalCountBefore = XiamProposal::query()->count();
    $assignmentCountBefore = ExamAssignment::query()->count();

    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake([
        new ToolCall('candidate-pool-read', 'scheduling_candidate_pool_list_1', ['limit' => 10]),
        new ToolCall('batch-assignment-proposal', 'exams_session_assign_1', [
            'session_id' => (int) $session->id,
            'candidate_refs' => $candidateRefs,
        ]),
        'Bulk assignment is not available through Xiam. Please assign candidates in bulk at /exams/sessions.',
    ]);

    $this->actingAs($guidance)->postJson(route('xiam.mode.update'), ['mode' => 'assistant'])->assertOk();
    xiamKeepCurrentSessionCookie();

    $accepted = $this->actingAs($guidance)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Can we schedule candidates into session EX-PUB-READY?',
            'idempotency_key' => 'batch-scheduling-candidate-pool-turn',
            'page_pack_key' => 'staff.exams.sessions.index@1',
            'page_pack_version' => 1,
        ])
        ->assertAccepted()
        ->json();

    $run = XiamRun::query()->findOrFail($accepted['run_id']);
    $events = XiamRunEvent::query()->where('run_id', $run->id)->orderBy('seq')->get();
    $toolCalls = $events->where('type', 'tool.call')->values();
    $resultByTool = $events->where('type', 'tool.result')->keyBy('payload.tool');
    $assignmentResult = $resultByTool['exams.session.assign@1'];
    $definition = collect(CapabilityDefinitions::all())
        ->first(fn ($capability): bool => $capability->id() === 'exams.session.assign@1');

    expect($definition)->not->toBeNull()
        ->and($definition->inputSchema['properties'])->toHaveKeys(['session_id', 'admission_attempt_id'])
        ->and($definition->inputSchema['properties'])->not->toHaveKeys(['candidate_refs', 'admission_attempt_ids'])
        ->and($definition->inputSchema['properties']['admission_attempt_id'])->toBe(['type' => 'integer'])
        ->and($definition->inputSchema['required'])->toBe(['session_id'])
        ->and(app(CapabilityInputValidator::class)->validate($definition->inputSchema, [
            'session_id' => (int) $session->id,
            'candidate_refs' => $candidateRefs,
        ]))->toBe('$.candidate_refs:unknown_field')
        ->and(app(CapabilityInputValidator::class)->validate($definition->inputSchema, [
            'session_id' => (int) $session->id,
            'admission_attempt_ids' => [1, 2],
        ]))->toBe('$.admission_attempt_ids:unknown_field')
        ->and($run->fresh()->status)->toBe(XiamRun::STATUS_SUCCEEDED)
        ->and($toolCalls->pluck('payload.tool')->all())
        ->toContain('scheduling.candidate_pool.list@1', 'exams.session.assign@1')
        ->and($resultByTool['scheduling.candidate_pool.list@1']->payload['successful'])->toBeTrue()
        ->and($assignmentResult->payload['successful'])->toBeFalse()
        ->and($events->firstWhere('type', 'skill.proposal'))->toBeNull()
        ->and(XiamProposal::query()->count())->toBe($proposalCountBefore)
        ->and(ExamAssignment::query()->count())->toBe($assignmentCountBefore);
});
