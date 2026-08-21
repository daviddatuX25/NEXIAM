<?php

namespace App\Http\Requests\Omr\Canonical;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComposeOmrCanonicalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CapabilityAuthorizer::class)->allows($this->user(), CapabilityCatalog::GUIDANCE_SCORING_OMR);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'paper_width_mm' => ['required', 'numeric', 'min:127', 'max:356'],
            'paper_height_mm' => ['required', 'numeric', 'min:127', 'max:356'],
            'content_margin_mm' => ['required', 'array'],
            'content_margin_mm.top' => ['required', 'numeric', 'min:0'],
            'content_margin_mm.right' => ['required', 'numeric', 'min:0'],
            'content_margin_mm.bottom' => ['required', 'numeric', 'min:0'],
            'content_margin_mm.left' => ['required', 'numeric', 'min:0'],
            'printer_safe_margin_mm' => ['nullable', 'array'],
            'marker_inset_mm' => ['nullable', 'array'],
            'parametric' => ['nullable', 'boolean'],
            'alignment' => ['nullable', 'string', Rule::in(OmrPageDimensionMath::ALIGNMENTS)],
            'offset_x_px' => ['nullable', 'integer'],
            'offset_y_px' => ['nullable', 'integer'],
            'source_dpi' => ['nullable', 'numeric', 'min:1', 'max:2400'],
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }
}
