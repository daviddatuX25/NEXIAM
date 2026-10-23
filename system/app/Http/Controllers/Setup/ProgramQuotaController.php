<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\Admission\Support\QuotaReservation;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\BulkStoreProgramQuotaRequest;
use App\Http\Requests\Setup\StoreProgramQuotaRequest;
use App\Models\Admission\RegistrarQuotaGovernanceGrant;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Actions\QuotaCommandConflict;
use App\Setup\Actions\UpsertProgramQuota;
use App\Setup\Actions\UpsertProgramQuotas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scoped quota read projection and the existing quota mutation adapter.
 */
class ProgramQuotaController extends Controller
{
    public function quotas(Request $request, GovernanceAuthorizer $governance, ProgramGovernanceDiscussionQuery $discussions, CapabilityAuthorizer $capabilityAuthorizer): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $institution = Institution::current();
        $allPrograms = Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
        $cycles = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->orderByDesc('is_active')
            ->orderByDesc('opens_on')
            ->get(['id', 'code', 'name', 'school_year', 'semester', 'is_active'])
            ->filter(function (AcademicCycle $candidate) use ($actor, $allPrograms, $governance): bool {
                foreach ($allPrograms as $program) {
                    if ($governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $candidate)
                        || $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_READ, $program, $candidate)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
        if ($cycles->isEmpty() && AcademicCycle::query()->where('institution_id', $institution->id)->exists()) {
            abort(403);
        }
        $selectedValue = $request->query('cycle_id', $request->query('cycle'));
        $cycle = $selectedValue === null
            ? $cycles->firstWhere('is_active', true)
            : $cycles->first(static fn (AcademicCycle $candidate): bool => (string) $candidate->id === (string) $selectedValue || $candidate->code === $selectedValue);
        if ($selectedValue !== null && $cycle === null) {
            abort(404);
        }
        $programs = $cycle === null ? collect() : $allPrograms
            ->filter(fn (Program $program): bool => $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle)
                || $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_READ, $program, $cycle))
            ->values();
        $selectedProgram = null;
        if ($request->query->has('program_id')) {
            $selectedProgram = $programs->firstWhere('id', $request->query('program_id'));
            abort_if($selectedProgram === null, 404);
            $programs = collect([$selectedProgram]);
            $cycles = $cycles->filter(fn (AcademicCycle $candidate): bool => $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $selectedProgram, $candidate)
                || $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_READ, $selectedProgram, $candidate))->values();
        }
        $programIds = $programs->pluck('id')->all();
        $quotas = $cycle === null || $programIds === []
            ? collect()
            : ProgramQuota::query()
                ->with(['program:id,code,name', 'lastMutatedBy:id,name', 'changes' => fn ($query) => $query->limit(8)->with('actor:id,name')])
                ->where('academic_cycle_id', $cycle->id)
                ->whereIn('program_id', $programIds)
                ->orderBy('program_id')
                ->get();
        $committedByProgram = $cycle === null || $programIds === []
            ? []
            : QuotaReservation::batchCommittedForCycle((int) $cycle->id, $programIds);
        $writeEnabled = (bool) config('admission.quota_writes_enabled', false);
        $projections = $programs->map(function (Program $program) use ($cycle, $quotas, $committedByProgram, $governance, $actor, $discussions, $writeEnabled): array {
            $quota = $quotas->firstWhere('program_id', $program->id);
            $committed = (int) ($committedByProgram[$program->id] ?? 0);
            $capacity = $quota === null ? null : (int) $quota->slots;
            $canManage = $writeEnabled && $cycle !== null && $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, true);
            $canReadDiscussion = $cycle !== null && $governance->allows($actor, CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ, $program, $cycle);

            return [
                'program' => ['id' => $program->id, 'code' => $program->code, 'name' => $program->name],
                'quota' => $quota === null ? null : [
                    'id' => $quota->id,
                    'slots' => $capacity,
                    'version' => (int) $quota->version,
                    'last_notice' => $quota->last_notice,
                    'last_mutated_by' => $quota->lastMutatedBy?->name,
                    'updated_at' => $quota->updated_at?->toIso8601String(),
                    'changes' => $quota->changes->map(static fn ($change): array => [
                        'id' => $change->id,
                        'actor' => $change->actor?->name,
                        'from_slots' => $change->from_slots,
                        'to_slots' => $change->to_slots,
                        'reason' => $change->reason ?? $change->notice,
                        'version' => (int) $change->version,
                        'created_at' => $change->created_at?->toIso8601String(),
                    ])->values()->all(),
                ],
                'commitments' => $committed,
                'remaining' => $capacity === null ? null : max(0, $capacity - $committed),
                'over_capacity' => $capacity === null ? 0 : max(0, $committed - $capacity),
                'can_manage' => $canManage,
                'write_enabled' => $writeEnabled,
                'hub_url' => route('setup.programs.hub', ['program' => $program->id, 'cycle_id' => $cycle->id, 'tab' => 'quotas'], false),
                'discussion' => $canReadDiscussion ? $discussions->list($actor, $program, $cycle, 'quota') : null,
            ];
        })->all();
        $canManageGrants = $cycle !== null && $capabilityAuthorizer->allowsAny($actor, [
            CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
            CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
        ], ['institution_id' => (int) $institution->id]);
        $grantTable = (new RegistrarQuotaGovernanceGrant)->getTable();
        $registrarGrants = $canManageGrants && $programIds !== []
            ? RegistrarQuotaGovernanceGrant::query()
                ->with(['recipient:id,name', 'actor:id,name'])
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->whereIn('program_id', $programIds)
                ->whereNotExists(function ($query) use ($grantTable): void {
                    $query->selectRaw('1')
                        ->from($grantTable.' as newer')
                        ->whereColumn('newer.recipient_user_id', $grantTable.'.recipient_user_id')
                        ->whereColumn('newer.program_id', $grantTable.'.program_id')
                        ->whereColumn('newer.academic_cycle_id', $grantTable.'.academic_cycle_id')
                        ->whereColumn('newer.version', '>', $grantTable.'.version');
                })
                ->get()
                ->map(static fn (RegistrarQuotaGovernanceGrant $grant): array => [
                    'id' => $grant->id,
                    'recipient_id' => $grant->recipient_user_id,
                    'recipient_name' => $grant->recipient?->name,
                    'program_id' => $grant->program_id,
                    'cycle_id' => $grant->academic_cycle_id,
                    'version' => $grant->version,
                    'status' => $grant->status,
                    'reason' => $grant->reason,
                    'actor_name' => $grant->actor?->name,
                ])->all()
            : [];
        $registrarCandidates = $canManageGrants
            ? StaffProfile::query()
                ->with('user:id,name')
                ->where('institution_id', $institution->id)
                ->where('office', 'registrar')
                ->where(fn ($query) => $query->where('status', StaffProfile::STATUS_ACTIVE)->orWhereNull('status'))
                ->whereHas('user')
                ->orderBy('user_id')
                ->get()
                ->map(static fn (StaffProfile $profile): array => ['id' => $profile->user_id, 'name' => $profile->user?->name])
                ->all()
            : [];

        $canManageAny = collect($projections)->contains(static fn (array $projection): bool => $projection['can_manage']);
        $canReadAny = $projections !== [];

        return Inertia::render('Setup/Quotas', [
            'page_state' => $cycle === null ? 'no-cycle' : ($projections === [] ? 'no-authorized-programs' : 'ready'),
            'cycles' => $cycles->map(static fn (AcademicCycle $item): array => [...$item->only(['id', 'code', 'name', 'is_active']), 'staff_label' => $item->staffLabel()])->all(),
            'cycle' => $cycle === null ? null : [...$cycle->only(['id', 'code', 'name', 'school_year', 'semester', 'is_active']), 'staff_label' => $cycle->staffLabel()],
            'selected_program' => $selectedProgram?->only(['id', 'code', 'name']),
            'program_hub_url' => $selectedProgram === null ? null : route('setup.programs.hub', ['program' => $selectedProgram->id, 'cycle_id' => $cycle->id, 'tab' => 'quotas'], false),
            'programs' => $programs->map(static fn (Program $item): array => $item->only(['id', 'code', 'name']))->all(),
            'quota_projections' => $projections,
            'registrar_grants' => $registrarGrants,
            'registrar_candidates' => $registrarCandidates,
            'can_manage_registrar_grants' => $canManageGrants,
            'quotas' => $quotas->map(static fn (ProgramQuota $quota): array => [
                'id' => $quota->id,
                'program_id' => $quota->program_id,
                'program_code' => $quota->program?->code,
                'program_name' => $quota->program?->name,
                'slots' => $quota->slots,
                'version' => (int) $quota->version,
                'last_notice' => $quota->last_notice,
                'last_mutated_by' => $quota->lastMutatedBy?->name,
                'updated_at' => $quota->updated_at?->toIso8601String(),
                'changes' => $quota->changes->map(static fn ($change): array => [
                    'id' => $change->id,
                    'actor' => $change->actor?->name,
                    'from_slots' => $change->from_slots,
                    'to_slots' => $change->to_slots,
                    'reason' => $change->reason ?? $change->notice,
                    'version' => (int) $change->version,
                    'created_at' => $change->created_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
            'capabilities' => ['quota' => $canManageAny, 'quota_read' => $canReadAny],
            'is_program_head' => $actor->actorScope()->office === 'program',
        ]);
    }

    public function storeQuota(
        StoreProgramQuotaRequest $request,
        UpsertProgramQuota $upsert,
    ): RedirectResponse|JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $program = Program::query()->findOrFail($request->validated('program_id'));
        $cycle = AcademicCycle::query()->findOrFail($request->validated('academic_cycle_id'));

        try {
            $change = $upsert->handle(
                actor: $actor,
                program: $program,
                cycle: $cycle,
                slots: (int) $request->validated('slots'),
                reason: $request->validated('reason'),
                expectedVersion: (int) $request->validated('expected_version'),
                idempotencyKey: $request->validated('idempotency_key'),
            );
        } catch (QuotaCommandConflict $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'quota_version_conflict',
                'current' => $e->current,
            ], 409);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $change->program_quota_id,
                'program_id' => $program->getKey(),
                'academic_cycle_id' => $cycle->getKey(),
                'slots' => $change->to_slots,
                'version' => $change->version,
            ], 'receipt' => [
                'change_id' => $change->getKey(),
                'version' => $change->version,
                'from_slots' => $change->from_slots,
                'to_slots' => $change->to_slots,
                'reason' => $change->reason,
                'actor_user_id' => $change->actor_user_id,
                'created_at' => $change->created_at,
            ]]);
        }

        return back()->with('status', 'Program quota saved.');
    }

    public function bulkStore(BulkStoreProgramQuotaRequest $request, UpsertProgramQuotas $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $cycle = AcademicCycle::query()->findOrFail($validated['academic_cycle_id']);

        try {
            $changes = $action->handle($actor, $cycle, $validated['quotas'], $validated['idempotency_key']);
        } catch (QuotaCommandConflict $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'quota_version_conflict',
                'current' => $e->current,
            ], 409);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => array_map(static fn ($change): array => [
                'id' => $change->program_quota_id,
                'program_id' => $change->programQuota->program_id,
                'academic_cycle_id' => $change->programQuota->academic_cycle_id,
                'slots' => $change->to_slots,
                'version' => $change->version,
                'change_id' => $change->getKey(),
            ], $changes)]);
        }

        return back()->with('status', 'Quotas saved for the selected programs.');
    }
}
