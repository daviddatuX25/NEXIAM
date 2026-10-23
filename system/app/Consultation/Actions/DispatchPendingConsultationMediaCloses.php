<?php

declare(strict_types=1);

namespace App\Consultation\Actions;

use App\Consultation\Services\JitsiMediaAdapter;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationEncounter;
use Illuminate\Support\Facades\DB;

final class DispatchPendingConsultationMediaCloses
{
    public function __construct(
        private readonly JitsiMediaAdapter $media,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param list<int> $encounterIds */
    public function handle(array $encounterIds): void
    {
        foreach (array_values(array_unique($encounterIds)) as $encounterId) {
            $encounter = ConsultationEncounter::query()->find($encounterId);
            if ($encounter === null
                || $encounter->media_close_status !== ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING
                || ! is_string($encounter->room_name)
                || $encounter->room_name === '') {
                continue;
            }

            $requestId = $this->media->terminationRequestId($encounter);
            if (! $this->media->terminateConference($encounter, $requestId)) {
                continue;
            }

            DB::transaction(function () use ($encounter, $requestId): void {
                $lockedEncounter = ConsultationEncounter::query()->lockForUpdate()->findOrFail($encounter->getKey());
                if ($lockedEncounter->room_name !== $encounter->room_name
                    || (int) $lockedEncounter->schedule_generation !== (int) $encounter->schedule_generation
                    || $lockedEncounter->media_close_status !== ConsultationEncounter::MEDIA_CLOSE_STATUS_PENDING) {
                    return;
                }

                $changes = ['media_close_status' => ConsultationEncounter::MEDIA_CLOSE_STATUS_ACKNOWLEDGED];
                if ($lockedEncounter->status === ConsultationEncounter::STATUS_OUTCOME_RECORDED) {
                    $changes['status'] = ConsultationEncounter::STATUS_CLOSED_WITH_OUTCOME;
                    $changes['closed_at'] = $lockedEncounter->closed_at ?? now();
                }
                $lockedEncounter->update($changes);
                $this->audit->handle('consultation.media_room_terminated', [
                    'encounter_id' => $lockedEncounter->getKey(),
                    'room_name' => $lockedEncounter->room_name,
                    'schedule_generation' => (int) $lockedEncounter->schedule_generation,
                    'request_id' => $requestId,
                ], 'system:jitsi');
            });
        }
    }
}
