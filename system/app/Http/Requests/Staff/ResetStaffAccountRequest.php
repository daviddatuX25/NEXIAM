<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class ResetStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['required', 'string', 'max:255'],
            'totp_code' => ['required', 'digits:6'],
            'operation_id' => ['required', 'uuid'],
            'expected_generation' => ['required', 'string', 'max:64'],
        ];
    }
}
