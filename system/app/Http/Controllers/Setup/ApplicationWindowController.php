<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreApplicationWindowRequest;
use App\Models\Foundation\ApplicationWindow;
use Illuminate\Http\RedirectResponse;

/**
 * Application-window upsert. Cycle-scoped via validation; one compact surface
 * so it stays a single controller rather than merging into cycle lifecycle.
 */
class ApplicationWindowController extends Controller
{
    use HasSetupCapabilities;

    public function storeWindow(StoreApplicationWindowRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $window = ApplicationWindow::query()->updateOrCreate(
            [
                'academic_cycle_id' => $request->validated('academic_cycle_id'),
                'code' => strtoupper($request->validated('code')),
            ],
            [
                'name' => $request->validated('name'),
                'opens_at' => $request->validated('opens_at'),
                'closes_at' => $request->validated('closes_at'),
                'status' => $request->validated('status'),
            ],
        );

        $audit->handle('setup.window_upserted', [
            'window_id' => $window->id,
            'code' => $window->code,
            'status' => $window->status,
        ], (string) $request->user()->id);

        return back()->with('status', 'Application window saved.');
    }
}
