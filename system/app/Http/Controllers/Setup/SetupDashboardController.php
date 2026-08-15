<?php

namespace App\Http\Controllers\Setup;

use App\Application\Capabilities\CapabilityInvocationRequest;
use App\Application\Capabilities\CapabilityKernel;
use App\Authorization\CapabilityAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Setup landing hub. Surfaces per-capability readiness categories
 * (institutional catalog, aptitude/assessment, program quotas).
 */
class SetupDashboardController extends Controller
{
    use HasSetupCapabilities;

    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        CapabilityKernel $capabilities,
    ): Response {
        $caps = $this->capabilities($authorizer, $request);

        if (! $caps['setup'] && ! $caps['assessment'] && ! $caps['quota']) {
            abort(403);
        }

        $readiness = $capabilities->invoke(new CapabilityInvocationRequest(
            key: 'setup.readiness.read',
            version: 1,
            input: [],
            actor: $request->user(),
            channel: 'http_page',
            surfaceKey: 'staff',
            pagePackKey: $request->route()?->getName() === 'setup.index'
                ? 'staff.setup.readiness@1'
                : null,
            correlationId: $request->headers->get('X-Correlation-Id'),
        ));

        abort_unless($readiness->ok && is_array($readiness->data), 403, $readiness->reason ?? 'capability_denied');

        return Inertia::render('Setup/Index', [
            'page_state' => 'ready',
            'readiness' => $readiness->data,
            'capabilities' => $caps,
        ]);
    }
}
