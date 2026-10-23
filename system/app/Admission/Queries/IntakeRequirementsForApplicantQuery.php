<?php

namespace App\Admission\Queries;

use App\Models\Foundation\IntakeRequirement;
use InvalidArgumentException;

class IntakeRequirementsForApplicantQuery
{
    /**
     * @return list<array{label: string, item_kind: string, is_mandatory: bool}>
     */
    public function forApplicantType(int $institutionId, string $applicantType): array
    {
        $track = match ($applicantType) {
            'new' => IntakeRequirement::TRACK_FRESHMAN,
            'transferee' => IntakeRequirement::TRACK_TRANSFEREE,
            default => throw new InvalidArgumentException('Unsupported public applicant type.'),
        };

        return IntakeRequirement::query()
            ->where('institution_id', $institutionId)
            ->active()
            ->forTrack($track)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['label', 'item_kind', 'is_mandatory'])
            ->map(static fn (IntakeRequirement $requirement): array => [
                'label' => (string) $requirement->label,
                'item_kind' => (string) $requirement->item_kind,
                'is_mandatory' => (bool) $requirement->is_mandatory,
            ])
            ->all();
    }
}
