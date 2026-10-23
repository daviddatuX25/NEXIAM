<?php

namespace App\Http\Controllers\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\UpdateIntakeFieldPolicyRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirementLevel;
use App\Models\User;
use App\Setup\Services\IntakeFieldPolicyRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IntakeFieldPolicyController extends Controller
{
    use HasSetupCapabilities;

    public function __construct(
        private readonly IntakeFieldPolicyRegistry $registry,
    ) {}

    private function requireRequirementsSetup(CapabilityAuthorizer $authorizer, Request $request): void
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $institutionId = Institution::query()->value('id');
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

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireRequirementsSetup($authorizer, $request);
        $institution = Institution::current();

        $cycleId = $request->query('cycle_id');
        $cycle = $cycleId !== null
            ? AcademicCycle::query()->where('institution_id', $institution->id)->find($cycleId)
            : AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->first();

        if ($cycle === null) {
            $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->latest('id')->first();
        }

        $cycles = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->orderByDesc('id')
            ->get();

        $policies = $cycle !== null
            ? $this->registry->getOrSeedForCycle($institution, $cycle)
            : collect();

        $activeVersion = $policies->first()?->version ?? 1;

        $groupedPolicies = $policies->groupBy('field_group')->map(function ($group) {
            return $group->map(function ($policy) {
                return [
                    'id' => $policy->id,
                    'field_key' => $policy->field_key->value,
                    'label' => $policy->label,
                    'field_group' => $policy->field_group,
                    'requirement_level' => $policy->requirement_level->value,
                    'is_mandatory_floor' => $policy->field_key->isSystemMandatory(),
                    'is_active' => $policy->is_active,
                    'sort_order' => $policy->sort_order,
                ];
            });
        });

        return Inertia::render('Setup/IntakeFields', [
            'page_state' => 'ready',
            'cycles' => $cycles->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->staffLabel(),
                'is_active' => $c->is_active,
                'allow_guidance_all_in_one_intake' => $c->allow_guidance_all_in_one_intake,
            ]),
            'selected_cycle_id' => $cycle?->id,
            'allow_guidance_all_in_one_intake' => $cycle?->allow_guidance_all_in_one_intake ?? false,
            'policy_version' => $activeVersion,
            'grouped_policies' => $groupedPolicies,
            'requirement_levels' => [
                ['value' => IntakeRequirementLevel::InstitutionRequired->value, 'label' => 'Required'],
                ['value' => IntakeRequirementLevel::Optional->value, 'label' => 'Optional'],
                ['value' => IntakeRequirementLevel::Disabled->value, 'label' => 'Disabled'],
            ],
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function update(
        UpdateIntakeFieldPolicyRequest $request,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireRequirementsSetup($authorizer, $request);
        $institution = Institution::current();

        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->findOrFail($request->validated('academic_cycle_id'));

        $rawPolicies = $request->validated('policies');
        $updates = [];
        foreach ($rawPolicies as $item) {
            $updates[$item['field_key']] = $item['requirement_level'];
        }

        $newPolicies = $this->registry->updateRevision(
            $institution,
            $cycle,
            $updates,
            $request->user()
        );

        $newVersion = $newPolicies->first()?->version ?? 1;

        $audit->handle('setup.intake_field_policy_revised', [
            'cycle_id' => $cycle->id,
            'new_version' => $newVersion,
            'fields_updated' => count($updates),
        ], (string) $request->user()->id);

        return back()->with('status', "Application field policy updated to version {$newVersion}.");
    }

    public function toggleGuidanceDelegation(
        Request $request,
        int $cycleId,
        CapabilityAuthorizer $authorizer,
        RecordAuditEvent $audit,
    ): RedirectResponse {
        $this->requireRequirementsSetup($authorizer, $request);
        $validated = $request->validate([
            'allow_guidance_all_in_one_intake' => ['required', 'boolean'],
        ]);
        $institution = Institution::current();

        DB::transaction(function () use ($cycleId, $institution, $validated, $request, $audit): void {
            $cycle = AcademicCycle::query()
                ->where('institution_id', $institution->id)
                ->lockForUpdate()
                ->findOrFail($cycleId);
            $cycle->update([
                'allow_guidance_all_in_one_intake' => $validated['allow_guidance_all_in_one_intake'],
            ]);
            $audit->handle('setup.guidance_intake_delegation_updated', [
                'cycle_id' => $cycle->id,
                'allow_guidance_all_in_one_intake' => $cycle->allow_guidance_all_in_one_intake,
            ], (string) $request->user()->id);
        });

        return redirect()->back()->with('status', 'Guidance application delegation updated.');
    }
}
