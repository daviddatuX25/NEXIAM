<?php

namespace App\ResultSheet\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;

final class DeactivateResultSheetTemplate
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(ResultSheetTemplate $template, User $actor): ResultSheetTemplate
    {
        $template->update(['is_active' => false]);

        $this->recordAuditEvent->handle('result_template.deactivated', [
            'template_id' => $template->id,
            'name' => $template->name,
        ], (string) $actor->id);

        return $template->fresh();
    }
}
