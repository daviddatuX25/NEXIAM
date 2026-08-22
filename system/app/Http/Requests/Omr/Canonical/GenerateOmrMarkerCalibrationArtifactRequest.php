<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class GenerateOmrMarkerCalibrationArtifactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows(
            $this->user(),
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
        );
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'renderer_version' => ['nullable', 'string', 'max:120'],
            'printer' => ['required', 'string', 'max:190'],
            'device' => ['required', 'string', 'max:190'],
            'paper' => ['required', 'string', 'max:190'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
