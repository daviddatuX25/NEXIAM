<?php

use App\AdmissionEngine\DTOs\ProgramCompatibilityItem;
use App\AdmissionEngine\Jobs\PrecomputeApplicantCmiJob;

test('program compatibility item accepts and preserves float precision', function (): void {
    $item = new ProgramCompatibilityItem(
        rank: 1,
        programCode: 'BSIT',
        programName: 'Information Technology',
        compatibilityIndex: 88.42,
        band: 'high_match',
        passedCutoff: true,
        drivers: ['Quantitative score meets honors benchmark'],
    );

    expect($item->compatibilityIndex)->toBe(88.42)
        ->and($item->toArray()['compatibility_index'])->toBe(88.42);
});

test('precompute applicant cmi job implements unique queueable contract', function (): void {
    $job = new PrecomputeApplicantCmiJob(12345);

    expect($job->uniqueId())->toBe('12345')
        ->and($job->tries)->toBe(3)
        ->and($job->uniqueFor)->toBe(300);
});
