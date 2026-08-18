<?php

namespace App\ResultSheet\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivateResultSheetTemplate
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ResultSheetTemplate $template, User $actor): ResultSheetTemplate
    {
        return DB::transaction(function () use ($template, $actor): ResultSheetTemplate {
            ResultSheetTemplate::query()->where('is_active', true)->update(['is_active' => false]);

            $template->update(['is_active' => true]);

            $this->recordAuditEvent->handle('result_template.activated', [
                'template_id' => $template->id,
                'name' => $template->name,
            ], (string) $actor->id);

            return $template->fresh();
        });
    }
}
