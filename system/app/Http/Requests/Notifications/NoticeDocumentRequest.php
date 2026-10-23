<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class NoticeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $domain = (string) $this->route('domain');
        $slug = (string) $this->route('slug');
        $family = CommunicationTemplateRegistry::findBySlug($domain, $slug);
        abort_unless($family !== null && CommunicationTemplateRegistry::supportsPdf($domain, $slug, $family['event_key']), 404);
        app(CommunicationTemplateScope::class)->authorizeFullSource($this->user(), $domain);

        return true;
    }

    protected function documentGuards(bool $mutation = true): array
    {
        return [
            'base_version' => ['required', 'integer', 'min:0'],
            'document_revision' => ['required', 'integer', 'min:1'],
            'lock_version' => ['present', 'nullable', 'integer', 'min:1'],
            ...($mutation ? ['idempotency_key' => ['required', 'string', 'max:128']] : []),
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = array_unique(array_map(static fn (string $key): string => explode('.', $key)[0], array_keys($this->rules())));
            foreach (array_diff(array_keys($this->all()), $allowed) as $key) {
                $validator->errors()->add($key, 'This field is not supported by this document operation.');
            }
        }];
    }
}
