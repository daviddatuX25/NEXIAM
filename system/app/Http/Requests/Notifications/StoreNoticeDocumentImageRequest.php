<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use Illuminate\Validation\Rules\File;

class StoreNoticeDocumentImageRequest extends NoticeDocumentRequest
{
    public function rules(): array
    {
        return [
            ...$this->documentGuards(),
            'document_draft_id' => ['required', 'integer', 'min:1'],
            'image' => [
                'required',
                'file',
                File::image()
                    ->types(['png', 'jpeg', 'jpg'])
                    ->max('2mb'),
            ],
        ];
    }
}
