<?php

namespace App\Http\Requests\ResultSheet;

use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResultSheetTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'mode' => ['sometimes', Rule::in([ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX])],
            'paper_size' => ['sometimes', Rule::in([ResultSheetTemplate::PAPER_A4, ResultSheetTemplate::PAPER_LEGAL, ResultSheetTemplate::PAPER_LETTER])],
            'orientation' => ['sometimes', Rule::in([ResultSheetTemplate::ORIENTATION_PORTRAIT, ResultSheetTemplate::ORIENTATION_LANDSCAPE])],
            'logical_unit' => ['sometimes', Rule::in([ResultSheetTemplate::LOGICAL_FULL, ResultSheetTemplate::LOGICAL_HALF_A4, ResultSheetTemplate::LOGICAL_HALF_LEGAL, ResultSheetTemplate::LOGICAL_HALF_LETTER])],
            'content' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:docx,odt', 'max:5120'],
            'watermark_text' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
