<?php

use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Reports\Queries\CourseQuotaQualifiersDataQuery;
use App\Reports\Surfaces\CourseQuotaQualifiersSurface;

function acceptedQualifier(string $lastName, string $acceptedAt, array $attributes = []): AdmissionAttempt
{
    $attempt = submitSyntheticApplicant(lastName: $lastName)['attempt'];
    $attempt->update(array_merge([
        'status' => AdmissionAttempt::STATUS_ACCEPTED,
        'accepted_at' => $acceptedAt,
    ], $attributes));

    return $attempt->fresh('identity');
}

function quotaFor(string $programCode, int $cycleId, int $slots): void
{
    $program = Program::query()->firstOrCreate(
        ['code' => $programCode],
        ['institution_id' => AcademicCycle::active()?->institution_id ?? 1, 'name' => "Program {$programCode}", 'is_active' => true],
    );
    ProgramQuota::query()->updateOrCreate(
        ['program_id' => $program->id, 'academic_cycle_id' => $cycleId],
        ['slots' => $slots],
    );
}

beforeEach(function (): void {
    seedE2Foundation();
});

test('course quota qualifiers calculates cycle quota and accepted summary in acceptance order', function (): void {
    $cycle = AcademicCycle::active();
    quotaFor('BSN', $cycle->id, 2);
    quotaFor('BSIT', $cycle->id, 3);

    acceptedQualifier('Later', '2026-09-12 10:00:00', ['preferred_program_code' => 'BSIT']);
    acceptedQualifier('Earlier', '2026-09-10 10:00:00', ['preferred_program_code' => 'BSN']);
    $otherStatus = submitSyntheticApplicant(lastName: 'Pending')['attempt'];
    $foreignCycle = AcademicCycle::query()->create([
        'institution_id' => $cycle->institution_id,
        'code' => 'QUALIFIER-OTHER-CYCLE',
        'name' => 'Other Cycle',
        'opens_on' => '2027-01-01',
        'closes_on' => '2027-12-31',
    ]);
    acceptedQualifier('Foreign Cycle', '2026-09-11 10:00:00', [
        'academic_cycle_id' => $foreignCycle->id,
    ]);

    $report = app(CourseQuotaQualifiersDataQuery::class)->handle([]);

    expect($report['target_quota'])->toBe(5)
        ->and($report['accepted_count'])->toBe(2)
        ->and($report['remaining_slots'])->toBe(3)
        ->and($report['fill_rate'])->toBe('40.00%')
        ->and(array_column($report['qualifiers'], 'applicant_name'))->toBe(['Test Earlier', 'Test Later'])
        ->and(array_column($report['qualifiers'], 'row_no'))->toBe([1, 2])
        ->and($report['qualifiers'][0]['admission_status'])->toBe('Accepted')
        ->and($report['qualifiers'][0]['accepted_at'])->toBe('2026-09-10')
        ->and($otherStatus->fresh()->status)->toBe(AdmissionAttempt::STATUS_PENDING);
});

test('course quota qualifiers filters matching preference codes and caps fill rate at quota', function (): void {
    $cycle = AcademicCycle::active();
    quotaFor('BSN', $cycle->id, 1);
    quotaFor('BSIT', $cycle->id, 4);
    acceptedQualifier('Preferred', '2026-09-10 10:00:00', ['preferred_program_code' => 'BSN']);
    acceptedQualifier('Preference', '2026-09-11 10:00:00', [
        'preferred_program_code' => 'BSIT',
        'program_preference_1_code' => 'BSN',
    ]);
    acceptedQualifier('Excluded', '2026-09-12 10:00:00', ['preferred_program_code' => 'BSIT', 'program_preference_1_code' => 'BSIT']);

    $report = app(CourseQuotaQualifiersDataQuery::class)->handle([
        'academic_cycle_id' => $cycle->id,
        'program_code' => 'BSN',
    ]);

    expect($report['target_quota'])->toBe(1)
        ->and($report['accepted_count'])->toBe(2)
        ->and($report['remaining_slots'])->toBe(0)
        ->and($report['fill_rate'])->toBe('100.00%')
        ->and(array_column($report['qualifiers'], 'program_code'))->toBe(['BSN', 'BSIT']);
});

test('course quota qualifiers formula-escapes strings and returns zero statistics without cycle quotas', function (): void {
    $cycle = AcademicCycle::active();
    $attempt = acceptedQualifier('Applicant', '2026-09-10 10:00:00', [
        'reference_code' => '=REF',
        'strand' => '+STEM',
        'preferred_program_code' => '-BSN',
        'gwa' => '1.75',
    ]);
    $attempt->identity->update(['first_name' => '@Name']);

    $report = app(CourseQuotaQualifiersDataQuery::class)->handle(['academic_cycle_id' => $cycle->id]);

    expect($report['target_quota'])->toBe(0)
        ->and($report['accepted_count'])->toBe(1)
        ->and($report['remaining_slots'])->toBe(0)
        ->and($report['fill_rate'])->toBe('0.00%')
        ->and($report['qualifiers'][0])->toMatchArray([
            'reference_code' => "'=REF",
            'applicant_name' => "'@Name Applicant",
            'strand' => "'+STEM",
            'gwa' => '1.75',
            'program_code' => "'-BSN",
        ])
        ->and($report['prepared_by'])->toBe('Registrar Clerk')
        ->and($report['verified_by'])->toBe('Admissions Officer')
        ->and($report['approved_by'])->toBe('Campus Registrar');
});

test('course quota qualifier surface data shape matches the sample context', function (): void {
    $cycle = AcademicCycle::active();
    $report = app(CourseQuotaQualifiersSurface::class)->resolveData(['academic_cycle_id' => $cycle->id]);

    expect(array_keys($report))->toBe(array_keys(app(CourseQuotaQualifiersSurface::class)->sampleContext()))
        ->and($report['qualifiers'])->toBe([]);
});
