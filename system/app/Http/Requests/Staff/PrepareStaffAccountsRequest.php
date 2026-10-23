<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PrepareStaffAccountsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if (is_array($this->input('recipients'))) {
            return [
                'review_token' => ['required', 'string'],
                'recipients' => ['required', 'array', 'min:1', 'max:30'],
                'recipients.*.operation_id' => ['required', 'uuid', 'distinct'],
                'recipients.*.name' => ['required', 'string', 'max:255'],
                'recipients.*.email' => ['nullable', 'email', 'max:255'],
                'recipients.*.office' => ['required', 'string', 'max:32'],
                'recipients.*.roles' => ['required', 'array', 'min:1'],
                'recipients.*.roles.*' => ['required', 'string'],
                'recipients.*.program_codes' => ['sometimes', 'array'],
                'recipients.*.program_codes.*' => ['string'],
                'recipients.*.demo_mode' => ['sometimes', 'boolean'],
            ];
        }

        return [
            'review_token' => ['required', 'string'],
            'mode' => ['sometimes', Rule::in(['create', 'assign_existing', 'prepare_dean'])],
            'operation_id' => ['required', 'uuid'],
            'target_user_id' => ['required_if:mode,assign_existing', 'nullable', 'integer', 'exists:users,id'],
            'department_id' => ['required_if:mode,prepare_dean', 'nullable', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'office' => ['required', 'string', 'max:32'],
            'roles' => ['required_if:mode,create', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct'],
            'program_codes' => $this->input('mode') === 'assign_existing'
                ? ['required', 'array', 'size:1']
                : ['sometimes', 'array'],
            'program_codes.*' => ['string', 'distinct'],
            'demo_mode' => ['sometimes', 'boolean'],
        ];
    }
}
