<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class CreateOmrPhysicalChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:quick,full'],
            // Optional only for historical links; new callers must send one
            // of the two explicit method identifiers.
            'method' => ['sometimes', 'string', 'in:pre_shaded,hand_shaded'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
