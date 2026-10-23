<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class AdoptOmrProductionCaptureSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'pending_capture_id' => ['required', 'uuid'],
            'pending_capture_revision' => ['required', 'integer', 'min:1'],
            'template_version_id' => ['prohibited'],
            'issuance_id' => ['prohibited'],
            'applicant_number' => ['prohibited'],
            'admission_attempt_id' => ['prohibited'],
            'page_no' => ['prohibited'],
            'copy_policy' => ['prohibited'],
        ];
    }
}
