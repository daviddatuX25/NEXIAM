<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrivacyPolicy;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrivacyPolicyController extends Controller
{
    public function index(): Response
    {
        $policies = PrivacyPolicy::orderByDesc('is_active')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Admin/PrivacyPolicies/Index', [
            'policies' => $policies,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PrivacyPolicies/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        // If marking as active, deactivate all others
        if (! empty($validated['is_active'])) {
            PrivacyPolicy::where('is_active', true)->update(['is_active' => false]);
        }

        PrivacyPolicy::create($validated);

        app(AuditService::class)->log('privacy_policy.created', PrivacyPolicy::class, PrivacyPolicy::latest()->first()?->id, [], ['title' => $validated['title']]);

        return redirect()->route('admin.applications.privacy-policies.index')
            ->with('success', 'Privacy policy created.');
    }

    public function edit(PrivacyPolicy $privacyPolicy): Response
    {
        $this->authorizeActivePolicy($privacyPolicy);

        return Inertia::render('Admin/PrivacyPolicies/Edit', [
            'policy' => $privacyPolicy,
        ]);
    }

    public function update(Request $request, PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $this->authorizeActivePolicy($privacyPolicy);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_active' => ['boolean'],
        ]);

        // If marking as active, deactivate all others first
        if (! empty($validated['is_active'])) {
            PrivacyPolicy::where('is_active', true)
                ->where('id', '!=', $privacyPolicy->id)
                ->update(['is_active' => false]);
        }

        $privacyPolicy->update($validated);

        app(AuditService::class)->log('privacy_policy.updated', PrivacyPolicy::class, $privacyPolicy->id, [], ['title' => $validated['title']]);

        return redirect()->route('admin.applications.privacy-policies.index')
            ->with('success', 'Privacy policy updated.');
    }

    public function destroy(PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $this->authorizeActivePolicy($privacyPolicy);

        app(AuditService::class)->log('privacy_policy.deleted', PrivacyPolicy::class, $privacyPolicy->id, [], ['title' => $privacyPolicy->title]);

        $privacyPolicy->delete();

        return redirect()->route('admin.applications.privacy-policies.index')
            ->with('success', 'Privacy policy deleted.');
    }

    /**
     * Staff cannot edit/delete active policies — only super_admin and registrar_administrator can.
     */
    private function authorizeActivePolicy(PrivacyPolicy $policy): void
    {
        if ($policy->is_active) {
            $user = request()->user();
            $roles = $user->roles->pluck('name');

            if (! $roles->intersect(['super_admin', 'registrar_administrator'])->count()) {
                abort(403, 'Only administrators can modify the active privacy policy.');
            }
        }
    }

    public function activate(PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        // Deactivate all others first
        PrivacyPolicy::where('is_active', true)
            ->where('id', '!=', $privacyPolicy->id)
            ->update(['is_active' => false]);

        $privacyPolicy->update(['is_active' => true]);

        app(AuditService::class)->log('privacy_policy.activated', PrivacyPolicy::class, $privacyPolicy->id, [], []);

        return redirect()->route('admin.applications.privacy-policies.index')
            ->with('success', 'Privacy policy activated.');
    }

    public function deactivate(PrivacyPolicy $privacyPolicy): RedirectResponse
    {
        $privacyPolicy->update(['is_active' => false]);

        app(AuditService::class)->log('privacy_policy.deactivated', PrivacyPolicy::class, $privacyPolicy->id, [], []);

        return redirect()->route('admin.applications.privacy-policies.index')
            ->with('success', 'Privacy policy deactivated.');
    }

    /**
     * Public JSON endpoint: returns the active privacy policy for the apply page.
     */
    public function active(): JsonResponse
    {
        $policy = PrivacyPolicy::active();

        if (! $policy) {
            return response()->json(['policy' => null]);
        }

        return response()->json([
            'policy' => [
                'title' => $policy->title,
                'content' => $policy->content,
            ],
        ]);
    }
}
