<?php

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Queries\CaseConversationQuery;
use App\Admission\Support\CaseMessageRecipients;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

require_once __DIR__.'/../../Support/CaseMessagingHelpers.php';

/**
 * @return array{attempt: AdmissionAttempt, applicant: User, guidance: User, phUser: User, program: Program}
 */
function conversationCohesionFixture(TestCase $test, string $email): array
{
    seedE2Foundation();
    [$attempt, $applicant, $guidance] = buildMessagingReadyAttempt($test, $email);
    $program = Program::query()->where('code', 'BSN')->firstOrFail();
    app(SetGuidanceProgramPool::class)->handle($attempt, $guidance, $program->code, [
        ['program_code' => $program->code, 'display_order' => 1, 'basis' => 'preference'],
    ], null, 'conversation-cohesion-pool-'.md5($email));
    app(AcknowledgeAssistedProgramSelection::class)->handle($attempt, $applicant, 'conversation-cohesion-selection-'.md5($email));
    $phUser = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $phUser->staffProfile->update(['program_codes' => [$program->code]]);

    return [
        'attempt' => $attempt,
        'applicant' => $applicant,
        'guidance' => $guidance,
        'phUser' => $phUser->fresh(),
        'program' => $program,
    ];
}

test('case message recipients include the current applicant identity and exclude the sender', function (): void {
    $fixture = conversationCohesionFixture($this, 'cohesion-recipient-scope@example.test');
    Queue::fake();
    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Current participant notice',
        'A generic notice should go to the current applicant.',
        $fixture['phUser'],
        (string) Str::uuid(),
    );

    $recipients = app(CaseMessageRecipients::class)->recipientsFor($thread, $fixture['phUser']);

    expect($recipients->contains(fn (User|ApplicantIdentity $recipient): bool => $recipient instanceof ApplicantIdentity
        && (int) $recipient->id === (int) $fixture['attempt']->applicant_identity_id))->toBeTrue()
        ->and($recipients->contains(fn (User|ApplicantIdentity $recipient): bool => $recipient instanceof User
            && (int) $recipient->id === (int) $fixture['phUser']->id))->toBeFalse();
    expect(NotificationDelivery::query()
        ->where('recipient_type', ApplicantIdentity::class)
        ->where('recipient_id', $fixture['attempt']->applicant_identity_id)
        ->where('payload->thread_id', $thread->id)
        ->exists())->toBeTrue();
});

test('authorized support opening succeeds without creating a notice when no eligible recipient remains', function (): void {
    $fixture = conversationCohesionFixture($this, 'cohesion-no-recipient@example.test');
    Queue::fake();
    $fixture['applicant']->applicantAccount()->delete();
    User::query()
        ->where('id', '!=', $fixture['phUser']->id)
        ->whereHas('staffProfile', fn ($profiles) => $profiles->where('office', 'program'))
        ->get()
        ->each(fn (User $user) => $user->staffProfile()->update(['status' => StaffProfile::STATUS_INACTIVE]));

    $thread = app(OpenApplicantSupportConversation::class)->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'No eligible recipient',
        'The authorized opener must still commit.',
        $fixture['phUser'],
        (string) Str::uuid(),
    );

    expect($thread->exists)->toBeTrue()
        ->and($thread->messages()->count())->toBe(1)
        ->and(app(CaseMessageRecipients::class)->recipientsFor($thread, $fixture['phUser']))->toBeEmpty()
        ->and(NotificationDelivery::query()->where('payload->thread_id', $thread->id)->exists())->toBeFalse();
});

test('summary continuation crosses more than five hundred denied candidates without skipping an authorized root', function (): void {
    $fixture = conversationCohesionFixture($this, 'cohesion-authorized-continuation@example.test');
    Queue::fake();
    $open = app(OpenApplicantSupportConversation::class);
    $firstReadable = $open->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'First readable root',
        'First readable message',
        $fixture['phUser'],
        (string) Str::uuid(),
    );
    $secondReadable = $open->handle(
        $fixture['attempt'],
        $fixture['program']->id,
        'Second readable root',
        'Second readable message',
        $fixture['phUser'],
        (string) Str::uuid(),
    );
    $messages = RecordThreadMessage::query()->whereIn('record_thread_id', [$firstReadable->id, $secondReadable->id])->get();
    $messages->firstWhere('record_thread_id', $firstReadable->id)->update(['created_at' => '2025-01-01 00:00:00']);
    $messages->firstWhere('record_thread_id', $secondReadable->id)->update(['created_at' => '2024-01-01 00:00:00']);

    $otherProgram = Program::query()->where('id', '!=', $fixture['program']->id)->where('institution_id', $fixture['attempt']->institution_id)->firstOrFail();
    for ($index = 1; $index <= 501; $index++) {
        $denied = RecordThread::query()->create([
            'admission_attempt_id' => $fixture['attempt']->id,
            'program_id' => $otherProgram->id,
            'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
            'subject' => "Denied candidate {$index}",
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $fixture['phUser']->id,
            'latest_message_sequence' => 1,
        ]);
        RecordThreadMessage::query()->create([
            'record_thread_id' => $denied->id,
            'sender_user_id' => $fixture['phUser']->id,
            'sequence' => 1,
            'body' => 'Denied candidate message',
            'created_at' => now()->addSeconds($index),
        ]);
    }

    $query = app(CaseConversationQuery::class);
    $firstPage = $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
        perPage: 1,
    );
    $secondPage = $query->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['phUser'],
        page: 2,
        perPage: 1,
        cursor: $firstPage['pagination']['next_cursor'],
    );

    expect($firstPage['data'][0]['id'])->toBe((string) $firstReadable->id)
        ->and($firstPage['pagination']['has_more'])->toBeTrue()
        ->and($firstPage['pagination']['next_cursor'])->toBeString()
        ->and($secondPage['data'])->toHaveCount(1)
        ->and($secondPage['data'][0]['id'])->toBe((string) $secondReadable->id)
        ->and($secondPage['pagination']['has_more'])->toBeFalse();
});

test('denied-only summary scope does not expose candidate continuation', function (): void {
    $fixture = conversationCohesionFixture($this, 'cohesion-denied-only@example.test');
    Queue::fake();

    for ($index = 1; $index <= 501; $index++) {
        $denied = RecordThread::query()->create([
            'admission_attempt_id' => $fixture['attempt']->id,
            'program_id' => $fixture['program']->id,
            'thread_type' => RecordThread::TYPE_APPLICANT_SUPPORT,
            'subject' => "Denied-only candidate {$index}",
            'status' => RecordThread::STATUS_OPEN,
            'created_by_user_id' => $fixture['phUser']->id,
            'latest_message_sequence' => 1,
        ]);
        RecordThreadMessage::query()->create([
            'record_thread_id' => $denied->id,
            'sender_user_id' => $fixture['phUser']->id,
            'sequence' => 1,
            'body' => 'Denied-only candidate message',
            'created_at' => now()->addSeconds($index),
        ]);
    }

    $page = app(CaseConversationQuery::class)->summariesForAttempt(
        $fixture['attempt'],
        RecordThread::TYPE_APPLICANT_SUPPORT,
        $fixture['guidance'],
        perPage: 1,
    );

    expect($page['data'])->toBeEmpty()
        ->and($page['pagination']['has_more'])->toBeFalse()
        ->and($page['pagination']['next_cursor'])->toBeNull();
});
