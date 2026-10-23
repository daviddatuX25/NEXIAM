<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;

class PublishCommunicationTemplateVersionRequest extends CommunicationTemplateRequest
{
    public function rules(): array
    {
        if ($this->has('preview_receipts.print')) {
            return [...$this->guards(true), 'content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
                'preview_receipts' => ['required', 'array:email,portal,print'],
                'preview_receipts.email' => ['required', 'string'], 'preview_receipts.portal' => ['required', 'string'],
                'preview_receipts.print' => ['sometimes', 'string']];
        }

        if ($this->isReminderMessage()) {
            return [...$this->guards(true), 'content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
                'document_revision_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'preview_receipts' => ['required', 'array:email,portal,attachment'],
                'preview_receipts.attachment' => ['sometimes', 'string', 'max:16384'],
                'preview_receipts.email' => ['required', 'string', 'max:16384'],
                'preview_receipts.portal' => ['required', 'string', 'max:16384']];
        }

        return [...$this->guards(true), 'content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D'],
            'preview_receipts' => ['required', 'array:email,portal,print'],
            'preview_receipts.email' => ['required', 'string'], 'preview_receipts.portal' => ['required', 'string'],
            'preview_receipts.print' => ['sometimes', 'string']];
    }

    private function isReminderMessage(): bool
    {
        return NoticeMessageComposerQuery::supports((string) $this->route('domain'), (string) $this->route('slug'));
    }
}
