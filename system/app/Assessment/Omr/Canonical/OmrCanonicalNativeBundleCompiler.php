<?php

namespace App\Assessment\Omr\Canonical;

use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\NativeManifestDeriver;
use App\Assessment\Omr\OmrIdentityField;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use function count;
use function hash;
use function is_array;
use function json_encode;
use function ksort;
use function str_starts_with;

final class OmrCanonicalNativeBundleCompiler
{
    public function __construct(
        private readonly NativeManifestDeriver $manifestDeriver,
        private readonly OmrTemplateGeometryProjector $geometryProjector,
        private readonly MaterialOwnerAccess $materialOwnerAccess,
    ) {}

    /**
     * @param  array<string, mixed>  $authoringManifest
     * @return array{version:OmrAnswerSheetTemplateVersion,bundle:NativeBundle,relative_dir:string}
     */
    public function compile(OmrTemplateAuthoringDraft $draft, ?OmrMarkerProfile $profile, array $authoringManifest, ?User $actor = null): array
    {
        $actor ??= $draft->ownerUser ?? User::query()->find($draft->owner_user_id);
        $authorizedInstitutionId = $actor === null
            ? (int) $draft->institution_id
            : (int) ($actor->staffProfile?->institution_id ?? $draft->institution_id);
        if ($draft->exists === false || $draft->getKey() === null) {
            throw new OmrCanonicalCompileException('compile_owner_missing', 'Compilation requires a persisted authoring draft.');
        }
        $draft = OmrTemplateAuthoringDraft::query()->with('assets')->findOrFail($draft->getKey());
        $institutionId = (int) ($draft->institution_id ?? 0);
        $isSynthetic = $draft->is_synthetic;
        if ($institutionId < 1
            || $institutionId !== $authorizedInstitutionId
            || ($actor !== null && (int) $draft->owner_user_id !== (int) $actor->getKey())
            || ! Institution::query()->whereKey($institutionId)->exists()
            || ! is_bool($isSynthetic)) {
            throw new OmrCanonicalCompileException('compile_owner_missing', 'A persisted owned draft, institution, and explicit provenance are required before compilation.');
        }
        if ($draft->test_instrument_version_id !== null
            && ! TestInstrumentVersion::query()
                ->whereKey($draft->test_instrument_version_id)
                ->whereHas('instrument', static fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->where('is_synthetic', $isSynthetic))
                ->exists()) {
            throw new OmrCanonicalCompileException('compile_provenance_mismatch', 'The selected instrument profile does not share the draft institution and provenance.');
        }
        $authoring = (array) $draft->authoring_json;
        $artifactSet = (array) ($authoring['canonical']['artifact_set'] ?? []);
        if ($artifactSet === []) {
            throw new OmrCanonicalCompileException('compile_gate_missing', 'Materialize and digitally verify a candidate before compile.');
        }

        $templateModel = OmrAnswerSheetTemplate::query()
            ->where('institution_id', $institutionId)
            ->where('code', $draft->code)
            ->first();
        if ($templateModel === null) {
            $templateModel = OmrAnswerSheetTemplate::query()->create([
                'institution_id' => $institutionId,
                'code' => $draft->code,
                'name' => $draft->name,
                'is_synthetic' => $isSynthetic,
            ]);
        }
        if ((bool) $templateModel->is_synthetic !== $isSynthetic
            || ($draft->template_id !== null && (int) $draft->template_id !== (int) $templateModel->id)) {
            throw new OmrCanonicalCompileException('compile_provenance_mismatch', 'The linked Answer Sheet family has different ownership or immutable provenance.');
        }

        return DB::transaction(function () use ($draft, $profile, $authoringManifest, $templateModel, $artifactSet): array {
            $lockedTemplate = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail($templateModel->id);
            if ((int) $lockedTemplate->institution_id !== (int) $draft->institution_id
                || (bool) $lockedTemplate->is_synthetic !== (bool) $draft->is_synthetic
                || $lockedTemplate->code !== $draft->code) {
                throw new OmrCanonicalCompileException('compile_provenance_mismatch', 'The locked Answer Sheet family no longer matches draft ownership.');
            }
            $nextVersion = ((int) $lockedTemplate->versions()->max('version')) + 1;
            if ((int) ($artifactSet['candidate_version'] ?? 0) !== $nextVersion) {
                throw new OmrCanonicalCompileException('compile_candidate_stale', 'The materialized candidate is stale; return to mapping and rematerialize.');
            }

            return $this->promoteMaterializedCandidate($draft, $profile, $authoringManifest, $lockedTemplate, $nextVersion, $artifactSet);
        });
    }

    /**
     * Promote a previously materialized candidate without regenerating any
     * template, config, marker, reference, or PDF geometry.
     *
     * @param  array<string,mixed>  $authoringManifest
     * @param  array<string,mixed>  $artifactSet
     * @return array{version:OmrAnswerSheetTemplateVersion,bundle:NativeBundle,relative_dir:string}
     */
    private function promoteMaterializedCandidate(
        OmrTemplateAuthoringDraft $draft,
        ?OmrMarkerProfile $profile,
        array $authoringManifest,
        OmrAnswerSheetTemplate $templateModel,
        int $nextVersion,
        array $artifactSet,
    ): array {
        $expectedPages = array_map(
            static fn (int $pageNo): array => ['page_no' => $pageNo],
            range(1, (int) $draft->requested_page_count),
        );
        foreach ([$artifactSet, (array) ($artifactSet['template'] ?? []), (array) ($artifactSet['manifest'] ?? []), (array) ($artifactSet['review_manifest'] ?? []), $authoringManifest] as $representation) {
            if (($representation['registration_mode'] ?? null) !== $draft->registration_mode->value
                || ($representation['qr_mode'] ?? null) !== $draft->qr_mode->value
                || ($representation['compiled_pages'] ?? null) !== $expectedPages) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', 'Candidate registration, QR, or ordered page policy differs from the draft.');
            }
        }
        if (($profile !== null) !== ($draft->registration_mode === OmrRegistrationMode::FiducialAnchored)) {
            throw new OmrCanonicalCompileException('compile_candidate_invalid', 'Selected marker profile differs from the registration policy.');
        }
        $references = array_values((array) ($artifactSet['reference_assets'] ?? []));
        if (array_map(
            static fn (mixed $reference): array => ['page_no' => is_array($reference) ? (int) ($reference['page_no'] ?? 0) : 0],
            $references,
        ) !== $expectedPages
            || ($draft->registration_mode === OmrRegistrationMode::DocumentBound
                && (int) ($artifactSet['marker_asset_id'] ?? 0) > 0)) {
            throw new OmrCanonicalCompileException('compile_candidate_invalid', 'Candidate page references or marker assets differ from the paper policy.');
        }
        $assetIds = [
            'template.json' => (int) ($artifactSet['native_template_asset_id'] ?? 0),
            'config.json' => (int) ($artifactSet['native_config_asset_id'] ?? 0),
            'manifest.json' => (int) ($artifactSet['native_manifest_asset_id'] ?? 0),
            ...($profile !== null ? [basename((string) $profile->asset_path) => (int) ($artifactSet['marker_asset_id'] ?? 0)] : []),
        ];
        foreach ((array) ($artifactSet['reference_assets'] ?? []) as $reference) {
            if (is_array($reference) && isset($reference['page_no'], $reference['asset_id'])) {
                $assetIds['reference-page-'.((int) $reference['page_no']).'.svg'] = (int) $reference['asset_id'];
            }
        }
        foreach ($assetIds as $name => $assetId) {
            if ($assetId <= 0) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', "Materialized candidate is missing {$name}.");
            }
        }

        $files = [];
        foreach ($assetIds as $name => $assetId) {
            $asset = $draft->assets->firstWhere('id', $assetId);
            if (! $asset instanceof OmrTemplateAsset) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', "Materialized candidate asset {$name} is not owned by the draft.");
            }
            $bytes = Storage::disk($asset->disk)->get($asset->path);
            if (! is_string($bytes) || hash('sha256', $bytes) !== $asset->sha256) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', "Materialized candidate asset {$name} failed integrity validation.");
            }
            $files[$name] = $bytes;
        }

        $relativeDir = 'omr-bundles/institutions/'.$draft->institution_id.'/templates/'.$templateModel->id.'/v'.$nextVersion;
        $disk = Storage::disk('local');
        $disk->deleteDirectory($relativeDir);
        foreach ($files as $name => $bytes) {
            $disk->put($relativeDir.'/'.$name, $bytes);
        }
        try {
            $bundle = NativeBundle::loadFromDirectory($disk->path($relativeDir), $draft->code, $nextVersion);
            $derived = $this->manifestDeriver->derive($bundle);
            $qrReservation = $artifactSet['review_manifest']['qr_reservation'] ?? null;
            if ($qrReservation !== null) {
                if ($draft->qr_mode->value !== 'qr_signed' || ! is_array($qrReservation)) {
                    throw new OmrCanonicalCompileException('compile_candidate_invalid', 'QR reservation differs from the verified paper policy.');
                }
                $derived['manifest']['qr_reservation'] = $qrReservation;
                $derived['hash'] = hash('sha256', json_encode($derived['manifest'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            }
            if (($bundle->manifest['registration_mode'] ?? null) !== $draft->registration_mode->value
                || ($bundle->manifest['qr_mode'] ?? null) !== $draft->qr_mode->value
                || ($bundle->manifest['compiled_pages'] ?? null) !== $expectedPages
                || ($bundle->template['compiled_pages'] ?? null) !== $expectedPages
                || ($bundle->manifest['canonical_definition_hash'] ?? null) !== ($artifactSet['definition_hash'] ?? null)
                || ($bundle->fileHashes['template.json'] ?? null) !== ($artifactSet['template_hash'] ?? null)
                || ($bundle->fileHashes['config.json'] ?? null) !== ($artifactSet['config_hash'] ?? null)
                || ($derived['hash'] ?? null) !== ($artifactSet['review_manifest_hash'] ?? null)) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', 'Native candidate bytes differ from the verified paper policy or artifact hashes.');
            }
            $rawDocumentFields = $authoringManifest['studio']['document_fields']
                ?? $authoringManifest['mapping']['input']['document_fields']
                ?? $authoringManifest['canonical']['mapping']['document_fields']
                ?? $draft->authoring_json['studio']['document_fields']
                ?? $draft->authoring_json['mapping']['input']['document_fields']
                ?? $draft->authoring_json['canonical']['mapping']['document_fields']
                ?? $draft->authoring_json['mapping']['document_fields']
                ?? [];
            $paperWidth = (float) ($authoringManifest['paper']['width_mm'] ?? 210.0);
            $paperHeight = (float) ($authoringManifest['paper']['height_mm'] ?? 297.0);
            $markerInsets = (array) (
                $authoringManifest['marker']['insets_mm']
                ?? $authoringManifest['insets_mm']
                ?? $authoringManifest['canonical']['settings']['marker_inset_mm']
                ?? $authoringManifest['settings']['marker_inset_mm']
                ?? $draft->authoring_json['marker']['insets_mm']
                ?? $draft->authoring_json['canonical']['settings']['marker_inset_mm']
                ?? ['left' => 12.0, 'top' => 12.0, 'right' => 12.0, 'bottom' => 14.0]
            );
            $frameLeft = $draft->registration_mode === OmrRegistrationMode::FiducialAnchored
                ? (float) ($markerInsets['left'] ?? 12.0)
                : 0.0;
            $frameTop = $draft->registration_mode === OmrRegistrationMode::FiducialAnchored
                ? (float) ($markerInsets['top'] ?? 12.0)
                : 0.0;
            $frameWidth = $draft->registration_mode === OmrRegistrationMode::FiducialAnchored
                ? $paperWidth - $frameLeft - (float) ($markerInsets['right'] ?? 12.0)
                : $paperWidth;
            $frameHeight = $draft->registration_mode === OmrRegistrationMode::FiducialAnchored
                ? $paperHeight - $frameTop - (float) ($markerInsets['bottom'] ?? 14.0)
                : $paperHeight;
            $documentFields = [];
            foreach ((array) $rawDocumentFields as $field) {
                if (! is_array($field)) {
                    continue;
                }
                $pageNo = (int) ($field['page_no'] ?? 1);
                $x = (float) ($field['x_mm'] ?? 0.0);
                $y = (float) ($field['y_mm'] ?? 0.0);
                $width = (float) ($field['w_mm'] ?? 0.0);
                $height = (float) ($field['h_mm'] ?? 0.0);
                if ($width > 0 && $height > 0 && $frameWidth > 0 && $frameHeight > 0) {
                    $documentFields[] = [
                        'id' => (string) ($field['id'] ?? 'field_'.$pageNo.'_'.($field['type'] ?? 'custom_input')),
                        'type' => (string) ($field['type'] ?? 'custom_input'),
                        'label' => (string) ($field['label'] ?? 'Document Field'),
                        'page_no' => $pageNo,
                        'region' => [
                            'x' => round(max(0.0, min(1.0, ($x - $frameLeft) / $frameWidth)), 4),
                            'y' => round(max(0.0, min(1.0, ($y - $frameTop) / $frameHeight)), 4),
                            'w' => round(max(0.0, min(1.0, $width / $frameWidth)), 4),
                            'h' => round(max(0.0, min(1.0, $height / $frameHeight)), 4),
                        ],
                        'transcription' => false,
                    ];
                }
            }
            $derived['manifest']['document_fields'] = $documentFields;
            $derived['hash'] = hash('sha256', json_encode($derived['manifest'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $version = DB::transaction(function () use ($draft, $templateModel, $nextVersion, $bundle, $derived, $authoringManifest, $relativeDir): OmrAnswerSheetTemplateVersion {
                $lockedTemplate = OmrAnswerSheetTemplate::query()->lockForUpdate()->findOrFail($templateModel->id);
                if (((int) $lockedTemplate->versions()->max('version')) + 1 !== $nextVersion) {
                    throw new OmrCanonicalCompileException('compile_conflict', 'Another version was compiled concurrently.');
                }
                [$width, $height] = $bundle->pageDimensions(1);
                $version = OmrAnswerSheetTemplateVersion::query()->create([
                    'template_id' => $lockedTemplate->id,
                    'test_instrument_version_id' => null,
                    'version' => $nextVersion,
                    'status' => OmrAnswerSheetTemplateVersion::STATUS_DRAFT,
                    'registration_mode' => $draft->registration_mode,
                    'qr_mode' => $draft->qr_mode,
                    'page_count' => $bundle->pageCount(),
                    'page_width' => $width,
                    'page_height' => $height,
                    'identity_regions_json' => $this->identityRegionsFor($derived['manifest'], $authoringManifest),
                    'provider_config_json' => ['driver' => (string) config('omr.provider.driver')],
                    'config_hash' => (string) $bundle->fileHashes['config.json'],
                    'template_json_hash' => hash('sha256', json_encode($bundle->template, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'native_bundle_version' => (string) $bundle->manifest['bundle_version'],
                    'native_bundle_json' => ['template' => $bundle->template, 'config' => $bundle->config, 'manifest' => $bundle->manifest, 'files' => $bundle->fileHashes, 'relative_dir' => $relativeDir],
                    'native_bundle_hash' => $bundle->bundleHash,
                    'engine_name' => $bundle->manifest['engine']['name'] ?? 'OMRChecker',
                    'engine_commit' => $bundle->manifest['engine']['commit'] ?? '',
                    'patch_identity' => $bundle->manifest['engine']['patch_set'] ?? '',
                    'review_manifest_json' => $derived['manifest'],
                    'review_manifest_hash' => $derived['hash'],
                    'authoring_manifest' => $authoringManifest,
                ]);
                $this->geometryProjector->project($version, $derived['manifest']);

                return $version;
            });
        } catch (\Throwable $exception) {
            $disk->deleteDirectory($relativeDir);
            throw $exception;
        }

        return ['version' => $version, 'bundle' => $bundle, 'relative_dir' => $relativeDir];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<int, array<string, array{x: float, y: float, w: float, h: float}>>
     */
    private function identityRegionsFor(array $manifest, ?array $authoringManifest = null): array
    {
        $regions = [];
        foreach ((array) ($manifest['fields'] ?? []) as $field) {
            $label = (string) ($field['label'] ?? '');
            $code = match (true) {
                str_starts_with($label, 'APPLICANT_NUMBER_') => OmrIdentityField::CODE_APPLICANT_NUMBER,
                str_starts_with($label, 'BIRTHDATE_') => OmrIdentityField::CODE_BIRTHDATE,
                default => null,
            };
            if ($code === null || ! is_array($field['region'] ?? null)) {
                continue;
            }

            $pageNo = (int) ($field['page'] ?? 0);
            if ($pageNo < 1 || $pageNo > count((array) ($manifest['pages'] ?? []))) {
                throw new OmrCanonicalCompileException('compile_candidate_invalid', 'Review manifest identity geometry references an unknown page.');
            }
            $existing = $regions[$pageNo][$code] ?? null;
            $regions[$pageNo][$code] = $existing === null
                ? $field['region']
                : OmrTemplateGeometryProjector::unionRegions([$existing, $field['region']]);
        }

        $identity = (array) ($authoringManifest['identity_method'] ?? $authoringManifest['identity'] ?? []);
        $writeInBoxes = (array) ($identity['write_in_boxes'] ?? []);
        $paperWidth = (float) ($authoringManifest['paper']['width_mm'] ?? 210.0);
        $paperHeight = (float) ($authoringManifest['paper']['height_mm'] ?? 297.0);

        foreach ($writeInBoxes as $box) {
            if (! is_array($box)) {
                continue;
            }
            $pageNo = (int) ($box['page_no'] ?? 1);
            $x = (float) ($box['x_mm'] ?? 0.0);
            $y = (float) ($box['y_mm'] ?? 0.0);
            $w = (float) ($box['w_mm'] ?? 0.0);
            $h = (float) ($box['h_mm'] ?? 0.0);
            if ($w > 0 && $h > 0 && $paperWidth > 0 && $paperHeight > 0) {
                $regions[$pageNo]['applicant_number_write_in'] = [
                    'x' => round($x / $paperWidth, 4),
                    'y' => round($y / $paperHeight, 4),
                    'w' => round($w / $paperWidth, 4),
                    'h' => round($h / $paperHeight, 4),
                ];
            }
        }

        ksort($regions);

        return $regions;
    }
}
