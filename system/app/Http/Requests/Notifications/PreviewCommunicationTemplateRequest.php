<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;

class PreviewCommunicationTemplateRequest extends CommunicationTemplateRequest
{
    public function rules(): array
    {
        if ($this->has('content_sha256')) {
            return [...$this->guards(true, false), 'view' => ['required', 'in:email,portal,print'],
                'content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']];
        }

        if ($this->isReminderMessage()) {
            return [...$this->guards(true, false), 'view' => ['required', 'in:email,portal'],
                'output_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']];
        }

        return [...$this->guards(true, false), 'view' => ['required', 'in:email,portal,print'],
            'content_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']];
    }

    private function isReminderMessage(): bool
    {
        return NoticeMessageComposerQuery::supports((string) $this->route('domain'), (string) $this->route('slug'));
    }
}
