<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Notifications\Templates\Queries\NoticeMessageComposerQuery;

final class StoreNoticeMessageImageRequest extends CommunicationTemplateRequest
{
    public function rules(): array
    {
        abort_unless(NoticeMessageComposerQuery::supports((string) $this->route('domain'), (string) $this->route('slug')), 404);

        return [...$this->guards(true), 'draft_id' => ['required', 'uuid'],
            'image' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:2048']];
    }
}
