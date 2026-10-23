<?php

namespace App\Http\Requests\ResultSheet;

use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyzeResultSheetTemplateRequest extends FormRequest
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
            'mode' => ['sometimes', Rule::in([ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX])],
            'content' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:docx,odt', 'max:16384'],
            'logical_unit' => ['sometimes', Rule::in([ResultSheetTemplate::LOGICAL_FULL])],
        ];
    }
}
