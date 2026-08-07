<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordConsultationDecision
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(
        AdmissionAttempt $attempt,
        User $counselor,
        string $decisionType,
        ?string $notes,
        string $idempotencyKey,
    ): ConsultationDecision {
        $existing = ConsultationDecision::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($attempt, $counselor, $decisionType, $notes, $idempotencyKey): ConsultationDecision {
            $decision = ConsultationDecision::query()->create([
                'admission_attempt_id' => $attempt->id,
                'counselor_user_id' => $counselor->id,
                'decision_type' => $decisionType,
                'notes' => $notes,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->recordAuditEvent->handle('consultation.decision_recorded', [
                'decision_id' => $decision->id,
                'attempt_id' => $attempt->id,
                'decision_type' => $decisionType,
            ], (string) $counselor->id);

            return $decision;
        });
    }
}
