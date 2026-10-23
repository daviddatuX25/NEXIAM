<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Support\StaffPresenceWorkspace;
use App\Models\Auth\StaffPresenceSession;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;

final class TouchStaffPresenceSession
{
    public function __construct(
        private readonly StartStaffPresenceSession $start,
        private readonly StaffPresenceWorkspace $workspace,
    ) {}

    public function handle(User $user, Request $request, int $sessionVersion, bool $explicitHeartbeat = false): void
    {
        $profile = $user->staffProfile()->first();
        $sessionId = $request->session()->getId();
        if (! $profile instanceof StaffProfile || ! $profile->isActive() || $profile->institution_id === null || $sessionId === '') {
            return;
        }

        $workspace = $this->workspace->resolve($request);
        if ($workspace['background'] && ! $explicitHeartbeat) {
            return;
        }

        $hash = hash_hmac('sha256', $sessionId, (string) config('app.key'));
        $session = StaffPresenceSession::query()->where('session_id_hash', $hash)->first();
        if ($session === null) {
            $this->start->handle($user, $request, $sessionVersion, false);

            return;
        }
        if ($session->ended_at !== null || (int) $session->user_id !== (int) $user->getKey()) {
            return;
        }

        $workspaceChanged = ! $workspace['background']
            && ($session->current_path !== $workspace['path'] || $session->workspace_area !== $workspace['area']);
        $query = StaffPresenceSession::query()->whereKey($session->getKey())->whereNull('ended_at');
        if (! $workspaceChanged) {
            $query->where('last_active_at', '<=', now()->subSeconds(60));
        }

        $updates = [
            'last_active_at' => now(),
            'auth_session_version' => $sessionVersion,
            'institution_id' => $profile->institution_id,
            'office' => (string) $profile->office,
            'ip_address' => $request->ip(),
        ];
        if ($workspaceChanged) {
            $updates['current_path'] = $workspace['path'];
            $updates['workspace_area'] = $workspace['area'];
        }
        $query->update($updates);
    }
}
