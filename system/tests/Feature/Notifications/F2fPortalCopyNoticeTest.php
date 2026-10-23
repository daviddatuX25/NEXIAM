<?php

use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Printing\PrintRenderer;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function f2fPortalNoticeFixture(): array
{
    seedE2Foundation();
    Storage::fake('sensitive');
    config(['mail.default' => 'array']);
    $attempt = submitSyntheticApplicant('portal-copy-notice@example.test', 'PortalCopyNotice')['attempt'];
    $attempt->update(['status' => 'accepted']);
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $bytes = app(PrintRenderer::class)->render('<h1>Official frozen examination result</h1>');
    $path = 'results/'.$attempt->id.'/portal-copy-notice.pdf';
    app(SensitiveAssetStore::class)->store($bytes, $path);
    $document = ResultDocument::query()->create([
        'admission_attempt_id' => $attempt->id,
        'status' => ResultDocument::STATUS_READY,
        'template_version' => 'notice-fixture',
        'checksum' => hash('sha256', 'notice-frozen-document'),
        'artifact_manifest' => ['pdf' => ['status' => 'ready', 'path' => $path, 'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes)]],
    ]);
    $attempt->forceFill(['current_result_document_id' => $document->id])->save();
    $decision = ConsultationDecision::query()->create([
        'admission_attempt_id' => $attempt->id,
        'counselor_user_id' => $actor->id,
        'decision_type' => 'approve',
        'idempotency_key' => 'portal-copy-notice-decision',
    ]);
    $encounter = ConsultationEncounter::query()->create([
        'institution_id' => $attempt->institution_id,
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'result_document_version' => $document->version ?? 1,
        'counselor_user_id' => $actor->id,
        'medium' => ConsultationEncounter::MEDIUM_F2F,
        'booking_path' => ConsultationEncounter::BOOKING_PATH_DIRECT,
        'status' => ConsultationEncounter::STATUS_OUTCOME_RECORDED,
        'closed_at' => now(),
        'idempotency_key' => 'portal-copy-notice-encounter',
    ]);
    $release = ResultRelease::query()->create([
        'admission_attempt_id' => $attempt->id,
        'result_document_id' => $document->id,
        'released_by_user_id' => $actor->id,
        'release_version' => 1,
        'is_current' => true,
        'released_at' => now(),
        'idempotency_key' => 'portal-copy-notice-release',
        'mode' => 'online',
        'channel' => 'portal',
        'consultation_decision_id' => $decision->id,
    ]);
    $release->forceFill(['consultation_encounter_id' => $encounter->id])->save();
    $delivery = NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => (string) Str::uuid(),
        'event_type' => 'result.digital_issued.v1',
        'template_key' => 'result.digital_issued',
        'template_version' => 1,
        'recipient_type' => ApplicantIdentity::class,
        'recipient_id' => $attempt->applicant_identity_id,
        'channel' => NotificationDelivery::CHANNEL_MAIL,
        'status' => NotificationDelivery::STATUS_PENDING,
        'payload' => [
            'institution_id' => $attempt->institution_id,
            'attempt_id' => $attempt->id,
            'identity_id' => $attempt->applicant_identity_id,
            'release_id' => $release->id,
            'subject' => 'Your digital result is available',
            'body' => 'Your official result is available in My Results.',
            'action_label' => 'View Results',
            'action_url' => route('applicant.results.show'),
        ],
    ]);

    return compact('attempt', 'document', 'decision', 'release', 'delivery', 'actor');
}

function processF2fPortalCopyNotice(NotificationDelivery $delivery): void
{
    app()->call([new ProcessNotificationDelivery($delivery->id), 'handle']);
}

test('digital copy mail points to authenticated My Results without attaching the private PDF', function (): void {
    $fixture = f2fPortalNoticeFixture();
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $transport->flush();
    processF2fPortalCopyNotice($fixture['delivery']);

    expect($fixture['delivery']->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    $message = $transport->messages()->last()->getOriginalMessage();
    expect($message->getHtmlBody())->toContain('/applicant/results')
        ->not->toContain('portal-copy-notice.pdf')
        ->and($message->getAttachments())->toBe([]);
});

test('queued portal-copy mail is suppressed after its approved decision is superseded', function (): void {
    $fixture = f2fPortalNoticeFixture();
    ConsultationDecision::query()->create([
        'admission_attempt_id' => $fixture['attempt']->id,
        'counselor_user_id' => $fixture['actor']->id,
        'decision_type' => 'defer',
        'version' => 2,
        'idempotency_key' => 'portal-copy-notice-later-defer',
    ]);
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $transport->flush();
    processF2fPortalCopyNotice($fixture['delivery']);

    expect($fixture['delivery']->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($transport->messages()->all())->toBe([]);
});

test('queued portal-copy mail is suppressed when the stored official artifact disappears', function (): void {
    $fixture = f2fPortalNoticeFixture();
    Storage::disk('sensitive')->delete($fixture['document']->artifact_manifest['pdf']['path']);
    $transport = Mail::mailer('array')->getSymfonyTransport();
    $transport->flush();
    processF2fPortalCopyNotice($fixture['delivery']);

    expect($fixture['delivery']->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and($transport->messages()->all())->toBe([]);
});
