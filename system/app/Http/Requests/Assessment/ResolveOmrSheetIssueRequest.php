<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveOmrSheetIssueRequest extends FormRequest
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
            'resolution_code' => ['required', Rule::in(['manual_review', 'abstained'])],
            'resolution_reason' => ['required', 'string', 'max:255'],
        ];
    }
}
