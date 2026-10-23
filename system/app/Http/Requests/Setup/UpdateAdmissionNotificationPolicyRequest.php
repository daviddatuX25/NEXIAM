<?php

namespace App\Http\Requests\Setup;

use App\Notifications\Support\CommunicationTemplateScope;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdmissionNotificationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CommunicationTemplateScope::class)->canAccessFullSource($this->user(), 'admissions');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'applicant_status_email_enabled' => ['required', 'boolean'],
            'registrar_new_submission_alert_enabled' => ['required', 'boolean'],
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
                    'applicant_status_email_enabled',
                    'registrar_new_submission_alert_enabled',
                    '_token',
                    '_method',
                ];

                if (array_diff(array_keys($this->all()), $allowed) !== []) {
                    $validator->errors()->add('policy', 'Unsupported Admissions notification policy fields were supplied.');
                }
            },
        ];
    }
}
