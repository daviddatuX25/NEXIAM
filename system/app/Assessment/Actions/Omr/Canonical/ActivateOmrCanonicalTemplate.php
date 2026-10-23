<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function hash_equals;
use function in_array;
use function is_numeric;

final class ActivateOmrCanonicalTemplate
{
    public function __construct(
        private readonly OmrMarkerProfileService $profiles,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrAnswerSheetTemplateVersion $version,
        User $actor,
        bool $confirmActivation = false,
    ): OmrAnswerSheetTemplateVersion {
        if (! in_array($version->status, [
            OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
            OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
        ], true)) {
            throw new RuntimeException('activation_gate_missing');
        }
        $manifest = (array) $version->authoring_manifest;
        $qualification = (array) ($manifest['physical_qualification'] ?? []);

        // If not explicitly confirmed by authorized staff, enforce qualification checks
        if (! $confirmActivation && ($qualification['status'] ?? null) !== 'physical_qualified') {
            throw new RuntimeException('activation_gate_missing');
        }

        $target = new CompiledVersionDiagnosticTarget($version);
        if (! $target->qualificationCapable()) {
            throw new RuntimeException('activation_registration_mode_unsupported');
        }

        if (! $confirmActivation) {
            if (($qualification['registration_mode'] ?? null) !== $target->registrationMode()->value
                || ($qualification['qr_mode'] ?? null) !== $target->qrMode()->value
                || ($qualification['compiled_pages'] ?? null) !== $target->compiledPages()) {
                throw new RuntimeException('activation_policy_evidence_mismatch');
            }
        }

        // Execute atomic activation
        return DB::transaction(function () use ($version, $actor, $confirmActivation, $target): OmrAnswerSheetTemplateVersion {
            $locked = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
            if (! in_array($locked->status, [
                OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                OmrAnswerSheetTemplateVersion::STATUS_COMPILED,
            ], true)) {
                throw new RuntimeException('activation_gate_missing');
            }

            if (! $confirmActivation) {
                if ($target->registrationMode() === OmrRegistrationMode::FiducialAnchored) {
                    $profileSnapshot = is_array($locked->authoring_manifest['marker_profile'] ?? null)
                        ? $locked->authoring_manifest['marker_profile']
                        : [];
                    $profileId = $profileSnapshot['id'] ?? null;
                    $profile = is_numeric($profileId)
                        ? OmrMarkerProfile::query()->whereKey((int) $profileId)->lockForUpdate()->first()
                        : null;
                    $qualifiedProfile = $this->profiles->assertQualified($profile);
                    if ((int) ($profileSnapshot['id'] ?? 0) !== (int) $qualifiedProfile->id
                        || (string) ($profileSnapshot['code'] ?? '') !== (string) $qualifiedProfile->code
                        || (int) ($profileSnapshot['version'] ?? 0) !== (int) $qualifiedProfile->version
                        || ! hash_equals((string) ($profileSnapshot['asset_sha256'] ?? ''), (string) $qualifiedProfile->asset_sha256)) {
                        throw new RuntimeException('activation_marker_profile_mismatch');
                    }
                }

                throw new RuntimeException('physical_provenance_unavailable: Activation is blocked until genuine printer and device evidence can be verified.');
            }
            // The partial unique index enforces one active version globally, not per template.
            OmrAnswerSheetTemplateVersion::query()
                ->whereKeyNot($locked->id)
                ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
                ->update([
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_RETIRED,
                    'updated_at' => now(),
                ]);

            $locked->forceFill([
                'status' => OmrAnswerSheetTemplateVersion::STATUS_ACTIVE,
                'activated_by_user_id' => $actor->id,
                'activated_at' => now(),
            ])->save();

            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.template_activated', [
                'template_version_id' => $saved->id,
                'previous_active_versions_retired' => true,
                'native_bundle_hash' => $saved->native_bundle_hash,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
