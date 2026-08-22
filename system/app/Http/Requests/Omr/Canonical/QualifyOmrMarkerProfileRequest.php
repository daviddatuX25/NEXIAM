<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class QualifyOmrMarkerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'evidence_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'asset_sha256' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'renderer_version' => ['required', 'string', 'max:120'],
            'printer' => ['required', 'string', 'max:190'],
            'device' => ['required', 'string', 'max:190'],
            'paper' => ['required', 'string', 'max:190'],
            'size_mm' => ['required', 'numeric', 'gt:0'],
            'quiet_zone_mm' => ['required', 'numeric', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
