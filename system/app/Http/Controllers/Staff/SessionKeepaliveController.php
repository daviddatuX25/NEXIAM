<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Auth\Actions\RenewStaffSessionLease;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffSessionLeaseRequest;
use App\Models\Auth\StaffSessionLease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class SessionKeepaliveController extends Controller
{
    public function __invoke(StaffSessionLeaseRequest $request, RenewStaffSessionLease $renewLease): JsonResponse
    {
        return $this->keepalive($request, $renewLease);
    }

    public function keepalive(StaffSessionLeaseRequest $request, RenewStaffSessionLease $renewLease): JsonResponse
    {
        $user = $request->user();
        if ($user === null || $request->session()->get('auth.surface') !== 'staff') {
            abort(403);
        }

        $profile = $user->staffProfile()->first();
        if ($profile === null || ! $profile->isActive()) {
            abort(403);
        }

        $extendMinutes = (int) ($request->validated()['extend_minutes'] ?? 15);
        try {
            $leaseData = $renewLease->handle($user, $request->session()->getId(), now(), $extendMinutes);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'session_active' => true,
            'server_now' => $leaseData['server_now']->utc()->toIso8601String(),
            'idle_expires_at' => $leaseData['idle_expires_at']->utc()->toIso8601String(),
            'absolute_expires_at' => $leaseData['absolute_expires_at']->utc()->toIso8601String(),
            'effective_idle_minutes' => $leaseData['effective_idle_minutes'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || $request->session()->get('auth.surface') !== 'staff') {
            abort(403);
        }

        $profile = $user->staffProfile()->first();
        if ($profile === null || ! $profile->isActive()) {
            abort(403);
        }

        $now = now();
        $lease = StaffSessionLease::activeForSession($user->getKey(), $request->session()->getId(), $now);
        if ($lease === null) {
            return response()->json(['error' => 'session_expired'], 401);
        }

        return response()->json([
            'status' => 'ok',
            'session_active' => true,
            'server_now' => $now->utc()->toIso8601String(),
            'idle_expires_at' => $lease->idle_expires_at->utc()->toIso8601String(),
            'absolute_expires_at' => $lease->absolute_expires_at->utc()->toIso8601String(),
            'effective_idle_minutes' => $lease->effective_idle_minutes,
        ]);
    }
}
