<?php

namespace App\Setup\Services;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldKey;
use App\Models\Foundation\IntakeFieldPolicy;
use App\Models\Foundation\IntakeRequirementLevel;
use App\Models\User;
use Illuminate\Support\Collection;

final class IntakeFieldPolicyRegistry
{
    /**
     * Get or seed the active field policies for an academic cycle.
     *
     * @return Collection<int, IntakeFieldPolicy>
     */
    public function getOrSeedForCycle(Institution $institution, AcademicCycle $cycle, ?int $version = null): Collection
    {
        $existing = IntakeFieldPolicy::query()
            ->forCycle($institution->id, $cycle->id, $version)
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        // Seed defaults
        $policies = [];
        $sortOrder = 10;

        foreach (IntakeFieldKey::cases() as $fieldKey) {
            $policies[] = IntakeFieldPolicy::query()->create([
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'version' => $version ?? 1,
                'field_key' => $fieldKey,
                'field_group' => $fieldKey->fieldGroup(),
                'label' => $fieldKey->label(),
                'requirement_level' => $fieldKey->defaultLevel(),
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
            $sortOrder += 10;
        }

        return collect($policies);
    }

    /**
     * Increment version and save a new revision.
     *
     * @param  array<string, string>  $updates  [field_key => requirement_level]
     * @return Collection<int, IntakeFieldPolicy>
     */
    public function updateRevision(
        Institution $institution,
        AcademicCycle $cycle,
        array $updates,
        User $actor
    ): Collection {
        $institutionId = $institution->id;
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
            app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, $registrarScope)->allowed
                || app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope)->allowed,
            403,
        );
        $current = $this->getOrSeedForCycle($institution, $cycle);
        $nextVersion = ((int) $current->max('version')) + 1;

        $newPolicies = [];
        foreach ($current as $old) {
            $key = $old->field_key;
            $newLevel = $old->requirement_level;

            // Cannot alter system mandatory floor
            if (! $key->isSystemMandatory() && isset($updates[$key->value])) {
                $requested = IntakeRequirementLevel::tryFrom($updates[$key->value]);
                if ($requested !== null && ! $requested->isMandatoryFloor()) {
                    $newLevel = $requested;
                }
            }

            $newPolicies[] = IntakeFieldPolicy::query()->create([
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'version' => $nextVersion,
                'field_key' => $key,
                'field_group' => $key->fieldGroup(),
                'label' => $key->label(),
                'requirement_level' => $key->isSystemMandatory() ? IntakeRequirementLevel::SystemMandatory : $newLevel,
                'is_active' => $newLevel->isEnabled(),
                'sort_order' => $old->sort_order,
                'last_mutated_by_user_id' => $actor->id,
            ]);
        }

        return collect($newPolicies);
    }

    /**
     * Compile a resolved map of [field_key_string => IntakeRequirementLevel] for quick validation.
     *
     * @return array<string, IntakeRequirementLevel>
     */
    public function compiledRulesForCycle(Institution $institution, AcademicCycle $cycle, ?int $version = null): array
    {
        $policies = $this->getOrSeedForCycle($institution, $cycle, $version);
        $rules = [];

        foreach ($policies as $policy) {
            $rules[$policy->field_key->value] = $policy->requirement_level;
        }

        return $rules;
    }
}
