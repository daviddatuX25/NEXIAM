<?php

namespace App\Http\Requests;

use App\Models\ConsultationSummary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConsultationSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['super_admin', 'admin', 'counselor']) ?? false;
    }

    public function rules(): array
    {
        return [
            'recommended_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'counselor_comments' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in([
                ConsultationSummary::STATUS_PENDING,
                ConsultationSummary::STATUS_DRAFT,
                ConsultationSummary::STATUS_RELEASED,
            ])],
        ];
    }
}
