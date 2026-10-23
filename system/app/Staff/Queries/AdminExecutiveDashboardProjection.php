<?php

namespace App\Staff\Queries;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Room;
use App\Models\Staffing\InstitutionalSetupDelegation;
use App\Models\User;

final class AdminExecutiveDashboardProjection
{
    public function __construct(
        private readonly AdmissionsFunnelAggregateQuery $admissions,
        private readonly AssessmentThroughputAggregateQuery $assessment,
        private readonly ProgramQuotaVarianceAggregateQuery $quota,
    ) {}

    public function for(User $user): array
    {
        $institutionId = $user->actorScope()->institutionId;
        $cycle = AcademicCycle::active();
        $cycleId = $cycle?->id;
        $rooms = Room::query()->where('institution_id', $institutionId)->where('is_active', true);
        $delegations = InstitutionalSetupDelegation::query()->active()->where('institution_id', $institutionId)
            ->with(['recipient:id,name,email', 'actor:id,name'])->get();
        $auditEvents = AuditEvent::query()->where(function ($query) use ($institutionId): void {
            $query->whereNull('payload->institution_id')
                ->orWhere('payload->institution_id', $institutionId)
                ->orWhere('payload->institution_id', (string) $institutionId);
        });

        return [
            'as_of' => now()->toIso8601String(),
            'active_cycle' => $cycle ? ['id' => $cycle->id, 'code' => $cycle->code, 'name' => $cycle->staffLabel()] : null,
            'admissions' => $this->admissions->for($cycleId, $institutionId),
            'assessment' => $this->assessment->for($cycleId, $institutionId),
            'quota_variance' => $this->quota->for($cycleId, $institutionId),
            'facility_health' => [
                'open_application_windows' => ApplicationWindow::query()->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
                    ->where('status', 'published')->where('opens_at', '<=', now())->where('closes_at', '>=', now())->count(),
                'active_exam_rooms' => (clone $rooms)->count(),
                'seat_capacity' => (int) (clone $rooms)->sum('capacity'),
                'active_setup_delegations' => $delegations->map(fn ($delegation) => ['id' => $delegation->id, 'reason' => $delegation->reason, 'recipient' => $delegation->recipient?->only(['id', 'name', 'email']), 'actor' => $delegation->actor?->only(['id', 'name']), 'granted_at' => $delegation->granted_at?->toIso8601String()])->all(),
            ],
            'supervisory_audit' => [
                'last_24_hours' => (clone $auditEvents)->where('created_at', '>=', now()->subDay())->count(),
                'last_7_days' => (clone $auditEvents)->where('created_at', '>=', now()->subDays(7))->count(),
                'recent_events' => (clone $auditEvents)->latest('created_at')->limit(10)->get()->map(fn ($event) => ['id' => $event->id, 'event_type' => $event->event_type, 'actor' => $event->actor, 'created_at' => $event->created_at?->toIso8601String()])->all(),
            ],
        ];
    }
}
