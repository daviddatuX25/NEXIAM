<?php

namespace App\Testing;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

trait HasPracticeProvenance
{
    public static function bootHasPracticeProvenance(): void
    {
        static::saving(function (Model $root): void {
            if ($root->exists) {
                return;
            }
            $workspace = app(PracticeWorkspace::class);
            $workspace->assertWritable();
            $setId = $workspace->currentId() ?? $root->practice_fixture_set_id;
            if ($root instanceof AdmissionAttempt) {
                $identitySet = ApplicantIdentity::query()->findOrFail($root->applicant_identity_id)->practice_fixture_set_id;
                if ($setId !== null && $identitySet !== $setId) {
                    throw new RuntimeException('Practice admission must use an identity owned by the selected set.');
                }
                $setId = $identitySet;
            }
            if ($setId !== null) {
                $set = PracticeFixtureSet::query()->whereKey($setId)->where('status', 'active')->firstOrFail();
                if (($root->getAttribute('institution_id') !== null && (int) $root->institution_id !== (int) $set->institution_id)
                    || ($root->getAttribute('academic_cycle_id') !== null && (int) $root->academic_cycle_id !== (int) $set->academic_cycle_id)) {
                    throw new RuntimeException('Practice records must stay in the set institution and academic cycle.');
                }
                if ($root->practice_fixture_set_id !== null && $root->practice_fixture_set_id !== $setId) {
                    throw new RuntimeException('Practice ownership does not match the selected set.');
                }
                $root->practice_fixture_set_id = $setId;
            }
        });
        static::saving(function (Model $root): void {
            $workspace = app(PracticeWorkspace::class);
            if ($root->exists && $root->isDirty('practice_fixture_set_id')) {
                throw new RuntimeException('Existing records cannot be adopted by or moved between practice sets.');
            }
            $setId = $root->practice_fixture_set_id;
            if ($root->exists && $workspace->currentId() !== null && $setId !== $workspace->currentId()) {
                throw new RuntimeException('This record is not owned by the selected practice set.');
            }
            if ($setId !== null) {
                $set = PracticeFixtureSet::query()->find($setId);
                if ($set === null || $set->status !== 'active') {
                    throw new RuntimeException('Retired practice records cannot be changed.');
                }
                if (($root->getAttribute('institution_id') !== null && (int) $root->institution_id !== (int) $set->institution_id)
                    || ($root->getAttribute('academic_cycle_id') !== null && (int) $root->academic_cycle_id !== (int) $set->academic_cycle_id)) {
                    throw new RuntimeException('Practice records cannot move outside their institution or academic cycle.');
                }
            }
        });
    }

    public function practiceFixtureSet(): BelongsTo
    {
        return $this->belongsTo(PracticeFixtureSet::class);
    }

    public function scopeForPracticeSet(Builder $query, ?string $setId): Builder
    {
        return PracticePurpose::root($query, $this->getTable(), $setId);
    }

    public function scopeForWorkspace(Builder $query): Builder
    {
        return $this->scopeForPracticeSet($query, app(PracticeWorkspace::class)->currentId());
    }
}
