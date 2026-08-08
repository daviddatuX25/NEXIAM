<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled(['date', 'start_time', 'end_time']) && ! $this->filled('starts_at')) {
            $this->merge([
                'starts_at' => trim($this->string('date')->toString().' '.$this->string('start_time')->toString()),
                'effective_end_at' => trim($this->string('date')->toString().' '.$this->string('end_time')->toString()),
            ]);
        }
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
            'expected_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
