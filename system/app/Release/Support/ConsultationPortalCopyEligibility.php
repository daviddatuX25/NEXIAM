<?php

declare(strict_types=1);

namespace App\Release\Support;

use App\Consultation\Support\AttemptResultReadiness;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\User;

final class ConsultationPortalCopyEligibility
{
    public function isEligible(
        AdmissionAttempt $attempt,
        ConsultationDecision $decision,
        ConsultationEncounter $encounter,
        ResultDocument $document,
        User $actor,
    ): bool {
        $latestDecision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();
        $retracted = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('result_document_id', $document->id)
            ->where('channel', 'portal')
            ->where(function ($query): void {
                $query->where('is_current', false)
                    ->orWhereNotNull('unreleased_at')
                    ->orWhereNotNull('superseded_at');
            })
            ->exists();

        return ! $retracted
            && $attempt->status === AdmissionAttempt::STATUS_ACCEPTED
            && AttemptResultReadiness::isReady($attempt)
            && (int) $decision->admission_attempt_id === (int) $attempt->id
            && (int) $encounter->admission_attempt_id === (int) $attempt->id
            && (int) $document->admission_attempt_id === (int) $attempt->id
            && (int) $attempt->current_result_document_id === (int) $document->id
            && $document->status === ResultDocument::STATUS_READY
            && (int) ($latestDecision?->id ?? 0) === (int) $decision->id
            && in_array($decision->decision_type, ['approve', 'modify', 'override', 'refer'], true)
            && $encounter->medium === ConsultationEncounter::MEDIUM_F2F
            && in_array($encounter->status, [
                ConsultationEncounter::STATUS_OUTCOME_RECORDED,
                ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME,
            ], true)
            && $encounter->closed_at !== null
            && (int) $encounter->institution_id === (int) $attempt->institution_id
            && (int) $decision->counselor_user_id === (int) $actor->id
            && (int) $encounter->counselor_user_id === (int) $actor->id
            && ($encounter->result_document_id === null || (int) $encounter->result_document_id === (int) $document->id)
            && ($encounter->result_document_version === null || (int) $encounter->result_document_version === (int) $document->version);
    }
}
