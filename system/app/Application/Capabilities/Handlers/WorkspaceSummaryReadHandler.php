<?php

namespace App\Application\Capabilities\Handlers;

use App\Staff\Queries\StaffDashboardProjection;
use Illuminate\Contracts\Auth\Authenticatable;

final class WorkspaceSummaryReadHandler
{
    public function __construct(
        private readonly StaffDashboardProjection $projection,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{summary: array{institution_id: int, active_cycle_name: string, pending_reviews_count: int, today_sessions_count: int, upcoming_sessions_count: int, draft_sessions_count: int, as_of: string}}
     */
    public function handle(array $input = [], ?Authenticatable $actor = null): array
    {
        $actor ??= auth()->user();
        $institutionId = ($actor !== null && method_exists($actor, 'actorScope'))
            ? (int) $actor->actorScope()->institutionId
            : 0;

        return $this->projection->forXiamSafe($institutionId);
    }
}
