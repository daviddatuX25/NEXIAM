<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TestCommunicationTemplateMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $domain = (string) $this->route('domain');

        return app(CommunicationTemplateScope::class)->canAccessFullSource($this->user(), $domain);
    }

    public function rules(): array
    {
        if (NoticeMessageComposerQuery::supports((string) $this->route('domain'), (string) $this->route('slug'))) {
            return ['base_version' => ['required', 'integer', 'min:1'], 'family_revision' => ['required', 'integer', 'min:1'],
                'lock_version' => ['required', 'integer', 'min:1'], 'draft_id' => ['sometimes', 'uuid'],
                'idempotency_key' => ['required', 'string', 'max:128'],
                'output_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
                'preview_receipt' => ['required', 'string', 'max:16384']];
        }

        return ['base_version' => ['required', 'integer', 'min:1'],
            'family_revision' => ['required', 'integer', 'min:1'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:128'],
            'content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'preview_receipt' => ['required', 'string', 'max:16384']];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), array_keys($this->rules())) as $key) {
                $validator->errors()->add($key, 'This synthetic test does not accept destinations, context or additional fields.');
            }
        }];
    }
}
