<?php

namespace App\Http\Requests;

use App\Models\AdmissionSlipTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdmissionSlipTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'registrar_administrator']) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in([AdmissionSlipTemplate::MODE_HTML, AdmissionSlipTemplate::MODE_DOCX])],
            'paper_size' => ['sometimes', Rule::in([
                AdmissionSlipTemplate::PAPER_A4,
                AdmissionSlipTemplate::PAPER_LEGAL,
                AdmissionSlipTemplate::PAPER_LETTER,
            ])],
            'orientation' => ['sometimes', Rule::in([
                AdmissionSlipTemplate::ORIENTATION_PORTRAIT,
                AdmissionSlipTemplate::ORIENTATION_LANDSCAPE,
            ])],
            'logical_unit' => ['sometimes', Rule::in([
                AdmissionSlipTemplate::LOGICAL_FULL,
                AdmissionSlipTemplate::LOGICAL_HALF_A4,
                AdmissionSlipTemplate::LOGICAL_HALF_LEGAL,
                AdmissionSlipTemplate::LOGICAL_HALF_LETTER,
            ])],
            'content' => ['required_if:mode,'.AdmissionSlipTemplate::MODE_HTML, 'nullable', 'string'],
            'docx' => ['required_if:mode,'.AdmissionSlipTemplate::MODE_DOCX, 'nullable', 'file', 'mimes:docx', 'max:5120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
