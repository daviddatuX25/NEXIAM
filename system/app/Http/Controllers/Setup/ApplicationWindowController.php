<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreApplicationWindowRequest;
use App\Setup\Actions\StoreApplicationWindow;
use Illuminate\Http\RedirectResponse;

/**
 * Application-window upsert. Cycle-scoped via validation; one compact surface
 * so it stays a single controller rather than merging into cycle lifecycle.
 */
class ApplicationWindowController extends Controller
{
    use HasSetupCapabilities;

    public function storeWindow(
        StoreApplicationWindowRequest $request,
        CapabilityAuthorizer $authorizer,
        StoreApplicationWindow $storeWindow,
    ): RedirectResponse {
        $this->requireCycleSetup($authorizer, $request);

        $storeWindow->handle($request->validated(), $request->user());

        return back()->with('status', 'Application window saved.');
    }
}
