<?php

namespace App\Http\Requests\Setup;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\Institution;
use Illuminate\Foundation\Http\FormRequest;

final class SaveApplicationSuggestionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $institution = Institution::current();

        return app(CapabilityAuthorizer::class)->decideFresh($this->user(), CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, [
            'institution_id' => (int) $institution->id,
            'office' => 'registrar',
        ])->allowed;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'category' => ['required', 'string', 'in:schools,shs,programs'],
            'expected_revision' => ['required', 'integer', 'min:0'],
            'entries' => ['present', 'array', 'max:5000'],
            'entries.*' => ['required', 'array:label,aliases'],
            'entries.*.label' => ['required', 'string', 'max:255'],
            'entries.*.aliases' => ['sometimes', 'array', 'max:20'],
            'entries.*.aliases.*' => ['required', 'string', 'max:255'],
        ];
    }
}
