<?php

namespace App\Http\Requests\Setup;

use App\Models\Foundation\IntakeRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListDocumentRequirementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(
            static fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(['search', 'track', 'kind', 'mandatory', 'status', 'page']),
        ));
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'track' => ['nullable', Rule::in(IntakeRequirement::TRACKS)],
            'kind' => ['nullable', Rule::in(IntakeRequirement::KINDS)],
            'mandatory' => ['nullable', Rule::in(['mandatory', 'optional'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
