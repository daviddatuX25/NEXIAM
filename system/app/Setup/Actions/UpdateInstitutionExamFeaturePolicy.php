<?php

namespace App\Setup\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateInstitutionExamFeaturePolicy
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function updateSeatingAvailability(User $actor, bool $enabled, int $expectedVersion): Institution
    {
        return $this->update(
            $actor,
            setting: 'enable_seating_arrangements',
            feature: 'assigned_seating',
            capability: CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            enabled: $enabled,
            expectedVersion: $expectedVersion,
        );
    }

    public function updateAlternateBookletSetsAvailability(User $actor, bool $enabled, int $expectedVersion): Institution
    {
        return $this->update(
            $actor,
            setting: 'enable_multi_set_booklets',
            feature: 'alternate_booklet_sets',
            capability: CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
            enabled: $enabled,
            expectedVersion: $expectedVersion,
        );
    }

    private function update(
        User $actor,
        string $setting,
        string $feature,
        string $capability,
        bool $enabled,
        int $expectedVersion,
    ): Institution {
        $institution = Institution::current();

        return DB::transaction(function () use ($actor, $institution, $setting, $feature, $capability, $enabled, $expectedVersion): Institution {
            $lockedInstitution = Institution::query()->lockForUpdate()->findOrFail($institution->id);
            $decision = $this->authorizer->decideFresh($actor, $capability, [
                'institution_id' => (int) $lockedInstitution->id,
            ]);
            if (! $decision->allowed) {
                throw new AuthorizationException('Current institution feature settings authority is required.');
            }

            if ($lockedInstitution->examFeaturePolicyVersion() !== $expectedVersion) {
                throw ValidationException::withMessages([
                    'policy' => 'Exam feature availability changed. Reload and review the settings before saving.',
                ]);
            }

            $before = (bool) $lockedInstitution->getAttribute($setting);
            if ($before === $enabled) {
                return $lockedInstitution;
            }

            $nextVersion = $expectedVersion + 1;
            $lockedInstitution->forceFill([
                $setting => $enabled,
                'exam_feature_policy_version' => $nextVersion,
            ])->save();

            $this->audit->handle('setup.exam_feature_policy_updated', [
                'institution_id' => (int) $lockedInstitution->id,
                'feature' => $feature,
                'before' => $before,
                'after' => $enabled,
                'revision' => $nextVersion,
                'request_id' => (string) Str::uuid(),
            ], (string) $actor->id);

            return $lockedInstitution->fresh();
        });
    }
}
