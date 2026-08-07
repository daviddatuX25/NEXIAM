<?php

namespace App\Http\Middleware;

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

        $interactionModes = collect(config('nexi.interaction_modes', []))
            ->filter(fn (array $mode): bool => (bool) ($mode['enabled'] ?? false))
            ->map(fn (array $mode): array => [
                'key' => (string) $mode['key'],
                'label' => (string) $mode['label'],
            ])
            ->values()
            ->all();

        $defaultInteraction = (string) config('nexi.default_interaction', 'assistant');
        $allowedKeys = array_column($interactionModes, 'key');
        if ($allowedKeys !== [] && ! in_array($defaultInteraction, $allowedKeys, true)) {
            $defaultInteraction = $allowedKeys[0];
        }

        $usage = config('nexi.usage', []);

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

            'staff_navigation' => $this->staffNavigation($request),

            /*
             * Nexi presentation seam (GF-22 / master §8.4–8.5).
             * Provider/RAG remain E3 — UI is assistive chrome with typed-action previews only.
             */
            'nexi' => [
                'enabled' => (bool) config('nexi.enabled', true),
                'mode' => (string) config('nexi.mode', 'degraded'),
                'disclosure' => (string) config(
                    'nexi.disclosure',
                    'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.'
                ),
                'default_interaction' => $defaultInteraction,
                'interaction_modes' => $interactionModes,
                'usage' => [
                    'used' => (int) ($usage['used'] ?? 0),
                    'limit' => (int) ($usage['limit'] ?? 0),
                    'unit' => (string) ($usage['unit'] ?? 'asks'),
                    'period' => (string) ($usage['period'] ?? 'today'),
                ],
            ],
        ];
    }


    /**
     * @return list<array{href: string, label: string, icon: string, match: string, group: string}>
     */
    private function staffNavigation(Request $request): array
    {
        return app(StaffNavigationBuilder::class)->for($request->user());
    }
}
