<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('appointment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time_slot' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'max_slots' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'duration_minutes' => $this->input('duration_minutes', 30),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
