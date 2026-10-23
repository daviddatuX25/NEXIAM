<?php

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Operation;
use App\Models\User;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use Database\Seeders\ApplicantOfficeInquiryDemoSeeder;
use Illuminate\Support\Str;
use RuntimeException;

/** @return array{applicant: User, registrar: User, attempt: AdmissionAttempt, practice_set: PracticeFixtureSet} */
function practiceOfficeInquiryFixture(): array
{
    seedE2Foundation();
    (new ApplicantOfficeInquiryDemoSeeder)->run();

    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $registrar = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::REGISTRAR_EMAIL)->firstOrFail();
    $guidance = User::query()->where('email', ApplicantOfficeInquiryDemoSeeder::GUIDANCE_EMAIL)->firstOrFail();
    $practiceSet = PracticeFixtureSet::query()->create([
        'label' => 'Office inquiry practice',
        'institution_id' => $cycle->institution_id,
        'academic_cycle_id' => $cycle->id,
        'scenario' => 'student',
        'purpose' => 'research',
        'created_by_user_id' => $guidance->id,
        'actor_bindings' => ['registrar' => $registrar->id, 'guidance' => $guidance->id],
        'material_snapshot' => [],
        'credentials' => [],
    ]);
    $submission = app(PracticeWorkspace::class)->run(
        $practiceSet,
        fn (): array => submitSyntheticApplicant('office-inquiry.'.Str::uuid().'@example.test', 'Inquiry'),
    );
    $attempt = $submission['attempt'];
    $applicant = app(AuthenticateApplicantWithAccessKey::class)->handle($submission['access_token'], '2001-01-01');
    $practiceSet->update(['credentials' => [(string) $attempt->applicant_identity_id => [
        'access_key' => $submission['access_token'],
        'birthdate' => '2001-01-01',
        'admission_attempt_id' => $attempt->id,
    ]]]);
    $registrar->authenticationState?->forceFill([
        'activation_completed_at' => now(),
        'password_set_at' => now(),
        'must_change_password' => false,
        'mfa_required' => false,
        'two_factor_generation' => (string) Str::uuid(),
    ])->save();

    return compact('applicant', 'registrar', 'attempt') + ['practice_set' => $practiceSet];
}

test('office inquiry create, send, and resolve save typed operation receipts in the attempt practice set', function (): void {
    $fixture = practiceOfficeInquiryFixture();
    $workspace = app(PracticeWorkspace::class);
    $commandTypes = [
        'admission.office-inquiry.create',
        'admission.office-inquiry.send',
        'admission.office-inquiry.resolve',
    ];
    $priorReceiptCount = Operation::query()->whereIn('type', $commandTypes)->count();
    $inquiryId = null;

    $create = $workspace->run($fixture['practice_set'], function () use ($fixture, &$inquiryId): void {
        $this->actingAs($fixture['applicant'])
            ->postJson('/applicant/inquiries', [
                'office' => 'registrar',
                'subject' => 'Practice inquiry',
                'body' => 'A question from the active practice set.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSuccessful()
            ->assertJsonPath('office', 'registrar');

        $inquiryId = (int) Operation::query()
            ->where('type', 'admission.office-inquiry.create')
            ->where('payload->attempt_id', (string) $fixture['attempt']->id)
            ->latest('created_at')
            ->firstOrFail()
            ->payload['receipt_inquiry_id'];
    });

    expect($create)->toBeNull()->and($inquiryId)->toBeGreaterThan(0);

    $workspace->run($fixture['practice_set'], function () use ($fixture, $inquiryId): void {
        $this->actingAs($fixture['applicant'])
            ->postJson("/inquiries/{$inquiryId}/messages", [
                'body' => 'A follow-up from the active practice set.',
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSuccessful();

        $resolve = actingAsAdmissionSupportStaff($this, $fixture['registrar'])
            ->withSession([
                'practice_fixture_set_id' => $fixture['practice_set']->id,
                'practice_fixture_set_actor_id' => $fixture['registrar']->id,
            ])
            ->postJson("/inquiries/{$inquiryId}/resolve", [
                'idempotency_key' => (string) Str::uuid(),
            ]);
        expect($resolve->status())->toBe(200, $resolve->getContent());

    });
    expect(Operation::query()->whereIn('type', $commandTypes)->count())->toBe($priorReceiptCount + 3);
});

test('inquiry operation receipts reject a different practice set and unknown command types', function (): void {
    $fixture = practiceOfficeInquiryFixture();
    $otherSet = PracticeFixtureSet::query()->create([
        'label' => 'Other office inquiry practice',
        'institution_id' => $fixture['attempt']->institution_id,
        'academic_cycle_id' => $fixture['attempt']->academic_cycle_id,
        'scenario' => 'application-review',
        'purpose' => 'demo',
        'created_by_user_id' => $fixture['registrar']->id,
        'actor_bindings' => ['applicant' => $fixture['applicant']->id],
        'material_snapshot' => [],
        'credentials' => [],
    ]);

    app(PracticeWorkspace::class)->run($otherSet, function () use ($fixture): void {
        expect(fn () => Operation::query()->create([
            'idempotency_key' => $fixture['registrar']->id.':admission.office-inquiry.create:attempt:'.$fixture['attempt']->id.':'.Str::uuid(),
            'type' => 'admission.office-inquiry.create',
            'status' => Operation::STATUS_SUCCEEDED,
            'attempts' => 0,
            'payload' => ['attempt_id' => (string) $fixture['attempt']->id],
            'finished_at' => now(),
        ]))->toThrow(RuntimeException::class, 'This case/session is outside the selected practice set.');

        expect(fn () => Operation::query()->create([
            'idempotency_key' => $fixture['registrar']->id.':admission.office-inquiry.unknown:attempt:'.$fixture['attempt']->id.':'.Str::uuid(),
            'type' => 'admission.office-inquiry.unknown',
            'status' => Operation::STATUS_SUCCEEDED,
            'attempts' => 0,
            'payload' => ['attempt_id' => (string) $fixture['attempt']->id],
            'finished_at' => now(),
        ]))->toThrow(RuntimeException::class, 'Institutional configuration is read-only in a practice workspace.');
    });
});
