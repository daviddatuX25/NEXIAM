<?php

declare(strict_types=1);

namespace App\Release\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Release\Jobs\GenerateConsultationResultPdf;
use App\Release\Jobs\IssueConsultationPortalCopy;
use App\Release\Support\ConsultationPortalCopyEligibility;
use App\Release\Support\ResultDocumentPdf;

final class QueueConsultationPortalCopy
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ConsultationPortalCopyEligibility $eligibility,
        private readonly ResultDocumentPdf $pdf,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        ConsultationDecision $decision,
        ConsultationEncounter $encounter,
        ResultDocument $document,
        User $actor,
    ): string {
        if (
            ! $this->authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [
                'institution_id' => $attempt->institution_id,
                'office' => 'guidance',
            ])->allowed
            || ! $this->eligibility->isEligible($attempt, $decision, $encounter, $document, $actor)
        ) {
            return 'ineligible';
        }
        if (ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $document->id)
            ->where('channel', 'portal')
            ->where('is_current', true)
            ->exists()) {
            return 'already-issued';
        }

        if (! $this->pdf->isReady($document)) {
            if (! $this->pdf->canPrepare($document)) {
                return 'requires-reprepare';
            }

            GenerateConsultationResultPdf::dispatch(
                (int) $attempt->id,
                (int) $document->id,
                (int) $decision->id,
                (int) $encounter->id,
                (int) $actor->id,
            );

            return 'queued';
        }

        IssueConsultationPortalCopy::dispatch(
            (int) $attempt->id,
            (int) $document->id,
            (int) $decision->id,
            (int) $encounter->id,
            (int) $actor->id,
        );

        return 'queued';

    }
}
