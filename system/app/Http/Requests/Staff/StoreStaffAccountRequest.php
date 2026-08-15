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

    /**
     * HR may create permanent staff (role checklist) or support staff
     * (office-derived role). Office actors may only create their office
     * support staff — roles/office are resolved server-side in the ceiling.
     */
    public function rules(): array
    {
        $ceiling = app(StaffRoleCeiling::class);
        $isHr = $ceiling->isFullHr($this->user());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => $isHr
                ? ['string', Rule::in($ceiling->permanentRoleHelpers())]
                : ['string'],
            'account_type' => ['sometimes', 'string', Rule::in(['permanent', 'support'])],
            'office' => ['sometimes', 'string', 'max:64'],
            'program_codes' => ['sometimes', 'array'],
            'program_codes.*' => ['string', 'max:32'],
        ];
    }
}
