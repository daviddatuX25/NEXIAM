<?php

declare(strict_types=1);

namespace App\Auth\Actions;

use App\Auth\Support\StaffPresenceWorkspace;
use App\Auth\Support\UserAgentDeviceParser;
use App\Models\Auth\StaffPresenceSession;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class StartStaffPresenceSession
{
    public function __construct(
        private readonly UserAgentDeviceParser $deviceParser,
        private readonly StaffPresenceWorkspace $workspace,
    ) {}

    public function handle(User $user, Request $request, int $sessionVersion, bool $loginEstablished = true): ?StaffPresenceSession
    {
        $profile = $user->staffProfile()->first();
        $sessionId = $request->session()->getId();
        if (! $profile instanceof StaffProfile || ! $profile->isActive() || $profile->institution_id === null || $sessionId === '') {
            return null;
        }

        $now = now();
        $hash = hash_hmac('sha256', $sessionId, (string) config('app.key'));
        $route = $this->workspace->resolve($request);
        $device = $this->deviceParser->parse($request->userAgent());

        return StaffPresenceSession::query()->updateOrCreate(
            ['session_id_hash' => $hash],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'institution_id' => $profile->institution_id,
                'office' => (string) $profile->office,
                'auth_session_version' => $sessionVersion,
                'device_type' => $device['device_type'],
                'browser' => $device['browser'],
                'os' => $device['os'],
                'ip_address' => $request->ip(),
                'current_path' => $route['background'] ? null : $route['path'],
                'workspace_area' => $route['background'] ? null : $route['area'],
                'logged_in_at' => $loginEstablished ? $now : null,
                'first_observed_at' => $now,
                'last_active_at' => $now,
                'ended_at' => null,
                'end_reason' => null,
            ],
        );
    }
}
