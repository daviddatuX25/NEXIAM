<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Applicant;
use App\Models\ConsultationSummary;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConsultationSummaryService
{
    public function __construct(
        private AuditService $auditService
    ) {}


    public function getOrCreateForApplicant(int $applicantId): ConsultationSummary
    {
        $summary = ConsultationSummary::firstOrCreate(
            ['applicant_id' => $applicantId],
            ['status' => ConsultationSummary::STATUS_PENDING]
        );

        return $summary;
    }

    public function release(ConsultationSummary $summary, User $user): void
    {
        DB::transaction(function () use ($summary, $user) {
            $summary->update([
                'status' => ConsultationSummary::STATUS_RELEASED,
                'released_at' => now(),
                'released_by' => $user->id,
            ]);

            $this->auditService->log(
                'consultation.released',
                ConsultationSummary::class,
                $summary->id,
                ['status' => ConsultationSummary::STATUS_PENDING],
                ['status' => ConsultationSummary::STATUS_RELEASED],
                "Consultation released for applicant {$summary->applicant_id}"
            );
        });
    }
}
