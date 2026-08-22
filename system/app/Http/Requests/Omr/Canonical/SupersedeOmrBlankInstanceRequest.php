<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class SupersedeOmrBlankInstanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'replacement_instance_id' => ['required', 'integer', 'exists:omr_blank_instances,id'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
