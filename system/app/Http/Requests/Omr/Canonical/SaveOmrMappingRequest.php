<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class SaveOmrMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.page_width' => ['required', 'integer', 'min:1'],
            'mapping.page_height' => ['required', 'integer', 'min:1'],
            'mapping.page_count' => ['required', 'integer', 'min:1', 'max:20'],
            'mapping.blocks' => ['required', 'array', 'min:1', 'max:100'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
