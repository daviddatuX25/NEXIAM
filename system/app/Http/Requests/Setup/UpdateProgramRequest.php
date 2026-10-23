<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('institution_id', Institution::current()->id)),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
