<?php

namespace App\Scheduling\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Scheduling\AssignmentInvariant;
use App\Scheduling\AssignmentPlanningMutex;
use App\Scheduling\CandidatePoolQuery;
use App\Scheduling\CohortPlanner;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use RuntimeException;

final class PreviewCohortDistribution
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly AssignmentPlanningMutex $planningMutex,
        private readonly AssignmentInvariant $invariant,
        private readonly CandidatePoolQuery $poolQuery,
        private readonly CohortPlanner $planner,
    ) {}

    /**
     * @param  list<int>  $targetSessionIds
     * @return array<string, mixed>
     */
    public function handle(
        string $domain,
        int $academicCycleId,
        array $targetSessionIds,
        string $sortStrategy,
        string $packingStrategy,
        User $actor,
    ): array {
        $this->authorize($domain, $actor);

        if ($academicCycleId <= 0) {
            throw new InvalidArgumentException('Academic cycle ID must be positive.');
        }

        if ($targetSessionIds === []) {
            throw new InvalidArgumentException('At least one target session ID is required.');
        }

        return $this->planningMutex->run($domain, $academicCycleId, function () use (
            $domain,
            $academicCycleId,
            $targetSessionIds,
            $sortStrategy,
            $packingStrategy,
        ): array {
            $sortedIds = collect($targetSessionIds)->unique()->sort()->values()->all();
            $sessionClass = $domain === AssignmentInvariant::DOMAIN_EXAM ? ExamSession::class : ConsultationSession::class;

            $sessions = $sessionClass::query()
                ->when($domain === AssignmentInvariant::DOMAIN_EXAM, fn ($query) => $query->forWorkspace())
                ->whereIn('id', $sortedIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->with('room')
                ->get();

            if ($sessions->count() !== count($sortedIds)) {
                throw new RuntimeException('One or more selected target sessions do not exist.');
            }

            foreach ($sessions as $session) {
                $this->invariant->validateTargetSessionEligible($session, $academicCycleId);
            }

            $institutionId = (int) AcademicCycle::query()->whereKey($academicCycleId)->value('institution_id');
            $candidates = $domain === AssignmentInvariant::DOMAIN_EXAM
                ? $this->poolQuery->forExam($academicCycleId, $institutionId)
                : $this->poolQuery->forConsultation($academicCycleId, $institutionId);

            return $this->planner->plan(
                $domain,
                $academicCycleId,
                $candidates,
                $sessions,
                $sortStrategy,
                $packingStrategy,
                $this->invariant,
            );
        });
    }

    private function authorize(string $domain, User $actor): void
    {
        $capability = match ($domain) {
            AssignmentInvariant::DOMAIN_EXAM => CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE,
            AssignmentInvariant::DOMAIN_CONSULTATION => CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN,
            default => throw new InvalidArgumentException("Unsupported distribution domain [{$domain}]."),
        };

        if (! $this->authorizer->allows($actor, $capability)) {
            throw new AuthorizationException("Unauthorized for [{$capability}].");
        }
    }
}
