<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Setup\Actions\CreateAcademicCycle;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupCycleCreateHandler
{
    private const CAPABILITY_ID = 'setup.cycles.create_draft@1';

    public function __construct(
        private readonly CreateAcademicCycle $action,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $schoolYear = $input['school_year'] ?? null;
        $semester = $input['semester'] ?? null;
        $startDate = $input['application_start_date'] ?? null;
        $endDate = $input['application_end_date'] ?? null;
        if (! is_string($schoolYear) || trim($schoolYear) === '') {
            throw new RuntimeException('school_year_invalid');
        }
        if (! (is_string($semester) || is_int($semester)) || ! in_array((string) $semester, ['1', '2'], true)) {
            throw new RuntimeException('semester_invalid');
        }
        if ($startDate !== null && ! is_string($startDate)) {
            throw new RuntimeException('application_start_date_invalid');
        }
        if ($endDate !== null && ! is_string($endDate)) {
            throw new RuntimeException('application_end_date_invalid');
        }

        $attributes = [
            'school_year' => trim($schoolYear),
            'semester' => $semester,
            'application_start_date' => $startDate,
            'application_end_date' => $endDate,
        ];
        $semesterLabel = ((string) $semester) === '1' ? '1st Semester' : '2nd Semester';
        $cycleName = trim($schoolYear).' '.$semesterLabel;
        if (! (bool) ($input['execute'] ?? false)) {
            $command = [
                'school_year' => $schoolYear,
                'semester' => $semester,
                'application_start_date' => $startDate,
                'application_end_date' => $endDate,
            ];

            $cycleExists = AcademicCycle::query()
                ->where('school_year', trim($schoolYear))
                ->where('semester', (string) $semester)
                ->exists();
            $validDates = $startDate === null || $endDate === null || $startDate <= $endDate;

            $checks = [
                [
                    'key' => 'cycle_unique',
                    'label' => 'Academic cycle uniqueness',
                    'status' => ! $cycleExists ? 'pass' : 'fail',
                    'detail' => ! $cycleExists
                        ? 'Academic cycle is unique for the institution'
                        : 'That school year and semester already exist',
                ],
                [
                    'key' => 'dates_order',
                    'label' => 'Application window dates',
                    'status' => $validDates ? 'pass' : 'fail',
                    'detail' => $validDates
                        ? 'Application window dates are valid'
                        : 'Application start date must precede end date',
                ],
            ];
            $outcome = (! $cycleExists && $validDates) ? 'pass' : 'fail';

            $isIsoDate = static fn (?string $val): bool => $val !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $val) === 1;

            $afterStartDate = $startDate === null
                ? ['type' => 'null']
                : ($isIsoDate($startDate)
                    ? ['type' => 'date', 'value' => $startDate]
                    : ['type' => 'text', 'value' => $startDate, 'multiline' => false]);

            $afterEndDate = $endDate === null
                ? ['type' => 'null']
                : ($isIsoDate($endDate)
                    ? ['type' => 'date', 'value' => $endDate]
                    : ['type' => 'text', 'value' => $endDate, 'multiline' => false]);

            $result = [
                'mode' => 'propose',
                'ok' => $outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => [
                    'school_year' => trim($schoolYear),
                    'semester' => (string) $semester,
                    'application_start_date' => $startDate,
                    'application_end_date' => $endDate,
                ],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'create',
                    'title' => 'Create academic cycle',
                    'sections' => [
                        ProposalViewContract::fieldsSection('cycle_create', 'Academic cycle details', [
                            ProposalViewContract::field(
                                'school_year',
                                'Academic year',
                                ['type' => 'missing'],
                                ['type' => 'text', 'value' => trim($schoolYear), 'multiline' => false],
                                'choice',
                            ),
                            ProposalViewContract::field(
                                'semester',
                                'Semester',
                                ['type' => 'missing'],
                                ['type' => 'enum', 'code' => (string) $semester, 'label' => $semesterLabel],
                                'choice',
                            ),
                            ProposalViewContract::field(
                                'application_start_date',
                                'Application start date',
                                ['type' => 'missing'],
                                $afterStartDate,
                                'choice',
                            ),
                            ProposalViewContract::field(
                                'application_end_date',
                                'Application end date',
                                ['type' => 'missing'],
                                $afterEndDate,
                                'choice',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($outcome, $checks),
                    'coverage' => [
                        'school_year' => ['classification' => 'display', 'field' => 'school_year'],
                        'semester' => ['classification' => 'display', 'field' => 'semester'],
                        'application_start_date' => ['classification' => 'display', 'field' => 'application_start_date'],
                        'application_end_date' => ['classification' => 'display', 'field' => 'application_end_date'],
                    ],
                ],
                'diff' => [[
                    'key' => 'cycle',
                    'label' => 'Cycle',
                    'before' => 'None',
                    'after' => trim($schoolYear).' Semester '.(string) $semester,
                ]],
                'evidence' => [
                    'outcome' => $outcome,
                    'checks' => $checks,
                ],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }

        $cycle = $this->action->handle($attributes, $actor);

        return [
            'mode' => 'execute',
            'ok' => true,
            'academic_cycle_id' => (int) $cycle->id,
            'code' => $cycle->code,
            'name' => $cycleName,
        ];
    }
}
