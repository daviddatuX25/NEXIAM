<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

final class MarkOmrChallengePrintedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', 'in:pre_shaded,hand_shaded'],
            'challenge_id' => ['required', 'integer', 'min:1'],
            'target_hash' => ['sometimes', 'string', 'size:64'],
            'plan_hash' => ['sometimes', 'string', 'size:64'],
            'expected_vector_hash' => ['sometimes', 'string', 'size:64'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
