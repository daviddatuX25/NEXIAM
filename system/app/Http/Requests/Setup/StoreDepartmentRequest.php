<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('departments', 'code')->where(fn ($q) => $q->where('institution_id', Institution::current()->id)),
            ],
            'name' => ['required', 'string', 'max:128'],
            'dean_user_id' => [
                'nullable',
                'integer',
                Rule::exists('staff_profiles', 'user_id')->where(fn ($query) => $query
                    ->where('institution_id', Institution::current()->id)
                    ->where('status', StaffProfile::STATUS_ACTIVE)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
