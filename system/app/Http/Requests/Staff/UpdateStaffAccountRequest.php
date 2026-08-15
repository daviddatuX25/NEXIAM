<?php

namespace App\Http\Requests\Staff;

use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null
            && app(StaffRoleCeiling::class)->canManagePersonnel($actor);
    }

    public function rules(): array
    {
        $ceiling = app(StaffRoleCeiling::class);
        $isHr = $ceiling->isFullHr($this->user());
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => $isHr
                ? ['string', Rule::in($ceiling->permanentRoleHelpers())]
                : ['string'],
            'office' => ['sometimes', 'string', 'max:64'],
            'program_codes' => ['sometimes', 'array'],
            'program_codes.*' => ['string', 'max:32'],
        ];
    }
}
