<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

class SaveNoticeDocumentDraftRequest extends NoticeDocumentRequest
{
    public function rules(): array
    {
        return [...$this->documentGuards(), 'state' => ['required', 'array:source_kind,document_asset_id,builder_json,paper_size,orientation'], 'state.source_kind' => ['required', 'in:builder,docx'],
            'state.document_asset_id' => ['present', 'nullable', 'uuid'], 'state.builder_json' => ['present', 'nullable', 'array'],
            'state.paper_size' => ['required', 'in:a4,letter,legal'], 'state.orientation' => ['required', 'in:portrait,landscape'],
            'stage' => ['sometimes', 'integer', 'between:1,3']];
    }
}
