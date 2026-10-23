<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Models\Auth\StaffSessionLease;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class EstablishStaffSessionLease
{
    public function handle(
        User $user,
        string $sessionId,
        CarbonInterface $now,
        ?int $effectiveIdleMinutes = null,
        ?CarbonInterface $interactiveAuthenticatedAt = null,
    ): StaffSessionLease {
        $effectiveIdleMinutes ??= 15;
        $authenticatedAt = $interactiveAuthenticatedAt ?? $now;
        $absoluteExpiresAt = $authenticatedAt->copy()->addHours(8);
        if ($now->greaterThanOrEqualTo($absoluteExpiresAt)) {
            throw new \RuntimeException('The staff shift has reached its absolute session limit.');
        }

        $idleExpiresAt = $now->copy()->addMinutes($effectiveIdleMinutes);
        if ($idleExpiresAt->greaterThan($absoluteExpiresAt)) {
            $idleExpiresAt = $absoluteExpiresAt;
        }

        return StaffSessionLease::query()->create([
            'user_id' => $user->getKey(),
            'session_id_hash' => hash_hmac('sha256', $sessionId, (string) config('app.key')),
            'inactivity_generation' => (string) Str::uuid(),
            'authenticated_at' => $authenticatedAt,
            'idle_expires_at' => $idleExpiresAt,
            'absolute_expires_at' => $absoluteExpiresAt,
            'effective_idle_minutes' => $effectiveIdleMinutes,
        ]);
    }
}
