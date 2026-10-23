<?php

namespace App\Http\Requests\Setup;

use App\Notifications\Support\CommunicationTemplateScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamNotificationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CommunicationTemplateScope::class)->canAccessFullSource($this->user(), 'examination');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $offsets = config('examination.notification_policy.allowed_offsets_minutes', [60, 120, 1440, 4320]);

        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'publication_email_enabled' => ['required', 'boolean'],
            'reminder_offsets_minutes' => ['required', 'array', 'max:4'],
            'reminder_offsets_minutes.*' => ['required', 'integer', Rule::in($offsets), 'distinct'],
            'reminder_email_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $allowed = [
                    'expected_version',
                    'publication_email_enabled',
                    'reminder_offsets_minutes',
                    'reminder_email_enabled',
                    '_token',
                    '_method',
                ];

                if (array_diff(array_keys($this->all()), $allowed) !== []) {
                    $validator->errors()->add('policy', 'Unsupported Examination notification policy fields were supplied.');
                }
            },
        ];
    }
}
