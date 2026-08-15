<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgramQuotaRequest extends FormRequest
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
            'program_id' => ['required', 'integer', 'exists:programs,id'],
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'slots' => ['required', 'integer', 'min:0', 'max:100000'],
            'notice' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
