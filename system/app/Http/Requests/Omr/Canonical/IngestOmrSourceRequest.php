<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestOmrSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows(
            $this->user(),
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source_kind' => ['required', 'string', Rule::in(OmrTemplateAuthoringDraft::SOURCE_KINDS)],
            'source' => ['required', 'file', 'max:25600'],
            'idempotency_key' => ['required', 'string', 'max:190'],
            'width_mm' => ['nullable', 'numeric', 'min:127', 'max:356'],
            'height_mm' => ['nullable', 'numeric', 'min:127', 'max:356'],
            'authoring_photo' => ['nullable', 'boolean'],
            'rectification_confirmed' => ['nullable', 'boolean'],
            'source_dpi' => ['nullable', 'numeric', 'min:1', 'max:2400'],
        ];
    }
}
