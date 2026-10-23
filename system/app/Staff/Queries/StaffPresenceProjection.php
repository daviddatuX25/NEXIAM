<?php

declare(strict_types=1);

namespace App\Staff\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class StaffPresenceProjection
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return array<string, mixed> */
    public function for(Request $request): array
    {
        $user = $request->user();
        $scope = $user?->actorScope();
        $institutionId = $scope?->institutionId;
        if ($user === null || $institutionId === null || ! $this->authorizer->decideFresh($user, CapabilityCatalog::AUDIT_LOGS_VIEW, ['institution_id' => $institutionId])->allowed) {
            abort(403);
        }

        $actorOffice = $scope->office;
        if (! in_array($actorOffice, ['admin', 'registrar', 'guidance'], true)) {
            abort(403);
        }

        $canFilterOffices = $actorOffice === 'admin';
        $requestedOffice = (string) $request->query('office', 'all');
        $officeFilter = 'all';
        if ($canFilterOffices) {
            if (! in_array($requestedOffice, ['all', 'admin', 'registrar', 'guidance', 'program'], true)) {
                abort(422);
            }
            $officeFilter = $requestedOffice;
        } else {
            $officeFilter = $actorOffice;
        }

        $requestedState = (string) $request->query('state', 'active');
        if (! in_array($requestedState, ['active', 'online', 'recent', 'offline', 'all'], true)) {
            abort(422);
        }
        $search = trim(mb_substr((string) $request->query('search', ''), 0, 100));
        $now = now();
        $base = $this->baseQuery($institutionId, $actorOffice, $officeFilter);
        $currentSessionHash = hash_hmac('sha256', $request->session()->getId(), (string) config('app.key'));

        $classified = (clone $base)->get()->map(function (object $row) use ($now, $actorOffice, $currentSessionHash): array {
            $lastActive = Carbon::parse($row->last_active_at);
            $age = max(0, $now->getTimestamp() - $lastActive->getTimestamp());
            $validActor = $row->profile_status === 'active'
                && (int) $row->profile_institution_id === (int) $row->institution_id
                && (string) $row->profile_office === (string) $row->office
                && (int) $row->auth_session_version === (int) $row->current_session_version;
            $status = $row->ended_at !== null || ! $validActor || $age > 1800
                ? 'offline'
                : ($age <= 300 ? 'online' : 'recent');
            $ip = null;
            if (is_string($row->ip_address) && $row->ip_address !== '') {
                try {
                    $ip = Crypt::decryptString($row->ip_address);
                } catch (DecryptException) {
                    $ip = null;
                }
            }

            return [
                'id' => (string) $row->id,
                'is_current_session' => hash_equals($currentSessionHash, (string) $row->session_id_hash),
                'user' => ['id' => (int) $row->user_id, 'name' => (string) $row->name],
                'office' => (string) $row->office,
                'device_type' => (string) $row->device_type,
                'browser' => (string) $row->browser,
                'os' => (string) $row->os,
                'current_path' => $row->current_path,
                'workspace_area' => $row->workspace_area,
                'logged_in_at' => $row->logged_in_at,
                'first_observed_at' => $row->first_observed_at,
                'last_active_at' => $row->last_active_at,
                'ended_at' => $row->ended_at,
                'status' => $status,
                'ip_address' => $actorOffice === 'admin' ? $ip : $this->maskIp($ip),
            ];
        });

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $classified = $classified->filter(fn (array $row): bool => str_contains(mb_strtolower($row['user']['name']), $needle));
        }
        if ($requestedState === 'active') {
            $classified = $classified->filter(fn (array $row): bool => in_array($row['status'], ['online', 'recent'], true));
        } elseif ($requestedState !== 'all') {
            $classified = $classified->where('status', $requestedState);
        }

        $allForSummary = $classified;
        $onlineUsers = $allForSummary->where('status', 'online')->pluck('user.id')->unique()->count();
        $recentUsers = $allForSummary->where('status', 'recent')->pluck('user.id')->unique()->count();
        $sessions = $allForSummary->sortByDesc('last_active_at')->take(100)->values()->map(function (array $row): array {
            $row['last_active_human'] = Carbon::parse($row['last_active_at'])->diffForHumans();

            return $row;
        })->all();

        return [
            'as_of' => $now->toIso8601String(),
            'summary' => [
                'online_staff' => $onlineUsers,
                'recent_staff' => $recentUsers,
                'active_sessions' => $allForSummary->whereIn('status', ['online', 'recent'])->count(),
            ],
            'scope' => [
                'office' => $canFilterOffices ? $officeFilter : $actorOffice,
                'can_filter_offices' => $canFilterOffices,
                'label' => $canFilterOffices ? 'All offices · institution-wide' : ucfirst($actorOffice).' Office · own office only',
            ],
            'office_options' => $canFilterOffices ? [
                ['value' => 'all', 'label' => 'All Offices'],
                ['value' => 'admin', 'label' => 'Admin & HR'],
                ['value' => 'registrar', 'label' => 'Registrar'],
                ['value' => 'guidance', 'label' => 'Guidance'],
                ['value' => 'program', 'label' => 'Academic Programs'],
            ] : [],
            'filters' => ['office' => $officeFilter, 'state' => $requestedState, 'search' => $search],
            'sessions' => $sessions,
        ];
    }

    private function baseQuery(int $institutionId, string $actorOffice, string $officeFilter): Builder
    {
        $query = DB::table('staff_presence_sessions as presence')
            ->join('staff_profiles as profile', 'profile.user_id', '=', 'presence.user_id')
            ->join('staff_authentication_states as auth_state', 'auth_state.user_id', '=', 'presence.user_id')
            ->join('users', 'users.id', '=', 'presence.user_id')
            ->where('presence.institution_id', $institutionId)
            ->whereColumn('presence.institution_id', 'profile.institution_id')
            ->whereColumn('presence.office', 'profile.office')
            ->select([
                'presence.id', 'presence.user_id', 'presence.institution_id', 'presence.office',
                'presence.device_type', 'presence.browser', 'presence.os', 'presence.ip_address', 'presence.session_id_hash',
                'presence.current_path', 'presence.workspace_area', 'presence.logged_in_at',
                'presence.first_observed_at', 'presence.last_active_at', 'presence.ended_at',
                'presence.auth_session_version', 'profile.status as profile_status',
                'profile.institution_id as profile_institution_id', 'profile.office as profile_office',
                'auth_state.session_version as current_session_version', 'users.name',
            ]);

        if ($actorOffice !== 'admin') {
            $query->where('presence.office', $actorOffice);
        } elseif ($officeFilter !== 'all') {
            $query->where('presence.office', $officeFilter);
        }

        return $query->orderByDesc('presence.last_active_at')->orderByDesc('presence.id');
    }

    private function maskIp(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.xxx';
        }

        $packed = inet_pton($ip);
        if ($packed === false) {
            return null;
        }

        return inet_ntop(substr($packed, 0, 8).str_repeat("\0", 8)).'/64';
    }
}
