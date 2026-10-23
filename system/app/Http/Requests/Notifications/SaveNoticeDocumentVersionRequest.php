<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

class SaveNoticeDocumentVersionRequest extends NoticeDocumentRequest
{
    public function rules(): array
    {
        return [...$this->documentGuards(), 'render_sha256' => ['required', 'regex:/\A[a-f0-9]{64}\z/D'],
            'preview_receipt' => ['required', 'string', 'max:8192']];
    }
}
