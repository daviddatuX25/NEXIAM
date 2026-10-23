<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class ResetStaffAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'targets' => ['required', 'array', 'min:1', 'max:50'],
            'targets.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'current_password' => ['required', 'string', 'max:255'],
            'totp_code' => ['required', 'digits:6'],
            'operation_ids' => ['required', 'array'],
            'operation_ids.*' => ['required', 'uuid'],
            'expected_generations' => ['required', 'array'],
            'expected_generations.*' => ['required', 'string', 'max:64'],
        ];
    }
}
