<?php

namespace App\Application\Capabilities\Publication;

/**
 * Code-owned active publication pointer (OpenSpec 2+4).
 */
final class ActiveRulePublication
{
    public function current(): RulePublication
    {
        return new RulePublication(
            version: 'openspec8.v1',
            compatibleDigests: [
                'setup.readiness.catalog@1' => 'sha256:setup-readiness-catalog-v1',
                'knowledge.sources.catalog@1' => 'sha256:knowledge-sources-catalog-v1',
                'knowledge.lifecycle@1' => 'sha256:knowledge-lifecycle-v1',
                'exams.sessions.catalog@1' => 'sha256:exams-sessions-catalog-v1',
                'exams.slots.catalog@1' => 'sha256:exams-slots-catalog-v1',
                'exams.slot.reserve@1' => 'sha256:exams-slot-reserve-v1',
                'exams.session.reservation@1' => 'sha256:exams-session-reservation-v1',
                'exams.session.assign@1' => 'sha256:exams-session-assign-v1',
                'exams.session.publish@1' => 'sha256:exams-session-publish-v1',
            ],
        );
    }
}
