<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use App\Staffing\Support\StaffVoucherLifetime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class BatchProvisionStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && app(StaffRoleCeiling::class)->canManagePersonnel($actor);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'destination_cycle_code' => ['required', 'string', 'max:64'],
            'voucher_mode' => ['required', 'string', Rule::in(array_values(array_diff(StaffVoucherLifetime::ACTIVATION_MODES, ['custom'])))],
            'operation_id' => ['required', 'uuid'],
            'recipients' => ['required', 'array', 'min:1', 'max:50'],
            'recipients.*' => ['required', 'array:operation_id,name,office,roles,program_codes'],
            'recipients.*.operation_id' => ['required', 'uuid', 'distinct'],
            'recipients.*.name' => ['required', 'string', 'min:2', 'max:100'],
            'recipients.*.office' => ['required', 'string', 'in:admin,registrar,guidance,program'],
            'recipients.*.roles' => ['required', 'array', 'min:1'],
            'recipients.*.roles.*' => ['required', 'string', Rule::in(CapabilityCatalog::personnelRoleHelpers())],
            'recipients.*.program_codes' => ['nullable', 'array'],
            'recipients.*.program_codes.*' => ['required', 'string', 'max:32'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $actor = $this->user();
                if (! $actor instanceof User) {
                    return;
                }

                $institutionId = (int) ($actor->staffProfile?->institution_id ?? 0);
                if ($institutionId < 1) {
                    return;
                }

                $cycleCode = $this->input('destination_cycle_code');
                if (! is_string($cycleCode) || trim($cycleCode) !== $cycleCode) {
                    $validator->errors()->add('destination_cycle_code', 'Choose a valid destination cycle in your institution.');
                } elseif (! AcademicCycle::query()->where('institution_id', $institutionId)->where('code', $cycleCode)->exists()) {
                    $validator->errors()->add('destination_cycle_code', 'Choose a destination cycle in your institution.');
                }

                $roleOffices = [
                    'institutional_admin' => 'admin',
                    'hr_administrator' => 'admin',
                    'registrar_admin' => 'registrar',
                    'registrar_clerk' => 'registrar',
                    'registrar_support_staff' => 'registrar',
                    'guidance_counselor' => 'guidance',
                    'guidance_clerk' => 'guidance',
                    'guidance_support_staff' => 'guidance',
                    'program_head' => 'program',
                ];
                $ceiling = app(StaffRoleCeiling::class);

                foreach ($this->input('recipients', []) as $index => $recipient) {
                    if (! is_array($recipient)) {
                        continue;
                    }

                    $name = is_string($recipient['name'] ?? null) ? trim($recipient['name']) : '';
                    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                        $validator->errors()->add("recipients.{$index}.name", 'Enter a staff name between 2 and 100 characters.');
                    }

                    $requestedOffice = (string) ($recipient['office'] ?? '');
                    $office = $ceiling->resolveCreateOffice($actor, $requestedOffice);
                    if ($office !== $requestedOffice) {
                        $validator->errors()->add("recipients.{$index}.office", 'Personnel must be created in an office within your authority.');
                    }

                    $requestedRoles = (array) ($recipient['roles'] ?? []);
                    $roles = $ceiling->resolveCreateRoles($actor, ['roles' => $requestedRoles], $office);
                    if (! $ceiling->isFullHr($actor) && $roles !== $requestedRoles) {
                        $validator->errors()->add("recipients.{$index}.roles", 'This role is not available for personnel creation in your office.');
                    }

                    foreach ($roles as $role) {
                        if (($roleOffices[$role] ?? null) !== $office) {
                            $validator->errors()->add("recipients.{$index}.roles", 'Choose role helpers that belong to the selected office.');
                            break;
                        }
                    }

                    $programCodes = array_values((array) ($recipient['program_codes'] ?? []));
                    $isProgramHead = in_array('program_head', $roles, true);
                    if (($isProgramHead && ($office !== 'program' || $programCodes === []))
                        || (! $isProgramHead && $programCodes !== [])) {
                        $validator->errors()->add("recipients.{$index}.program_codes", 'Only a Program Head may receive active program assignments, and at least one is required.');
                    }

                    if ($programCodes !== []) {
                        $validCodes = Program::query()
                            ->where('institution_id', $institutionId)
                            ->where('is_active', true)
                            ->whereHas('department', fn ($query) => $query
                                ->where('institution_id', $institutionId)
                                ->where('is_active', true))
                            ->whereIn('code', $programCodes)
                            ->pluck('code')
                            ->all();

                        if (count($validCodes) !== count($programCodes)) {
                            $validator->errors()->add("recipients.{$index}.program_codes", 'Select only active programs in your institution.');
                        }
                    }
                }
            },
        ];
    }
}
