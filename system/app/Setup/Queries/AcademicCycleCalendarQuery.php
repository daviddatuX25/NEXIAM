<?php

namespace App\Setup\Queries;

use App\Admission\Queries\CurrentIntakeQuery;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;

final class AcademicCycleCalendarQuery
{
    public function __construct(
        private readonly CurrentIntakeQuery $currentIntake,
    ) {}

    /**
     * @return array{
     *     cycle_id: int|null,
     *     label: string|null,
     *     window_id: int|null,
     *     opens_at: string|null,
     *     closes_at: string|null,
     *     cycle_closes_on: string|null,
     *     status: string,
     *     availability: string,
     *     can_extend: bool,
     *     baseline: array<string, int|string|bool|null>|null,
     *     timezone: 'UTC',
     *     effective_window: array{id: int, name: string, opens_at: string, closes_at: string}|null,
     * }
     */
    public function forInstitution(Institution $institution): array
    {
        $intake = $this->currentIntake->forInstitution($institution);
        $cycle = $intake['cycle'] ?? AcademicCycle::query()
            ->where('institution_id', $institution->getKey())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($cycle === null) {
            return [
                'cycle_id' => null,
                'label' => null,
                'window_id' => null,
                'opens_at' => null,
                'closes_at' => null,
                'cycle_closes_on' => null,
                'status' => 'none',
                'availability' => 'no_active_cycle',
                'can_extend' => false,
                'baseline' => null,
                'timezone' => 'UTC',
                'effective_window' => null,
            ];
        }

        $window = $cycle->primaryWindow();
        if ($window === null) {
            return [
                'cycle_id' => (int) $cycle->getKey(),
                'label' => $cycle->staffLabel(),
                'window_id' => null,
                'opens_at' => null,
                'closes_at' => null,
                'cycle_closes_on' => $cycle->closes_on?->toDateString(),
                'status' => 'none',
                'availability' => 'no_window',
                'can_extend' => false,
                'baseline' => null,
                'timezone' => 'UTC',
                'effective_window' => null,
            ];
        }

        $effectiveWindow = null;
        if ($intake !== null && (int) $intake['cycle']->getKey() === (int) $cycle->getKey()) {
            $effectiveWindow = [
                'id' => (int) $intake['window']->getKey(),
                'name' => (string) $intake['window']->name,
                'opens_at' => $intake['window']->opens_at->copy()->utc()->toIso8601String(),
                'closes_at' => $intake['window']->closes_at->copy()->utc()->toIso8601String(),
            ];
        }
        $availability = $this->availability($institution, $cycle, $window, $intake);

        return [
            'cycle_id' => (int) $cycle->getKey(),
            'label' => $cycle->staffLabel(),
            'window_id' => (int) $window->getKey(),
            'opens_at' => $window->opens_at?->copy()->utc()->toIso8601String(),
            'closes_at' => $window->closes_at?->copy()->utc()->toIso8601String(),
            'cycle_closes_on' => $cycle->closes_on?->toDateString(),
            'status' => (string) $window->status,
            'availability' => $availability,
            'can_extend' => $availability === 'open'
                && $cycle->closes_on?->toDateString() === $window->closes_at?->copy()->utc()->toDateString(),
            'baseline' => self::baselineFor($cycle, $window),
            'timezone' => 'UTC',
            'effective_window' => $effectiveWindow,
        ];
    }

    /**
     * @return array{
     *     cycle_id: int,
     *     cycle_is_active: bool,
     *     cycle_opens_on: string,
     *     cycle_closes_on: string,
     *     school_year: string,
     *     semester: string,
     *     window_id: int,
     *     opens_at: string,
     *     closes_at: string,
     *     status: string
     * }
     */
    public static function baselineFor(AcademicCycle $cycle, ApplicationWindow $window): array
    {
        return [
            'cycle_id' => (int) $cycle->getKey(),
            'cycle_is_active' => (bool) $cycle->is_active,
            'cycle_opens_on' => $cycle->opens_on?->toDateString() ?? '',
            'cycle_closes_on' => $cycle->closes_on?->toDateString() ?? '',
            'school_year' => (string) $cycle->school_year,
            'semester' => (string) $cycle->semester,
            'window_id' => (int) $window->getKey(),
            'opens_at' => $window->opens_at?->copy()->utc()->format('Y-m-d H:i:s') ?? '',
            'closes_at' => $window->closes_at?->copy()->utc()->format('Y-m-d H:i:s') ?? '',
            'status' => (string) $window->status,
        ];
    }

    /**
     * @param  array{cycle: AcademicCycle, window: ApplicationWindow}|null  $intake
     */
    private function availability(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        ?array $intake,
    ): string {
        $now = now()->utc();
        $today = $now->toDateString();
        if ($intake !== null
            && (int) $intake['cycle']->getKey() === (int) $cycle->getKey()
            && (int) $intake['window']->getKey() !== (int) $window->getKey()) {
            return 'another_window_open';
        }

        if ($cycle->opens_on === null || $cycle->closes_on === null || $window->opens_at === null || $window->closes_at === null) {
            return 'missing_dates';
        }

        if ($window->status !== 'published') {
            return 'unpublished';
        }

        if ($cycle->opens_on->toDateString() > $today) {
            return 'cycle_upcoming';
        }

        if ($cycle->closes_on->toDateString() < $today) {
            return 'cycle_ended';
        }

        if ($window->opens_at->copy()->utc()->gt($now)) {
            return 'upcoming';
        }

        if ($window->closes_at->copy()->utc()->lt($now)) {
            return 'ended';
        }

        if ($intake !== null
            && (int) $intake['cycle']->getKey() === (int) $cycle->getKey()
            && (int) $intake['window']->getKey() === (int) $window->getKey()) {
            return 'open';
        }

        if (PublicPolicy::designatedIntake((int) $institution->getKey()) === null) {
            return 'policy_unavailable';
        }

        return 'not_available';
    }
}
