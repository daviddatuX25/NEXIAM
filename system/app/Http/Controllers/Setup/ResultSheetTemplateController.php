<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\ResultSheet\StoreResultSheetTemplateRequest;
use App\Http\Requests\ResultSheet\UpdateResultSheetTemplateRequest;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\ResultSheet\Actions\ActivateResultSheetTemplate;
use App\ResultSheet\Actions\DeactivateResultSheetTemplate;
use App\ResultSheet\Actions\SaveResultSheetTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Result-sheet template authority (legacy Admin › Result templates). Gates on
 * `guidance.assessment.catalog` — the Test-Administrator catalog capability
 * reserved for aptitude areas and result templates.
 */
class ResultSheetTemplateController extends Controller
{
    use HasSetupCapabilities;

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/ResultSheetTemplates/Index', [
            'page_state' => 'ready',
            'templates' => ResultSheetTemplate::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'mode', 'paper_size', 'orientation', 'logical_unit', 'watermark_text', 'is_active', 'document_extension', 'updated_at']),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/ResultSheetTemplates/Create', [
            'page_state' => 'ready',
            'placeholderGroups' => [],
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function store(
        StoreResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        SaveResultSheetTemplate $save,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $save->create($request->validated(), $request->user());

        return redirect()->route('setup.result-templates.index')->with('status', 'Result sheet template created.');
    }

    public function edit(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
    ): Response {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/ResultSheetTemplates/Edit', [
            'page_state' => 'ready',
            'template' => $resultTemplate,
            'placeholderGroups' => [],
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function update(
        UpdateResultSheetTemplateRequest $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        SaveResultSheetTemplate $save,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $save->update($resultTemplate, $request->validated(), $request->user());

        return redirect()->route('setup.result-templates.index')->with('status', 'Result sheet template updated.');
    }

    public function activate(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        ActivateResultSheetTemplate $activate,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $activate->handle($resultTemplate, $request->user());

        return back()->with('status', 'Template activated.');
    }

    public function deactivate(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ResultSheetTemplate $resultTemplate,
        DeactivateResultSheetTemplate $deactivate,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);

        $deactivate->handle($resultTemplate, $request->user());

        return back()->with('status', 'Template deactivated.');
    }
}
