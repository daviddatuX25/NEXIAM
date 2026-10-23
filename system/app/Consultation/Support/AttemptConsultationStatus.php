<?php

namespace App\Consultation\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ConsultationDecision;

/**
 * Derived "Advised" predicate (A2, founder 2026-08-14): the counselor
 * CONFIRMED a decision (approve/modify/override) — attendance alone is not
 * enough. Never persisted; recomputed on every read.
 */
final class AttemptConsultationStatus
{
    public static function isAdvised(AdmissionAttempt $attempt): bool
    {
        $latest = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->orderByDesc('id')
            ->first();

        return $latest !== null && in_array($latest->decision_type, ['approve', 'modify', 'override'], true);
    }
}
