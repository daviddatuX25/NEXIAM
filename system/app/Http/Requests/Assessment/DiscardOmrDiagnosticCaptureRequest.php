<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

final class DiscardOmrDiagnosticCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:1'],
            'expected_capture_version' => ['required', 'integer', 'min:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
