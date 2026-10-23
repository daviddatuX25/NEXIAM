<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;

final class EnsureAttemptRequirementChecklist
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(AdmissionAttempt $attempt, ?User $actor = null): ?AdmissionAttemptRequirementSet
    {
        $currentSet = AdmissionAttemptRequirementSet::query()
            ->where('admission_attempt_id', $attempt->getKey())
            ->where('is_current', true)
            ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->first();

        if ($currentSet !== null) {
            return $currentSet;
        }

        if (AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $attempt->getKey())->exists()) {
            return null;
        }

        $track = $this->boundary->currentTrack($attempt);
        if ($track === null) {
            return null;
        }

        $requirements = IntakeRequirement::query()
            ->where('institution_id', $attempt->institution_id)
            ->active()
            ->forTrack($track)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($requirements->isEmpty()) {
            return null;
        }

        $set = AdmissionAttemptRequirementSet::query()->create([
            'admission_attempt_id' => $attempt->getKey(),
            'applicant_track' => $track,
            'catalog_version' => 1,
            'is_current' => true,
            'initialized_by_user_id' => $actor?->getKey(),
            'initialized_at' => now(),
        ]);

        foreach ($requirements as $requirement) {
            AdmissionAttemptRequirementItem::query()->create([
                'requirement_set_id' => $set->getKey(),
                'intake_requirement_id' => $requirement->getKey(),
                'code' => $requirement->code,
                'label' => $requirement->label,
                'item_kind' => $requirement->item_kind,
                'is_mandatory' => $requirement->is_mandatory,
                'sort_order' => $requirement->sort_order,
            ]);
        }

        $this->recordAuditEvent->handle('admission.requirement_set_initialized', [
            'attempt_id' => (int) $attempt->getKey(),
            'requirement_set_id' => (int) $set->getKey(),
            'applicant_track' => $track,
            'source' => 'ensure_attempt_requirement_checklist',
        ], $actor === null ? 'system' : (string) $actor->getKey());

        return $set->load(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
    }
}
