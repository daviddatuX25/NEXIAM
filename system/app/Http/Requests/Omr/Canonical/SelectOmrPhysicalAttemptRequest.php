<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

final class SelectOmrPhysicalAttemptRequest extends FormRequest
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
            'page_no' => ['required', 'integer', 'min:1', 'max:20'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:512'],
            'target_hash' => ['nullable', 'string', 'size:64'],
            'plan_hash' => ['nullable', 'string', 'size:64'],
            'expected_vector_hash' => ['nullable', 'string', 'size:64'],
            'page_version' => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
