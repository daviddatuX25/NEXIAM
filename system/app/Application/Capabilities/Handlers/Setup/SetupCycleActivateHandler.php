<?php

namespace App\Application\Capabilities\Handlers\Setup;

use App\Ai\Proposals\ProposalReview;
use App\Ai\Proposals\ProposalViewContract;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Setup\Actions\ActivateAcademicCycle;
use App\Setup\Support\AcademicCycleActivationEvaluator;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

final class SetupCycleActivateHandler
{
    private const CAPABILITY_ID = 'setup.cycles.activate@1';

    public function __construct(
        private readonly ActivateAcademicCycle $action,
        private readonly AcademicCycleActivationEvaluator $evaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $cycleId = $input['cycle_id'] ?? null;
        if (! is_int($cycleId) || $cycleId < 1) {
            throw new RuntimeException('cycle_id_invalid');
        }

        $cycle = AcademicCycle::query()->find($cycleId);
        if (! $cycle instanceof AcademicCycle) {
            throw new RuntimeException('academic_cycle_not_found');
        }

        $evaluation = $this->evaluator->evaluate($cycle);
        if (! (bool) ($input['execute'] ?? false)) {
            $command = ['cycle_id' => (int) $cycle->id];

            $activeCycle = AcademicCycle::query()
                ->where('institution_id', $cycle->institution_id)
                ->where('is_active', true)
                ->first();

            $beforeRecord = $activeCycle !== null
                ? ['type' => 'record', 'label' => (string) $activeCycle->code, 'context' => array_values(array_filter([(string) $activeCycle->staffLabel()]))]
                : ['type' => 'null'];

            $afterRecord = [
                'type' => 'record',
                'label' => (string) $cycle->code,
                'context' => array_values(array_filter([(string) $cycle->staffLabel()])),
            ];

            $result = [
                'mode' => 'propose',
                'ok' => $evaluation->outcome === 'pass',
                'capability_id' => self::CAPABILITY_ID,
                'command_input' => $command,
                'impact' => ['cycle_id' => (int) $cycle->id],
                'proposal_review' => [
                    'version' => 1,
                    'operation' => 'transition',
                    'title' => 'Activate academic cycle',
                    'sections' => [
                        ProposalViewContract::fieldsSection('cycle_activation', 'Academic cycle activation', [
                            ProposalViewContract::field('cycle', 'Academic cycle', $beforeRecord, $afterRecord, 'choice'),
                            ProposalViewContract::field(
                                'status',
                                'Activation state',
                                ['type' => 'boolean', 'value' => (bool) $cycle->is_active, 'true_label' => 'Active', 'false_label' => 'Inactive'],
                                ['type' => 'boolean', 'value' => true, 'true_label' => 'Active', 'false_label' => 'Inactive'],
                                'effect',
                            ),
                        ]),
                    ],
                    'preflight' => ProposalViewContract::preflight($evaluation->outcome, $evaluation->checks),
                    'coverage' => [
                        'cycle_id' => ['classification' => 'display', 'field' => 'cycle'],
                    ],
                ],
                'diff' => $evaluation->diff,
                'evidence' => ['outcome' => $evaluation->outcome, 'checks' => $evaluation->checks],
            ];
            ProposalReview::fromResult(self::CAPABILITY_ID, $result);

            return $result;
        }

        if (! $actor instanceof User) {
            throw new RuntimeException('staff_actor_required');
        }
        if ($evaluation->outcome !== 'pass') {
            return ['mode' => 'denied', 'reason' => 'academic_cycle_activation_preflight_failed', 'evidence' => ['checks' => $evaluation->checks]];
        }

        $activated = $this->action->handle($cycle, $actor);

        return [
            'mode' => 'execute',
            'ok' => true,
            'cycle_id' => (int) $activated->id,
            'code' => $activated->code,
            'is_active' => (bool) $activated->is_active,
        ];
    }
}
