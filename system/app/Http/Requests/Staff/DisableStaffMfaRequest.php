<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Foundation\Http\FormRequest;

class DisableStaffMfaRequest extends FormRequest
{
    public function authorize(StaffRoleCeiling $ceiling): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        return $ceiling->canManageMfa($actor, $target);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
