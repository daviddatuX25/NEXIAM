<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\StoreProgramQuotaRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\User;
use App\Setup\Actions\UpsertProgramQuota;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Program quota surface. Gated by `program.quota.manage` directly — the
 * Program Head operates within their assigned programs; Guidance TA path sets
 * quotas with notices. Distinct from the registrar setup catalog.
 */
class ProgramQuotaController extends Controller
{
    public function quotas(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
            abort(403);
        }

        /** @var User $actor */
        $actor = $request->user();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->first()
            ?? AcademicCycle::query()->orderByDesc('opens_on')->first();

        $programsQuery = Program::query()->where('is_active', true)->orderBy('code');
        if ($actor->hasRole('program_head')) {
            $codes = $actor->actorScope()->programCodes;
            $programsQuery->whereIn('code', $codes === [] ? ['__none__'] : $codes);
        }

        $programs = $programsQuery->get(['id', 'code', 'name']);

        $quotas = ProgramQuota::query()
            ->with(['program:id,code,name', 'lastMutatedBy:id,name', 'changes' => fn ($q) => $q->limit(8)->with('actor:id,name')])
            ->when($cycle, fn ($q) => $q->where('academic_cycle_id', $cycle->id))
            ->when(
                $actor->hasRole('program_head'),
                fn ($q) => $q->whereIn('program_id', $programs->pluck('id')),
            )
            ->orderBy('program_id')
            ->get();

        return Inertia::render('Setup/Quotas', [
            'page_state' => 'ready',
            'cycle' => $cycle ? [
                'id' => $cycle->id,
                'code' => $cycle->code,
                'name' => $cycle->name,
                'school_year' => $cycle->school_year,
                'semester' => $cycle->semester,
                'staff_label' => $cycle->staffLabel(),
            ] : null,
            'programs' => $programs,
            'quotas' => $quotas->map(fn (ProgramQuota $quota) => [
                'id' => $quota->id,
                'program_id' => $quota->program_id,
                'program_code' => $quota->program?->code,
                'program_name' => $quota->program?->name,
                'slots' => $quota->slots,
                'last_notice' => $quota->last_notice,
                'last_mutated_by' => $quota->lastMutatedBy?->name,
                'updated_at' => $quota->updated_at?->toIso8601String(),
                'changes' => $quota->changes->map(fn ($change) => [
                    'id' => $change->id,
                    'actor' => $change->actor?->name,
                    'from_slots' => $change->from_slots,
                    'to_slots' => $change->to_slots,
                    'notice' => $change->notice,
                    'created_at' => $change->created_at?->toIso8601String(),
                ])->values(),
            ])->values(),
            'capabilities' => [
                'setup' => $authorizer->allows($request->user(), CapabilityCatalog::REGISTRAR_SETUP_MANAGE),
                'assessment' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG),
                'quota' => true,
            ],
            'is_program_head' => $actor->hasRole('program_head'),
        ]);
    }

    public function storeQuota(
        StoreProgramQuotaRequest $request,
        CapabilityAuthorizer $authorizer,
        UpsertProgramQuota $upsert,
    ): RedirectResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::PROGRAM_QUOTA_MANAGE)) {
            abort(403);
        }

        $program = Program::query()->findOrFail($request->validated('program_id'));
        $cycle = AcademicCycle::query()->findOrFail($request->validated('academic_cycle_id'));

        try {
            $upsert->handle(
                actor: $request->user(),
                program: $program,
                cycle: $cycle,
                slots: (int) $request->validated('slots'),
                notice: $request->validated('notice'),
            );
        } catch (RuntimeException $e) {
            abort(403, $e->getMessage());
        }

        return back()->with('status', 'Program quota saved.');
    }
}
