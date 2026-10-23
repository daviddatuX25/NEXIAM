<?php

namespace App\Http\Controllers\Setup;

use App\Admission\Queries\ProgramGovernanceDiscussionQuery;
use App\Admission\Support\QuotaReservation;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Communications\Queries\AnnouncementQuery;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkExemplar;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\RegistrarQuotaGovernanceGrant;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProgramHubController extends Controller
{
    public function __construct(
        private readonly AnnouncementQuery $announcements,
        private readonly ProgramGovernanceDiscussionQuery $discussions,
        private readonly KnownHistoricalBenchmarkRecovery $historicalBenchmarks,
    ) {}

    public function show(Request $request, Program $program, CapabilityAuthorizer $authorizer, GovernanceAuthorizer $governance): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $institution = Institution::current();
        abort_unless((int) $program->institution_id === (int) $institution->id, 404);
        $program->load('department:id,code,name');

        $cycles = $this->authorizedCycles($actor, $program, $authorizer, $governance);
        $cycle = $this->selectedCycle($request, $cycles);
        $canManageQuota = $cycle !== null && $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle, true);
        $canReadQuota = $cycle !== null && ($governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle)
            || $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_READ, $program, $cycle));
        $quota = $this->quotaProjection($actor, $program, $cycle, $governance);
        $canManageGrants = $cycle !== null && $authorizer->allowsAny($actor, [
            CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
            CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
        ], ['institution_id' => (int) $institution->id]);
        $grantTable = (new RegistrarQuotaGovernanceGrant)->getTable();
        $registrarGrants = $canManageGrants
            ? RegistrarQuotaGovernanceGrant::query()
                ->with(['recipient:id,name', 'actor:id,name'])
                ->where('institution_id', $institution->id)
                ->where('program_id', $program->id)
                ->where('academic_cycle_id', $cycle->id)
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
        $publicContent = $program->is_active ? $this->announcements->publicProgram((string) $program->code) : [];
        $publicProfile = $publicContent['publicProfile'] ?? null;

        return Inertia::render('Setup/Programs/Hub', [
            'program' => [
                'id' => (int) $program->id,
                'code' => (string) $program->code,
                'name' => (string) $program->name,
                'is_active' => (bool) $program->is_active,
                'is_admission_offered' => (bool) $program->is_admission_offered,
                'department' => $program->department?->only(['id', 'code', 'name']),
                'public_profile' => $publicProfile,
                'short_description' => $publicProfile['short_description'] ?? null,
                'description' => $publicProfile['description'] ?? null,
            ],
            'cycles' => $cycles->map(static fn (AcademicCycle $item): array => [
                'id' => (int) $item->id,
                'code' => (string) $item->code,
                'name' => (string) $item->name,
                'school_year' => $item->school_year,
                'semester' => $item->semester,
                'is_active' => (bool) $item->is_active,
                'staff_label' => $item->staffLabel(),
            ])->values()->all(),
            'selected_cycle_id' => $cycle === null ? null : (int) $cycle->id,
            'quota' => $quota,
            'can_manage_quota' => $canManageQuota,
            'quota_settings_url' => $canReadQuota ? route('setup.quotas', ['program_id' => $program->id, 'cycle_id' => $cycle->id], false) : null,
            'registrar_grants' => $registrarGrants,
            'registrar_candidates' => $registrarCandidates,
            'can_manage_registrar_grants' => $canManageGrants,
            'announcements' => $publicContent['announcements'] ?? [],
            'policies' => $publicContent['programPolicies'] ?? [],
            'benchmarks' => $this->benchmarkProjection($program, $cycle),
        ]);
    }

    /** @return Collection<int, AcademicCycle> */
    private function authorizedCycles(
        User $actor,
        Program $program,
        CapabilityAuthorizer $authorizer,
        GovernanceAuthorizer $governance,
    ): Collection {
        $institutionId = (int) $program->institution_id;
        $programScope = ['institution_id' => $institutionId, 'program' => (string) $program->code];
        $hasProgramAccess = $authorizer->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_READ, $programScope)
            || $authorizer->allows($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW, $programScope)
            || $authorizer->allowsAny($actor, [
                CapabilityCatalog::SETUP_PROGRAMS_MANAGE,
                CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            ], ['institution_id' => $institutionId])
            || $authorizer->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, ['institution_id' => $institutionId, 'office' => 'guidance']);

        $cycles = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->orderByDesc('is_active')
            ->orderByDesc('opens_on')
            ->orderByDesc('id')
            ->get(['id', 'institution_id', 'code', 'name', 'school_year', 'semester', 'is_active', 'opens_on']);

        $cycles = $cycles->filter(fn (AcademicCycle $cycle): bool => ($cycle->is_active && $hasProgramAccess)
            || $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_READ, $program, $cycle)
            || $governance->allows($actor, CapabilityCatalog::PROGRAM_QUOTA_MANAGE, $program, $cycle));

        abort_unless($hasProgramAccess || $cycles->isNotEmpty(), 403);

        return $cycles->values();
    }

    /** @param Collection<int, AcademicCycle> $cycles */
    private function selectedCycle(Request $request, Collection $cycles): ?AcademicCycle
    {
        $selectedValue = $request->query('cycle_id', $request->query('cycle'));
        if ($selectedValue === null) {
            return $cycles->firstWhere('is_active', true) ?? $cycles->first();
        }

        $cycle = $cycles->first(static fn (AcademicCycle $candidate): bool => (string) $candidate->id === (string) $selectedValue
            || $candidate->code === $selectedValue);
        abort_if($cycle === null, 404);

        return $cycle;
    }

    /** @return array<string, mixed> */
    private function quotaProjection(
        User $actor,
        Program $program,
        ?AcademicCycle $cycle,
        GovernanceAuthorizer $governance,
    ): array {
        $quota = $cycle === null ? null : ProgramQuota::query()
            ->with([
                'lastMutatedBy:id,name',
                'changes' => fn ($query) => $query->limit(8)->with('actor:id,name'),
            ])
            ->where('academic_cycle_id', $cycle->id)
            ->where('program_id', $program->id)
            ->first();
        $commitments = $cycle === null
            ? 0
            : QuotaReservation::committedForCycle((int) $cycle->id, (int) $program->id);
        $slots = $quota === null ? null : (int) $quota->slots;
        $canReadDiscussion = $cycle !== null
            && $governance->allows($actor, CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ, $program, $cycle);

        return [
            'id' => $quota?->id,
            'slots' => $slots,
            'last_notice' => $quota?->last_notice,
            'last_mutated_by' => $quota?->lastMutatedBy?->name,
            'updated_at' => $quota?->updated_at?->toIso8601String(),
            'commitments' => $commitments,
            'remaining' => $slots === null ? null : max(0, $slots - $commitments),
            'over_capacity' => $slots === null ? 0 : max(0, $commitments - $slots),
            'changes' => $quota?->changes->map(static fn ($change): array => [
                'id' => (int) $change->id,
                'actor' => $change->actor?->name,
                'from_slots' => $change->from_slots,
                'to_slots' => $change->to_slots,
                'notice' => $change->notice,
                'created_at' => $change->created_at?->toIso8601String(),
            ])->values()->all() ?? [],
            'discussion' => $canReadDiscussion ? $this->discussions->list($actor, $program, $cycle, 'quota') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function benchmarkProjection(Program $program, ?AcademicCycle $cycle): array
    {
        $studioUrl = route('setup.decision-support.index', array_filter([
            'program_id' => (int) $program->id,
            'cycle_id' => $cycle === null ? null : (int) $cycle->id,
        ], static fn (mixed $value): bool => $value !== null));
        $projection = [
            'available' => false,
            'cycle_id' => $cycle === null ? null : (int) $cycle->id,
            'set_id' => null,
            'set_name' => null,
            'version' => null,
            'activated_at' => null,
            'cutoffs' => [],
            'studio_url' => $studioUrl,
        ];

        if ($cycle === null) {
            return $projection;
        }

        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ($activation === null
            || (int) $activation->institution_id !== (int) $program->institution_id
            || (int) $activation->academic_cycle_id !== (int) $cycle->id) {
            return $projection;
        }

        $set = AdmissionBenchmarkSet::query()
            ->where('institution_id', $program->institution_id)
            ->where('status', 'published')
            ->with(['features', 'exemplars.program', 'exemplars.values.feature'])
            ->find($activation->benchmark_set_id);
        if ($set === null
            || ! hash_equals((string) $set->schema_sha256, $set->computedSchemaDigest())
            || ! hash_equals((string) $set->content_sha256, $set->computedContentDigest())
            || $this->historicalBenchmarks->isKnownHistoricalSet($set)) {
            return $projection;
        }

        $cutoff = $set->exemplars->first(static fn (AdmissionBenchmarkExemplar $exemplar): bool => (int) $exemplar->program_id === (int) $program->id
            && (int) $exemplar->tier_level === 3
            && $exemplar->tier_label === 'Cutoff');
        if ($cutoff === null) {
            return $projection;
        }

        $cutoffs = $cutoff->values
            ->filter(static fn ($value): bool => $value->feature?->logical_type === 'numeric')
            ->map(static function ($value): array {
                $definition = $value->feature?->source_definition_json;

                return [
                    'feature_key' => (string) $value->feature->feature_key,
                    'label' => (string) $value->feature->label,
                    'value' => $value->is_missing || $value->numeric_value === null ? null : (float) $value->numeric_value,
                    'is_missing' => (bool) $value->is_missing,
                    'unit' => is_array($definition) && is_string($definition['unit'] ?? null) ? $definition['unit'] : null,
                ];
            })
            ->values()
            ->all();

        return [
            ...$projection,
            'available' => true,
            'set_id' => (int) $set->id,
            'set_name' => (string) $set->name,
            'version' => (int) $set->version,
            'activated_at' => $activation->activated_at?->toIso8601String(),
            'cutoffs' => $cutoffs,
        ];
    }
}
