<?php

declare(strict_types=1);

use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Institution;
use Database\Seeders\PublicIntakeConsentDemoSeeder;
use Database\Seeders\PublicPoliciesDemoSeeder;

test('wayfinding privacy notice is published without replacing the intake policy', function (): void {
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    (new PublicPoliciesDemoSeeder)->run();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $policy = PublicPolicy::query()
        ->where('institution_id', $institution->getKey())
        ->where('title', 'Campus Navigation & Geolocation Privacy Notice')
        ->with('publishedRevision')
        ->firstOrFail();

    expect($policy->status)->toBe('published')
        ->and($policy->kind)->toBe('privacy')
        ->and($policy->publisher_key)->toBe('office:registrar')
        ->and($policy->is_designated_intake)->toBeFalse();

    $digest = $policy->publishedRevision?->contentDigest();
    expect($digest)->not->toBeNull()
        ->and($digest)->toMatch('/\A[a-f0-9]{64}\z/');

    $intakePolicy = PublicPolicy::designatedIntake((int) $institution->getKey());
    expect($intakePolicy)->not->toBeNull()
        ->and((int) $intakePolicy->getKey())->not->toBe((int) $policy->getKey());
});
