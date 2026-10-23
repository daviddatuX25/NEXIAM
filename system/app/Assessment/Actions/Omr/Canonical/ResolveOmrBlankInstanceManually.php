<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\User;

final class ResolveOmrBlankInstanceManually
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /** @return array{instance:OmrBlankInstance,recognition_blocked:bool} */
    public function handle(
        User $actor,
        string $qrState,
        string $displayCode,
        bool $confirmed,
        ?int $institutionId = null,
        ?string $cycleCode = null,
    ): array {
        if ($qrState === 'present_invalid') {
            throw new OmrBlankInstanceException('qr_signature_invalid', 'Manual entry cannot override a present but invalid QR payload.');
        }
        if (! in_array($qrState, ['absent', 'undecodable'], true)) {
            throw new OmrBlankInstanceException('manual_recovery_state_invalid', 'Manual recovery requires an absent or undecodable QR state.');
        }
        if (! $confirmed) {
            throw new OmrBlankInstanceException('manual_confirmation_required', 'Staff confirmation is required before manual resolution.');
        }
        $query = OmrBlankInstance::query()
            ->with('academicCycle')
            ->where('display_code', strtoupper(trim($displayCode)));
        if (($institutionId === null) !== ($cycleCode === null)) {
            throw new OmrBlankInstanceException('scope_incomplete', 'Both institution and academic-cycle scope are required.');
        }
        if ($institutionId !== null && $cycleCode !== null) {
            $query
                ->where('institution_id', $institutionId)
                ->whereHas('academicCycle', function ($query) use ($cycleCode): void {
                    $query->where('code', trim($cycleCode));
                });
        }
        $instance = $query->first();
        if ($instance === null) {
            throw new OmrBlankInstanceException('instance_not_found', 'Display code did not resolve to a blank instance in the active scope.');
        }
        if ($instance->status === OmrBlankInstance::STATUS_VOID) {
            throw new OmrBlankInstanceException('superseded_instance', 'Voided blank instances cannot be captured.');
        }
        $this->recordAuditEvent->handle('instance_resolved_manually', [
            'instance_id' => $instance->id,
            'display_code' => $instance->display_code,
            'qr_state' => $qrState,
            'recognition_blocked' => true,
            'institution_id' => $institutionId,
            'cycle' => $cycleCode,
        ], (string) $actor->id);

        return ['instance' => $instance, 'recognition_blocked' => true];
    }
}
