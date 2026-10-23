<?php

namespace App\Http\Requests\ResultSheet;

use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewResultSheetTemplateRequest extends FormRequest
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
            'mode' => ['required', Rule::in([ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX])],
            'paper_size' => ['sometimes', Rule::in([ResultSheetTemplate::PAPER_A4, ResultSheetTemplate::PAPER_LEGAL, ResultSheetTemplate::PAPER_LETTER])],
            'orientation' => ['sometimes', Rule::in([ResultSheetTemplate::ORIENTATION_PORTRAIT, ResultSheetTemplate::ORIENTATION_LANDSCAPE])],
            'logical_unit' => ['sometimes', Rule::in([ResultSheetTemplate::LOGICAL_FULL])],
            'content' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:docx,odt', 'max:16384'],
            'builder_json' => ['nullable'],
            'template_id' => ['nullable', 'integer', 'exists:result_sheet_templates,id'],
        ];
    }
}
