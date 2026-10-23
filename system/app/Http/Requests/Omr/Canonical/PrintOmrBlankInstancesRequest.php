<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrBlankInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrintOmrBlankInstancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'copy_policy' => [
                'sometimes',
                'string',
                Rule::in([
                    OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
                    OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
                ]),
            ],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
