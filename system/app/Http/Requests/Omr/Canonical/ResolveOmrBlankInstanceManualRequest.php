<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveOmrBlankInstanceManualRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'qr_state' => ['required', 'string', Rule::in(['absent', 'undecodable', 'present_invalid'])],
            'display_code' => ['required', 'string', 'max:24'],
            'confirmed' => ['required', 'boolean', 'accepted'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
