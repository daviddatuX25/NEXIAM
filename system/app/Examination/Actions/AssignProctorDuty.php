<?php

namespace App\Examination\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AssignProctorDuty
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ExamSession $session, User $proctor, User $actor, string $idempotencyKey): ProctorDuty
    {
        $existing = ProctorDuty::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($session, $proctor, $actor, $idempotencyKey): ProctorDuty {
            $duty = ProctorDuty::query()->create([
                'exam_session_id' => $session->id,
                'user_id' => $proctor->id,
                'status' => ProctorDuty::STATUS_ACTIVE,
                'idempotency_key' => $idempotencyKey,
                'assigned_at' => now(),
            ]);

            $this->recordAuditEvent->handle('exam.proctor_duty_assigned', [
                'duty_id' => $duty->id,
                'exam_session_id' => $session->id,
                'proctor_user_id' => $proctor->id,
            ], (string) $actor->id);

            return $duty;
        });
    }
}
