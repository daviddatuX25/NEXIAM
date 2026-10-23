<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\OmrTemplateVerificationRun;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Evidence payload for the DERIVED physical qualification gate (35.30).
 *
 * The pass itself is derived from the persisted full-challenge run. Document-bound
 * paper has no marker-center measurement; printer/device provenance remains
 * a separate mandatory gate for either registration mode.
 */
class RunOmrPhysicalQualificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $run = $this->route('run');
        $documentBound = $run instanceof OmrTemplateVerificationRun
            && $run->templateVersion?->registration_mode === OmrRegistrationMode::DocumentBound;

        return [
            'printer' => ['required', 'string', 'max:190'],
            'device' => ['required', 'string', 'max:190'],
            'paper' => ['required', 'string', 'max:190'],
            'renderer_version' => ['required', 'string', 'max:120'],
            'measured_marker_center_mm' => $documentBound ? ['prohibited'] : ['required', 'array', 'size:2'],
            'measured_marker_center_mm.0' => $documentBound ? ['prohibited'] : ['required', 'numeric', 'gt:0'],
            'measured_marker_center_mm.1' => $documentBound ? ['prohibited'] : ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
