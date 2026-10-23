<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Applicant\Support\ApplicantNavigationBuilder;
use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Notifications\NotificationProjection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedE2Foundation();
});

test('applicant navigation orders all seven-step destinations and resolves canonical routes', function (): void {
    $applicant = submitSyntheticApplicant('seven-step-nav@example.test', 'SevenStepNav');
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicant['access_token'],
        '2001-01-01',
    );

    $navigation = app(ApplicantNavigationBuilder::class)->for($user);
    $items = collect($navigation)->reject(fn (array $item): bool => in_array($item['key'], ['notifications', 'qr'], true));

    expect($items->pluck('key')->all())->toBe([
        'portal',
        'application',
        'schedule',
        'exam_day',
        'exam_scoring',
        'release_schedule',
        'release_day',
        'results',
        'program_choice',
        'clearance',
        'admission_support',
        'access_history',
    ])
        ->and($items->pluck('label')->all())->toBe([
            'Home',
            'My Application',
            'Exam Schedule',
            'Exam Day',
            'Exam Scoring',
            'Release Schedule',
            'Release Day',
            'My Results',
            'Program Choice',
            'Admission Clearance',
            'Messages',
            'Privacy & access history',
        ])
        ->and($items->firstWhere('key', 'release_schedule')['href'])->toBe(route('applicant.consultation'));
});

test('new applicant destinations require auth and old release-schedule URIs are removed', function (): void {
    $destinations = [
        'applicant.exam-day' => '/applicant/exam-day',
        'applicant.exam-scoring' => '/applicant/exam-scoring',
        'applicant.release-day' => '/applicant/release-day',
        'applicant.admission-clearance' => '/applicant/admission-clearance',
        'applicant.consultation' => '/applicant/release-schedule',
    ];

    foreach ($destinations as $name => $uri) {
        $route = Route::getRoutes()->getByName($name);
        expect($route)->not->toBeNull()
            ->and($route->uri())->toBe(ltrim($uri, '/'))
            ->and($route->methods())->toContain('GET')
            ->and($route->gatherMiddleware())->toContain('auth');
    }

    $applicant = submitSyntheticApplicant('seven-step-route@example.test', 'SevenStepRoute');
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicant['access_token'],
        '2001-01-01',
    );
    foreach (array_keys($destinations) as $name) {
        $this->get(route($name))->assertRedirect(route('login'));
    }

    foreach (array_keys($destinations) as $name) {
        $this->actingAs($user)
            ->get(route($name))
            ->assertOk();
    }

    $this->actingAs($user)
        ->get('/applicant/consultation')
        ->assertNotFound();
    $this->actingAs($user)
        ->post('/applicant/consultation/reserve')
        ->assertNotFound();
});

test('release schedule notice actions canonicalize only applicant event projections without mutating payloads', function (): void {
    $applicant = submitSyntheticApplicant('release-notice-nav@example.test', 'ReleaseNoticeNav');
    $user = app(AuthenticateApplicantWithAccessKey::class)->handle(
        $applicant['access_token'],
        '2001-01-01',
    );
    $identity = $applicant['attempt']->identity;
    $versionedPayload = [
        'snapshot_schema_version' => 'notice-delivery-2',
        'portal' => [
            'title' => 'A schedule is available',
            'body' => 'Review the release schedule.',
            'action_label' => 'View schedule',
            'action_url' => 'https://old.example.test/applicant/consultation',
        ],
    ];
    $flatLegacyPayload = [
        'subject' => 'Release reservation',
        'body' => 'Your release schedule is confirmed.',
        'action_label' => 'View schedule',
        'action_url' => 'https://old.example.test/applicant/consultation',
    ];

    $applicantNotice = makeReleaseNavigationNotice(
        'consultation.session_published.v1',
        ApplicantIdentity::class,
        $identity->id,
        $versionedPayload,
    );
    $flatLegacyNotice = makeReleaseNavigationNotice(
        'consultation.slot_reserved.v1',
        ApplicantIdentity::class,
        $identity->id,
        $flatLegacyPayload,
    );
    $unrelatedApplicantNotice = makeReleaseNavigationNotice(
        'unrelated.event.v1',
        ApplicantIdentity::class,
        $identity->id,
        $versionedPayload,
    );
    $staffNotice = makeReleaseNavigationNotice(
        'consultation.slot_reserved.v1',
        User::class,
        $user->id,
        $versionedPayload,
    );

    $rows = collect(app(NotificationProjection::class)->list($user))->keyBy('id');

    expect($rows[$applicantNotice->id]['action_url'])->toBe('/applicant/release-schedule')
        ->and($rows[$flatLegacyNotice->id]['action_url'])->toBe('/applicant/release-schedule')
        ->and($rows[$unrelatedApplicantNotice->id]['action_url'])->toBe($versionedPayload['portal']['action_url'])
        ->and($rows[$staffNotice->id]['action_url'])->toBe($versionedPayload['portal']['action_url'])
        ->and($applicantNotice->fresh()->payload)->toBe($versionedPayload)
        ->and($flatLegacyNotice->fresh()->payload)->toBe($flatLegacyPayload)
        ->and($unrelatedApplicantNotice->fresh()->payload)->toBe($versionedPayload)
        ->and($staffNotice->fresh()->payload)->toBe($versionedPayload);
});

function makeReleaseNavigationNotice(
    string $eventType,
    string $recipientType,
    int $recipientId,
    array $payload,
): NotificationDelivery {
    return NotificationDelivery::query()->create([
        'id' => (string) Str::uuid(),
        'event_id' => hash('sha256', Str::uuid()->toString()),
        'event_type' => $eventType,
        'event_version' => 1,
        'recipient_type' => $recipientType,
        'recipient_id' => $recipientId,
        'channel' => NotificationDelivery::CHANNEL_IN_APP,
        'template_key' => $eventType,
        'template_version' => 1,
        'payload' => $payload,
        'status' => NotificationDelivery::STATUS_PENDING,
        'attempts' => 0,
    ]);
}
