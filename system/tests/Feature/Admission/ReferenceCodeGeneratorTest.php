<?php

use App\Admission\Support\ApplicantReferenceResolver;
use App\Admission\Support\ReferenceCodeGenerator;
use App\Consultation\Queries\ConsultationApplicantLookupQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;

test('allocates consecutive references for a cycle', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $generator = app(ReferenceCodeGenerator::class);

    expect($generator->next($cycle))->toBe('AY26-00001')
        ->and($generator->next($cycle))->toBe('AY26-00002');
});

test('allocation advances past references used in another semester', function (): void {
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycleOne = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $identityOne = ApplicantIdentity::query()->create([
        'institution_id' => $institution->id,
        'normalized_key' => str_repeat('a', 64),
        'first_name' => 'First',
        'last_name' => 'Applicant',
        'birthdate' => '2001-01-01',
        'sex' => 'female',
    ]);
    $identityTwo = ApplicantIdentity::query()->create([
        'institution_id' => $institution->id,
        'normalized_key' => str_repeat('b', 64),
        'first_name' => 'Second',
        'last_name' => 'Applicant',
        'birthdate' => '2002-02-02',
        'sex' => 'male',
    ]);

    foreach ([[$identityOne, 'AY26-00001'], [$identityTwo, 'AY26-00002']] as [$identity, $reference]) {
        AdmissionAttempt::query()->create([
            'applicant_identity_id' => $identity->id,
            'institution_id' => $institution->id,
            'academic_cycle_id' => $cycleOne->id,
            'reference_code' => $reference,
            'status' => AdmissionAttempt::STATUS_PENDING,
        ]);
    }

    $cycleTwo = AcademicCycle::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-CYCLE-2026-2',
        'name' => 'Academic Year 2026–2027 2nd Semester',
        'school_year' => '2026-2027',
        'semester' => '2nd Semester',
        'opens_on' => '2026-11-01',
        'closes_on' => '2027-03-31',
        'is_active' => false,
    ]);

    expect(app(ReferenceCodeGenerator::class)->next($cycleTwo))->toBe('AY26-00003');
});

test('resolves exact canonical and shorthand references within a cycle', function (string $input): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $attempt = submitSyntheticApplicant('reference-resolver@example.test')['attempt'];

    expect(ApplicantReferenceResolver::resolveInCycle($cycle, $input)?->id)->toBe($attempt->id);
})->with(['AY26-00001', 'ay26-00001', '00001', '1', '2600001']);

test('does not resolve a neighboring sequence', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    submitSyntheticApplicant('reference-resolver-neighbor@example.test');

    expect(ApplicantReferenceResolver::resolveInCycle($cycle, 'AY26-00011'))->toBeNull();
});

test('normalizes numeric shorthand and detects batch duplicates in stage import logic', function (): void {
    seedE2Foundation();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $attempt = submitSyntheticApplicant('normalize-shorthand@example.test')['attempt'];

    expect($attempt->reference_code)->toBe('AY26-00001')
        ->and(ApplicantReferenceResolver::normalizeForCycle($cycle, '1'))->toBe('AY26-00001')
        ->and(ApplicantReferenceResolver::normalizeForCycle($cycle, '00001'))->toBe('AY26-00001')
        ->and(ApplicantReferenceResolver::normalizeForCycle($cycle, '2600001'))->toBe('AY26-00001')
        ->and(ApplicantReferenceResolver::normalizeForCycle($cycle, '5'))->toBe('AY26-00005');
});

test('consultation lookup query and qr checkin fallback resolve shorthand numbers in active cycle', function (): void {
    seedE2Foundation();
    $attempt = submitSyntheticApplicant('lookup-shorthand@example.test')['attempt'];
    $attempt->update(['status' => AdmissionAttempt::STATUS_ACCEPTED]);

    $lookup = app(ConsultationApplicantLookupQuery::class);
    $byOne = $lookup->matchingAcceptedAttempts('1', 10, (int) $attempt->institution_id);
    $byPadded = $lookup->matchingAcceptedAttempts('00001', 10, (int) $attempt->institution_id);

    expect($byOne->pluck('id')->all())->toContain($attempt->id)
        ->and($byPadded->pluck('id')->all())->toContain($attempt->id);
});
