<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramRequest extends FormRequest
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
                Rule::unique('programs', 'code')->where(fn ($q) => $q->where('institution_id', Institution::current()->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(fn ($query) => $query
                    ->where('institution_id', Institution::current()->id)),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
