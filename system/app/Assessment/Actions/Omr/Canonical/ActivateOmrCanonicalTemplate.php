<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ActivateOmrCanonicalTemplate
{
    public function __construct(
        private readonly OmrMarkerProfileService $profiles,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(OmrAnswerSheetTemplateVersion $version, User $actor): OmrAnswerSheetTemplateVersion
    {
        if ($version->status !== OmrAnswerSheetTemplateVersion::STATUS_DRAFT) {
            throw new RuntimeException('activation_gate_missing');
        }
        $manifest = (array) $version->authoring_manifest;
        if (($manifest['physical_qualification']['status'] ?? null) !== 'physical_qualified') {
            throw new RuntimeException('activation_gate_missing');
        }
        $profileId = $manifest['marker_profile']['id'] ?? null;
        $profile = $profileId ? OmrMarkerProfile::query()->find($profileId) : null;
        $this->profiles->assertQualified($profile);
        $bundle = (array) $version->native_bundle_json;
        $pages = (array) (($bundle['template']['pages'] ?? []));
        if ($pages === [] || count($pages) < 1) {
            throw new RuntimeException('marker_contract_missing');
        }
        foreach ($pages as $page) {
            $markers = (array) ($page['markers'] ?? []);
            if (count((array) ($markers['centers'] ?? [])) !== 4 || ($markers['asset_name'] ?? null) === null) {
                throw new RuntimeException('marker_contract_missing');
            }
        }

        return DB::transaction(function () use ($version, $actor): OmrAnswerSheetTemplateVersion {
            $locked = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
            OmrAnswerSheetTemplateVersion::query()
                ->where('template_id', $locked->template_id)
                ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
                ->where('id', '!=', $locked->id)
                ->update(['status' => OmrAnswerSheetTemplateVersion::STATUS_RETIRED, 'updated_at' => now()]);
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
