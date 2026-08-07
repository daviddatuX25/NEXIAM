<?php

namespace App\Services;

use App\Models\GradingSession;
use Illuminate\Support\Collection;

class PrintBatchService
{
    public function markPrinted(GradingSession $session, array $applicantIds, bool $printed): void
    {
        $ts = $printed ? now() : null;

        foreach ($applicantIds as $aid) {
            $session->applicants()->updateExistingPivot($aid, ['result_printed_at' => $ts]);
        }
    }

    public function getApplicantsForPrint(GradingSession $session): Collection
    {
        return $session->applicants()
            ->with(['application'])
            ->get();
    }
}
