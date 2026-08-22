<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class RegisterOmrBlankInstanceCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'qr_payload' => ['nullable', 'string', 'max:300'],
            'template_version_id' => ['required', 'integer', 'exists:omr_answer_sheet_template_versions,id'],
            'page_no' => ['required', 'integer', 'min:1'],
            'capture_page_id' => ['required', 'integer', 'exists:omr_capture_sheet_pages,id'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
