<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Models\Foundation\AcademicCycle;
use App\Models\Notifications\CommunicationTemplate;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Queries\NoticeTemplateNavigationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationResultsDeskController extends Controller
{
    /**
     * Show the institution's result and consultation notice catalog.
     */
    public function show(
        Request $request,
        CommunicationTemplateScope $scope,
        NoticeTemplateNavigationQuery $navigationQuery,
    ): Response {
        $user = $request->user();
        $institution = $scope->authorizeMetadata($user, 'results');
        $institutionId = (int) $institution->id;
        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->first();
        $notices = $scope->projectMetadataCollection(CommunicationTemplate::query()
            ->where('institution_id', $institutionId)
            ->where('domain', 'results')
            ->with('draft:id,communication_template_id')
            ->orderBy('name')
            ->get(['id', 'domain', 'event_key', 'slug', 'active_version', 'family_revision', 'updated_at']));

        return Inertia::render('Setup/Notifications/Results', [
            'active_cycle' => $activeCycle ? [
                'id' => $activeCycle->id,
                'label' => $activeCycle->staffLabel(),
            ] : null,
            'navigation' => $navigationQuery->forDesk($user, 'results'),
            'notices' => $notices,
            'capabilities' => [
                'can_manage' => $scope->canAccessFullSource($request->user(), 'results', $institution),
            ],
            'page_state' => 'ready',
        ]);
    }

    /**
     * Result and consultation notices are governed by their release workflows, not a standalone policy.
     */
    public function update(Request $request, CommunicationTemplateScope $scope): RedirectResponse
    {
        $scope->authorizeFullSource($request->user(), 'results');

        return back()->with('status', 'Result and consultation notices are governed by their release workflows; no standalone policy was changed.');
    }
}
