<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class ReviewOmrPhysicalCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:pass,fail'],
            'capture_id' => ['sometimes', 'integer', 'min:1'],
            'method' => ['sometimes', 'string', 'in:pre_shaded,hand_shaded'],
            'page_no' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
