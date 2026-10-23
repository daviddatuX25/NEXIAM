<?php

namespace App\Http\Requests\Staff;

use App\Staffing\Support\StaffVoucherLifetime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssueStaffVoucherBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'operation_id' => ['required', 'uuid'],
            'targets' => ['required', 'array', 'min:1', 'max:100'],
            'targets.*.id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'purpose' => ['sometimes', Rule::in(['activation', 'password_reset', 'mfa_reset'])],
            'mode' => ['sometimes', 'string', Rule::in([...StaffVoucherLifetime::ACTIVATION_MODES, ...StaffVoucherLifetime::RECOVERY_MODES])],
            'custom_expiry' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'timezone' => ['nullable', 'timezone'],
            'replace' => ['sometimes', 'boolean'],
            'send_email' => ['sometimes', 'boolean'],
        ];
    }
}
