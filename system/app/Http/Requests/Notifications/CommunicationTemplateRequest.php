<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Support\CommunicationTemplateScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class CommunicationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(CommunicationTemplateScope::class)->authorizeFullSource($this->user(), (string) $this->route('domain'));

        return true;
    }

    protected function guards(bool $requiresDraft = false, bool $mutation = true): array
    {
        return [
            'base_version' => ['required', 'integer', 'min:1'], 'family_revision' => ['required', 'integer', 'min:1'],
            'lock_version' => $requiresDraft ? ['required', 'integer', 'min:1'] : ['present', 'nullable', 'integer', 'min:1'],
            ...($mutation ? ['idempotency_key' => ['required', 'string', 'max:128']] : []),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($this->rules())));
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'This field is not supported by this notice operation.');
            }
        }];
    }
}
