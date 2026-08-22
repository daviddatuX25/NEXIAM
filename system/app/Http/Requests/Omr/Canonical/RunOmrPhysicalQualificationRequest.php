<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use Illuminate\Foundation\Http\FormRequest;

class RunOmrPhysicalQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'printer' => ['required', 'string', 'max:190'],
            'device' => ['required', 'string', 'max:190'],
            'paper' => ['required', 'string', 'max:190'],
            'renderer_version' => ['required', 'string', 'max:120'],
            'measured_marker_center_mm' => ['required', 'array', 'size:2'],
            'measured_marker_center_mm.0' => ['required', 'numeric', 'gt:0'],
            'measured_marker_center_mm.1' => ['required', 'numeric', 'gt:0'],
            'outcomes' => ['required', 'array', 'min:1'],
            'outcomes.*' => ['required', 'string', 'in:pass,fail'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
