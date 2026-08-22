<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\User;

final class ResolveOmrBlankInstanceIssue
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    /** @param array<string, mixed> $resolution */
    public function handle(OmrBlankInstance $instance, User $actor, string $issueCode, array $resolution): OmrBlankInstance
    {
        if (! in_array($issueCode, ['duplicate_instance', 'page_already_captured', 'page_missing_from_set', 'tamper_detected', 'template_mismatch', 'superseded_instance'], true)) {
            throw new OmrBlankInstanceException('issue_code_invalid', 'Unknown blank instance issue code.');
        }
        $this->recordAuditEvent->handle('omr.blank_instance_issue_triaged', [
            'instance_id' => $instance->id,
            'issue_code' => $issueCode,
            'resolution' => $resolution,
        ], (string) $actor->id);

        return $instance->fresh('pages');
    }
}
