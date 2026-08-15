<?php

namespace App\Consultation\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Consultation\ConsultationSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnpublishConsultationSession
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ConsultationSession $session, User $actor, int $expectedVersion, string $reason): ConsultationSession
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Unpublish requires a reason.');
        }

        return DB::transaction(function () use ($session, $actor, $expectedVersion, $reason): ConsultationSession {
            $session = ConsultationSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $session->version !== $expectedVersion) {
                throw new RuntimeException('Stale consultation session version.');
            }

            if ($session->status !== ConsultationSession::STATUS_PUBLISHED) {
                throw new RuntimeException('Only published sessions may be unpublished.');
            }

            $session->update([
                'status' => ConsultationSession::STATUS_DRAFT,
                'version' => $session->version + 1,
            ]);

            $this->recordAuditEvent->handle('consultation.session_unpublished', [
                'consultation_session_id' => $session->id,
                'reason' => $reason,
                'prior_published_at' => $session->published_at?->toIso8601String(),
            ], (string) $actor->id);

            return $session->fresh();
        });
    }
}
