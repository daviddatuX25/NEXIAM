<?php

declare(strict_types=1);

namespace App\Release\Jobs;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Release\Actions\DeliverResultAction;
use App\Release\Actions\QueueConsultationPortalCopy;
use App\Release\Support\ConsultationPortalCopyEligibility;
use App\Release\Support\ResultDocumentPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class IssueConsultationPortalCopy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $attemptId,
        public readonly int $documentId,
        public readonly int $decisionId,
        public readonly int $encounterId,
        public readonly int $actorId,
    ) {}

    public function handle(
        ConsultationPortalCopyEligibility $eligibility,
        ResultDocumentPdf $pdf,
        QueueConsultationPortalCopy $queuePortalCopy,
        DeliverResultAction $deliverResult,
    ): void {
        $attempt = AdmissionAttempt::query()->find($this->attemptId);
        $document = ResultDocument::query()->find($this->documentId);
        $decision = ConsultationDecision::query()->find($this->decisionId);
        $encounter = ConsultationEncounter::query()->find($this->encounterId);
        $actor = User::query()->find($this->actorId);
        if ($attempt === null || $document === null || $decision === null || $encounter === null || $actor === null) {
            return;
        }
        if (! $eligibility->isEligible($attempt, $decision, $encounter, $document, $actor)) {
            return;
        }

        try {
            $pdf->read($document);
        } catch (\RuntimeException) {
            $queuePortalCopy->handle($attempt, $decision, $encounter, $document, $actor);

            return;
        }

        $deliverResult->issuePortal(
            $attempt,
            $document,
            $actor,
            'consultation-portal-copy-'.$decision->id,
            (int) $decision->id,
            (int) $encounter->id,
        );
    }
}
