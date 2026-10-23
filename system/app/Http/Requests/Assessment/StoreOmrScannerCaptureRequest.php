<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOmrScannerCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpeg,png,webp', 'max:25600'],
            'client_capture_id' => ['required', 'uuid'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
