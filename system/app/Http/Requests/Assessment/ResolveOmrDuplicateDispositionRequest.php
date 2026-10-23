<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class ResolveOmrDuplicateDispositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rejected_sheet_id' => ['required', 'integer', 'exists:omr_capture_sheets,id'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
            'survivor_version' => ['required', 'integer', 'min:0'],
            'rejected_version' => ['required', 'integer', 'min:0'],
        ];
    }
}
