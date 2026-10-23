<?php

declare(strict_types=1);

namespace App\Assessment\Support;

use App\Models\Assessment\AssessmentResultSet;
use App\Models\Examination\AssessmentAdministration;
use RuntimeException;

final class IndividualResultMutationFence
{
    public function isFrozen(AssessmentAdministration $administration): bool
    {
        return $administration->binding_state === AssessmentAdministration::STATE_FINALIZED
            || AssessmentResultSet::query()
                ->where('administration_id', $administration->id)
                ->where('status', AssessmentResultSet::STATUS_FINALIZED)
                ->exists();
    }

    public function assertMutable(AssessmentAdministration $administration): void
    {
        if ($this->isFrozen($administration)) {
            throw new RuntimeException('assessment_post_finalization_correction_blocked');
        }
    }
}
