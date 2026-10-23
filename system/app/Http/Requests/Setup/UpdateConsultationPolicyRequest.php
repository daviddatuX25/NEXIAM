<?php

declare(strict_types=1);

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateConsultationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'academic_cycle_id' => ['required', 'integer', 'min:1'],
            'normal_method' => ['required', Rule::in(['online', 'f2f'])],
            'allow_online_exceptions' => ['required', 'boolean'],
            'expected_version' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $allowed = [
                    'academic_cycle_id',
                    'normal_method',
                    'allow_online_exceptions',
                    'expected_version',
                    '_token',
                    '_method',
                ];

                if (array_diff(array_keys($this->all()), $allowed) !== []) {
                    $validator->errors()->add('policy', 'Unsupported consultation policy fields were supplied.');
                }
            },
        ];
    }
}
