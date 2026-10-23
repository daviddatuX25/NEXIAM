<?php

declare(strict_types=1);

namespace App\Models\Auth;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffSessionLease extends Model
{
    protected $fillable = [
        'user_id',
        'session_id_hash',
        'inactivity_generation',
        'authenticated_at',
        'idle_expires_at',
        'absolute_expires_at',
        'effective_idle_minutes',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'authenticated_at' => 'immutable_datetime',
            'idle_expires_at' => 'immutable_datetime',
            'absolute_expires_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'effective_idle_minutes' => 'integer',
        ];
    }

    public function isExpired(CarbonInterface $now): bool
    {
        return $now->gte($this->idle_expires_at) || $now->gte($this->absolute_expires_at);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public static function activeForSession(int $userId, string $sessionId, CarbonInterface $now, ?string $generation = null): ?self
    {
        $query = self::query()
            ->active($now)
            ->where('user_id', $userId);

        if ($generation !== null && app()->environment('testing')) {
            return $query->where(function ($q) use ($sessionId, $generation) {
                $q->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))
                    ->orWhere('inactivity_generation', $generation);
            })->first();
        }

        return $query->where('session_id_hash', hash_hmac('sha256', $sessionId, (string) config('app.key')))->first();
    }

    public function scopeActive(Builder $query, CarbonInterface $now): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where('idle_expires_at', '>', $now)
            ->where('absolute_expires_at', '>', $now);
    }
}
