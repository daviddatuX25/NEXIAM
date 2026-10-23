<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreProgramRequest;
use App\Http\Requests\Setup\UpdateProgramRequest;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Program catalog CRUD (institutional setup catalog, shared `setup.manage`).
 */
class ProgramController extends Controller
{
    use HasSetupCapabilities;

    public function programs(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireProgramSetup($authorizer, $request);
        $actor = $request->user()->fresh();
        $scope = $actor->actorScope();
        $canManagePublicProfile = $authorizer->decideFresh($actor, CapabilityCatalog::PROGRAM_PUBLIC_PROFILE_MANAGE, [
            'institution_id' => $scope->institutionId,
            'office' => 'program',
        ])->allowed;

        return Inertia::render('Setup/Programs', [
            'page_state' => 'ready',
            'programs' => Program::query()
                ->where('institution_id', Institution::current()->id)
                ->with('department:id,code,name')
                ->orderBy('code')
                ->get([
                    'id', 'code', 'name', 'is_active', 'institution_id', 'department_id',
                ])
                ->map(function (Program $program) use ($scope, $canManagePublicProfile): Program {
                    $owned = $canManagePublicProfile && $program->is_active
                        && (int) $program->institution_id === $scope->institutionId
                        && in_array($program->code, $scope->programCodes, true);
                    $program->setAttribute('department_id', $program->department_id === null ? null : (int) $program->department_id);
                    $program->setAttribute('public_details_href', $owned ? '/setup/programs/'.$program->id.'/public-details' : null);

                    return $program;
                }),
            'departments' => Department::query()
                ->where('institution_id', Institution::current()->id)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'dean_user_id']),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeProgram(StoreProgramRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireProgramSetup($authorizer, $request);

        $program = Program::query()->create([
            'institution_id' => Institution::current()->id,
            'department_id' => $request->validated('department_id'),
            'code' => strtoupper($request->validated('code')),
            'name' => $request->validated('name'),
            'is_active' => (bool) ($request->validated('is_active') ?? true),
        ]);

        $audit->handle('setup.program_upserted', [
            'program_id' => $program->id,
            'code' => $program->code,
            'is_active' => $program->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Program saved.');
    }

    public function updateProgram(
        UpdateProgramRequest $request,
        Program $program,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireProgramSetup($authorizer, $request);
        abort_unless((int) $program->institution_id === (int) Institution::current()->id, 404);

        $program->update($request->validated());

        $audit->handle('setup.program_upserted', [
            'program_id' => $program->id,
            'code' => $program->code,
            'is_active' => $program->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Program updated.');
    }
}
