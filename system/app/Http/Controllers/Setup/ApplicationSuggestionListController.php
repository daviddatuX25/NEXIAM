<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\SaveApplicationSuggestionList;
use App\Foundation\Queries\ApplicationSuggestionListsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\SaveApplicationSuggestionListRequest;
use App\Models\Foundation\Institution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

final class ApplicationSuggestionListController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer, ApplicationSuggestionListsQuery $lists): Response
    {
        $institution = Institution::current();
        abort_unless($authorizer->decideFresh($request->user(), CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, [
            'institution_id' => (int) $institution->id, 'office' => 'registrar',
        ])->allowed, 403);

        return Inertia::render('Setup/ApplicationSuggestions', [
            'suggestion_lists' => $lists->forStaff((int) $institution->id),
        ]);
    }

    public function update(SaveApplicationSuggestionListRequest $request, SaveApplicationSuggestionList $save): RedirectResponse
    {
        $save->handle($request->validated(), $request->user());

        return Redirect::back()->with('status', 'Application suggestions saved.');
    }
}
