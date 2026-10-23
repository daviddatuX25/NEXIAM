<?php

namespace App\Testing\Queries;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class InstitutionalOmrBookletQuery
{
    public function hasActiveFinalizedBooklet(): bool
    {
        return $this->query()->exists();
    }

    public function getActiveFinalizedBooklet(): ?OmrQuestionBookletVersion
    {
        $versions = $this->eligible();

        return $versions->count() === 1 ? $versions->first() : null;
    }

    public function eligible(bool $lock = false): Collection
    {
        $query = $this->query()->orderByDesc('finalized_at')->orderByDesc('id');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    /**
     * @return Builder<OmrQuestionBookletVersion>
     */
    private function query(): Builder
    {
        return OmrQuestionBookletVersion::query()
            ->whereNotNull('finalized_at')
            ->where('status', OmrQuestionBookletVersion::STATUS_ACTIVE)
            ->whereHas('templateVersion', fn (Builder $query) => $query
                ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
                ->whereHas('template', fn (Builder $query) => $query->where('is_synthetic', false)));
    }
}
