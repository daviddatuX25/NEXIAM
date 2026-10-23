<?php

namespace App\Http\Requests\Staff;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class ApplicantLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->decideFresh($this->user(), CapabilityCatalog::APPLICANT_QR_RESOLVE)->allowed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:2048'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'academic_cycle_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'program_code' => ['sometimes', 'nullable', 'string', 'max:64'],
            'exam_session_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'consultation_session_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
