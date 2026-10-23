<?php

use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

function notificationOperationsSession(string $code): ExamSession
{
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    return ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => $code,
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
        'version' => 1,
    ]);
}

function notificationOperationsDelivery(
    User $recipient,
    string $eventId,
    string $eventType,
    array $payload,
    string $channel = NotificationDelivery::CHANNEL_IN_APP,
    string $status = NotificationDelivery::STATUS_DELIVERED,
): NotificationDelivery {
    return NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => $eventId,
        'event_type' => $eventType,
        'event_version' => 1,
        'recipient_type' => User::class,
        'recipient_id' => $recipient->id,
        'channel' => $channel,
        'template_key' => 'notification.operations.test',
        'template_version' => 1,
        'payload' => $payload,
        'status' => $status,
        'attempts' => 1,
    ]);
}

test('activity returns stable distinct occurrences in fifteen-row pages', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = notificationOperationsSession('EX-OPS-PAGING');

    foreach (range(1, 16) as $index) {
        notificationOperationsDelivery(
            $guidance,
            hash('sha256', 'ops-page-'.$index),
            'exam.session_published.v1',
            ['session_id' => (string) $session->id],
        );
    }

    $first = $this->actingAs($guidance)->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->assertJsonPath('per_page', 15)
        ->assertJsonCount(15, 'data')
        ->json('data');
    $repeat = $this->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->json('data');
    $second = $this->getJson(route('setup.notifications.occurrences', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->json('data');

    $firstIds = collect($first)->pluck('occurrence_id');
    $secondIds = collect($second)->pluck('occurrence_id');

    expect($firstIds->all())->toBe(collect($repeat)->pluck('occurrence_id')->all())
        ->and($firstIds->unique())->toHaveCount(15)
        ->and($secondIds->unique())->toHaveCount(1)
        ->and($firstIds->intersect($secondIds))->toBeEmpty()
        ->and($firstIds->concat($secondIds)->unique())->toHaveCount(16)
        ->and(collect($first)->every(fn (array $row): bool => $row['domain'] === 'examination'))->toBeTrue();
});
test('schema three admission and result activity resolves institution-scoped attempt resources', function (): void {
    $submission = submitSyntheticApplicant('notice-ops-schema3@example.test', 'Operations Candidate');
    $attempt = $submission['attempt'];
    $identity = $attempt->identity;
    $cycle = AcademicCycle::query()->findOrFail($attempt->academic_cycle_id);
    $familyPayload = static function (string $domain, string $slug, string $eventType, string $templateKey, array $resource) use ($cycle, $identity): array {
        return [
            'snapshot_schema_version' => 'notice-delivery-3',
            'institution_id' => (int) $cycle->institution_id,
            'domain' => $domain,
            'canonical_slug' => $slug,
            'event_type' => $eventType,
            'template_key' => $templateKey,
            'recipient' => ['type' => ApplicantIdentity::class, 'id' => (int) $identity->id, 'email' => $identity->email],
            'event_resource' => $resource,
            'template' => ['id' => (string) Str::uuid(), 'version' => 1, 'content_sha256' => str_repeat('a', 64),
                'content_schema_version' => 'notice-content-3', 'email_schema_version' => 'notice-email-1',
                'email_renderer_version' => 'notice-email-1'],
            'context' => ['values' => ['applicant_name' => $identity->fullName()], 'missing_required_keys' => []],
            'email' => ['subject_template' => 'Notice {{applicant_name}}',
                'blueprint' => ['schema_version' => 'notice-email-1', 'layout' => 'flow',
                    'blocks' => [['type' => 'paragraph', 'text' => 'Hello {{applicant_name}}']]],
                'assets' => [], 'preparation_failure' => null],
            'portal' => ['title' => 'Notice', 'body' => 'Hello', 'action_label' => null, 'action_url' => null],
            'print' => null,
        ];
    };
    $rows = [
        ['admissions', 'accepted', 'admission.accepted.v1', 'admission.accepted',
            ['id' => $attempt->id, 'attempt_id' => $attempt->id, 'identity_id' => $identity->id]],
        ['results', 'released-online', 'result.released.v1', 'result.released.online',
            ['id' => 91, 'release_id' => 91, 'release_version' => 1, 'attempt_id' => $attempt->id, 'identity_id' => $identity->id]],
    ];
    foreach ($rows as [$domain, $slug, $eventType, $templateKey, $resource]) {
        NotificationDelivery::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => hash('sha256', 'schema3-operations-'.$eventType),
            'event_type' => $eventType,
            'event_version' => 1,
            'recipient_type' => ApplicantIdentity::class,
            'recipient_id' => $identity->id,
            'channel' => NotificationDelivery::CHANNEL_MAIL,
            'template_key' => $templateKey,
            'template_version' => 1,
            'payload' => $familyPayload($domain, $slug, $eventType, $templateKey, $resource),
            'status' => NotificationDelivery::STATUS_DELIVERED,
            'attempts' => 1,
        ]);
    }

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $admissionRows = $this->actingAs($registrar)
        ->getJson(route('setup.notifications.occurrences', ['domain' => 'admissions']))
        ->assertOk()->json('data');
    $admissionEventId = hash('sha256', 'schema3-operations-admission.accepted.v1');
    $admissionOccurrenceRow = collect($admissionRows)->first(
        fn (array $row): bool => str_starts_with($row['occurrence_id'], $admissionEventId.'~'),
    );
    expect($admissionOccurrenceRow)->not->toBeNull();
    $admissionOccurrence = $this->actingAs($registrar)
        ->get(route('setup.notifications.occurrences.show', ['occurrence' => $admissionOccurrenceRow['occurrence_id']]))
        ->assertOk();
    $admissionOccurrence->assertInertia(fn ($page) => $page
        ->where('occurrence.domain', 'admissions')
        ->where('occurrence.occurrence_id', $admissionOccurrenceRow['occurrence_id'])
        ->has('deliveries.data', 1));

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $resultRows = $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences', ['domain' => 'results']))
        ->assertOk()->json('data');
    $resultEventId = hash('sha256', 'schema3-operations-result.released.v1');
    $resultOccurrenceRow = collect($resultRows)->first(
        fn (array $row): bool => str_starts_with($row['occurrence_id'], $resultEventId.'~'),
    );
    expect($resultOccurrenceRow)->not->toBeNull();
    $resultOccurrence = $this->actingAs($guidance)
        ->get(route('setup.notifications.occurrences.show', ['occurrence' => $resultOccurrenceRow['occurrence_id']]))
        ->assertOk();
    $resultOccurrence->assertInertia(fn ($page) => $page
        ->where('occurrence.domain', 'results')
        ->where('occurrence.occurrence_id', $resultOccurrenceRow['occurrence_id'])
        ->has('deliveries.data', 1));
});

test('one occurrence groups its channels and does not duplicate recipient counts', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = notificationOperationsSession('EX-OPS-CHANNELS');
    $eventId = hash('sha256', 'ops-grouped-channels');

    foreach ([NotificationDelivery::CHANNEL_MAIL, NotificationDelivery::CHANNEL_IN_APP] as $channel) {
        notificationOperationsDelivery(
            $guidance,
            $eventId,
            'exam.session_published.v1',
            ['session_id' => (string) $session->id],
            $channel,
        );
    }

    $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.recipient_count', 1)
        ->assertJsonPath('data.0.channel_summaries', [
            ['channel' => 'mail', 'status' => 'accepted_mail', 'count' => 1],
            ['channel' => 'in_app', 'status' => 'available_in_app', 'count' => 1],
        ]);
});

test('status and channel filters select occurrences without changing full delivery summaries', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = notificationOperationsSession('EX-OPS-MIXED-FILTER');
    $eventId = hash('sha256', 'ops-mixed-filter');
    notificationOperationsDelivery(
        $guidance,
        $eventId,
        'exam.session_published.v1',
        ['session_id' => (string) $session->id],
        NotificationDelivery::CHANNEL_MAIL,
        NotificationDelivery::STATUS_FAILED_TERMINAL,
    );
    notificationOperationsDelivery(
        User::factory()->create(),
        $eventId,
        'exam.session_published.v1',
        ['session_id' => (string) $session->id],
        NotificationDelivery::CHANNEL_IN_APP,
        NotificationDelivery::STATUS_DELIVERED,
    );

    foreach ([
        ['status' => NotificationDelivery::STATUS_FAILED_TERMINAL],
        ['channel' => NotificationDelivery::CHANNEL_MAIL],
    ] as $filters) {
        $rows = $this->actingAs($guidance)
            ->getJson(route('setup.notifications.occurrences', $filters))
            ->assertOk()
            ->json('data');
        $row = collect($rows)->first(fn (array $candidate): bool => str_starts_with($candidate['occurrence_id'], $eventId.'~'));

        expect($row)->not->toBeNull();

        expect($row['occurrence_id'])->toStartWith($eventId.'~')
            ->and($row['status'])->toBe('mixed')
            ->and($row['recipient_count'])->toBe(2)
            ->and($row['channel_summaries'])->toHaveCount(2);

        $this->get(route('setup.notifications.occurrences.show', ['occurrence' => $row['occurrence_id']]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('occurrence.occurrence_id', $row['occurrence_id'])
                ->where('occurrence.status', 'mixed')
                ->has('deliveries.data', 2));
    }
});

test('consultation occurrence resource identity and access use the consultation session institution', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();
    $session = ConsultationSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'CS-OPS-'.Str::upper(Str::random(8)),
        'status' => ConsultationSession::STATUS_PUBLISHED,
        'booking_mode' => ConsultationSession::BOOKING_STAFF_ONLY,
        'scheduled_at' => now()->addDays(2),
        'version' => 1,
    ]);
    $eventId = hash('sha256', 'ops-consultation-session-resource');
    $delivery = notificationOperationsDelivery(
        $guidance,
        $eventId,
        'consultation.session_published.v1',
        ['consultation_session_id' => (string) $session->id, 'attempt_id' => '999999999'],
    );
    app(RecordAuditEvent::class)->handle('notification.delivery_delivered', ['delivery_id' => $delivery->id]);

    $rows = $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences', ['domain' => 'results']))
        ->assertOk()
        ->json('data');
    $row = collect($rows)->first(fn (array $candidate): bool => str_starts_with($candidate['occurrence_id'], $eventId.'~'));

    expect($row)->not->toBeNull()
        ->and($row['occurrence_id'])->toStartWith($eventId.'~');

    $this->get(route('setup.notifications.occurrences.show', ['occurrence' => $row['occurrence_id']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('occurrence.occurrence_id', $row['occurrence_id'])
            ->where('occurrence.domain', 'results')
            ->has('deliveries.data', 1)
            ->has('history.data', 1)
            ->where('history.data.0.event', 'Delivery accepted'));
});

test('failed terminal deliveries aggregate as failed', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = notificationOperationsSession('EX-OPS-FAILED');
    $eventId = hash('sha256', 'ops-failed-terminal');
    notificationOperationsDelivery(
        $guidance,
        $eventId,
        'exam.session_published.v1',
        ['session_id' => (string) $session->id],
        NotificationDelivery::CHANNEL_MAIL,
        NotificationDelivery::STATUS_FAILED_TERMINAL,
    );

    $rows = $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->json('data');
    $row = collect($rows)->first(fn (array $candidate): bool => str_starts_with($candidate['occurrence_id'], $eventId.'~'));

    expect($row)->not->toBeNull()
        ->and($row['status'])->toBe('failed');

});

test('malformed activity filters are rejected', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    foreach ([
        ['domain' => 'global'],
        ['status' => 'successful'],
        ['channel' => 'sms'],
        ['from' => '2026-10-03T10:00:00Z'],
        ['from' => '2026-10-04', 'to' => '2026-10-03'],
    ] as $filters) {
        $this->actingAs($guidance)
            ->getJson(route('setup.notifications.occurrences', $filters))
            ->assertUnprocessable();
    }
});

test('cross-office and cross-institution occurrence details are not disclosed', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $session = notificationOperationsSession('EX-OPS-SCOPE');
    notificationOperationsDelivery(
        $guidance,
        hash('sha256', 'ops-scope-event'),
        'exam.session_published.v1',
        ['session_id' => (string) $session->id],
    );

    $occurrenceId = $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->json('data.0.occurrence_id');

    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $this->actingAs($registrar)
        ->get(route('setup.notifications.occurrences.show', ['occurrence' => $occurrenceId]))
        ->assertNotFound();

    $foreignInstitution = Institution::query()->create([
        'code' => 'OPS-FOREIGN-'.Str::upper(Str::random(8)),
        'name' => 'Foreign operations test institution',
    ]);
    $foreignGuidance = User::factory()->create();
    StaffProfile::query()->create([
        'user_id' => $foreignGuidance->id,
        'institution_id' => $foreignInstitution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $foreignGuidance->givePermissionTo(CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);

    $this->actingAs($foreignGuidance)
        ->get(route('setup.notifications.occurrences.show', ['occurrence' => $occurrenceId]))
        ->assertNotFound();
});

test('same legacy event identifier with different resource identity remains isolated', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $firstSession = notificationOperationsSession('EX-OPS-LEGACY-A');
    $secondSession = notificationOperationsSession('EX-OPS-LEGACY-B');
    $eventId = hash('sha256', 'legacy-ambiguous-event-id');

    notificationOperationsDelivery(
        $guidance,
        $eventId,
        'exam.session_published.v1',
        ['session_id' => (string) $firstSession->id],
        NotificationDelivery::CHANNEL_MAIL,
    );
    notificationOperationsDelivery(
        $guidance,
        $eventId,
        'exam.session_published.v1',
        ['session_id' => (string) $secondSession->id],
        NotificationDelivery::CHANNEL_IN_APP,
    );

    $rows = $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->json('data');
    $legacyRows = collect($rows)->filter(fn (array $row): bool => str_starts_with($row['occurrence_id'], $eventId.'~'))->values();

    expect($legacyRows)->toHaveCount(2)
        ->and($legacyRows->pluck('occurrence_id')->unique())->toHaveCount(2)
        ->and($legacyRows->pluck('channel_summaries')->flatten(1)->pluck('channel')->sort()->values()->all())->toBe(['in_app', 'mail']);

    foreach ($legacyRows as $row) {
        $detail = $this->get(route('setup.notifications.occurrences.show', ['occurrence' => $row['occurrence_id']]));
        $detail->assertOk()->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Occurrence')
            ->where('occurrence.occurrence_id', $row['occurrence_id'])
            ->has('deliveries.data', 1));
    }
});

test('occurrence history is real, scoped to its deliveries, and redacted', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $firstSession = notificationOperationsSession('EX-OPS-HISTORY-A');
    $secondSession = notificationOperationsSession('EX-OPS-HISTORY-B');
    $emptySession = notificationOperationsSession('EX-OPS-HISTORY-EMPTY');
    $firstDelivery = notificationOperationsDelivery(
        $guidance,
        hash('sha256', 'ops-history-event-a'),
        'exam.session_published.v1',
        ['session_id' => (string) $firstSession->id],
    );
    $secondDelivery = notificationOperationsDelivery(
        $guidance,
        hash('sha256', 'ops-history-event-b'),
        'exam.session_published.v1',
        ['session_id' => (string) $secondSession->id],
    );
    notificationOperationsDelivery(
        $guidance,
        hash('sha256', 'ops-history-event-empty'),
        'exam.session_published.v1',
        ['session_id' => (string) $emptySession->id],
    );

    $recordAuditEvent = app(RecordAuditEvent::class);
    $recordAuditEvent->handle('notification.delivery_retrying', [
        'delivery_id' => $firstDelivery->id,
        'attempts' => 2,
        'diagnostic' => 'private@example.test',
        'token' => 'do-not-expose',
    ]);
    $recordAuditEvent->handle('notification.delivery_failed', [
        'delivery_id' => $secondDelivery->id,
        'attempts' => 3,
        'diagnostic' => 'different private detail',
    ]);

    $rows = $this->actingAs($guidance)
        ->getJson(route('setup.notifications.occurrences'))
        ->assertOk()
        ->json('data');
    $firstOccurrence = collect($rows)->first(
        fn (array $row): bool => str_starts_with($row['occurrence_id'], hash('sha256', 'ops-history-event-a').'~'),
    );
    $emptyOccurrence = collect($rows)->first(
        fn (array $row): bool => str_starts_with($row['occurrence_id'], hash('sha256', 'ops-history-event-empty').'~'),
    );

    expect($firstOccurrence)->not->toBeNull()
        ->and($emptyOccurrence)->not->toBeNull();
    $this->get(route('setup.notifications.occurrences.show', ['occurrence' => $firstOccurrence['occurrence_id']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Occurrence')
            ->has('history.data', 1)
            ->where('history.data.0.event', 'Retry scheduled')
            ->has('history.data.0', 2)
            ->missing('history.data.0.delivery_id')
            ->missing('history.data.0.diagnostic')
            ->missing('history.data.0.token')
            ->missing('history.data.0.payload'));

    $this->get(route('setup.notifications.occurrences.show', ['occurrence' => $emptyOccurrence['occurrence_id']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications/Occurrence')
            ->has('history.data', 0)
            ->where('history.total', 0));
});
