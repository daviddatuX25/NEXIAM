<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOmrStudioStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'overlay' => ['required', 'array'],
            'overlay.boxes' => ['present', 'array', 'max:100'],
            'overlay.boxes.*.tag' => ['required', 'string', 'max:64'],
            'overlay.boxes.*.scope' => ['required', 'string', 'max:32'],
            'overlay.boxes.*.x' => ['required', 'numeric', 'min:0'],
            'overlay.boxes.*.y' => ['required', 'numeric', 'min:0'],
            'overlay.boxes.*.width' => ['required', 'numeric', 'gt:0'],
            'overlay.boxes.*.height' => ['required', 'numeric', 'gt:0'],
            'overlay.boxes.*.overflow' => ['nullable', 'string', 'max:16'],
            'overlay.boxes.*.font_size' => ['nullable', 'numeric', 'min:1', 'max:120'],
            'overlay.boxes.*.content' => ['nullable', 'string', 'max:1000'],
            'overlay.boxes.*.name' => ['nullable', 'string', 'max:64'],
            'mapping' => ['nullable', 'array'],
            'mapping.page_width' => ['required_with:mapping.blocks', 'nullable', 'integer', 'min:1'],
            'mapping.page_height' => ['required_with:mapping.blocks', 'nullable', 'integer', 'min:1'],
            'mapping.page_count' => ['required_with:mapping.blocks', 'nullable', 'integer', 'min:1', 'max:20'],
            'mapping.blocks' => ['nullable', 'array', 'max:100'],
            'studio' => ['nullable', 'array'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'source_kind' => ['nullable', 'string', Rule::in(OmrTemplateAuthoringDraft::SOURCE_KINDS)],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
