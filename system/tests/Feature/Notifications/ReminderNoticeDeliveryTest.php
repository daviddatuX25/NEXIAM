<?php

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Channels\MailDeliveryChannel;
use App\Notifications\Mail\NotificationMailable;
use App\Notifications\NotificationBusinessValidity;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationIntent;
use App\Notifications\NotificationIntentDispatcher;
use App\Notifications\NotificationProjection;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Queries\NotificationOccurrenceQuery;
use App\Notifications\Templates\Email\NoticeEmailAssetVerifier;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\Email\NoticeLegacyEmailRenderer;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\PendingMail;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

beforeEach(function (): void {
    seedE2Foundation();
    Queue::fake();
    Mail::fake();
});

function reminderNoticeFixture(string $text = 'Hello {{applicant_name}}: {{session_code}}, {{room_name}} at {{exam_time}}. {{institution_name}}'): array
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $template = CommunicationTemplate::query()->create(['institution_id' => $cycle->institution_id,
        'domain' => 'examination', 'slug' => 'reminder', 'event_key' => 'exam.reminder.v1',
        'name' => 'Countdown Reminder', 'active_version' => 1]);
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => $text]]];
    $version = $template->versions()->create(['version' => 1, 'content_schema_version' => 'notice-content-2',
        'subject' => 'Reminder for {{applicant_name}}', 'body' => 'Configured portal caption is not the email body',
        'email_subject' => '', 'email_body' => '', 'in_app_title' => '', 'in_app_body' => '',
        'email_blueprint' => $blueprint, 'email_renderer_version' => 'notice-email-1',
        'content_sha256' => hash('sha256', json_encode($blueprint, JSON_THROW_ON_ERROR)),
        'document_mode' => 'none', 'created_by_user_id' => $actor->id]);
    $session = ExamSession::query()->create(['academic_cycle_id' => $cycle->id, 'room_id' => $room->id,
        'code' => 'FROZEN-REMINDER', 'status' => ExamSession::STATUS_PUBLISHED,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY, 'starts_at' => now()->addHours(24),
        'effective_end_at' => now()->addHours(26), 'version' => 7,
        'notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(7)]);
    $applicants = [];
    foreach (['Alpha', 'Beta'] as $name) {
        $applicant = submitSyntheticApplicant('frozen-'.strtolower($name).'@example.test', $name);
        $user = app(AuthenticateApplicantWithAccessKey::class)->handle($applicant['access_token'], '2001-01-01');
        $assignment = ExamAssignment::query()->create(['exam_session_id' => $session->id,
            'admission_attempt_id' => $applicant['attempt']->id, 'status' => ExamAssignment::STATUS_ASSIGNED,
            'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED, 'version' => 1]);
        $applicants[] = ['identity' => $applicant['attempt']->identity, 'attempt' => $applicant['attempt'],
            'assignment' => $assignment, 'user' => $user];
    }

    return compact('cycle', 'room', 'actor', 'template', 'version', 'session', 'applicants');
}

function reminderNoticeEmit(): void
{
    expect(Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']))->toBe(0);
}

function reminderNoticeRows(array $applicant): Collection
{
    return NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')
        ->where('recipient_id', $applicant['identity']->id)->get();
}

function reminderNoticeProcess(NotificationDelivery $delivery): void
{
    app()->call([(new ProcessNotificationDelivery($delivery->id)), 'handle']);
}

function reminderNoticeImage(array &$fixture): array
{
    Storage::fake('local');
    $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=', true);
    $id = (string) Str::uuid();
    $path = 'communication-templates/'.$fixture['template']->id.'/'.$id.'.png';
    Storage::disk('local')->put($path, $bytes);
    $asset = CommunicationTemplateDocument::query()->create(['id' => $id,
        'communication_template_id' => $fixture['template']->id, 'institution_id' => $fixture['cycle']->institution_id,
        'original_name' => 'header.png', 'storage_path' => $path, 'checksum' => hash('sha256', $bytes),
        'mime_type' => 'image/png', 'file_size_bytes' => strlen($bytes), 'created_by_user_id' => $fixture['actor']->id]);
    $blueprint = $fixture['version']->email_blueprint;
    $blueprint['blocks'][] = ['type' => 'image', 'asset_id' => $asset->id, 'alt' => 'Local logo for {{applicant_name}}', 'width' => '100%'];
    reminderNoticePublish($fixture, ['email_blueprint' => $blueprint]);

    return compact('asset', 'bytes', 'path');
}

test('real reminder freezes two actual recipients without any renderer storage bytes print or provider before commit', function (): void {
    $fixture = reminderNoticeFixture();
    reminderNoticeImage($fixture);
    app()->instance(NoticeEmailRenderer::class, Mockery::mock(NoticeEmailRenderer::class)->shouldNotReceive('render')->getMock());
    app()->instance(NoticeEmailAssetVerifier::class, Mockery::mock(NoticeEmailAssetVerifier::class)->shouldNotReceive('verify')->getMock());
    Storage::shouldReceive('disk')->never();
    reminderNoticeEmit();
    Mail::assertNothingSent();
    foreach ($fixture['applicants'] as $applicant) {
        $rows = reminderNoticeRows($applicant);
        expect($rows)->toHaveCount(2);
        $payload = $rows->first()->payload;
        expect($payload['snapshot_schema_version'])->toBe('notice-delivery-2')
            ->and($payload['recipient']['id'])->toBe($applicant['identity']->id)
            ->and($payload['context']['values']['applicant_name'])->toBe($applicant['identity']->fullName())
            ->and($payload['context']['values']['institution_name'])->toBe($fixture['cycle']->institution->name)
            ->and($payload['context']['values'])->not->toHaveKeys(['building', 'seat_number', 'email', 'access_key'])
            ->and($payload['email']['preparation_failure'])->toBeNull()
            ->and($payload['print'])->toBeNull()
            ->and($payload)->not->toHaveKeys(['subject', 'body', 'event_context', 'session_id', 'document_snapshot'])
            ->and($rows[0]->payload)->toBe($rows[1]->payload);
    }
    $original = NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')->get()->pluck('payload', 'id')->all();
    reminderNoticeEmit();
    expect(NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')->get()->pluck('payload', 'id')->all())->toBe($original);
    $activityQuery = app(NotificationOccurrenceQuery::class);
    $activity = $activityQuery->activity($fixture['actor'], $activityQuery->accessibleDomains($fixture['actor']), ['domain' => 'examination']);
    expect($activity->total())->toBe(2);
    $occurrence = $activityQuery->occurrence($fixture['actor'], $activity->items()[0]['occurrence_id']);
    expect($occurrence['resource_id'])->toBe((string) $fixture['session']->id)
        ->and($activityQuery->deliveries($fixture['actor'], $occurrence)->total())->toBe(2);
});

test('one missing applicant fact freezes deterministic email failure while actual own bell list seen and read stay independent', function (): void {
    $fixture = reminderNoticeFixture();
    $first = $fixture['applicants'][0];
    $second = $fixture['applicants'][1];
    $first['identity']->update(['first_name' => '', 'middle_name' => null, 'last_name' => '', 'suffix' => null]);
    reminderNoticeEmit();
    $rows = reminderNoticeRows($first);
    $inbox = $rows->firstWhere('channel', 'in_app');
    $mail = $rows->firstWhere('channel', 'mail');
    expect($mail->payload['context']['missing_required_keys'])->toBe(['applicant_name'])
        ->and($mail->payload['email']['preparation_failure'])->toBe(['code' => 'email_context_missing', 'missing_context_keys' => ['applicant_name']]);
    $original = $mail->payload;
    reminderNoticeProcess($inbox);
    reminderNoticeProcess($mail);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_RETRYING)
        ->and($mail->fresh()->last_error)->toBe('email_context_missing:applicant_name')
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    Mail::assertNothingSent();
    $projection = app(NotificationProjection::class);
    $bell = $projection->forUser($first['user']);
    $list = collect($projection->list($first['user']))->firstWhere('id', $inbox->id);
    expect($bell['unread_count'])->toBeGreaterThanOrEqual(1)
        ->and($list['subject'])->toBe($original['portal']['title'])
        ->and($list['body'])->toBe($original['portal']['body'])
        ->and($list['action_url'])->toBe($original['portal']['action_url'])
        ->and($list['action_label'])->toBe($original['portal']['action_label'])
        ->and($list['email_delivery']['status'])->toBe(NotificationDelivery::STATUS_RETRYING)
        ->and($inbox->fresh()->seen_at)->not->toBeNull()
        ->and($inbox->fresh()->read_at)->toBeNull()
        ->and(collect($projection->list($second['user']))->pluck('id'))->not->toContain($inbox->id);
    $this->actingAs($second['user'])->postJson(route('notifications.read', $inbox))->assertForbidden();
    $unread = $projection->unreadCount($first['user']);
    $this->actingAs($first['user'])->postJson(route('notifications.read', $inbox))->assertOk();
    expect($projection->unreadCount($first['user']))->toBe($unread - 1)
        ->and($mail->fresh()->read_at)->toBeNull();
    $first['identity']->update(['first_name' => 'Now Available']);
    $mail->update(['next_attempt_at' => now()->subSecond()]);
    reminderNoticeProcess($mail);
    expect($mail->fresh()->payload)->toBe($original)
        ->and($mail->fresh()->last_error)->toBe('email_context_missing:applicant_name');
    [$mailer, $transport] = reminderNoticeActualMailer();
    $otherMail = reminderNoticeRows($second)->firstWhere('channel', 'mail');
    reminderNoticeProcess($otherMail);
    expect($otherMail->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($transport->messages())->toHaveCount(1)
        ->and($mailer->captured[0]->hasTo($second['identity']->email))->toBeTrue()
        ->and($mailer->captured[0]->renderedEmail['text'])->toContain($second['identity']->fullName());
});

test('missing building source fails only when referenced and never substitutes a sample or room alias', function (): void {
    $fixture = reminderNoticeFixture('Building {{building}}, room {{room_name}}');
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    expect($mail->payload['context']['values'])->not->toHaveKey('building')
        ->and($mail->payload['context']['missing_required_keys'])->toBe(['building']);
    reminderNoticeProcess($mail);
    expect($mail->fresh()->last_error)->toBe('email_context_missing:building');
    Mail::assertNothingSent();
});

test('frozen pinned renderer and CID MIME bytes retry unchanged after publication and mutable fact changes', function (): void {
    $fixture = reminderNoticeFixture();
    $image = reminderNoticeImage($fixture);
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    $original = $mail->payload;
    [$mailer, $transport] = reminderNoticeActualMailer();
    $assets = app(NoticeEmailAssetVerifier::class)->verify($original['institution_id'], $original['template']['id'],
        $original['email']['blueprint'], $original['email']['assets']);
    $first = app(NoticeEmailRenderer::class)->render($original['email']['blueprint'], $original['context']['values'],
        array_keys($original['context']['values']), $original['template']['email_schema_version'],
        $original['template']['email_renderer_version'], $assets, $original['email']['subject_template']);
    Storage::disk('local')->put($image['path'], 'initial asset preparation failure');
    reminderNoticeProcess($mail);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_RETRYING)
        ->and($transport->messages())->toHaveCount(0);
    Storage::disk('local')->put($image['path'], $image['bytes']);
    reminderNoticePublish($fixture, [
        'subject' => 'Newest must not be substituted',
        'email_renderer_version' => 'notice-email-2', 'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => 'New template body']]],
    ]);
    app()->instance(NoticeEmailRenderer::class, new class extends NoticeEmailRenderer
    {
        public function render(array $blueprint, array $context, array $allowedTokens, string $schemaVersion, string $rendererVersion, array $assets = [], string $subject = ''): array
        {
            if ($rendererVersion === 'notice-email-2') {
                return parent::render($blueprint, $context, $allowedTokens, $schemaVersion, 'notice-email-1', $assets, $subject.' changed-v2');
            }

            return parent::render($blueprint, $context, $allowedTokens, $schemaVersion, $rendererVersion, $assets, $subject);
        }
    });
    $fixture['applicants'][0]['identity']->update(['first_name' => 'Changed Current Name']);
    $fixture['room']->update(['name' => 'Changed Current Room']);
    $fixture['session']->update(['code' => 'CHANGED-CURRENT', 'version' => 8]);
    expect(app(NotificationBusinessValidity::class)->isCurrent($mail))->toBeTrue();
    $mail->update(['next_attempt_at' => now()->subSecond()]);
    reminderNoticeProcess($mail);
    $retry = $mailer->captured[0];
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($transport->messages())->toHaveCount(1)
        ->and($retry->renderedEmail)->toBe($first)
        ->and($mail->fresh()->payload)->toBe($original)
        ->and($retry->content()->htmlString)->toBe($first['production_html'])
        ->and($retry->renderedEmail['production_html'])->toContain('cid:'.$original['email']['assets'][0]['cid'])
        ->and($retry->renderedEmail['production_html'])->not->toContain('data:image', 'Changed Current Name', 'Changed Current Room');
    $mime = new Email;
    foreach ($retry->envelope()->using as $callback) {
        $callback($mime);
    }
    $part = $mime->getAttachments()[0];
    expect($part->getContentId())->toBe($original['email']['assets'][0]['cid'])
        ->and($part->getContentType())->toBe('image/png')
        ->and(hash('sha256', $part->getBody()))->toBe(hash('sha256', $image['bytes']))
        ->and($mime->getTextBody())->toBe($retry->renderedEmail['text']);
    reminderNoticeEmit();
    expect(reminderNoticeRows($fixture['applicants'][0]))->toHaveCount(2)
        ->and($mail->fresh()->payload)->toBe($original);
});

test('postcommit renderer asset and provider failures leave actual inbox and domain intact', function (string $failure): void {
    $fixture = reminderNoticeFixture();
    $image = in_array($failure, ['asset_missing', 'asset_corrupt'], true) ? reminderNoticeImage($fixture) : null;
    reminderNoticeEmit();
    $rows = reminderNoticeRows($fixture['applicants'][0]);
    $mail = $rows->firstWhere('channel', 'mail');
    $inbox = $rows->firstWhere('channel', 'in_app');
    $frozen = $mail->payload;
    if ($failure === 'renderer') {
        $renderer = Mockery::mock(NoticeEmailRenderer::class);
        $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('email_render_failed'));
        app()->instance(NoticeEmailRenderer::class, $renderer);
    } elseif ($failure === 'asset_missing') {
        Storage::disk('local')->delete($image['path']);
    } elseif ($failure === 'asset_corrupt') {
        Storage::disk('local')->put($image['path'], 'corrupt');
    } else {
        $pending = Mockery::mock(PendingMail::class);
        $pending->shouldReceive('send')->once()->andThrow(new RuntimeException('provider_failed'));
        Mail::shouldReceive('to')->once()->andReturn($pending);
    }
    reminderNoticeProcess($mail);
    reminderNoticeProcess($inbox);
    $expectedStatus = $failure === 'provider' ? NotificationDelivery::STATUS_OUTCOME_UNKNOWN : NotificationDelivery::STATUS_RETRYING;
    expect($mail->fresh()->status)->toBe($expectedStatus)
        ->and($mail->fresh()->payload)->toBe($frozen)
        ->and($fixture['session']->fresh()->status)->toBe(ExamSession::STATUS_PUBLISHED)
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    $projection = app(NotificationProjection::class);
    $list = collect($projection->list($fixture['applicants'][0]['user']))->firstWhere('id', $inbox->id);
    $bell = collect($projection->forUser($fixture['applicants'][0]['user'])['recent'])->firstWhere('id', $inbox->id);
    expect($list['subject'])->toBe($frozen['portal']['title'])
        ->and($list['body'])->toBe($frozen['portal']['body'])
        ->and($bell['body'])->toBe($frozen['portal']['body'])
        ->and($list['email_delivery']['status'])->toBe($expectedStatus)
        ->and($inbox->fresh()->read_at)->toBeNull();
})->with(['renderer', 'asset_missing', 'asset_corrupt', 'provider']);

test('unavailable pinned renderer or schema never silently selects current implementation', function (string $pin): void {
    $fixture = reminderNoticeFixture();
    reminderNoticePublish($fixture, $pin === 'renderer' ? ['email_renderer_version' => 'notice-email-unavailable']
        : ['email_blueprint' => array_replace($fixture['version']->email_blueprint, ['schema_version' => 'notice-email-unavailable'])]);
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    reminderNoticeProcess($mail);
    expect($mail->fresh()->last_error)->toBe('email_renderer_unavailable')
        ->and($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_RETRYING);
    Mail::assertNothingSent();
})->with(['renderer', 'schema']);

test('effective snapshotted policy suppresses mail despite template and injected print settings', function (): void {
    $fixture = reminderNoticeFixture();
    reminderNoticePublish($fixture, ['document_mode' => 'builder', 'builder_json' => ['invalid' => true]]);
    $snapshot = ExamNotificationPolicy::defaults()->snapshot(7);
    $snapshot['reminder_email_enabled'] = false;
    $fixture['session']->update(['notification_policy_snapshot' => $snapshot]);
    reminderNoticeEmit();
    foreach ($fixture['applicants'] as $applicant) {
        $rows = reminderNoticeRows($applicant);
        expect($rows)->toHaveCount(1)->and($rows->first()->channel)->toBe('in_app')
            ->and($rows->first()->payload['print'])->toBeNull();
    }
    Mail::assertNothingSent();
});

test('resource scoped missing template keeps established static schema without tenant or domain fallback', function (): void {
    $fixture = reminderNoticeFixture();
    $foreign = Institution::query()->create(['code' => 'FOREIGN-REMINDER', 'name' => 'Foreign Institution']);
    $fixture['template']->update(['institution_id' => $foreign->id]);
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    expect($mail->payload)->not->toHaveKey('snapshot_schema_version')
        ->and($mail->payload['subject'])->toContain('FROZEN-REMINDER')
        ->and($mail->payload['institution_id'])->toBe($fixture['cycle']->institution_id);
});

test('new reminder schema retains generation assignment schedule and own recipient business validity', function (string $change): void {
    $fixture = reminderNoticeFixture();
    reminderNoticeEmit();
    $delivery = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'in_app');
    expect(app(NotificationBusinessValidity::class)->isCurrent($delivery))->toBeTrue();
    match ($change) {
        'generation' => $fixture['session']->update(['notification_policy_snapshot' => ExamNotificationPolicy::defaults()->snapshot(8)]),
        'schedule' => $fixture['session']->update(['starts_at' => now()->addHours(25)]),
        'assignment' => $fixture['applicants'][0]['assignment']->update(['status' => ExamAssignment::STATUS_REMOVED, 'version' => 2]),
        'recipient' => $delivery->update(['recipient_id' => $fixture['applicants'][1]['identity']->id]),
    };
    reminderNoticeProcess($delivery);
    expect($delivery->fresh()->status)->toBe(NotificationDelivery::STATUS_SUPERSEDED)
        ->and(collect(app(NotificationProjection::class)->list($fixture['applicants'][0]['user']))->pluck('id'))->not->toContain($delivery->id);
})->with(['generation', 'schedule', 'assignment', 'recipient']);

test('snapshot serialization errors roll back all delivery writes rather than becoming an email failure', function (): void {
    $fixture = reminderNoticeFixture();
    $fixture['applicants'][0]['identity']->update(['first_name' => "invalid\xB1"]);
    expect(fn () => Artisan::call('notifications:send-exam-reminders', ['--window' => '24h']))->toThrow(JsonException::class);
    expect(NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')->count())->toBe(0);
    Mail::assertNothingSent();
});

test('historical top level inbox and mail markup remain byte identical without new renderer', function (): void {
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $payload = ['subject' => 'Historical subject', 'body' => '<p>Historical <strong>rendered</strong> &amp; content</p>',
        'body_is_html' => true, 'action_label' => 'Historical action', 'action_url' => url('/historical')];
    $rows = app(NotificationIntentDispatcher::class)->dispatch(NotificationIntent::v1('historical.notice.v1', 'historical.notice', $payload),
        'historical-frozen', [$user], ['in_app', 'mail']);
    $expected = (new NotificationMailable('historical.notice', 1, $payload))->content()->htmlString;
    app()->instance(NoticeEmailRenderer::class, Mockery::mock(NoticeEmailRenderer::class)->shouldNotReceive('render')->getMock());
    [$mailer] = reminderNoticeActualMailer();
    app(MailDeliveryChannel::class)->deliver($rows->firstWhere('channel', 'mail'));
    $message = $mailer->captured[0];
    expect($message->content()->htmlString)->toBe($expected)->and($message->payload)->toBe($payload);
    $row = collect(app(NotificationProjection::class)->list($user))->firstWhere('id', $rows->firstWhere('channel', 'in_app')->id);
    expect($row['subject'])->toBe($payload['subject'])->and($row['body'])->toBe($payload['body'])
        ->and($row['action_label'])->toBe($payload['action_label'])->and($row['action_url'])->toBe($payload['action_url']);
});

test('retained original asset repair permits retry without rewriting frozen content', function (): void {
    $fixture = reminderNoticeFixture();
    $image = reminderNoticeImage($fixture);
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    $snapshot = $mail->payload;
    Storage::disk('local')->put($image['path'], 'corrupted attempt');
    reminderNoticeProcess($mail);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_RETRYING);
    Storage::disk('local')->put($image['path'], $image['bytes']);
    $mail->update(['next_attempt_at' => now()->subSecond()]);
    [$mailer, $transport] = reminderNoticeActualMailer();
    reminderNoticeProcess($mail);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($mail->fresh()->payload)->toBe($snapshot);
    expect($transport->messages())->toHaveCount(1)
        ->and($mailer->captured[0]->renderedEmail['assets'][0]['sha256'])->toBe($snapshot['email']['assets'][0]['sha256']);
});

test('disabled snapshotted reminder window creates no occurrence or channel', function (): void {
    $fixture = reminderNoticeFixture();
    $snapshot = ExamNotificationPolicy::defaults()->snapshot(7);
    $snapshot['reminder_offsets_minutes'] = [];
    $fixture['session']->update(['notification_policy_snapshot' => $snapshot]);
    reminderNoticeEmit();
    expect(NotificationDelivery::query()->where('event_type', 'exam.reminder.v1')->count())->toBe(0);
    Mail::assertNothingSent();
});

test('unsnapshotted cycle policy retains nullable session override precedence', function (): void {
    $fixture = reminderNoticeFixture();
    $policy = ExamNotificationPolicy::defaults()->toArray();
    $policy['reminder_email_enabled'] = false;
    $fixture['cycle']->update(['exam_notification_policy' => $policy]);
    $fixture['session']->update(['notification_policy_snapshot' => null,
        'notification_policy' => ['schema_version' => 1, 'reminder_email_enabled' => true]]);
    reminderNoticeEmit();
    foreach ($fixture['applicants'] as $applicant) {
        expect(reminderNoticeRows($applicant))->toHaveCount(2);
    }
    Mail::assertNothingSent();
});

test('reminder mail is document free even when an active version injects print configuration', function (): void {
    $fixture = reminderNoticeFixture();
    reminderNoticePublish($fixture, ['document_mode' => 'builder', 'builder_json' => ['invalid' => true]]);
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    expect($mail->payload['print'])->toBeNull();
    [$mailer] = reminderNoticeActualMailer();
    app(MailDeliveryChannel::class)->deliver($mail);
    expect($mailer->captured[0]->attachments())->toBe([]);
});

test('activated semantic admission version freezes before commit and delivers independently safe inbox afterward', function (): void {
    $fixture = reminderNoticeFixture();
    $fixture['template']->update(['domain' => 'admissions', 'slug' => 'accepted', 'event_key' => 'admission.accepted.v1']);
    reminderNoticePublish($fixture, ['subject' => 'Offer for {{applicant_name}}', 'body' => 'Application {{application_no}} is accepted.', 'email_blueprint' => [
        'schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}} from {{institution_name}}']],
    ]]);
    $fixture['cycle']->update(['admission_notification_policy' => [
        'schema_version' => 1, 'version' => 1, 'applicant_status_email_enabled' => true,
        'registrar_new_submission_alert_enabled' => false,
    ]]);
    app()->instance(NoticeEmailRenderer::class, Mockery::mock(NoticeEmailRenderer::class)->shouldNotReceive('render')->getMock());
    app()->instance(NoticeEmailAssetVerifier::class, Mockery::mock(NoticeEmailAssetVerifier::class)->shouldNotReceive('verify')->getMock());
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $attempt = $fixture['applicants'][0]['attempt'];
    $accepted = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
    $rows = NotificationDelivery::query()->where('event_type', 'admission.accepted.v1')
        ->where('recipient_id', $fixture['applicants'][0]['identity']->id)->get();
    $mail = $rows->firstWhere('channel', 'mail');
    $inbox = $rows->firstWhere('channel', 'in_app');
    expect($accepted->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
        ->and($rows)->toHaveCount(2)
        ->and($mail->payload['email']['preparation_failure'])->toBeNull()
        ->and($mail->payload['context']['values']['applicant_name'])->toBe($fixture['applicants'][0]['identity']->fullName())
        ->and($mail->payload['portal']['title'])->toBe('Offer for '.$fixture['applicants'][0]['identity']->fullName())
        ->and($mail->payload['portal']['action_label'])->toBeNull()
        ->and($mail->payload['portal']['action_url'])->toBeNull()
        ->and($inbox->payload['portal']['body'])->toContain($attempt->reference_code);
    app()->instance(NoticeEmailRenderer::class, new NoticeEmailRenderer);
    app()->instance(NoticeEmailAssetVerifier::class, new NoticeEmailAssetVerifier);
    [$mailer, $transport] = reminderNoticeActualMailer();
    reminderNoticeProcess($mail);
    reminderNoticeProcess($inbox);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($transport->messages())->toHaveCount(1)
        ->and($mailer->captured[0]->renderedEmail['text'])->toContain($fixture['applicants'][0]['identity']->fullName())
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($accepted->fresh()->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED);
    $projection = app(NotificationProjection::class);
    $list = collect($projection->list($fixture['applicants'][0]['user']))->firstWhere('id', $inbox->id);
    $bell = collect($projection->forUser($fixture['applicants'][0]['user'])['recent'])->firstWhere('id', $inbox->id);
    expect($list['subject'])->toBe($inbox->payload['portal']['title'])
        ->and($bell['body'])->toBe($inbox->payload['portal']['body'])
        ->and($list['email_delivery']['status'])->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($inbox->fresh()->seen_at)->not->toBeNull()
        ->and($inbox->fresh()->read_at)->toBeNull();
    $activityQuery = app(NotificationOccurrenceQuery::class);
    $activity = $activityQuery->activity($registrar, $activityQuery->accessibleDomains($registrar), [
        'domain' => 'admissions', 'search' => 'admission.accepted.v1',
    ]);
    expect($activity->total())->toBe(1);
    $occurrence = $activityQuery->occurrence($registrar, $activity->items()[0]['occurrence_id']);
    expect($occurrence['resource_id'])->toBe((string) $attempt->id)
        ->and($activityQuery->deliveries($registrar, $occurrence)->total())->toBe(2)
        ->and($activityQuery->verifyDeliveryInOccurrence($registrar, $occurrence, $mail->id)?->id)->toBe($mail->id)
        ->and($activityQuery->occurrence($fixture['actor'], $activity->items()[0]['occurrence_id']))->toBeNull();
});

function reminderNoticeActualMailer(): array
{
    $transport = new ArrayTransport;
    $mailer = new class('reminder-delivery-proof', app('view'), $transport, app('events')) extends Mailer
    {
        public array $captured = [];

        protected function sendMailable($mailable)
        {
            $this->captured[] = $mailable;

            return parent::sendMailable($mailable);
        }
    };
    $mailer->alwaysFrom('notification-proof@example.test', 'Notification Proof');
    Mail::swap($mailer);

    return [$mailer, $transport];
}

test('actual MessageSending cancellation cannot claim delivered or automatically resend', function (): void {
    $fixture = reminderNoticeFixture();
    reminderNoticeEmit();
    [, $transport] = reminderNoticeActualMailer();
    app('events')->listen(MessageSending::class, fn (): bool => false);
    $rows = reminderNoticeRows($fixture['applicants'][0]);
    $mail = $rows->firstWhere('channel', 'mail');
    $inbox = $rows->firstWhere('channel', 'in_app');
    $snapshot = $mail->payload;
    $queuedBefore = Queue::pushed(ProcessNotificationDelivery::class)->count();
    reminderNoticeProcess($mail);
    reminderNoticeProcess($inbox);
    reminderNoticeProcess($mail);
    expect($transport->messages())->toHaveCount(0)
        ->and($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_OUTCOME_UNKNOWN)
        ->and($mail->fresh()->attempts)->toBe(1)
        ->and($mail->fresh()->delivered_at)->toBeNull()
        ->and($mail->fresh()->provider_reference)->toBeNull()
        ->and($mail->fresh()->next_attempt_at)->toBeNull()
        ->and($mail->fresh()->last_error)->toBe('mail_send_unobserved')
        ->and($mail->fresh()->payload)->toBe($snapshot)
        ->and(Queue::pushed(ProcessNotificationDelivery::class)->count())->toBe($queuedBefore)
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
});

test('actual transport acceptance then MessageSent listener failure fences ambiguity without duplicate provider call', function (): void {
    $fixture = reminderNoticeFixture();
    reminderNoticeEmit();
    [, $transport] = reminderNoticeActualMailer();
    app('events')->listen(MessageSent::class, function (): void {
        throw new RuntimeException('Post-send listener failure');
    });
    $rows = reminderNoticeRows($fixture['applicants'][0]);
    $mail = $rows->firstWhere('channel', 'mail');
    $inbox = $rows->firstWhere('channel', 'in_app');
    $snapshot = $mail->payload;
    $queuedBefore = Queue::pushed(ProcessNotificationDelivery::class)->count();
    reminderNoticeProcess($mail);
    reminderNoticeProcess($inbox);
    reminderNoticeProcess($mail);
    expect($transport->messages())->toHaveCount(1)
        ->and($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_OUTCOME_UNKNOWN)
        ->and($mail->fresh()->last_error)->toBe('provider_outcome_unknown')
        ->and($mail->fresh()->attempts)->toBe(1)
        ->and($mail->fresh()->next_attempt_at)->toBeNull()
        ->and($mail->fresh()->delivered_at)->toBeNull()
        ->and($mail->fresh()->payload)->toBe($snapshot)
        ->and(Queue::pushed(ProcessNotificationDelivery::class)->count())->toBe($queuedBefore)
        ->and($inbox->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED);
    $row = collect(app(NotificationProjection::class)->list($fixture['applicants'][0]['user']))->firstWhere('id', $inbox->id);
    expect($row['subject'])->toBe($snapshot['portal']['title'])
        ->and($row['email_delivery']['outcome_unknown'])->toBeTrue()
        ->and($row['email_delivery']['delivered_at'])->toBeNull();
});

test('actual mailer transport returning no SentMessage remains unobserved and never delivered', function (): void {
    $fixture = reminderNoticeFixture();
    reminderNoticeEmit();
    $transport = new class extends ArrayTransport
    {
        public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
        {
            return null;
        }
    };
    $mailer = new Mailer('unobserved-transport-proof', app('view'), $transport, app('events'));
    $mailer->alwaysFrom('notification-proof@example.test', 'Notification Proof');
    Mail::swap($mailer);
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    reminderNoticeProcess($mail);
    reminderNoticeProcess($mail);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_OUTCOME_UNKNOWN)
        ->and($mail->fresh()->last_error)->toBe('mail_send_unobserved')
        ->and($mail->fresh()->delivered_at)->toBeNull()
        ->and($mail->fresh()->provider_reference)->toBeNull()
        ->and($mail->fresh()->attempts)->toBe(1)
        ->and($mail->fresh()->next_attempt_at)->toBeNull();
});

test('actual reminder portal link freezes exact configured local origin and renders without ambient host substitution', function (): void {
    $fixture = reminderNoticeFixture();
    config(['app.url' => 'http://localhost:18000']);
    URL::forceRootUrl('http://ambient-foreign.example.test');
    reminderNoticePublish($fixture, ['action_label' => 'Review Examination Details', 'action_route' => 'applicant.portal', 'email_blueprint' => [
        'schema_version' => 'notice-email-1', 'layout' => 'flow',
        'blocks' => [['type' => 'paragraph', 'runs' => [
            ['text' => 'Review your examination schedule', 'link' => '{{portal_url}}'],
        ]]],
    ]]);
    reminderNoticeEmit();
    $mail = reminderNoticeRows($fixture['applicants'][0])->firstWhere('channel', 'mail');
    $expectedUrl = 'http://localhost:18000'.route('applicant.schedule', absolute: false);
    expect($mail->payload['context']['values']['portal_url'])->toBe($expectedUrl)
        ->and($mail->payload['portal']['action_url'])->toBe('http://localhost:18000'.route('applicant.portal', absolute: false));
    [$mailer, $transport] = reminderNoticeActualMailer();
    reminderNoticeProcess($mail);
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($transport->messages())->toHaveCount(1)
        ->and($mailer->captured[0]->renderedEmail['production_html'])->toContain('href="'.$expectedUrl.'"')
        ->and($mailer->captured[0]->renderedEmail['text'])->toContain($expectedUrl);
});

function reminderNoticePublish(array &$fixture, array $overrides): CommunicationTemplateVersion
{
    $fields = $fixture['version']->only($fixture['version']->getFillable());
    $version = $fixture['template']->versions()->create(array_replace($fields, [
        'version' => (int) $fixture['version']->version + 1,
    ], $overrides));
    $fixture['template']->update(['active_version' => $version->version]);
    $fixture['version'] = $version;

    return $version;
}

test('current historical channel version freezes raw templates and renders its retained branch only after commit', function (): void {
    $fixture = reminderNoticeFixture();
    $fixture['template']->update(['domain' => 'admissions', 'slug' => 'accepted', 'event_key' => 'admission.accepted.v1']);
    reminderNoticePublish($fixture, [
        'content_schema_version' => 'notice-content-1', 'email_renderer_version' => 'legacy',
        'subject' => null, 'body' => null, 'email_blueprint' => null, 'content_sha256' => null,
        'email_subject' => 'Legacy offer {{applicant_name}}',
        'email_body' => '<p>Dear <strong>{{applicant_name}}</strong>, your offer is ready.</p>',
        'in_app_title' => 'Retained legacy short title', 'in_app_body' => 'Retained legacy short body',
    ]);
    $fixture['cycle']->update(['admission_notification_policy' => [
        'schema_version' => 1, 'version' => 1, 'applicant_status_email_enabled' => true,
        'registrar_new_submission_alert_enabled' => false,
    ]]);
    app()->instance(NoticeLegacyEmailRenderer::class,
        Mockery::mock(NoticeLegacyEmailRenderer::class)->shouldNotReceive('render')->getMock());
    app()->instance(NoticeEmailRenderer::class, Mockery::mock(NoticeEmailRenderer::class)->shouldNotReceive('render')->getMock());
    Storage::shouldReceive('disk')->never();
    $attempt = $fixture['applicants'][0]['attempt'];
    $name = $fixture['applicants'][0]['identity']->fullName();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
    $mail = NotificationDelivery::query()->where('event_type', 'admission.accepted.v1')
        ->where('recipient_id', $fixture['applicants'][0]['identity']->id)->where('channel', 'mail')->firstOrFail();
    $snapshot = $mail->payload;
    expect($snapshot['template']['content_schema_version'])->toBe('notice-content-1')
        ->and($snapshot['template']['email_schema_version'])->toBe('notice-email-legacy-1')
        ->and($snapshot['template']['email_renderer_version'])->toBe('legacy')
        ->and($snapshot['email']['blueprint']['body_template'])->toContain('{{applicant_name}}')
        ->and($fixture['version']->fresh()->email_blueprint)->toBeNull()
        ->and($fixture['version']->fresh()->content_sha256)->toBeNull();
    reminderNoticePublish($fixture, ['content_schema_version' => 'notice-content-2', 'email_renderer_version' => 'notice-email-1',
        'subject' => 'Newest semantic offer', 'content_sha256' => hash('sha256', 'new semantic offer'),
        'email_blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [['type' => 'paragraph', 'text' => 'New semantic content']]],
    ]);
    $fixture['applicants'][0]['identity']->update(['first_name' => 'Changed Current Fact']);
    app()->instance(NoticeLegacyEmailRenderer::class,
        new NoticeLegacyEmailRenderer);
    [$mailer, $transport] = reminderNoticeActualMailer();
    reminderNoticeProcess($mail);
    $expected = (new NotificationMailable('admission.accepted', $snapshot['template']['version'], [
        'subject' => 'Legacy offer '.$name, 'body' => '<p>Dear <strong>'.$name.'</strong>, your offer is ready.</p>',
        'body_is_html' => true, 'action_label' => null, 'action_url' => '#',
    ]))->content()->htmlString;
    expect($mail->fresh()->status)->toBe(NotificationDelivery::STATUS_DELIVERED)
        ->and($transport->messages())->toHaveCount(1)
        ->and($mailer->captured[0]->renderedEmail['production_html'])->toBe($expected)
        ->and($mailer->captured[0]->renderedEmail['subject'])->toBe('Legacy offer '.$name)
        ->and($mail->fresh()->payload)->toBe($snapshot);
});
