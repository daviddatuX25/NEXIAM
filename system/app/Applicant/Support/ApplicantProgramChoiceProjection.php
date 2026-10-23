<?php

namespace App\Applicant\Support;

use App\Admission\Support\AttemptProgramFacts;
use App\Admission\Support\QuotaReservation;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramDecline;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use Illuminate\Support\Collection;

final readonly class ApplicantProgramChoiceProjection
{
    private const TABFM_DISCLAIMER = 'TabPFN/TabFM compatibility and fit signals are algorithmic decision support designed for qualified Guidance staff. They do not constitute an autonomous admission decision.';

    public function __construct(
        private ConsultationAdmissionSupportQuery $supportQuery,
        private ApplicantResultAccess $resultAccess,
    ) {}

    /**
     * @return array{
     *     readiness: array<string, mixed>,
     *     program_pool: array<string, mixed>,
     *     analysis: array{status: string, message: string},
     *     tabfm: array{status: 'available'|'unavailable'|'not_ready', is_admission_decision: false, requires_counselor_interpretation: true, disclaimer: string, matches: list<array{program_code: string, program_name: string, fit_band: string, compatibility_index: float, drivers: list<string>}>}
     * }
     */
    public function for(AdmissionAttempt $attempt): array
    {
        $support = $this->supportQuery->for($attempt);
        $readiness = $support['readiness'];
        $pool = $support['program_pool'];
        $selection = $readiness['selection'];
        $resultAvailable = $this->resultAccess->forAttempt($attempt, false)['available'];
        $options = array_values(array_filter(
            array_map(static fn (array $option): array => [
                'program_code' => (string) ($option['program_code'] ?? ''),
                'program_name' => (string) ($option['program_name'] ?? ''),
                'basis' => $option['basis'] ?? null,
            ], $pool['options'] ?? []),
            static fn (array $option): bool => $option['program_code'] !== '',
        ));
        $defaultCode = is_string($pool['default'] ?? null) ? $pool['default'] : null;
        $selectionCode = is_string($readiness['selection']['program_code'] ?? null)
            ? $readiness['selection']['program_code']
            : null;
        $relevantCodes = array_values(array_unique(array_filter([
            ...array_column($options, 'program_code'),
            $defaultCode,
            $selectionCode,
        ])));
        $programsByCode = Program::query()
            ->where('institution_id', $attempt->institution_id)
            ->whereIn('code', $relevantCodes)
            ->get(['id', 'code'])
            ->keyBy('code');
        $programIds = $programsByCode->pluck('id')->all();
        $quotasByProgramId = collect();
        $committedByProgramId = [];
        if ($attempt->academic_cycle_id !== null && $programIds !== []) {
            $quotasByProgramId = ProgramQuota::query()
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->whereIn('program_id', $programIds)
                ->get(['program_id', 'slots'])
                ->keyBy('program_id');
            $committedByProgramId = QuotaReservation::batchCommittedForCycle(
                (int) $attempt->academic_cycle_id,
                $programIds,
                $attempt->practice_fixture_set_id,
            );
        }
        $quotaSnapshot = static function (?int $programId) use ($quotasByProgramId, $committedByProgramId): ?array {
            if ($programId === null) {
                return null;
            }
            $quotaRow = $quotasByProgramId->get($programId);
            if ($quotaRow === null) {
                return [
                    'configured' => false,
                    'capacity' => null,
                    'committed' => null,
                    'remaining' => null,
                ];
            }
            $capacity = (int) $quotaRow->slots;
            $committed = (int) ($committedByProgramId[$programId] ?? 0);
            $remaining = max(0, $capacity - $committed);

            return [
                'configured' => true,
                'capacity' => $capacity,
                'committed' => $committed,
                'remaining' => $remaining,
            ];
        };
        $effectiveOptions = $this->effectiveOptions($attempt, $options, $quotaSnapshot, $programsByCode);
        $defaultOption = $defaultCode === null ? null : collect($effectiveOptions)->firstWhere('program_code', $defaultCode);
        $defaultProgramId = $defaultCode !== null ? $programsByCode->get($defaultCode)?->id : null;
        $default = $defaultOption === null ? null : [
            'program_code' => $defaultOption['program_code'],
            'program_name' => $defaultOption['program_name'],
            'quota' => $quotaSnapshot($defaultProgramId),
        ];
        $selectionProgramId = $selectionCode !== null ? $programsByCode->get($selectionCode)?->id : null;
        $standingSelectionDiffersFromPool = $selectionCode !== null
            && ! collect($effectiveOptions)->contains('program_code', $selectionCode);
        $clearanceConfirmed = AttemptProgramFacts::isClearanceConfirmed($attempt);
        $canMutate = (bool) ($readiness['accepted'] ?? false)
            && (bool) ($readiness['result_released'] ?? false)
            && (bool) ($readiness['advised'] ?? false)
            && ! (bool) ($readiness['enrolling'] ?? false)
            && ! (bool) ($readiness['enrolled'] ?? false)
            && ! $clearanceConfirmed;
        $hasStandingSelection = in_array($selection['status'] ?? null, ProgramSelection::NON_TERMINAL_STATUSES, true);
        $awaitingConfirmation = $hasStandingSelection
            && ($selection['source'] ?? null) === ProgramSelection::SOURCE_GUIDANCE_DEFAULT
            && ! (bool) ($selection['acknowledged'] ?? false);
        $actionState = match (true) {
            $effectiveOptions === [] => 'awaiting_recommendation',
            ! (bool) ($readiness['accepted'] ?? false) => 'not_accepted',
            ! (bool) ($readiness['result_released'] ?? false) => 'awaiting_release',
            ! (bool) ($readiness['advised'] ?? false) => 'awaiting_advice',
            $clearanceConfirmed || ! $canMutate => 'locked',
            $awaitingConfirmation => 'pending_acknowledgement',
            $hasStandingSelection && ! $standingSelectionDiffersFromPool => 'confirmed',
            default => 'ready_to_choose',
        };
        $choices = $canMutate ? $effectiveOptions : [];
        if ($selectionCode !== null) {
            $choices = array_values(array_filter(
                $choices,
                static fn (array $option): bool => $option['program_code'] !== $selectionCode,
            ));
        }
        $canAcknowledge = $canMutate
            && $awaitingConfirmation
            && ! $standingSelectionDiffersFromPool;
        $rawModel = collect($support['models'] ?? [])->firstWhere('slot', 'tabfm_pairwise');
        $canShowTabFm = $resultAvailable
            && ($rawModel['current_applicable'] ?? false) === true
            && ($rawModel['status'] ?? null) === 'evaluated';
        $matches = $canShowTabFm ? $this->matches($rawModel['recommendations'] ?? []) : [];
        $tabFmStatus = ! $resultAvailable
            ? 'not_ready'
            : ($canShowTabFm && $matches !== [] ? 'available' : 'unavailable');

        return [
            'readiness' => [
                'accepted' => (bool) ($readiness['accepted'] ?? false),
                'result_released' => (bool) ($readiness['result_released'] ?? false),
                'advised' => (bool) ($readiness['advised'] ?? false),
                'selection' => [
                    'status' => $selection['status'] ?? null,
                    'source' => $selection['source'] ?? null,
                    'program_code' => $selection['program_code'] ?? null,
                    'acknowledged' => (bool) ($selection['acknowledged'] ?? false),
                    'quota' => $quotaSnapshot($selectionProgramId),
                ],
                'enrolling' => (bool) ($readiness['enrolling'] ?? false),
                'enrolled' => (bool) ($readiness['enrolled'] ?? false),
            ],
            'program_pool' => [
                'selection_program' => $selection['program_code'] ?? null,
                'selection_status' => $selection['status'] ?? null,
                'default' => $default,
                'options' => $choices,
                'quota_as_of' => now()->toIso8601String(),
            ],
            'actions' => [
                'state' => $actionState,
                'can_acknowledge' => $canAcknowledge,
                'can_choose' => $canMutate && $choices !== [],
                'can_cancel' => $canMutate && $hasStandingSelection,
                'standing_selection_differs_from_pool' => $standingSelectionDiffersFromPool,
                'has_recommendation' => $default !== null || $effectiveOptions !== [],
                'clearance_confirmed' => $clearanceConfirmed,
            ],
            'analysis' => [
                'status' => match ($tabFmStatus) {
                    'available' => 'evaluated',
                    'not_ready' => 'result_not_ready',
                    default => 'unavailable',
                },
                'message' => $tabFmStatus === 'available'
                    ? 'Program compatibility signals are available for review by qualified Guidance staff.'
                    : ($tabFmStatus === 'not_ready'
                        ? 'Program compatibility signals are not ready because your result has not been released.'
                        : 'No current program compatibility evaluation is available. Your Guidance Recommendation and available program choices remain the source for your next step.'),
            ],
            'tabfm' => [
                'status' => $tabFmStatus,
                'is_admission_decision' => false,
                'requires_counselor_interpretation' => true,
                'disclaimer' => self::TABFM_DISCLAIMER,
                'matches' => $matches,
            ],
        ];
    }

    /**
     * @param  list<array{program_code: string, program_name: string, basis: mixed}>  $options
     * @return list<array{program_code: string, program_name: string, basis: mixed}>
     */
    private function effectiveOptions(AdmissionAttempt $attempt, array $options, \Closure $quotaSnapshot, Collection $programsByCode): array
    {
        $poolId = ProgramPool::currentFor($attempt->id)?->id;
        if ($poolId === null || $options === []) {
            return [];
        }

        $declinedProgramIds = ProgramDecline::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('program_pool_id', $poolId)
            ->pluck('program_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return array_values(array_filter(
            array_map(static fn (array $option): array => [
                ...$option,
                'quota' => $quotaSnapshot($programsByCode->get($option['program_code'])?->id),
            ], $options),
            static function (array $option) use ($declinedProgramIds, $programsByCode): bool {
                $programId = $programsByCode->get($option['program_code'])?->id;

                return $programId !== null && ! in_array((int) $programId, $declinedProgramIds, true);
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $recommendations
     * @return list<array{program_code: string, program_name: string, fit_band: string, compatibility_index: float, drivers: list<string>}>
     */
    private function matches(array $recommendations): array
    {
        $matches = [];

        foreach ($recommendations as $recommendation) {
            $score = $recommendation['compatibility_index'] ?? null;
            if (! is_numeric($score) || ! is_finite((float) $score)) {
                continue;
            }

            $band = match ($recommendation['band'] ?? null) {
                'high_match' => 'High Fit',
                'moderate_match' => 'Moderate Fit',
                default => 'Borderline',
            };
            $matches[] = [
                'program_code' => (string) ($recommendation['program_code'] ?? ''),
                'program_name' => (string) ($recommendation['program_name'] ?? ''),
                'fit_band' => $band,
                'compatibility_index' => max(0.0, min(100.0, round((float) $score, 2))),
                'drivers' => array_values(array_filter(
                    is_array($recommendation['drivers'] ?? null) ? $recommendation['drivers'] : [],
                    static fn (mixed $driver): bool => is_string($driver) && trim($driver) !== '',
                )),
            ];
        }

        return $matches;
    }
}
