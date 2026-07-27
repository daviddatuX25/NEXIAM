<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'ai_exam_companion_enabled' => ['sometimes', 'boolean'],
            'notify_on_publish' => ['sometimes', 'boolean'],
            'release_mode' => ['sometimes', 'in:online,f2f'],
            'ai_companion_persona' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'allow_direct_assessment' => ['sometimes', 'boolean'],
            'allow_flexible_applications' => ['sometimes', 'boolean'],
            'enable_normalized_scores' => ['sometimes', 'boolean'],
            'admission_slip_enabled' => ['sometimes', 'boolean'],
            'admission_slip_html_template' => ['sometimes', 'nullable', 'string', 'max:65000'],
        ];
    }
}
