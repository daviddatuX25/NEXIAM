<?php

namespace App\Http\Requests\ResultSheet;

use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResultSheetTemplateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', 'array'],
            'state.name' => ['nullable', 'string', 'max:255'],
            'state.mode' => ['required', Rule::in([ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX])],
            'state.source_kind' => ['nullable', Rule::in([ResultSheetTemplate::SOURCE_BUILDER, ResultSheetTemplate::SOURCE_DOCX, ResultSheetTemplate::SOURCE_LEGACY_HTML])],
            'state.paper_size' => ['nullable', Rule::in([ResultSheetTemplate::PAPER_A4, ResultSheetTemplate::PAPER_LEGAL, ResultSheetTemplate::PAPER_LETTER])],
            'state.orientation' => ['nullable', Rule::in([ResultSheetTemplate::ORIENTATION_PORTRAIT, ResultSheetTemplate::ORIENTATION_LANDSCAPE])],
            'state.logical_unit' => ['nullable', Rule::in([ResultSheetTemplate::LOGICAL_FULL])],
            'state.content' => ['nullable', 'string'],
            'state.builder_json' => ['nullable', 'array'],
            'state.builder_schema_version' => ['nullable', 'integer', 'min:1'],
            'state.compiler_version' => ['nullable', 'string', 'max:64'],
            'state.watermark_text' => ['nullable', 'string', 'max:50'],
            'state.is_active' => ['nullable', 'boolean'],
            'stage' => ['required', 'integer', 'between:1,3'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ];
    }
}
