<?php

use App\Admission\Actions\MarkProgramGovernanceDiscussionRead;
use App\Admission\Actions\OpenProgramGovernanceDiscussion;
use App\Admission\Actions\SendProgramGovernanceMessage;
use App\Admission\Actions\SetProgramGovernanceDiscussionState;
use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramGovernanceMessage;
use App\Models\Admission\ProgramGovernanceRead;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Operation;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;

function governanceDiscussionFixture(): array
{
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'department_id' => null,
        'code' => 'GOV-DISC-'.fake()->unique()->numerify('####'),
        'name' => 'Governance Discussion Program',
        'is_active' => true,
    ]);
    $cycle = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'GOV-DISC-CYCLE-'.fake()->unique()->numerify('####'),
        'name' => 'Governance Discussion Cycle',
        'school_year' => '2026-2027',
        'semester' => 'First',
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => false,
    ]);
    $actor = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $actor->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'department_id' => null,
        'program_codes' => [$program->code],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $actor->givePermissionTo([
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
    ]);

    return [$actor, $program, $cycle];
}

test('discussion persists attributed raw messages, latest-first bounded paging, and exact command replays', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();

    $open = app(OpenProgramGovernanceDiscussion::class);
    $thread = $open->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'Tier review', 'discussion-open-1');
    $replayedThread = $open->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'Tier review', 'discussion-open-1');

    expect($thread->id)->toBe($replayedThread->id)
        ->and($thread->institution_id)->toBe($program->institution_id)
        ->and($thread->program_id)->toBe($program->id)
        ->and($thread->academic_cycle_id)->toBe($cycle->id)
        ->and($thread->created_by_user_id)->toBe($actor->id)
        ->and($thread->topic)->toBe(ProgramGovernanceThread::TOPIC_BENCHMARK)
        ->and($thread->status)->toBe(ProgramGovernanceThread::STATUS_OPEN)
        ->and(Schema::hasColumn('program_governance_threads', 'admission_attempt_id'))->toBeFalse();

    $send = app(SendProgramGovernanceMessage::class);
    $first = $send->handle($thread, $actor, $program, $cycle, '<script>keep as untrusted text</script>', 'discussion-message-1');
    $same = $send->handle($thread, $actor, $program, $cycle, '<script>keep as untrusted text</script>', 'discussion-message-1');
    expect(fn () => $send->handle($thread, $actor, $program, $cycle, 'Changed body', 'discussion-message-1'))
        ->toThrow(RuntimeException::class);
    $second = $send->handle($thread, $actor, $program, $cycle, 'Second governance message', 'discussion-message-2');
    $third = $send->handle($thread, $actor, $program, $cycle, 'Newest governance message', 'discussion-message-3');

    expect($same->id)->toBe($first->id)
        ->and(ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(3)
        ->and($first->body)->toContain('<script>')
        ->and($second->sender_user_id)->toBe($actor->id)
        ->and($third->created_at)->not->toBeNull();

    $history = app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle, perPage: 2);
    expect($history['messages'])->toHaveCount(2)
        ->and($history['messages'][0]['body'])->toBe('Second governance message')
        ->and($history['messages'][1]['body'])->toBe('Newest governance message')
        ->and($history['pagination'])->toMatchArray(['current_page' => 1, 'per_page' => 2, 'total' => 3, 'has_more' => true])
        ->and($history['can_read'])->toBeTrue()
        ->and($history['can_send'])->toBeTrue()
        ->and($history['thread']['has_applicant_details'])->toBeFalse();

    $older = app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle, page: 2, perPage: 2);
    $olderByCursor = app(ProgramGovernanceDiscussionQuery::class)->history(
        $thread,
        $actor,
        $program,
        $cycle,
        perPage: 2,
        beforeMessageId: $history['pagination']['next_before_message_id'],
    );
    expect($older['messages'])->toHaveCount(1)
        ->and($older['messages'][0]['id'])->toBe($first->id)
        ->and($olderByCursor['messages'][0]['id'])->toBe($first->id);

    $oversized = app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle, perPage: 5000);
    expect($oversized['pagination']['per_page'])->toBe(100);
});

test('closed history stays readable while send and state replay require current authorization', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Lifecycle review',
        'lifecycle-open',
    );
    $message = app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'Recorded before closure', 'lifecycle-message');
    $stateAction = app(SetProgramGovernanceDiscussionState::class);
    $closed = $stateAction->handle($thread, $actor, $program, $cycle, ProgramGovernanceThread::STATUS_CLOSED, 'lifecycle-close');
    $closedReplay = $stateAction->handle($thread, $actor, $program, $cycle, ProgramGovernanceThread::STATUS_CLOSED, 'lifecycle-close');

    expect($closed->status)->toBe(ProgramGovernanceThread::STATUS_CLOSED)
        ->and($closedReplay->id)->toBe($thread->id)
        ->and(app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle)['messages'][0]['id'])->toBe($message->id)
        ->and(app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle)['can_send'])->toBeFalse()
        ->and(fn () => app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'After closure', 'closed-message'))
        ->toThrow(RuntimeException::class);

    $actor->revokePermissionTo(CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ);
    expect(fn () => app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle))
        ->toThrow(AuthorizationException::class);
});

test('read markers are actor scoped, monotonic, auditable and cannot mark another thread message', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $open = app(OpenProgramGovernanceDiscussion::class);
    $thread = $open->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'Read test', 'read-open');
    $otherThread = $open->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'Other discussion', 'read-open-other');
    $send = app(SendProgramGovernanceMessage::class);
    $first = $send->handle($thread, $actor, $program, $cycle, 'One', 'read-message-one');
    $second = $send->handle($thread, $actor, $program, $cycle, 'Two', 'read-message-two');
    $foreign = $send->handle($otherThread, $actor, $program, $cycle, 'Foreign', 'read-message-foreign');

    $listing = app(ProgramGovernanceDiscussionQuery::class)->list($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, perPage: 1);
    expect($listing['threads'])->toHaveCount(1)
        ->and($listing['threads'][0]['id'])->toBe($otherThread->id)
        ->and($listing['threads'][0]['message_count'])->toBe(1)
        ->and($listing['threads'][0]['unread_count'])->toBe(0)
        ->and($listing['threads'][0]['unread'])->toBeFalse()
        ->and($listing['unread_threads'])->toBe(0)
        ->and($listing['thread_count'])->toBe(2)
        ->and($listing['pagination'])->toMatchArray(['current_page' => 1, 'per_page' => 1, 'total' => 2, 'has_more' => true]);

    $mark = app(MarkProgramGovernanceDiscussionRead::class);
    $marker = $mark->handle($thread, $actor, $program, $cycle, 'read-all', $second->id);
    $replay = $mark->handle($thread, $actor, $program, $cycle, 'read-all', $second->id);
    expect($marker->last_read_message_id)->toBe($second->id)
        ->and($replay->id)->toBe($marker->id)
        ->and(app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle)['unread_count'])->toBe(0);

    $later = $send->handle($thread, $actor, $program, $cycle, 'Three', 'read-message-three');
    $marker = $mark->handle($thread, $actor, $program, $cycle, 'read-later', $later->id);
    $marker = $mark->handle($thread, $actor, $program, $cycle, 'read-earlier', $first->id);
    expect($marker->last_read_message_id)->toBe($later->id)
        ->and(fn () => $mark->handle($thread, $actor, $program, $cycle, 'read-forged', $foreign->id))
        ->toThrow(RuntimeException::class)
        ->and(ProgramGovernanceRead::query()->where('program_governance_thread_id', $thread->id)->where('user_id', $actor->id)->count())->toBe(1);
});

test('discussion unread state counts other actors, acknowledges only empty threads, and respects displayed cursors', function (): void {
    [$sender, $program, $cycle] = governanceDiscussionFixture();
    $reader = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $reader->id,
        'institution_id' => $program->institution_id,
        'office' => 'program',
        'department_id' => null,
        'program_codes' => [$program->code],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $reader->givePermissionTo([
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
    ]);

    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $sender,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Empty thread subject',
        'unread-empty-open',
    );
    $query = app(ProgramGovernanceDiscussionQuery::class);
    expect($query->summary($sender, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK))
        ->toMatchArray(['thread_count' => 1, 'unread_threads' => 0])
        ->and($query->summary($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK))
        ->toMatchArray(['thread_count' => 1, 'unread_threads' => 1])
        ->and($query->list($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, search: 'Empty thread')['threads'][0]['unread_count'])
        ->toBe(0);

    $read = app(MarkProgramGovernanceDiscussionRead::class);
    $emptyAcknowledgment = $read->handle($thread, $reader, $program, $cycle, 'unread-empty-ack');
    expect($emptyAcknowledgment->last_read_message_id)->toBeNull()
        ->and($emptyAcknowledgment->last_read_at)->not->toBeNull()
        ->and($query->summary($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK)['unread_threads'])->toBe(0);

    $send = app(SendProgramGovernanceMessage::class);
    $first = $send->handle($thread, $sender, $program, $cycle, 'First reply', 'unread-first');
    $second = $send->handle($thread, $sender, $program, $cycle, 'Second reply', 'unread-second');
    expect(fn () => $read->handle($thread, $reader, $program, $cycle, 'unread-invalid-empty-ack'))
        ->toThrow(RuntimeException::class);
    $read->handle($thread, $reader, $program, $cycle, 'unread-first-read', $first->id);
    expect($query->history($thread, $reader, $program, $cycle)['unread_count'])->toBe(1)
        ->and($query->summary($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK)['unread_threads'])->toBe(1);

    $third = $send->handle($thread, $sender, $program, $cycle, 'Arrived after the displayed boundary', 'unread-third');
    expect($query->history($thread, $reader, $program, $cycle)['unread_count'])->toBe(2);
    $read->handle($thread, $reader, $program, $cycle, 'unread-all-displayed', $third->id);
    expect($query->summary($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK)['unread_threads'])->toBe(0);

    $ownMessage = $send->handle($thread, $reader, $program, $cycle, 'My reply is not unread to me', 'unread-own-message');
    expect($query->history($thread, $reader, $program, $cycle)['unread_count'])->toBe(0)
        ->and($query->summary($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK)['unread_threads'])->toBe(0)
        ->and($second->id)->toBeLessThan($third->id)
        ->and($ownMessage->sender_user_id)->toBe($reader->id);
    $laterOtherReply = $send->handle($thread, $sender, $program, $cycle, 'Another reply after the read marker', 'unread-fourth');
    expect($laterOtherReply->sender_user_id)->toBe($sender->id)
        ->and($query->summary($reader, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK)['unread_threads'])->toBe(1);

});

test('read authorization is checked before empty acknowledgment receipts', function (): void {
    [$owner, $program, $cycle] = governanceDiscussionFixture();
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $owner,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Read authority',
        'read-authority-open',
    );
    $owner->revokePermissionTo(CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ);

    expect(fn () => app(MarkProgramGovernanceDiscussionRead::class)->handle($thread, $owner, $program, $cycle, 'read-authority-ack'))
        ->toThrow(AuthorizationException::class)
        ->and(ProgramGovernanceRead::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(0)
        ->and(Operation::query()->where('type', 'admission.governance-discussion.read.mark')->count())->toBe(0);
    $owner->givePermissionTo([
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
    ]);
    $quotaThread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $owner,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_QUOTA,
        'Other topic authority',
        'read-other-topic-open',
    );
    $owner->revokePermissionTo(CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ);

    expect(fn () => app(MarkProgramGovernanceDiscussionRead::class)->handle($quotaThread, $owner, $program, $cycle, 'read-other-topic-ack'))
        ->toThrow(AuthorizationException::class)
        ->and(ProgramGovernanceRead::query()->where('program_governance_thread_id', $quotaThread->id)->count())->toBe(0)
        ->and(Operation::query()->where('type', 'admission.governance-discussion.read.mark')->count())->toBe(0);
});

test('forged route context and invalid quota references are denied without creating a thread', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $otherProgram = Program::query()->create([
        'institution_id' => $program->institution_id,
        'department_id' => null,
        'code' => 'GOV-DISC-OTHER-'.fake()->unique()->numerify('####'),
        'name' => 'Other Governance Program',
        'is_active' => true,
    ]);
    $otherCycle = AcademicCycle::query()->create([
        'institution_id' => $program->institution_id,
        'code' => 'GOV-DISC-OTHER-CYCLE-'.fake()->unique()->numerify('####'),
        'name' => 'Other Governance Cycle',
        'school_year' => '2027-2028',
        'semester' => 'First',
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonths(6)->toDateString(),
        'is_active' => false,
    ]);

    expect(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Forged revision context',
        'forged-reference',
        admissionBenchmarkRevisionId: PHP_INT_MAX,
    ))->toThrow(RuntimeException::class)
        ->and(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
            $actor,
            $otherProgram,
            $otherCycle,
            ProgramGovernanceThread::TOPIC_BENCHMARK,
            'Out of scope',
            'out-of-scope-open',
        ))->toThrow(AuthorizationException::class)
        ->and(ProgramGovernanceThread::query()->count())->toBe(0);

    $thread = app(OpenProgramGovernanceDiscussion::class)->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'Route binding', 'route-open');
    expect(fn () => app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $otherProgram, $cycle))
        ->toThrow(RuntimeException::class)
        ->and(fn () => app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $otherProgram, $cycle, 'Forged route program', 'forged-route-send'))
        ->toThrow(RuntimeException::class)
        ->and(fn () => app(SetProgramGovernanceDiscussionState::class)->handle($thread, $actor, $otherProgram, $cycle, ProgramGovernanceThread::STATUS_CLOSED, 'forged-route-state'))
        ->toThrow(RuntimeException::class)
        ->and(fn () => app(MarkProgramGovernanceDiscussionRead::class)->handle($thread, $actor, $otherProgram, $cycle, 'forged-route-read'))
        ->toThrow(RuntimeException::class)
        ->and(ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(0)
        ->and(ProgramGovernanceRead::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(0)
        ->and(fn () => app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $otherCycle, 'Forged route cycle', 'forged-route-cycle-send'))
        ->toThrow(RuntimeException::class);
});

test('audit failure rolls back discussion message and idempotency receipt', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'Audit failure', 'audit-open');
    app()->instance(RecordAuditEvent::class, new class extends RecordAuditEvent
    {
        public function handle(string $eventType, array $payload, string $actor = 'system'): AuditEvent
        {
            throw new RuntimeException('Audit storage unavailable.');
        }
    });

    expect(fn () => app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'Must roll back', 'audit-message'))
        ->toThrow(RuntimeException::class)
        ->and(ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(0)
        ->and(Operation::query()->where('type', 'admission.governance-discussion.message.send')->count())->toBe(0);

    expect(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Must roll back open',
        'audit-open-failure',
    ))->toThrow(RuntimeException::class)
        ->and(ProgramGovernanceThread::query()->count())->toBe(1)
        ->and(Operation::query()->where('type', 'admission.governance-discussion.open')->count())->toBe(1);

    expect(fn () => app(SetProgramGovernanceDiscussionState::class)->handle($thread, $actor, $program, $cycle, ProgramGovernanceThread::STATUS_CLOSED, 'audit-state'))
        ->toThrow(RuntimeException::class)
        ->and(fn () => app(MarkProgramGovernanceDiscussionRead::class)->handle($thread, $actor, $program, $cycle, 'audit-read'))
        ->toThrow(RuntimeException::class)
        ->and($thread->fresh()->status)->toBe(ProgramGovernanceThread::STATUS_OPEN)
        ->and(ProgramGovernanceRead::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(0);
});
test('benchmark revision references are accepted only for their exact program cycle', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $draft = AdmissionBenchmarkDraft::query()->create([
        'institution_id' => $program->institution_id,
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'base_benchmark_set_id' => null,
        'feature_schema_digest' => hash('sha256', 'features'),
        'current_version' => 1,
    ]);
    $revision = AdmissionBenchmarkRevision::query()->create([
        'draft_id' => $draft->id,
        'version' => 1,
        'actor_id' => $actor->id,
        'old_content' => null,
        'content' => ['tiers' => []],
        'reason' => 'Initial governance draft',
        'predecessor_revision_id' => null,
        'restored_from_revision_id' => null,
        'command_receipt' => 'fixture-revision-'.fake()->uuid(),
    ]);
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Revision-linked review',
        'revision-open-valid',
        admissionBenchmarkRevisionId: $revision->id,
    );

    $otherProgram = Program::query()->create([
        'institution_id' => $program->institution_id,
        'department_id' => null,
        'code' => 'GOV-DISC-REV-'.fake()->unique()->numerify('####'),
        'name' => 'Revision scope isolation',
        'is_active' => true,
    ]);
    StaffProfile::query()->where('user_id', $actor->id)->update(['program_codes' => [$program->code, $otherProgram->code]]);
    expect($thread->admission_benchmark_revision_id)->toBe($revision->id)
        ->and(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
            $actor,
            $otherProgram,
            $cycle,
            ProgramGovernanceThread::TOPIC_BENCHMARK,
            'Forged cross-program revision',
            'revision-open-forged',
            admissionBenchmarkRevisionId: $revision->id,
        ))->toThrow(RuntimeException::class)
        ->and(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
            $actor,
            $otherProgram,
            $cycle,
            ProgramGovernanceThread::TOPIC_BENCHMARK,
            'Changed context retry',
            'revision-open-valid',
        ))->toThrow(RuntimeException::class)
        ->and(ProgramGovernanceThread::query()->count())->toBe(1);
});

test('discussion operations do not mutate admission selection quota or release state', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $quota = ProgramQuota::query()->create([
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 7,
        'last_notice' => null,
        'last_mutated_by_user_id' => null,
    ]);
    $before = [
        'quotas' => ProgramQuota::query()->count(),
        'selections' => ProgramSelection::query()->count(),
        'pools' => ProgramPool::query()->count(),
        'follow_ups' => ProgramFollowUp::query()->count(),
        'decisions' => ConsultationDecision::query()->count(),
        'releases' => ResultRelease::query()->count(),
        'enrollments' => EnrollmentStamp::query()->count(),
        'benchmark_sets' => AdmissionBenchmarkSet::query()->count(),
        'benchmark_activations' => AdmissionBenchmarkActivation::query()->count(),
    ];
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_BENCHMARK, 'No business mutation', 'no-business-open');
    $message = app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'Approved: reserve this applicant and activate this benchmark.', 'no-business-message');
    app(MarkProgramGovernanceDiscussionRead::class)->handle($thread, $actor, $program, $cycle, 'no-business-read', $message->id);
    app(SetProgramGovernanceDiscussionState::class)->handle($thread, $actor, $program, $cycle, ProgramGovernanceThread::STATUS_READ_ONLY, 'no-business-state');

    expect($message->id)->toBeInt()
        ->and([
            'quotas' => ProgramQuota::query()->count(),
            'selections' => ProgramSelection::query()->count(),
            'pools' => ProgramPool::query()->count(),
            'follow_ups' => ProgramFollowUp::query()->count(),
            'decisions' => ConsultationDecision::query()->count(),
            'releases' => ResultRelease::query()->count(),
            'enrollments' => EnrollmentStamp::query()->count(),
            'benchmark_sets' => AdmissionBenchmarkSet::query()->count(),
            'benchmark_activations' => AdmissionBenchmarkActivation::query()->count(),
        ])->toBe($before)
        ->and($quota->refresh()->slots)->toBe(7);
});

test('discussion topic capabilities do not cross-authorize another topic', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();

    expect(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_QUOTA,
        'Quota discussion',
        'quota-with-benchmark-only',
    ))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ProgramGovernanceDiscussionQuery::class)->list($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_ADVISING))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(ProgramGovernanceDiscussionQuery::class)->summary($actor, $program, $cycle, ProgramGovernanceThread::TOPIC_ADVISING))
        ->toThrow(AuthorizationException::class)
        ->and(ProgramGovernanceThread::query()->count())->toBe(0);
});

test('closed academic cycles retain authorized history but reject thread writes', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Archived history',
        'archived-thread-open',
    );
    app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'Readable archive message', 'archived-message');
    $cycle->update(['closes_on' => now()->subDay()->toDateString()]);

    expect(app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle)['messages'])->toHaveCount(1)
        ->and(fn () => app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'Cannot append to archive', 'archived-message-write'))
        ->toThrow(AuthorizationException::class);
});

test('preloaded staff identity does not retain participation after program assignment revocation', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Revocation test',
        'revocation-thread-open',
    );
    $actor->load('staffProfile');
    $actor->staffProfile->update(['program_codes' => []]);

    expect(fn () => app(SendProgramGovernanceMessage::class)->handle($thread, $actor, $program, $cycle, 'Lost scope', 'revoked-scope-message'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(ProgramGovernanceDiscussionQuery::class)->history($thread, $actor, $program, $cycle))
        ->toThrow(AuthorizationException::class)
        ->and(ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(0);
});

test('revoked send capability denies an otherwise exact message replay', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_BENCHMARK,
        'Replay authorization',
        'replay-auth-open',
    );
    $send = app(SendProgramGovernanceMessage::class);
    $message = $send->handle($thread, $actor, $program, $cycle, 'Original message receipt', 'replay-auth-message');
    $actor->revokePermissionTo(CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND);

    expect(fn () => $send->handle($thread, $actor, $program, $cycle, 'Original message receipt', 'replay-auth-message'))
        ->toThrow(AuthorizationException::class)
        ->and(ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id)->count())->toBe(1)
        ->and(ProgramGovernanceMessage::query()->findOrFail($message->id)->body)->toBe('Original message receipt');
});

test('quota receipt references are validated against the same program cycle topic', function (): void {
    [$actor, $program, $cycle] = governanceDiscussionFixture();
    $actor->givePermissionTo([
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
        CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
    ]);
    $quota = ProgramQuota::query()->create([
        'program_id' => $program->id,
        'academic_cycle_id' => $cycle->id,
        'slots' => 10,
    ]);
    $change = ProgramQuotaChange::query()->create([
        'program_quota_id' => $quota->id,
        'actor_user_id' => $actor->id,
        'from_slots' => null,
        'to_slots' => 10,
        'notice' => 'Initial scoped quota receipt',
        'created_at' => now(),
    ]);
    $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
        $actor,
        $program,
        $cycle,
        ProgramGovernanceThread::TOPIC_QUOTA,
        'Quota receipt review',
        'quota-receipt-valid',
        quotaChangeId: $change->id,
    );
    $otherProgram = Program::query()->create([
        'institution_id' => $program->institution_id,
        'department_id' => null,
        'code' => 'GOV-DISC-QUOTA-'.fake()->unique()->numerify('####'),
        'name' => 'Quota reference isolation',
        'is_active' => true,
    ]);
    StaffProfile::query()->where('user_id', $actor->id)->update(['program_codes' => [$program->code, $otherProgram->code]]);

    expect($thread->quota_change_id)->toBe($change->id)
        ->and(fn () => app(OpenProgramGovernanceDiscussion::class)->handle(
            $actor,
            $otherProgram,
            $cycle,
            ProgramGovernanceThread::TOPIC_QUOTA,
            'Forged quota context',
            'quota-receipt-forged',
            quotaChangeId: $change->id,
        ))->toThrow(RuntimeException::class)
        ->and(ProgramGovernanceThread::query()->count())->toBe(1);
});
