<?php

declare(strict_types=1);

namespace App\ResultSheet\Contracts;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ResultDocument;
use App\Models\User;

interface ResultDocumentAuthorityInterface
{
    /**
     * Prepares an immutable snapshot of all applicant-safe result variables.
     * Updates attempt->current_result_document_id under attempt lock.
     */
    public function prepareDocumentSnapshot(
        AdmissionAttempt $attempt,
        User $actor,
        string $idempotencyKey,
    ): ResultDocument;

    /**
     * Resolves the canonical readiness predicate for an attempt.
     */
    public function getResultReadiness(AdmissionAttempt $attempt): AttemptResultReadinessDto;
}
