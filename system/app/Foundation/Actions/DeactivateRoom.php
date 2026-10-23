<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeactivateRoom
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(Room $room, User|string $actor): Room
    {
        return DB::transaction(function () use ($room, $actor): Room {
            $lockedRoom = Room::query()->lockForUpdate()->findOrFail($room->id);
            if ($actor instanceof User && $actor->staffProfile()->exists() && ! (
                app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_ROOMS_MANAGE, [
                    'institution_id' => (int) $lockedRoom->institution_id,
                ])->allowed || app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                    'institution_id' => (int) $lockedRoom->institution_id,
                ])->allowed
            )) {
                throw new AuthorizationException;
            }

            if (! $lockedRoom->is_active) {
                return $lockedRoom;
            }

            $hasActiveFutureExam = ExamSession::query()
                ->where('room_id', $lockedRoom->id)
                ->where('status', '!=', ExamSession::STATUS_CANCELLED)
                ->where('effective_end_at', '>', now())
                ->exists();

            $hasActiveFutureConsultation = ConsultationSession::query()
                ->where('room_id', $lockedRoom->id)
                ->where('status', '!=', ConsultationSession::STATUS_CANCELLED)
                ->where('scheduled_at', '>', now())
                ->exists();

            if ($hasActiveFutureExam || $hasActiveFutureConsultation) {
                throw ValidationException::withMessages([
                    'is_active' => 'This room has active future sessions and cannot be deactivated.',
                ]);
            }

            $lockedRoom->update(['is_active' => false]);

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('setup.room_deactivated', [
                'room_id' => $lockedRoom->id,
                'is_active' => false,
            ], $actorId);

            return $lockedRoom->fresh();
        });
    }
}
