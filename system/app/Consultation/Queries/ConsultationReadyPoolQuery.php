<?php

namespace App\Consultation\Queries;

use App\Assessment\Support\IndividualScoringReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use Illuminate\Support\Collection;

/**
 * Ready pool: result-ready attempts with no active consultation assignment —
 * the convenience view for leftovers (founder: no special feature).
 */
final class ConsultationReadyPoolQuery
{
    public function __construct(private readonly IndividualScoringReadiness $scoringReadiness) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forSession(ConsultationSession $session): Collection
    {
        $query = AdmissionAttempt::query()->forWorkspace()
            ->with('identity')
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->whereHas('examAssignments', fn ($q) => $q->active())
            ->whereDoesntHave('consultationAssignments', fn ($c) => $c->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES))
            ->whereDoesntHave('consultationDecisions', fn ($cd) => $cd->whereIn('decision_type', ['approve', 'modify', 'override', 'refer']))
            ->orderBy('accepted_at', 'asc')
            ->orderBy('id', 'asc');
        $ready = collect();
        $query->chunk(100, function (Collection $attempts) use (&$ready): bool {
            $readiness = $this->scoringReadiness->forAttempts($attempts);
            $rows = $attempts
                ->filter(fn (AdmissionAttempt $attempt): bool => $readiness[$attempt->id]['ready'])
                ->take(500 - $ready->count())
                ->map(fn (AdmissionAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'reference_number' => $attempt->reference_code,
                    'name' => trim(implode(' ', array_filter([
                        $attempt->identity?->first_name,
                        $attempt->identity?->middle_name,
                        $attempt->identity?->last_name,
                    ]))) ?: ($attempt->reference_code ?? ''),
                    'email' => $attempt->identity?->email,
                ]);
            $ready = $ready->concat($rows);

            return $ready->count() < 500;
        });

        return $ready->values();
    }
}
