<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\StoreProgramRequest;
use App\Http\Requests\Setup\UpdateProgramRequest;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Illuminate\Http\RedirectResponse;
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
        $this->requireSetup($authorizer, $request);

        return Inertia::render('Setup/Programs', [
            'page_state' => 'ready',
            'programs' => Program::query()->orderBy('code')->get([
                'id', 'code', 'name', 'is_active', 'institution_id',
            ]),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeProgram(StoreProgramRequest $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $this->requireSetup($authorizer, $request);

        $program = Program::query()->create([
            'institution_id' => Institution::current()->id,
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
        $this->requireSetup($authorizer, $request);

        $program->update($request->validated());

        $audit->handle('setup.program_upserted', [
            'program_id' => $program->id,
            'code' => $program->code,
            'is_active' => $program->is_active,
        ], (string) $request->user()->id);

        return back()->with('status', 'Program updated.');
    }
}
