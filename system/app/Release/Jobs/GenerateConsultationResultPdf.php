<?php

declare(strict_types=1);

namespace App\Release\Jobs;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\Release\Actions\QueueConsultationPortalCopy;
use App\Release\Support\ConsultationPortalCopyEligibility;
use App\Release\Support\ResultDocumentPdf;
use App\ResultSheet\Rendering\PreparedResultDocumentRenderer;
use App\Scheduling\AssignmentPlanningMutex;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class GenerateConsultationResultPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

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
        CapabilityAuthorizer $authorizer,
        PreparedResultDocumentRenderer $preparedDocumentRenderer,
        SensitiveAssetStore $assetStore,
        AssignmentPlanningMutex $planningMutex,
    ): void {
        $attempt = AdmissionAttempt::query()->find($this->attemptId);
        $document = ResultDocument::query()->find($this->documentId);
        $decision = ConsultationDecision::query()->find($this->decisionId);
        $encounter = ConsultationEncounter::query()->find($this->encounterId);
        $actor = User::query()->find($this->actorId);
        if (
            $attempt === null
            || $document === null
            || $decision === null
            || $encounter === null
            || $actor === null
            || ! $authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [
                'institution_id' => $attempt->institution_id,
                'office' => 'guidance',
            ])->allowed
            || ! $eligibility->isEligible($attempt, $decision, $encounter, $document, $actor)
        ) {
            return;
        }

        $hasReadyPdf = true;
        try {
            $pdf->read($document);
        } catch (RuntimeException) {
            $hasReadyPdf = false;
        }
        if ($hasReadyPdf) {
            $queuePortalCopy->handle($attempt, $decision, $encounter, $document, $actor);

            return;
        }
        $artifacts = $preparedDocumentRenderer->render($document);
        $pdfBytes = $artifacts['pdf'];
        $checksum = hash('sha256', $pdfBytes);
        $path = 'results/'.$attempt->id.'/pdf/'.$document->id.'-'.$checksum.'.pdf';
        $assetStore->store($pdfBytes, $path);

        $mayQueue = $planningMutex->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $attempt->academic_cycle_id,
            function () use ($attempt, $document, $decision, $encounter, $actor, $authorizer, $eligibility, $pdf, $path, $pdfBytes, $checksum): bool {
                $lockedAttempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
                $lockedDocument = ResultDocument::query()->whereKey($document->id)->lockForUpdate()->first();
                $lockedDecision = ConsultationDecision::query()->whereKey($decision->id)->lockForUpdate()->first();
                $lockedEncounter = ConsultationEncounter::query()->whereKey($encounter->id)->lockForUpdate()->first();
                if (
                    $lockedAttempt === null
                    || $lockedDocument === null
                    || $lockedDecision === null
                    || $lockedEncounter === null
                    || ! $authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [
                        'institution_id' => $lockedAttempt->institution_id,
                        'office' => 'guidance',
                    ])->allowed
                    || ! $eligibility->isEligible($lockedAttempt, $lockedDecision, $lockedEncounter, $lockedDocument, $actor)
                ) {
                    return false;
                }

                try {
                    $pdf->read($lockedDocument);

                    return true;
                } catch (RuntimeException) {
                }

                $manifest = is_array($lockedDocument->artifact_manifest) ? $lockedDocument->artifact_manifest : [];
                $manifest['pdf'] = [
                    'status' => 'ready',
                    'path' => $path,
                    'size' => strlen($pdfBytes),
                    'sha256' => $checksum,
                    'generated_at' => now()->toIso8601String(),
                ];
                $lockedDocument->forceFill(['artifact_manifest' => $manifest])->save();

                return true;
            },
        );

        $document->refresh();
        if ($mayQueue && $pdf->isReady($document)) {
            $queuePortalCopy->handle($attempt, $decision, $encounter, $document, $actor);
        }
    }

    public function failed(Throwable $exception): void
    {
        $attempt = AdmissionAttempt::query()->find($this->attemptId);
        $document = ResultDocument::query()->find($this->documentId);
        $decision = ConsultationDecision::query()->find($this->decisionId);
        $encounter = ConsultationEncounter::query()->find($this->encounterId);
        $actor = User::query()->find($this->actorId);
        if ($attempt === null || $document === null || $decision === null || $encounter === null || $actor === null) {
            return;
        }

        app(AssignmentPlanningMutex::class)->run(
            AssignmentPlanningMutex::DOMAIN_CONSULTATION,
            (int) $attempt->academic_cycle_id,
            function () use ($attempt, $document, $decision, $encounter, $actor): void {
                $lockedAttempt = AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();
                $lockedDocument = ResultDocument::query()->whereKey($document->id)->lockForUpdate()->first();
                $lockedDecision = ConsultationDecision::query()->whereKey($decision->id)->lockForUpdate()->first();
                $lockedEncounter = ConsultationEncounter::query()->whereKey($encounter->id)->lockForUpdate()->first();
                if (
                    $lockedAttempt === null
                    || $lockedDocument === null
                    || $lockedDecision === null
                    || $lockedEncounter === null
                    || ! app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [
                        'institution_id' => $lockedAttempt->institution_id,
                        'office' => 'guidance',
                    ])->allowed
                    || ! app(ConsultationPortalCopyEligibility::class)->isEligible(
                        $lockedAttempt,
                        $lockedDecision,
                        $lockedEncounter,
                        $lockedDocument,
                        $actor,
                    )
                    || app(ResultDocumentPdf::class)->isReady($lockedDocument)
                ) {
                    return;
                }

                $manifest = is_array($lockedDocument->artifact_manifest) ? $lockedDocument->artifact_manifest : [];
                $manifest['pdf'] = [
                    'status' => 'failed',
                    'reason' => 'automatic_preparation_failed',
                ];
                $lockedDocument->forceFill(['artifact_manifest' => $manifest])->save();
            },
        );
    }
}
