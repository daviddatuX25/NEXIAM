<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Models\Auth\StaffSessionLease;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RevokeStaffSessionLease
{
    public function handle(User $user, string $sessionId, CarbonInterface $now): bool
    {
        $generation = session('auth.staff_inactivity_generation');

        return DB::transaction(function () use ($user, $sessionId, $now, $generation): bool {
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
            if ($lease === null || $lease->isRevoked()) {
                return false;
            }

            $lease->revoked_at = $now;

            return $lease->save();
        });
    }
}
