<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StorePrivacyPolicyRequest;
use App\Models\Foundation\PrivacyPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Privacy policy catalog (single-active-policy semantics).
 */
class PrivacyPolicyController extends Controller
{
    use HasSetupCapabilities;

    public function privacy(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Privacy', [
            'page_state' => 'ready',
            'policies' => PrivacyPolicy::query()->orderByDesc('id')->get([
                'id', 'title', 'content', 'is_active',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storePrivacy(StorePrivacyPolicyRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        if (! empty($request->validated('is_active'))) {
            PrivacyPolicy::query()->where('is_active', true)->update(['is_active' => false]);
        }

        $policy = PrivacyPolicy::query()->create([
            'title' => $request->validated('title'),
            'content' => $request->validated('content'),
            'is_active' => (bool) ($request->validated('is_active') ?? false),
        ]);

        $audit->handle('setup.privacy_upserted', [
            'policy_id' => $policy->id,
            'is_active' => $policy->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Privacy policy saved.');
    }

    public function activatePrivacy(
        Request $request,
        PrivacyPolicy $policy,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireSetup($authorizer, $request);

        PrivacyPolicy::query()->where('is_active', true)->update(['is_active' => false]);
        $policy->update(['is_active' => true]);

        $audit->handle('setup.privacy_activated', [
            'policy_id' => $policy->id,
        ], (string) $request->user()->id);

        return back()->with('status', 'Privacy policy activated.');
    }
}
