<?php

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Setup\Queries\ComputeSetupReadinessQuery;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

test('unauthorized user cannot access notification setup hub', function (): void {
    $outsider = User::query()->create([
        'name' => 'Outsider User',
        'email' => 'outsider@example.test',
        'password' => 'secret-password-40-chars-min-security-test',
    ]);

    $this->actingAs($outsider)
        ->get(route('setup.notifications'))
        ->assertForbidden();
});

test('authorized staff see only their scoped notification activity with unknown health', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $room = Room::query()->where('code', 'RM-101')->firstOrFail();

    // Create an exam session with custom notification policy
    ExamSession::query()->create([
        'academic_cycle_id' => $cycle->id,
        'room_id' => $room->id,
        'code' => 'EX-HUB-TEST-01',
        'status' => ExamSession::STATUS_DRAFT,
        'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
        'starts_at' => now()->addDays(2),
        'effective_end_at' => now()->addDays(2)->addHours(2),
        'version' => 1,
        'notification_policy' => [
            'schema_version' => 1,
            'publication_email_enabled' => false,
            'reminder_offsets_minutes' => [1440, 60],
            'reminder_email_enabled' => true,
        ],
    ]);

    $eventId = hash('sha256', 'hub-test-event');
    foreach ([NotificationDelivery::CHANNEL_MAIL, NotificationDelivery::CHANNEL_IN_APP] as $channel) {
        NotificationDelivery::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'event_type' => 'exam.session_published.v1',
            'event_version' => 1,
            'recipient_type' => User::class,
            'recipient_id' => $counselor->id,
            'channel' => $channel,
            'template_key' => 'exam.session_published',
            'template_version' => 1,
            'payload' => ['session_id' => (string) ExamSession::query()->where('code', 'EX-HUB-TEST-01')->value('id')],
            'status' => NotificationDelivery::STATUS_DELIVERED,
            'attempts' => 1,
        ]);
    }

    $this->actingAs($counselor)
        ->get(route('setup.notifications'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Setup/Notifications')
            ->has('activity.data', 1)
            ->where('activity.per_page', 15)
            ->where('activity.data.0.domain', 'examination')
            ->where('activity.data.0.recipient_count', 1)
            ->where('activity.data.0.channel_summaries', [
                ['channel' => 'mail', 'status' => 'accepted_mail', 'count' => 1],
                ['channel' => 'in_app', 'status' => 'available_in_app', 'count' => 1],
            ])
            ->missing('domain_cards.admissions')
            ->where('domain_cards.examination.can_access', true)
            ->where('domain_cards.examination.count', 1)
            ->where('domain_cards.results.can_access', true)
            ->where('domain_cards.results.count', 0)
            ->where('health.status', 'unknown')
            ->has('master_controls')
            ->has('domain_policies')
            ->where('master_controls.can_manage', false)
        );
});

test('setup readiness query includes communications and notification policies category', function (): void {
    $query = app(ComputeSetupReadinessQuery::class);
    $readiness = $query->handle();

    $institutionalGroup = collect($readiness['groups'])->firstWhere('key', 'institutional_setup');
    expect($institutionalGroup)->not->toBeNull();

    $notificationsCategory = collect($institutionalGroup['categories'])->firstWhere('key', 'notifications');
    expect($notificationsCategory)->not->toBeNull()
        ->and($notificationsCategory['href'])->toBe('/setup/notifications');
});

test('authorized registrar can update master communication controls with audit logging', function (): void {
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $this->actingAs($registrar)
        ->post(route('setup.notifications.controls.update'), [
            'outbound_email_enabled' => false,
            'portal_inbox_enabled' => true,
            'staff_action_alerts_enabled' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'Master communication controls updated.');

    $cycle->refresh();
    expect($cycle->communication_master_controls)->toBe([
        'outbound_email_enabled' => false,
        'portal_inbox_enabled' => true,
        'staff_action_alerts_enabled' => false,
    ]);

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'notification.master_controls_updated',
        'actor' => 'staff',
    ]);
});

test('guidance counselor without registrar setup manage cannot update master controls', function (): void {
    $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($counselor)
        ->post(route('setup.notifications.controls.update'), [
            'outbound_email_enabled' => false,
            'portal_inbox_enabled' => true,
            'staff_action_alerts_enabled' => false,
        ])
        ->assertForbidden();
});
