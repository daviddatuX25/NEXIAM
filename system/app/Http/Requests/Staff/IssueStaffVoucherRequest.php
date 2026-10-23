<?php

namespace App\Http\Requests\Staff;

use App\Staffing\Support\StaffVoucherLifetime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssueStaffVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'purpose' => ['sometimes', Rule::in(['activation', 'password_reset', 'mfa_reset'])],
            'mode' => ['sometimes', Rule::in([...StaffVoucherLifetime::ACTIVATION_MODES, ...StaffVoucherLifetime::RECOVERY_MODES])],
            'custom_expiry' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'timezone' => ['nullable', 'timezone'],
            'send_email' => ['sometimes', 'boolean'],
            'confirm_replace' => ['sometimes', 'accepted'],
        ];
    }
}
