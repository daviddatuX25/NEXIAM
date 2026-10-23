<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Ai\KnowledgeSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $defaults = [];
        if (! $this->exists('source_class')) {
            $defaults['source_class'] = KnowledgeSource::SOURCE_CLASS_POLICY;
        }
        if (! $this->exists('audience')) {
            $defaults['audience'] = KnowledgeSource::AUDIENCE_STAFF_ONLY;
        }
        foreach (['title', 'key', 'question', 'answer', 'body_text'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $defaults[$field] = trim($this->string($field)->toString());
            }
        }
        if ($defaults !== []) {
            $this->merge($defaults);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'title' => ['required', 'string', 'max:255'],
            'source_class' => ['required', Rule::in([
                KnowledgeSource::SOURCE_CLASS_POLICY,
                KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
            ])],
            'audience' => ['required', Rule::in([
                KnowledgeSource::AUDIENCE_STAFF_ONLY,
                KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
            ])],
            'question' => ['required_if:source_class,'.KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ, 'prohibited_unless:source_class,'.KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ, 'string', 'max:1000'],
            'answer' => ['required_if:source_class,'.KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ, 'prohibited_unless:source_class,'.KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ, 'string', 'min:20', 'max:50000'],
            'body_text' => ['required_if:source_class,'.KnowledgeSource::SOURCE_CLASS_POLICY, 'prohibited_unless:source_class,'.KnowledgeSource::SOURCE_CLASS_POLICY, 'string', 'min:20', 'max:50000'],
        ];
    }
}
