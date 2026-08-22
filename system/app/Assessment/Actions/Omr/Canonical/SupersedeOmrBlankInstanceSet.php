<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\User;

final class SupersedeOmrBlankInstanceSet
{
    public function __construct(private readonly OmrBlankInstanceService $instances, private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(OmrBlankInstance $instance, OmrBlankInstance $replacement, User $actor): OmrBlankInstance
    {
        if ((int) $instance->template_version_id !== (int) $replacement->template_version_id) {
            throw new OmrBlankInstanceException('template_mismatch', 'Replacement must use the same template version.');
        }
        $saved = $this->instances->supersede($instance, $replacement);
        $this->recordAuditEvent->handle('omr.blank_instance_set_superseded', [
            'instance_id' => $saved->id,
            'replacement_instance_id' => $replacement->id,
        ], (string) $actor->id);

        return $saved;
    }
}
