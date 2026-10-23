<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:128'],
            'dean_user_id' => [
                'nullable',
                'integer',
                Rule::exists('staff_profiles', 'user_id')->where(fn ($query) => $query
                    ->where('institution_id', Institution::current()->id)
                    ->where('status', StaffProfile::STATUS_ACTIVE)),
            ],
            'office_room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query
                    ->where('institution_id', Institution::current()->id)
                    ->where('is_active', true)),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
