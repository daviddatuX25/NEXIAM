<?php

use App\Admission\Support\ApplicantReference;
use App\Models\Foundation\AcademicCycle;

dataset('reference codes for sequence one', [
    'canonical uppercase' => ['AY26-00001'],
    'canonical lowercase' => ['ay26-00001'],
    'legacy' => ['APP-2026-00001'],
    'padded digits' => ['00001'],
    'compact number' => ['1'],
    'year plus sequence' => ['2600001'],
]);

test('formats the canonical reference using the cycle school year', function (): void {
    $cycle = new AcademicCycle(['school_year' => '2026-2027']);

    expect(ApplicantReference::format($cycle, 1))->toBe('AY26-00001');
});

test('formats the canonical reference using the cycle opening date when school year is absent', function (): void {
    $cycle = new AcademicCycle(['opens_on' => '2026-06-01']);

    expect(ApplicantReference::format($cycle, 1))->toBe('AY26-00001');
});

test('extracts sequence one across supported reference forms', function (string $reference): void {
    $cycle = new AcademicCycle(['school_year' => '2026-2027']);

    expect(ApplicantReference::extractSequence($reference, $cycle))->toBe(1)
        ->and(ApplicantReference::paddedDigits($reference, $cycle))->toBe('00001')
        ->and(ApplicantReference::compactNumber($reference, $cycle))->toBe('1');
})->with('reference codes for sequence one');

test('returns null for arbitrary non-numeric codes', function (): void {
    $cycle = new AcademicCycle(['school_year' => '2026-2027']);

    expect(ApplicantReference::extractSequence('SYN-CUSTOM', $cycle))->toBeNull()
        ->and(ApplicantReference::paddedDigits('SYN-CUSTOM', $cycle))->toBeNull()
        ->and(ApplicantReference::compactNumber('SYN-CUSTOM', $cycle))->toBeNull();
});

test('candidate codes include exact sequence representations without neighboring sequences', function (): void {
    $cycle = new AcademicCycle(['school_year' => '2026-2027']);
    $candidates = ApplicantReference::candidateCodes('1', $cycle);

    expect($candidates)->toContain('AY26-00001', 'APP-2026-00001', '00001', '1')
        ->not->toContain('AY26-00010', 'AY26-00011');
});
