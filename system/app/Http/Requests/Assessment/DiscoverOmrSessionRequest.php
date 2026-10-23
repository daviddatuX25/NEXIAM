<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class DiscoverOmrSessionRequest extends FormRequest
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
            'applicant_number' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'pending_capture' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
