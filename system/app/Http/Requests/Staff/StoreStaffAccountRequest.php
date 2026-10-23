<?php

namespace App\Http\Requests\Staff;

use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null
            && app(StaffRoleCeiling::class)->canManagePersonnel($actor);
    }

    protected function prepareForValidation(): void
    {
        $ceiling = app(StaffRoleCeiling::class);
        $office = $ceiling->resolveCreateOffice($this->user(), $this->input('office'));

        if ($office !== 'program') {
            $this->merge(['program_codes' => []]);
        }
    }

    /**
     * HR may create permanent staff (role checklist) or support staff
     * (office-derived role). Office actors may only create their office
     * support staff — roles/office are resolved server-side in the ceiling.
     */
    public function rules(): array
    {
        $ceiling = app(StaffRoleCeiling::class);
        $isHr = $ceiling->isFullHr($this->user());
        $office = $ceiling->resolveCreateOffice($this->user(), $this->input('office'));
        $institutionId = (int) ($this->user()?->actorScope()->institutionId ?? 0);
        $programCodeRule = Rule::exists('programs', 'code')
            ->where('institution_id', $institutionId)
            ->where('is_active', true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'sometimes', 'email', 'max:255', 'unique:users,email'],
            'password' => ['prohibited'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => $isHr
                ? ['string', Rule::in($ceiling->permanentRoleHelpers())]
                : ['string'],
            'account_type' => ['sometimes', 'string', Rule::in(['permanent', 'support'])],
            'office' => ['sometimes', 'string', Rule::in(['admin', 'registrar', 'guidance', 'program'])],
            'program_codes' => ['sometimes', 'array'],
            'program_codes.*' => $office === 'program'
                ? ['string', 'max:32', $programCodeRule]
                : ['string', 'max:32', Rule::in([])],
        ];
    }
}
