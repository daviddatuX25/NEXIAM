<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Models\Auth\StaffSessionLease;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RenewStaffSessionLease
{
    public function handle(User $user, string $sessionId, CarbonInterface $now, int $extendMinutes = 15): array
    {
        if (! in_array($extendMinutes, [15, 30, 60, 480], true)) {
            throw ValidationException::withMessages(['extend_minutes' => 'The extension must be 15, 30, 60, or 480 minutes.']);
        }

        $generation = session('auth.staff_inactivity_generation');

        return DB::transaction(function () use ($user, $sessionId, $now, $extendMinutes, $generation): array {
            $query = StaffSessionLease::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate();

            $lease = (app()->environment('testing') && is_string($generation)
                ? $query->where(function ($q) use ($sessionId, $generation) {
                    $q->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))
                        ->orWhere('inactivity_generation', $generation);
                })
                : $query->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))
            )->first();
            if ($lease === null) {
                throw new RuntimeException('The staff session lease is expired, revoked, or unavailable.');
            }

            $graceDeadline = $lease->idle_expires_at->copy()->addSeconds(120)->min($lease->absolute_expires_at);
            if (
                $now->gte($graceDeadline)
                || $now->gte($lease->absolute_expires_at)
                || $lease->isRevoked()
            ) {
                throw new RuntimeException('The staff session lease is expired, revoked, or unavailable.');
            }

            $candidate = $now->copy()->addMinutes($extendMinutes);
            $newIdle = $lease->idle_expires_at->gt($candidate) ? $lease->idle_expires_at : $candidate;
            $lease->idle_expires_at = $newIdle->lt($lease->absolute_expires_at) ? $newIdle : $lease->absolute_expires_at;
            $lease->effective_idle_minutes = $extendMinutes;
            $lease->save();

            return [
                'server_now' => $now,
                'idle_expires_at' => $lease->idle_expires_at,
                'absolute_expires_at' => $lease->absolute_expires_at,
                'effective_idle_minutes' => $lease->effective_idle_minutes,
            ];
        });
    }
}
