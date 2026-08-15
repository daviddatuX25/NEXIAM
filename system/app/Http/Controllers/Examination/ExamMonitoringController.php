<?php

namespace App\Http\Controllers\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Queries\MonitoringRosterQuery;
use App\Examination\Support\ProctorDutyGate;
use App\Http\Controllers\Controller;
use App\Models\Examination\ExamSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exam Monitoring — the conduct phase (published / in-progress), distinct from
 * scheduling (D-015/D-016). Test Administrators see all conduct sessions;
 * proctor-duty holders see only their duty sessions and can record attendance
 * / submission for those sessions only. Not a standalone proctor page.
 */
class ExamMonitoringController extends Controller
{
    public function monitoringIndex(
        Request $request,
        CapabilityAuthorizer $authorizer,
        MonitoringRosterQuery $query,
    ): Response {
        $user = $request->user();
        $canManage = $authorizer->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities());

        if (! $canManage && ! $query->hasAnyDuty($user)) {
            return Inertia::render('Examination/MonitoringIndex', [
                'page_state' => 'denied',
                'sessions' => [],
                'can_manage' => false,
            ]);
        }

        $sessions = $query->sessionsFor($user, $canManage);

        return Inertia::render('Examination/MonitoringIndex', [
            'page_state' => $sessions->isEmpty() ? 'empty' : 'ready',
            'sessions' => $sessions,
            'can_manage' => $canManage,
        ]);
    }

    public function monitoring(
        Request $request,
        ExamSession $session,
        CapabilityAuthorizer $authorizer,
        MonitoringRosterQuery $query,
    ): Response {
        $user = $request->user();
        $canManage = $authorizer->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities());

        if (! $canManage && ! ProctorDutyGate::hasActiveDuty($user, $session->id)) {
            abort(403);
        }

        $payload = $query->forSession($session);

        return Inertia::render('Examination/Monitoring', [
            'session' => $payload['session'],
            'roster' => $payload['roster'],
            'can_manage' => $canManage,
            'can_record' => $canManage || ProctorDutyGate::hasActiveDuty($user, $session->id),
        ]);
    }
}
