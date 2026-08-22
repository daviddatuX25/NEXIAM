<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class SelectOmrMarkerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'marker_profile_id' => ['required', 'integer', 'exists:omr_marker_profiles,id'],
            'insets' => ['nullable', 'array'],
            'insets.top' => ['nullable', 'numeric'],
            'insets.right' => ['nullable', 'numeric'],
            'insets.bottom' => ['nullable', 'numeric'],
            'insets.left' => ['nullable', 'numeric'],
            'lock_version' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
