<?php

use App\Admission\Support\ApplicantAccessKey;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    seedE2Foundation();
});

test('demo:seed-pipeline command with count generates applicants with readable filipino names', function (): void {
    $beforeCount = AdmissionAttempt::query()->count();

    $exitCode = Artisan::call('demo:seed-pipeline', ['count' => 5]);

    expect($exitCode)->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe($beforeCount + 5);

    $latestAttempts = AdmissionAttempt::query()->latest('id')->limit(5)->with(['identity', 'accessChallenge'])->get();

    foreach ($latestAttempts as $attempt) {
        expect($attempt->reference_code)->toMatch('/^AY\d{2}-\d{5}$/')
            ->and($attempt->status)->toBe(AdmissionAttempt::STATUS_PENDING)
            ->and($attempt->identity)->not->toBeNull()
            ->and($attempt->identity->first_name)->not->toBeEmpty()
            ->and($attempt->identity->last_name)->not->toBeEmpty()
            ->and($attempt->identity->middle_name)->not->toBeEmpty()
            // Ensure no legacy cartoonish joke names
            ->and($attempt->identity->last_name)->not->toBeIn(['Reviewer', 'Reserver', 'Seater', 'Grader', 'Consultant', 'Pooler', 'Enroller'])
            ->and($attempt->accessChallenge)->not->toBeNull()
            ->and($attempt->accessChallenge->token_hash)->toHaveLength(64)
            ->and($attempt->strand)->toBeIn(['STEM', 'ABM', 'HUMSS', 'TVL', 'GAS'])
            ->and($attempt->last_school_enrolled)->not->toBeEmpty();
    }
});

test('demo:seed-pipeline with --status=accepted generates accepted applicants', function (): void {
    $beforeCount = AdmissionAttempt::query()->count();

    $exitCode = Artisan::call('demo:seed-pipeline', ['count' => 3, '--status' => 'accepted']);

    expect($exitCode)->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe($beforeCount + 3);

    $attempts = AdmissionAttempt::query()->latest('id')->limit(3)->get();
    foreach ($attempts as $attempt) {
        expect($attempt->status)->toBe(AdmissionAttempt::STATUS_ACCEPTED)
            ->and($attempt->accepted_at)->not->toBeNull();
    }
});

test('demo:seed-pipeline without arguments prints usage and does not mutate attempts', function (): void {
    $beforeCount = AdmissionAttempt::query()->count();

    $exitCode = Artisan::call('demo:seed-pipeline');

    expect($exitCode)->toBe(0)
        ->and(AdmissionAttempt::query()->count())->toBe($beforeCount);
});

test('post /demo/generate creates applicants and returns structured json response', function (): void {
    $beforeCount = AdmissionAttempt::query()->count();

    $response = $this->postJson(route('demo.generate'), [
        'count' => 4,
        'status' => 'pending',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.count', 4);

    expect(AdmissionAttempt::query()->count())->toBe($beforeCount + 4);

    $data = $response->json('data.applicants');
    expect($data)->toHaveCount(4);

    foreach ($data as $applicant) {
        expect($applicant['reference_code'])->toMatch('/^AY\d{2}-\d{5}$/')
            ->and($applicant['full_name'])->not->toBeEmpty()
            ->and(ApplicantAccessKey::isValidShape($applicant['access_key']))->toBeTrue()
            ->and($applicant['status'])->toBe('pending');
    }
});

test('post /demo/generate validates count bounds', function (): void {
    $this->postJson(route('demo.generate'), ['count' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['count']);

    $this->postJson(route('demo.generate'), ['count' => 101])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['count']);
});

test('get /demo serves the pipeline generator surface', function (): void {
    $response = $this->get(route('demo.index'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Foundation/DemoGuide')
        ->has('pipeline_generator')
        ->where('pipeline_generator.default_count', 10)
        ->has('pipeline_generator.programs')
        ->has('pipeline_generator.recent_attempts')
    );
});

test('demo generation endpoints are forbidden in production environment', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    $this->get(route('demo.index'))->assertForbidden();
    $this->postJson(route('demo.generate'), ['count' => 5])->assertForbidden();

    $this->app->detectEnvironment(static fn (): string => 'testing');
});
