<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class VoidOmrBlankInstanceSet
{
    public function __construct(
        private readonly OmrBlankInstanceService $instances,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    public function handle(OmrBlankInstance $instance, User $actor): OmrBlankInstance
    {
        $this->assertActorScope($instance, $actor);
        $saved = $this->instances->void($instance);
        $this->recordAuditEvent->handle('omr.blank_instance_set_voided', ['instance_id' => $saved->id], (string) $actor->id);

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
