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

    protected function prepareForValidation(): void
    {
        /** @var User $target */
        $target = $this->route('user');
        $ceiling = app(StaffRoleCeiling::class);
        $office = $ceiling->resolveUpdateOffice($this->user(), $this->input('office'), $target->staffProfile?->office);

        if ($office !== 'program') {
            $this->merge(['program_codes' => []]);
        }
    }

    public function rules(): array
    {
        $ceiling = app(StaffRoleCeiling::class);
        $isHr = $ceiling->isFullHr($this->user());
        /** @var User $target */
        $target = $this->route('user');
        $target->loadMissing(['roles', 'authenticationState', 'staffProfile']);
        $isSupport = $target->roles->contains(fn ($role): bool => $ceiling->isSupportRole($role->name));
        $emailMayRemainPending = $target->email === null && ! $target->authenticationState?->activation_completed_at;
        $office = $ceiling->resolveUpdateOffice($this->user(), $this->input('office'), $target->staffProfile?->office);
        $institutionId = (int) ($target->staffProfile?->institution_id ?? 0);
        $programCodeRule = Rule::exists('programs', 'code')
            ->where('institution_id', $institutionId)
            ->where('is_active', true);

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [Rule::requiredIf(! $emailMayRemainPending), 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'roles' => ['sometimes', 'array'],
            'roles.*' => $isHr && ! $isSupport
                ? ['string', Rule::in($ceiling->permanentRoleHelpers())]
                : ['string'],
            'office' => ['sometimes', 'string', $isSupport
                ? Rule::in(['registrar', 'guidance'])
                : Rule::in(['admin', 'registrar', 'guidance', 'program'])],
            'program_codes' => ['sometimes', 'array'],
            'program_codes.*' => $office === 'program'
                ? ['string', 'max:32', $programCodeRule]
                : ['string', 'max:32', Rule::in([])],
        ];
    }
}
