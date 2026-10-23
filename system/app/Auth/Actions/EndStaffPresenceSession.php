<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Models\Auth\StaffPresenceSession;
use Illuminate\Http\Request;

final class EndStaffPresenceSession
{
    public function handle(Request $request, string $reason): void
    {
        $user = $request->user();
        if (! in_array($reason, ['logout', 'security_rejected'], true) || ! $request->hasSession() || $user === null) {
            return;
        }

        $sessionId = $request->session()->getId();
        $hash = hash_hmac('sha256', $sessionId, (string) config('app.key'));
        $now = now();
        $updated = StaffPresenceSession::query()
            ->where('user_id', $user->getKey())
            ->where('session_id_hash', $hash)
            ->whereNull('ended_at')
            ->update(['ended_at' => $now, 'end_reason' => $reason]);

        if ($updated === 0) {
            $latestActiveSession = StaffPresenceSession::query()
                ->where('user_id', $user->getKey())
                ->whereNull('ended_at')
                ->orderByDesc('last_active_at')
                ->first();

            $latestActiveSession?->update(['ended_at' => $now, 'end_reason' => $reason]);
        }
    }
}
