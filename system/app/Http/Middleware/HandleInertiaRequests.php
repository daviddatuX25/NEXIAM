<?php

namespace App\Http\Middleware;

use App\Ai\Http\NexiSessionViewBuilder;
use App\Models\User;
use App\Notifications\NotificationProjection;
use App\Support\Navigation\StaffNavigationBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $nexiView = app(NexiSessionViewBuilder::class)->build($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'capabilities' => $user->getAllPermissions()->pluck('name')->values(),
                    'scope' => $user->actorScope()->toArray(),
                    'is_applicant' => $user->applicantAccount()->exists(),
                    'is_staff' => $user->staffProfile()->exists(),
                ],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],

            /*
             * Own-data notification bell share (GF-22 §5.5). The client polls
             * with usePoll(30_000, () => ({ only: ['notifications'] })) so the
             * badge/list refresh without a full navigation.
             */
            'notifications' => $user instanceof User
                ? fn () => app(NotificationProjection::class)->forUser($user)
                : null,

            'staff_navigation' => $this->staffNavigation($request),
            'applicant_navigation' => fn () => app(\App\Applicant\Support\ApplicantNavigationBuilder::class)->for($user),

            /*
             * Transitional Nexi share (OpenSpec ai-assistant-foundation):
             * - `session` is the typed NexiSessionView v1 contract
             * - top-level enabled/mode/disclosure string keep the locked chrome mounting
             *   until NexiDock lands (frontend tree currently permission-blocked for this agent)
             */
            'nexi' => $this->nexiShare($nexiView),
        ];
    }

    /**
     * @return list<array{label: string|null, items: list<array{href: string, label: string, icon: string, match: string}>}>
     */
    private function staffNavigation(Request $request): array
    {
        return app(StaffNavigationBuilder::class)->for($request->user());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function nexiShare(?\App\Ai\Http\NexiSessionView $view): ?array
    {
        if ($view === null) {
            return null;
        }

        $session = $view->toArray();
        $visible = $session['availability'] !== 'hidden';

        $modes = [];
        foreach ($session['modes'] as $key) {
            $modes[] = [
                'key' => $key,
                'label' => $key === 'inquiry' ? 'Inquiry' : 'Assistant',
            ];
        }

        return [
            'enabled' => $visible,
            'mode' => $visible ? 'degraded' : 'disabled',
            'disclosure' => $session['disclosure']['text'],
            'default_interaction' => $session['active_mode'],
            'interaction_modes' => $modes,
            'usage' => [
                'used' => $session['usage']['used'] ?? 0,
                'limit' => $session['usage']['limit'] ?? 0,
                'unit' => $session['usage']['unit'] ?? 'asks',
                'period' => 'policy',
            ],
            'session' => $session,
        ];
    }
}
