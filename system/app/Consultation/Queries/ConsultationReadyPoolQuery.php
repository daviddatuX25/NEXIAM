<?php

namespace App\Consultation\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationSession;
use App\Models\Release\ResultDocument;
use Illuminate\Support\Collection;

/**
 * Ready pool: result-ready attempts with no active consultation assignment —
 * the convenience view for leftovers (founder: no special feature).
 */
final class ConsultationReadyPoolQuery
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function forSession(ConsultationSession $session): Collection
    {
        $rows = AdmissionAttempt::query()
            ->with('identity')
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where('academic_cycle_id', $session->academic_cycle_id)
            ->whereHas('examAssignments', fn ($q) => $q->whereHas(
                'examSession.gradingSession',
                fn ($g) => $g->where('status', GradingSession::STATUS_FINALIZED),
            ))
            ->whereHas('resultDocuments', fn ($d) => $d->where('status', ResultDocument::STATUS_READY))
            ->whereDoesntHave('consultationAssignments', fn ($c) => $c->whereIn('status', ConsultationAssignment::ACTIVE_STATUSES))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => $attempt->id,
                'reference_number' => $attempt->reference_code,
                'name' => trim(implode(' ', array_filter([
                    $attempt->identity?->first_name,
                    $attempt->identity?->middle_name,
                    $attempt->identity?->last_name,
                ]))) ?: ($attempt->reference_code ?? ''),
                'email' => $attempt->identity?->email,
            ]);

        /** @var Collection<int, array<string, mixed>> $rows */
        return $rows;
    }
}
