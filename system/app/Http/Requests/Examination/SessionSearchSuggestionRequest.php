<?php

namespace App\Http\Requests\Examination;

use Illuminate\Foundation\Http\FormRequest;

class SessionSearchSuggestionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('q'))) {
            $this->merge(['q' => trim($this->input('q'))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'academic_cycle_id' => ['required', 'integer'],
            'domain_context' => ['required', 'string', 'in:monitoring,grading,scheduling,consultation_scheduling'],
            'tab' => ['nullable', 'string', 'in:active,history', 'prohibited_if:domain_context,scheduling', 'prohibited_if:domain_context,consultation_scheduling'],
            'readiness' => ['nullable', 'string', 'in:ready,not_ready'],
            'status' => ['nullable', 'string', 'in:active,all,repair_required,draft,published,in_progress,completed,cancelled,open,review,finalized'],
            'exam_session_id' => ['nullable', 'integer'],
            'grading_session_id' => ['nullable', 'integer'],
            'lookup_scope' => ['nullable', 'string', 'in:current_session,selected_cycle'],
            'destination_kind' => ['nullable', 'string', 'in:score_entry,roster_focus'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'room_id' => ['nullable', 'integer'],
        ];
    }
}
