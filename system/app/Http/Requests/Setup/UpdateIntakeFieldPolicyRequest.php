<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeFieldKey;
use App\Models\Foundation\IntakeRequirementLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIntakeFieldPolicyRequest extends FormRequest
{
    public function authorize(CapabilityAuthorizer $authorizer): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        $institutionId = Institution::query()->value('id');
        $actorScope = $user->actorScope();
        $scope = [];
        if ($actorScope->institutionId !== null && $institutionId !== null) {
            $scope['institution_id'] = (int) $institutionId;
        }

        $registrarScope = $scope;
        if ($actorScope->office !== null) {
            $registrarScope['office'] = 'registrar';
        }

        return $authorizer->allows($user, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, $registrarScope)
            || $authorizer->allows($user, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, $scope);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validKeys = array_map(fn (IntakeFieldKey $k) => $k->value, IntakeFieldKey::cases());
        $allowedLevels = [
            IntakeRequirementLevel::InstitutionRequired->value,
            IntakeRequirementLevel::Optional->value,
            IntakeRequirementLevel::Disabled->value,
        ];

        return [
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'policies' => ['required', 'array'],
            'policies.*.field_key' => ['required', 'string', Rule::in($validKeys)],
            'policies.*.requirement_level' => ['required', 'string', Rule::in($allowedLevels)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $policies = $this->input('policies', []);
            if (! is_array($policies)) {
                return;
            }

            foreach ($policies as $index => $item) {
                $keyStr = $item['field_key'] ?? null;
                $levelStr = $item['requirement_level'] ?? null;

                if ($keyStr !== null) {
                    $key = IntakeFieldKey::tryFrom($keyStr);
                    if ($key?->isSystemMandatory() && $levelStr !== IntakeRequirementLevel::SystemMandatory->value) {
                        $validator->errors()->add(
                            "policies.{$index}.requirement_level",
                            "The field '{$key->label()}' is system-mandatory and cannot be modified."
                        );
                    }
                }
            }
        });
    }
}
