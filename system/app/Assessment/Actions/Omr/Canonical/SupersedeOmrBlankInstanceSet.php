<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceException;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class SupersedeOmrBlankInstanceSet
{
    public function __construct(
        private readonly OmrBlankInstanceService $instances,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function handle(OmrBlankInstance $instance, OmrBlankInstance $replacement, User $actor): OmrBlankInstance
    {
        $this->assertActorScope($instance, $actor);
        if ((int) $instance->template_version_id !== (int) $replacement->template_version_id) {
            throw new OmrBlankInstanceException('template_mismatch', 'Replacement must use the same template version.');
        }
        $this->assertActorScope($replacement, $actor);

        $saved = $this->instances->supersede($instance, $replacement);
        $this->recordAuditEvent->handle('omr.blank_instance_set_superseded', [
            'instance_id' => $saved->id,
            'replacement_instance_id' => $replacement->id,
        ], (string) $actor->id);

        return $saved;
    }

    private function assertActorScope(OmrBlankInstance $instance, User $actor): void
    {
        $institutionId = (int) $instance->institution_id;
        $cycle = AcademicCycle::query()
            ->whereKey((int) $instance->academic_cycle_id)
            ->where('institution_id', $institutionId)
            ->first();

        if ($institutionId < 1 || $cycle === null
            || ! $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
                'office' => 'guidance',
                'institution_id' => $institutionId,
                'cycle' => (string) $cycle->code,
            ])->allowed) {
            throw new AuthorizationException;
        }
    }
}
