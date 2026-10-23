<?php

namespace App\Http\Requests\ResultSheet;

use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResultSheetTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in([ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX])],
            'source_kind' => ['nullable', Rule::in([ResultSheetTemplate::SOURCE_BUILDER, ResultSheetTemplate::SOURCE_DOCX, ResultSheetTemplate::SOURCE_LEGACY_HTML])],
            'builder_schema_version' => ['nullable', 'integer', 'min:1'],
            'builder_json' => ['nullable'],
            'compiled_html' => ['nullable', 'string'],
            'compiler_version' => ['nullable', 'string', 'max:64'],
            'lock_version' => ['nullable', 'integer', 'min:1'],
            'draft_id' => ['nullable', 'integer', 'min:1'],
            'paper_size' => ['sometimes', Rule::in([ResultSheetTemplate::PAPER_A4, ResultSheetTemplate::PAPER_LEGAL, ResultSheetTemplate::PAPER_LETTER])],
            'orientation' => ['sometimes', Rule::in([ResultSheetTemplate::ORIENTATION_PORTRAIT, ResultSheetTemplate::ORIENTATION_LANDSCAPE])],
            'logical_unit' => ['sometimes', Rule::in([ResultSheetTemplate::LOGICAL_FULL])],
            'content' => ['nullable', 'string', Rule::requiredIf(fn (): bool => $this->input('mode') === ResultSheetTemplate::MODE_HTML && ! $this->filled('builder_json'))],
            'document' => ['required_if:mode,'.ResultSheetTemplate::MODE_DOCX, 'nullable', 'file', 'mimes:docx,odt', 'max:16384'],
            'watermark_text' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
