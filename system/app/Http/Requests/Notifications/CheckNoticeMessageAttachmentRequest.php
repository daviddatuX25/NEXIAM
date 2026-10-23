<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

final class CheckNoticeMessageAttachmentRequest extends CommunicationTemplateRequest
{
    public function rules(): array
    {
        return [...$this->guards(true, false), 'document_revision_id' => ['present', 'nullable', 'integer', 'min:1'],
            'binding_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/D']];
    }
}
