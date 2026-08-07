<?php

namespace App\Setup\Queries;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;

/**
 * Setup readiness — legacy Admin/Setup pattern: per category, create then activate checks,
 * one Configure href each (never merge aptitude + privacy).
 *
 * @return array{overall_percent: int, passed: int, total: int, categories: list<array<string, mixed>>}
 */
final class ComputeSetupReadinessQuery
{
    public function handle(): array
    {
        $categories = [
            $this->cycles(),
            $this->programs(),
            $this->rooms(),
            $this->privacy(),
            $this->grades(),
            $this->aptitude(),
            $this->quotas(),
        ];

        $all = collect($categories)->pluck('checks')->flatten(1);
        $total = $all->count();
        $passed = $all->where('passed', true)->count();

        return [
            'overall_percent' => $total === 0 ? 0 : (int) round(($passed / $total) * 100),
            'passed' => $passed,
            'total' => $total,
            'categories' => $categories,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cycles(): array
    {
        $total = AcademicCycle::query()->count();
        $active = AcademicCycle::query()->where('is_active', true)->first();
        $publishedWindows = ApplicationWindow::query()->where('status', 'published')->count();
        $activeHasWindow = $active !== null && $active->primaryWindow() !== null;

        return $this->category(
            key: 'cycles',
            label: 'Academic years & application windows',
            href: '/setup/cycles',
            capability: 'setup',
            checks: [
                $this->check(
                    'At least one academic year (with semester) created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} academic year record(s) on file." : 'Create a school year and semester before intake.',
                ),
                $this->check(
                    'An academic year is activated',
                    $active !== null,
                    'critical',
                    $active !== null
                        ? "Active: {$active->staffLabel()}."
                        : 'Activate one academic year so intake can open.',
                ),
                $this->check(
                    'Active year has an application window',
                    $activeHasWindow && $publishedWindows > 0,
                    'critical',
                    $activeHasWindow && $publishedWindows > 0
                        ? "Window: {$active->applicationWindowLabel()}."
                        : 'Set application window dates on the active year, then activate to publish.',
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function programs(): array
    {
        $total = Program::query()->count();
        $active = Program::query()->where('is_active', true)->count();

        return $this->category(
            key: 'programs',
            label: 'Programs & courses',
            href: '/setup/programs',
            capability: 'setup',
            checks: [
                $this->check(
                    'At least one program/course created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} program(s) configured." : 'Applicants need programs to apply for.',
                ),
                $this->check(
                    'At least one program is active',
                    $active > 0,
                    'critical',
                    $active > 0
                        ? "{$active} of {$total} program(s) active."
                        : ($total > 0
                            ? "All {$total} program(s) are deactivated."
                            : 'No programs to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rooms(): array
    {
        $total = Room::query()->count();
        $active = Room::query()->where('is_active', true)->count();
        $capacity = (int) Room::query()->where('is_active', true)->sum('capacity');

        return $this->category(
            key: 'rooms',
            label: 'Rooms & facilities',
            href: '/setup/rooms',
            capability: 'setup',
            checks: [
                $this->check(
                    'At least one room created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} room(s) configured." : 'Rooms are required for exam scheduling.',
                ),
                $this->check(
                    'At least one room is active',
                    $active > 0,
                    'important',
                    $active > 0
                        ? "{$active} active room(s); capacity {$capacity}."
                        : ($total > 0
                            ? "All {$total} room(s) are deactivated."
                            : 'No rooms to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function privacy(): array
    {
        $total = PrivacyPolicy::query()->count();
        $active = PrivacyPolicy::query()->where('is_active', true)->count();

        return $this->category(
            key: 'privacy',
            label: 'Privacy policies',
            href: '/setup/privacy',
            capability: 'setup',
            checks: [
                $this->check(
                    'At least one privacy policy created',
                    $total > 0,
                    'important',
                    $total > 0 ? "{$total} policy version(s)." : 'Required on the public apply form.',
                ),
                $this->check(
                    'A privacy policy is active',
                    $active > 0,
                    'important',
                    $active > 0
                        ? "{$active} active policy(ies)."
                        : ($total > 0 ? 'Policies exist but none are active.' : 'No policies to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function grades(): array
    {
        $total = IntakeGradeField::query()->count();
        $active = IntakeGradeField::query()->where('is_active', true)->count();

        return $this->category(
            key: 'grades',
            label: 'Intake grade fields',
            href: '/setup/grades',
            capability: 'setup',
            checks: [
                $this->check(
                    'At least one intake grade field created',
                    $total > 0,
                    'important',
                    $total > 0 ? "{$total} field(s)." : 'Catalog grades for apply / correction.',
                ),
                $this->check(
                    'At least one grade field is active',
                    $active > 0,
                    'important',
                    $active > 0
                        ? "{$active} of {$total} active."
                        : ($total > 0 ? 'All grade fields deactivated.' : 'No fields to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function aptitude(): array
    {
        $total = AptitudeArea::query()->count();
        $active = AptitudeArea::query()->where('is_active', true)->count();

        return $this->category(
            key: 'aptitude',
            label: 'Aptitude areas',
            href: '/setup/aptitude',
            capability: 'assessment',
            checks: [
                $this->check(
                    'At least one aptitude area created',
                    $total > 0,
                    'critical',
                    $total > 0 ? "{$total} area(s) configured." : 'Required for scoring — Test Administrator owns this.',
                ),
                $this->check(
                    'At least one aptitude area is active',
                    $active > 0,
                    'critical',
                    $active > 0
                        ? "{$active} of {$total} area(s) active."
                        : ($total > 0
                            ? "All {$total} area(s) deactivated — cannot score."
                            : 'No areas to activate.'),
                ),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function quotas(): array
    {
        $activePrograms = Program::query()->where('is_active', true)->count();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->first()
            ?? AcademicCycle::query()->orderByDesc('opens_on')->first();
        $quotaCount = $cycle
            ? ProgramQuota::query()->where('academic_cycle_id', $cycle->id)->count()
            : 0;
        $covered = $cycle && $activePrograms > 0
            ? ProgramQuota::query()
                ->where('academic_cycle_id', $cycle->id)
                ->whereIn('program_id', Program::query()->where('is_active', true)->select('id'))
                ->count()
            : 0;
        $allCovered = $activePrograms > 0 && $covered >= $activePrograms;

        return $this->category(
            key: 'quotas',
            label: 'Program quotas',
            href: '/setup/quotas',
            capability: 'quota',
            checks: [
                $this->check(
                    'At least one program quota set for the active cycle',
                    $quotaCount > 0,
                    'important',
                    $quotaCount > 0
                        ? "{$quotaCount} quota row(s) for ".($cycle?->code ?? 'cycle').'.'
                        : 'Program Heads / TA set slots before result release (D-087).',
                ),
                $this->check(
                    'Every active program has a quota for the cycle',
                    $allCovered,
                    'important',
                    $activePrograms === 0
                        ? 'No active programs yet.'
                        : ($allCovered
                            ? "All {$activePrograms} active program(s) have quotas."
                            : "{$covered} of {$activePrograms} active program(s) have quotas."),
                ),
            ],
        );
    }

    /**
     * @param  list<array{label: string, passed: bool, severity: string, detail: ?string}>  $checks
     * @return array<string, mixed>
     */
    private function category(
        string $key,
        string $label,
        string $href,
        string $capability,
        array $checks,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'href' => $href,
            'capability' => $capability,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{label: string, passed: bool, severity: string, detail: ?string}
     */
    private function check(string $label, bool $passed, string $severity, ?string $detail = null): array
    {
        return [
            'label' => $label,
            'passed' => $passed,
            'severity' => $severity,
            'detail' => $detail,
        ];
    }
}
