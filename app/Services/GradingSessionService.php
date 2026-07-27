<?php

namespace App\Services;

use App\Models\AptitudeArea;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GradingSessionService
{
    public function openForExamSession(ExamSession $examSession, User $openedBy): GradingSession
    {
        return DB::transaction(function () use ($examSession, $openedBy) {
            $session = GradingSession::create([
                'exam_session_id' => $examSession->id,
                'status' => GradingSession::STATUS_OPEN,
                'opened_at' => now(),
                'opened_by' => $openedBy->id,
            ]);

            foreach ($examSession->applicants as $applicant) {
                $session->applicants()->attach($applicant->id, ['result_printed_at' => null]);
            }

            return $session->load(['examSession.room', 'applicants']);
        });
    }

    public function updateWorkflowStatus(GradingSession $session, string $status): GradingSession
    {
        if ($status === GradingSession::STATUS_FINALIZED) {
            $this->ensureAllApplicantsScored($session);
        }

        $session->update(['status' => $status]);

        if ($status === GradingSession::STATUS_FINALIZED) {
            $session->update([
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ]);
        } else {
            $session->update(['finalized_at' => null, 'finalized_by' => null]);
        }

        return $session->fresh();
    }

    /**
     * Per E-010: All applicants must have scores for all active domains before finalize.
     *
     * @throws ValidationException
     */
    private function ensureAllApplicantsScored(GradingSession $session): void
    {
        $activeDomainIds = AptitudeArea::where('is_active', true)->pluck('id');
        $domainCount = $activeDomainIds->count();

        foreach ($session->applicants as $applicant) {
            $scoredDomainCount = $session->applicantScores()
                ->where('applicant_id', $applicant->id)
                ->whereIn('aptitude_area_id', $activeDomainIds)
                ->distinct()
                ->count('aptitude_area_id');

            if ($scoredDomainCount < $domainCount) {
                throw ValidationException::withMessages([
                    'status' => ['All applicants must have scores for every domain before finalizing.'],
                ]);
            }
        }
    }
}
