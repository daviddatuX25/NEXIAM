<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

class DiscardNoticeDocumentDraftRequest extends NoticeDocumentRequest
{
    public function rules(): array
    {
        return $this->documentGuards();
    }
}
