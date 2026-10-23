<?php

namespace App\Applicant\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Release\Actions\ReleaseResult;
use App\Release\Support\ResultDocumentPdf;

final class ApplicantResultAccess
{
    public function __construct(private readonly ResultDocumentPdf $pdf) {}

    /**
     * @return array{release: ?ResultRelease, document: ?ResultDocument, available: bool, pending: bool, retracted: bool, completed_f2f: bool, artifact_failed: bool}
     */
    public function forAttempt(AdmissionAttempt $attempt, bool $verifyArtifact = true): array
    {
        $document = $attempt->current_result_document_id === null
            ? null
            : ResultDocument::query()
                ->whereKey($attempt->current_result_document_id)
                ->where('admission_attempt_id', $attempt->id)
                ->where('status', ResultDocument::STATUS_READY)
                ->first();
        $release = $this->currentPortalRelease($attempt);
        $latestDecision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        $deliveryDecision = $latestDecision !== null
            && in_array($latestDecision->decision_type, ['approve', 'modify', 'override', 'refer'], true);
        $completedF2fEncounter = $this->completedF2fEncounter($attempt, $document);
        $pdfManifest = $document?->artifact_manifest['pdf'] ?? null;
        $pdfStatus = is_array($pdfManifest) ? ($pdfManifest['status'] ?? null) : null;
        $artifactReady = $verifyArtifact && $document !== null && $this->pdf->isReady($document);
        $canPrepare = $verifyArtifact && $document !== null && $this->pdf->canPrepare($document);
        $artifactFailed = $document !== null && ($pdfStatus === 'failed' || ($verifyArtifact && ! $artifactReady && ! $canPrepare));
        $retracted = $document !== null && ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $document->id)
            ->where(function ($query): void {
                $query->where('channel', 'portal')->orWhereNull('channel');
            })
            ->where(function ($query): void {
                $query->where('is_current', false)
                    ->orWhereNotNull('unreleased_at')
                    ->orWhereNotNull('superseded_at');
            })
            ->exists();
        $pending = $release === null
            && ! $retracted
            && ! $artifactFailed
            && $deliveryDecision
            && $completedF2fEncounter !== null
            && ($artifactReady || $canPrepare)
            && ! ResultRelease::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('result_document_id', $document?->id)
                ->where('channel', 'portal')
                ->where('consultation_decision_id', $latestDecision?->id)
                ->where('consultation_encounter_id', $completedF2fEncounter?->id)
                ->exists();
        $available = $release !== null
            && $document !== null
            && (int) $release->result_document_id === (int) $document->id
            && $this->releaseIsComplete($release, $attempt, $document, $latestDecision, $deliveryDecision, $completedF2fEncounter)
            && $pdfStatus !== 'failed'
            && (! $verifyArtifact || $artifactReady);

        return [
            'release' => $release,
            'document' => $available ? $document : null,
            'available' => $available,
            'pending' => $pending,
            'retracted' => $retracted,
            'completed_f2f' => $completedF2fEncounter !== null,
            'artifact_failed' => $artifactFailed,
        ];
    }

    private function currentPortalRelease(AdmissionAttempt $attempt): ?ResultRelease
    {
        if ($attempt->current_result_document_id === null) {
            return null;
        }

        $base = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $attempt->current_result_document_id)
            ->where('is_current', true)
            ->whereNull('unreleased_at')
            ->whereNull('superseded_at');

        return (clone $base)->where('channel', 'portal')->latest('id')->first()
            ?? $base->whereNull('channel')->latest('id')->first();
    }

    private function releaseIsComplete(
        ResultRelease $release,
        AdmissionAttempt $attempt,
        ResultDocument $document,
        ?ConsultationDecision $latestDecision,
        bool $deliveryDecision,
        ?ConsultationEncounter $completedF2fEncounter,
    ): bool {
        if ($release->channel === null && $release->physical_handover_at !== null) {
            return false;
        }

        if ($release->consultation_decision_id !== null || $release->consultation_encounter_id !== null) {
            if ($release->consultation_decision_id === null
                || $release->consultation_encounter_id === null
                || ! $deliveryDecision
                || (int) $release->consultation_decision_id !== (int) $latestDecision?->id
                || $release->mode !== ReleaseResult::MODE_ONLINE
                || $release->channel !== 'portal') {
                return false;
            }

            $encounter = ConsultationEncounter::query()
                ->whereKey($release->consultation_encounter_id)
                ->where('admission_attempt_id', $attempt->id)
                ->where('medium', ConsultationEncounter::MEDIUM_F2F)
                ->whereIn('status', [
                    ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                    ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
                ])
                ->whereNotNull('closed_at')
                ->first();

            return $encounter !== null
                && ($encounter->result_document_id === null || (int) $encounter->result_document_id === (int) $document->id)
                && ($encounter->result_document_version === null || (int) $encounter->result_document_version === (int) $document->version);
        }

        if ($release->mode === ReleaseResult::MODE_ONLINE) {
            return true;
        }

        return $release->mode === ReleaseResult::MODE_F2F
            && in_array($latestDecision?->decision_type, ['approve', 'modify', 'override'], true)
            && $completedF2fEncounter !== null;
    }

    private function completedF2fEncounter(AdmissionAttempt $attempt, ?ResultDocument $document): ?ConsultationEncounter
    {
        if ($document === null) {
            return null;
        }

        return ConsultationEncounter::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('medium', ConsultationEncounter::MEDIUM_F2F)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
            ])
            ->whereNotNull('closed_at')
            ->where(function ($query) use ($document): void {
                $query->whereNull('result_document_id')->orWhere('result_document_id', $document->id);
            })
            ->where(function ($query) use ($document): void {
                $query->whereNull('result_document_version')->orWhere('result_document_version', $document->version);
            })
            ->latest('id')
            ->first();
    }
}
