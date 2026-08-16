<?php

use App\Admission\Actions\SetGuidanceProgramPool;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use App\Release\Actions\ReleaseResult;

/**
 * Applicant status-spine hub + self-service records
 * (2026-08-15-applicant-portal-interface, design §3/§5/§8).
 */

test('hub renders empty state when the account has no attempt', function (): void {
    seedE2Foundation();

    $user = User::query()->create([
        'name' => 'No Attempt',
        'email' => 'hub-empty@example.test',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('page_state', 'empty'));
});

test('hub marks needs_correction on the submitted stage with fix action', function (): void {
    seedE2Foundation();

    $result = submitSyntheticApplicant('hub-correct@example.test');
    $applicant = app(\App\Admission\Actions\AuthenticateApplicantWithAccessKey::class)->handle(
        $result['access_token'],
        '2001-01-01',
    );

    $result['attempt']->update([
        'status' => AdmissionAttempt::STATUS_NEEDS_CORRECTION,
        'correction_notes' => 'Birthdate looks wrong.',
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('page_state', 'ready')
            ->where('notices.needs_correction', true)
            ->where('notices.correction_notes', 'Birthdate looks wrong.')
            ->where('stages.0.id', 'submitted')
            ->where('stages.0.status', 'action')
            ->where('stages.0.actions.0.key', 'fix_application')
            ->where('stages.1.status', 'current'));
});

test('hub renders released score artifact for MODE_ONLINE', function (): void {
    [$attempt, $applicant] = buildReleasedAdvisedAttempt($this, 'hub-online@example.test', releaseMode: ReleaseResult::MODE_ONLINE);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) use ($attempt) {
            $page
                ->component('Applicant/Portal')
                ->where('page_state', 'ready')
                ->where('identity.applicant_no', $attempt->reference_code);

            $stages = $page->toArray()['props']['stages'] ?? [];
            $results = collect($stages)->firstWhere('id', 'results');

            expect($results['status'])->toBe('done')
                ->and($results['release']['mode'])->toBe('online')
                ->and($results['profile'])->not->toBeNull()
                ->and($results['profile']['series'])->not->toBeEmpty();

            return $page;
        });
});

test('hub never serializes scores for MODE_F2F and shows claim state', function (): void {
    [$attempt, $applicant] = buildReleasedAdvisedAttempt($this, 'hub-f2f@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page
                ->component('Applicant/Portal')
                ->where('page_state', 'ready')
                ->where('notices.results_blocked', true);

            $stages = $page->toArray()['props']['stages'] ?? [];
            $results = collect($stages)->firstWhere('id', 'results');

            expect($results['status'])->toBe('action')
                ->and($results['release']['mode'])->toBe('f2f')
                ->and($results['profile'])->toBeNull()
                ->and($results['actions'][0]['key'])->toBe('book_consultation');

            return $page;
        });
});

test('hub renders terminal card for dismissed attempts', function (): void {
    [$attempt, $applicant] = buildReleasedAdvisedAttempt($this, 'hub-dismissed@example.test');

    $attempt->update([
        'status' => AdmissionAttempt::STATUS_DISMISSED,
        'dismissal_reason' => 'Missing documents.',
    ]);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/Portal')
            ->where('notices.dismissed', true)
            ->where('notices.dismissal_reason', 'Missing documents.')
            ->where('stages', []));
});

test('hub shows guidance default as auto stage with optional ack', function (): void {
    [$attempt, $applicant, $guidance] = buildReleasedAdvisedAttempt($this, 'hub-default@example.test', releaseMode: ReleaseResult::MODE_ONLINE);

    app(SetGuidanceProgramPool::class)->handle(
        $attempt,
        $guidance,
        DEFAULT_PROGRAM,
        [
            ['program_code' => DEFAULT_PROGRAM, 'display_order' => 1, 'basis' => 'preference'],
            ['program_code' => OTHER_PROGRAM, 'display_order' => 2, 'basis' => 'guidance_option'],
        ],
        'Test default.',
        'hub-pool-default',
    );

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/Portal');

            $stages = $page->toArray()['props']['stages'] ?? [];
            $program = collect($stages)->firstWhere('id', 'program_choice');

            expect($program['status'])->toBe('auto')
                ->and($program['summary'])->toContain('projection active')
                ->and($program['actions'][0]['key'])->toBe('ack_default');

            return $page;
        });
});

test('stage order follows the canonical spine', function (): void {
    [$attempt, $applicant] = buildReleasedAdvisedAttempt($this, 'hub-order@example.test', releaseMode: ReleaseResult::MODE_ONLINE);

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/Portal');

            $ids = collect($page->toArray()['props']['stages'] ?? [])->pluck('id')->all();

            expect($ids)->toBe([
                'submitted',
                'review',
                'exam',
                'results',
                'consultation',
                'program_choice',
                'enrolling',
                'enrolled',
            ]);

            return $page;
        });
});

test('my application is read-only and own-scope', function (): void {
    [$attempt, $applicant] = buildReleasedAdvisedAttempt($this, 'myapp-owner@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.application', ['attempt' => $attempt->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Applicant/MyApplication')
            ->where('application.reference_code', $attempt->reference_code)
            ->where(
                'application.personal.name',
                trim((string) $attempt->identity->first_name.' '.(string) $attempt->identity->last_name),
            )
            ->where('application.preferences.0', 'BSN')
            ->where('application.grades', fn ($grades) => count($grades) > 0));

    // Cross-applicant access is denied.
    $other = submitSyntheticApplicant('myapp-other@example.test');
    $otherUser = app(\App\Admission\Actions\AuthenticateApplicantWithAccessKey::class)->handle(
        $other['access_token'],
        '2001-01-01',
    );

    $this->actingAs($otherUser)
        ->get(route('applicant.application', ['attempt' => $attempt->id]))
        ->assertForbidden();
});

test('applicant shell share exposes server-authored navigation', function (): void {
    [$attempt, $applicant] = buildReleasedAdvisedAttempt($this, 'nav@example.test');

    $this->actingAs($applicant)
        ->get(route('applicant.portal'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('Applicant/Portal');

            $nav = $page->toArray()['props']['applicant_navigation'] ?? [];

            expect(collect($nav)->pluck('key')->all())->toBe([
                'portal',
                'application',
                'schedule',
                'consultation',
                'program_choice',
                'notifications',
                'qr',
                'admission_support',
            ]);

            return $page;
        });
});
