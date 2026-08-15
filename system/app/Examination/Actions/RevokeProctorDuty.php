<?php

namespace App\Examination\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Examination\ProctorDuty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RevokeProctorDuty
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function handle(ProctorDuty $duty, User $actor): ProctorDuty
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)) {
            throw new RuntimeException('proctor_assign_denied');
        }

        return DB::transaction(function () use ($duty, $actor): ProctorDuty {
            $duty = ProctorDuty::query()->lockForUpdate()->findOrFail($duty->id);

            if ($duty->status !== ProctorDuty::STATUS_ACTIVE) {
                throw new RuntimeException('Duty is not active.');
            }

            $duty->update([
                'status' => ProctorDuty::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

            $this->recordAuditEvent->handle('exam.proctor_duty_revoked', [
                'duty_id' => $duty->id,
            ], (string) $actor->id);

            return $duty->fresh() ?? $duty;
        });
    }
}
