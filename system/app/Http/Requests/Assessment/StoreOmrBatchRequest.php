<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreOmrBatchRequest extends FormRequest
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
            'template_version_id' => ['required', 'integer', 'exists:omr_answer_sheet_template_versions,id'],
            'booklet_version_id' => ['required', 'integer', 'exists:omr_question_booklet_versions,id'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }
}
