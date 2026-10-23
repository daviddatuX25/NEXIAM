<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

class UploadNoticeDocumentRequest extends NoticeDocumentRequest
{
    public function rules(): array
    {
        return [
            ...$this->documentGuards(),
            'document' => [
                'required',
                'file',
                'mimetypes:application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip,application/octet-stream',
                'extensions:docx',
                'max:10240',
            ],
            'document_draft_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
