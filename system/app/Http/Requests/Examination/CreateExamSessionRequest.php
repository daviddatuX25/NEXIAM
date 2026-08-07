<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class CreateExamSessionRequest extends FormRequest
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
            'academic_cycle_id' => ['required', 'integer', 'exists:academic_cycles,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'code' => ['required', 'string', 'max:64'],
            'starts_at' => ['required', 'date'],
            'effective_end_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
