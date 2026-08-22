<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\User;

final class VoidOmrBlankInstanceSet
{
    public function __construct(private readonly OmrBlankInstanceService $instances, private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(OmrBlankInstance $instance, User $actor): OmrBlankInstance
    {
        $saved = $this->instances->void($instance);
        $this->recordAuditEvent->handle('omr.blank_instance_set_voided', ['instance_id' => $saved->id], (string) $actor->id);

        return $saved;
    }
}
