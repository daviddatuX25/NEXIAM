<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreOmrSheetRequest extends FormRequest
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
            'admission_attempt_id' => ['nullable', 'integer', 'exists:admission_attempts,id'],
            'capture_mode' => ['required', 'in:manual,auto'],
            'client_capture_id' => ['required', 'uuid'],
            'page_no' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'image' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:10240'],
        ];
    }
}
