<?php

use App\Admission\Actions\ObserveApplicationOpeningAvailability;
use App\Models\Admission\ApplicationOpeningAvailabilityState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use Database\Seeders\PublicIntakeConsentDemoSeeder;

function prepareCurrentPeriodForOpeningAvailabilityTest(): array
{
    seedE2Foundation();
    (new PublicIntakeConsentDemoSeeder)->run();
    config(['application-opening-alerts.enabled' => true]);
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $cycle->forceFill([
        'opens_on' => now()->subMonth()->toDateString(),
        'closes_on' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ])->save();
    $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->firstOrFail();
    $window->forceFill([
        'opens_at' => now()->subHour(),
        'closes_at' => now()->addWeek(),
        'status' => 'published',
    ])->save();

    return [$cycle, $window, Institution::query()->findOrFail($cycle->institution_id)];
}

test('availability generation advances on a real close and reopen but not on a deadline extension', function (): void {
    [$cycle, $window, $institution] = prepareCurrentPeriodForOpeningAvailabilityTest();
    $observer = app(ObserveApplicationOpeningAvailability::class);

    $first = $observer->observeInstitution((int) $institution->id);
    expect($first)->toBeInstanceOf(ApplicationOpeningAvailabilityState::class)
        ->and($first->is_available)->toBeTrue()
        ->and($first->generation)->toBeGreaterThan(0);
    $generation = $first->generation;

    $window->forceFill(['closes_at' => now()->addWeeks(2)])->save();
    $afterExtension = $observer->observeSourceChange($window);
    expect($afterExtension->generation)->toBe($generation)
        ->and($afterExtension->is_available)->toBeTrue();

    $cycle->forceFill(['is_active' => false])->save();
    $closed = $observer->observeSourceChange($cycle);
    expect($closed->is_available)->toBeFalse()
        ->and($closed->generation)->toBe($generation);

    $cycle->forceFill(['is_active' => true])->save();
    $reopened = $observer->observeSourceChange($cycle);
    expect($reopened->is_available)->toBeTrue()
        ->and($reopened->generation)->toBe($generation + 1);
});
