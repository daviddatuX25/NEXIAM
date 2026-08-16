<?php

namespace App\Consultation\Queries;

use App\Consultation\Support\AttemptConsultationStatus;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Registrar eligibility surface (A3, founder 2026-08-14): a status search —
 * the Registrar counter is where students without phones check their status.
 * Shows pipeline status facts only (accepted / grading finalized / result
 * released / advised); never scores or counselor notes. Every card lookup is
 * audited.
 */
final class EnrollmentEligibilityQuery
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @return Collection<int, array{id: int, reference_number: string, name: string, status: string}>
     */
    public function search(string $query): Collection
    {
        $q = trim($query);

        if ($q === '') {
            return collect();
        }

        return AdmissionAttempt::query()
            ->with('identity')
            ->where(function ($inner) use ($q): void {
                $inner->where('reference_code', 'like', "%{$q}%")
                    ->orWhereHas('identity', fn ($identity) => $identity
                        ->where('first_name', 'like', "%{$q}%")
                        ->orWhere('last_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            })
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => $attempt->id,
                'reference_number' => $attempt->reference_code,
                'name' => trim(implode(' ', array_filter([
                    $attempt->identity?->first_name,
                    $attempt->identity?->middle_name,
                    $attempt->identity?->last_name,
                ]))) ?: ($attempt->reference_code ?? ''),
                'status' => $attempt->status,
            ]);
    }

    /**
     * Status-fact card for one attempt. Audited per lookup.
     *
     * @return array<string, mixed>
     */
    public function cardFor(AdmissionAttempt $attempt, User $actor): array
    {
        $this->recordAuditEvent->handle('registrar.eligibility_lookup', [
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
        ], (string) $actor->id);

        $gradingFinalized = ExamAssignment::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('examSession.gradingSession', fn ($grading) => $grading->where('status', GradingSession::STATUS_FINALIZED))
            ->exists();

        $release = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->first();

        $facts = [
            'accepted' => $attempt->status === AdmissionAttempt::STATUS_ACCEPTED,
            'grading_finalized' => $gradingFinalized,
            'result_released' => $release !== null,
            'advised' => AttemptConsultationStatus::isAdvised($attempt),
        ];

        $missing = collect($facts)
            ->filter(fn (bool $done): bool => ! $done)
            ->keys()
            ->values()
            ->all();

        // D-090/D-091 admission facts: selection, follow-up, stamp state.
        $selection = \App\Models\Admission\ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', \App\Models\Admission\ProgramSelection::NON_TERMINAL_STATUSES)
            ->with('program')
            ->latest('id')
            ->first();

        $followUps = \App\Models\Admission\ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', \App\Models\Admission\ProgramFollowUp::OPEN_STATUSES)
            ->with('program')
            ->get();

        $stamp = \App\Admission\Support\AttemptProgramFacts::activeStamp($attempt);

        return [
            'id' => $attempt->id,
            'reference_number' => $attempt->reference_code,
            'name' => trim(implode(' ', array_filter([
                $attempt->identity?->first_name,
                $attempt->identity?->middle_name,
                $attempt->identity?->last_name,
            ]))) ?: ($attempt->reference_code ?? ''),
            'status' => $attempt->status,
            'facts' => $facts,
            'released_at' => $release?->getAttributeValue('released_at')?->toIso8601String(),
            'eligible' => $missing === [],
            'missing' => $missing,
            'selection' => $selection === null ? null : [
                'program_code' => $selection->program?->code,
                'status' => $selection->status,
                'source' => $selection->source,
                'acknowledged' => $selection->applicant_acknowledged_at !== null,
            ],
            'follow_ups' => $followUps->map(fn ($f) => [
                'program_code' => $f->program?->code,
                'status' => $f->status,
            ])->all(),
            'stamp' => $stamp === null ? null : [
                'state' => $stamp->stamp_type,
                'provisional_program' => $stamp->provisionalProgram?->code,
                'final_program' => $stamp->finalProgram?->code,
                'paper_reference' => $stamp->paper_reference,
            ],
            // D-091: an open follow-up blocks only the final enrolled stamp.
            'may_start_enrolling' => $missing === [] && $stamp === null,
            'may_finalize_enrolled' => $stamp !== null
                && $stamp->stamp_type === \App\Models\Admission\EnrollmentStamp::STAMP_ENROLLING
                && $followUps->isEmpty(),
        ];
    }
}
