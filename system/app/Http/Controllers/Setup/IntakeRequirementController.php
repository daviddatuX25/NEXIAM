<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\ListDocumentRequirementsRequest;
use App\Http\Requests\Setup\SetDocumentRequirementActiveRequest;
use App\Http\Requests\Setup\StoreIntakeRequirementRequest;
use App\Http\Requests\Setup\UpdateIntakeRequirementRequest;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use App\Setup\Actions\CreateDocumentRequirement;
use App\Setup\Actions\SetDocumentRequirementActive;
use App\Setup\Actions\UpdateDocumentRequirement;
use App\Setup\Queries\ListDocumentRequirementsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntakeRequirementController extends Controller
{
    use HasSetupCapabilities;

    private function requireDocumentRequirementSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $institutionId = Institution::current()->id;
        $actorScope = $actor->actorScope();
        $scope = [];
        if ($actorScope->institutionId !== null && $institutionId !== null) {
            $scope['institution_id'] = (int) $institutionId;
        }

        $registrarScope = $scope;
        if ($actorScope->office !== null) {
            $registrarScope['office'] = 'registrar';
        }

        abort_unless(
            $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, $registrarScope)
                || $authorizer->allows($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope),
            403,
        );
    }

    public function index(
        ListDocumentRequirementsRequest $request,
        CapabilityAuthorizer $authorizer,
        ListDocumentRequirementsQuery $query,
    ): Response {
        $this->requireDocumentRequirementSetup($authorizer, $request);

        return Inertia::render('Setup/Requirements/Index', [
            ...$query->handle($request->validated()),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireDocumentRequirementSetup($authorizer, $request);

        return Inertia::render('Setup/Requirements/Create', [
            'tracks' => IntakeRequirement::trackOptions(),
            'kinds' => IntakeRequirement::kindOptions(),
            'defaults' => [
                'applicant_track' => IntakeRequirement::TRACK_FRESHMAN,
                'item_kind' => IntakeRequirement::KIND_DOCUMENT,
                'sort_order' => 100,
                'is_mandatory' => true,
                'is_active' => true,
            ],
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function store(
        StoreIntakeRequirementRequest $request,
        CapabilityAuthorizer $authorizer,
        CreateDocumentRequirement $action,
    ): RedirectResponse {
        $this->requireDocumentRequirementSetup($authorizer, $request);
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $request->validated());

        return to_route('setup.requirements')->with('status', 'Document requirement created.');
    }

    public function edit(
        Request $request,
        IntakeRequirement $requirement,
        CapabilityAuthorizer $authorizer,
    ): Response {
        $this->requireDocumentRequirementSetup($authorizer, $request);
        abort_unless((int) $requirement->institution_id === (int) Institution::current()->id, 404);

        return Inertia::render('Setup/Requirements/Edit', [
            'requirement' => [
                'id' => $requirement->id,
                'code' => $requirement->code,
                'label' => $requirement->label,
                'applicant_track' => $requirement->applicant_track,
                'item_kind' => $requirement->item_kind,
                'is_mandatory' => $requirement->is_mandatory,
                'is_active' => $requirement->is_active,
                'sort_order' => $requirement->sort_order,
                'version' => $requirement->version,
            ],
            'tracks' => IntakeRequirement::trackOptions(),
            'kinds' => IntakeRequirement::kindOptions(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function update(
        UpdateIntakeRequirementRequest $request,
        IntakeRequirement $requirement,
        CapabilityAuthorizer $authorizer,
        UpdateDocumentRequirement $action,
    ): RedirectResponse {
        $this->requireDocumentRequirementSetup($authorizer, $request);
        /** @var User $actor */
        $actor = $request->user();
        $action->handle($actor, $requirement, $request->validated());

        return to_route('setup.requirements')->with('status', 'Document requirement updated.');
    }

    public function setActive(
        SetDocumentRequirementActiveRequest $request,
        IntakeRequirement $requirement,
        CapabilityAuthorizer $authorizer,
        SetDocumentRequirementActive $action,
    ): RedirectResponse {
        $this->requireDocumentRequirementSetup($authorizer, $request);
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validated();
        $updated = $action->handle(
            $actor,
            $requirement,
            (bool) $data['is_active'],
            isset($data['expected_version']) ? (int) $data['expected_version'] : null,
        );

        return back()->with('status', $updated->is_active
            ? 'Document requirement activated.'
            : 'Document requirement deactivated.');
    }
}
