<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_string;
use function strlen;
use function strtolower;
use function trim;

/**
 * Independent, single-source-of-truth resolver for canonical Answer Sheet page
 * rasters and geometry across Studio Edit, Answer Sheet Preview, Test Kit
 * Paired Preview, and OMR Mock Session Shading Guides.
 */
final class OmrCanonicalSheetPageResolver
{
    private const ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

    public function __construct(
        private readonly ?SensitiveAssetStore $sensitiveStore = null,
    ) {}

    /**
     * Resolve the ordered per-page raster inventory for a compiled Answer Sheet version,
     * always preferring the full-sheet raster image (`raster_page`) over wireframe SVGs.
     *
     * @return list<array{page_no:int,width:int,height:int,asset_id:?int,asset_sha256:?string,mime:?string}>
     */
    public function pageInventoryForVersion(OmrAnswerSheetTemplateVersion $version): array
    {
        $version->loadMissing(['canonicalAssets', 'pages']);
        $assetsByPage = $this->resolveVersionPageAssets($version);

        return $version->pages
            ->sortBy([['page_no', 'asc'], ['id', 'asc']])
            ->values()
            ->map(static function ($page) use ($assetsByPage): array {
                $pageNo = (int) $page->page_no;
                $asset = $assetsByPage[$pageNo] ?? null;

                return [
                    'page_no' => $pageNo,
                    'width' => (int) $page->width,
                    'height' => (int) $page->height,
                    'asset_id' => $asset instanceof OmrTemplateAsset ? (int) $asset->id : null,
                    'asset_sha256' => $asset instanceof OmrTemplateAsset ? (string) $asset->sha256 : null,
                    'mime' => $asset instanceof OmrTemplateAsset ? (string) $asset->mime : null,
                ];
            })
            ->all();
    }

    /**
     * Resolve the canonical page asset for a compiled Answer Sheet version and page number.
     */
    public function pageAssetForVersion(OmrAnswerSheetTemplateVersion $version, int $pageNo): ?OmrTemplateAsset
    {
        if ($pageNo < 1) {
            return null;
        }
        $version->loadMissing('canonicalAssets');
        $assetsByPage = $this->resolveVersionPageAssets($version);

        return $assetsByPage[$pageNo] ?? null;
    }

    /**
     * Read and SHA-256 verify the raw bytes of a canonical template asset from its declared disk.
     *
     * @return array{bytes:string,mime:string,sha256:string,extension:string}
     */
    public function readVerifiedAsset(OmrTemplateAsset $asset): array
    {
        $path = is_string($asset->path) ? trim($asset->path) : '';
        $expectedSha = is_string($asset->sha256) ? strtolower(trim($asset->sha256)) : '';
        if ($path === '' || $expectedSha === '') {
            throw new RuntimeException('omr_sheet_asset_path_or_hash_missing');
        }

        $metadata = is_array($asset->metadata) ? $asset->metadata : [];
        if ((string) $asset->disk === 'sensitive'
            && is_string($metadata['hmac'] ?? null)
            && trim((string) $metadata['hmac']) !== '') {
            $store = $this->sensitiveStore ?? app(SensitiveAssetStore::class);
            $bytes = $store->read($path, (string) $metadata['hmac']);
        } else {
            $disk = Storage::disk((string) ($asset->disk ?: 'local'));
            if (! $disk->exists($path)) {
                throw new RuntimeException('omr_sheet_asset_missing_on_disk');
            }
            $bytes = (string) $disk->get($path);
        }

        $actualSha = hash('sha256', $bytes);
        if (! hash_equals($expectedSha, $actualSha)) {
            throw new RuntimeException('omr_sheet_asset_integrity_failed');
        }

        [$mime, $extension] = $this->normalizeMime((string) $asset->mime);

        return [
            'bytes' => $bytes,
            'mime' => $mime,
            'sha256' => $actualSha,
            'extension' => $extension,
        ];
    }

    /**
     * Build an inline, no-store HTTP response for a verified canonical page asset.
     */
    public function inlineResponse(OmrTemplateAsset $asset, string $filenamePrefix = 'omr-sheet-page'): Response
    {
        $resolved = $this->readVerifiedAsset($asset);

        return response($resolved['bytes'], 200, [
            'Content-Type' => $resolved['mime'],
            'Content-Length' => (string) strlen($resolved['bytes']),
            'Content-Disposition' => 'inline; filename="'.$filenamePrefix.'-'.$resolved['sha256'].$resolved['extension'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<int, OmrTemplateAsset>
     */
    private function resolveVersionPageAssets(OmrAnswerSheetTemplateVersion $version): array
    {
        $assetsByPage = [];
        foreach ($version->canonicalAssets as $asset) {
            if ($asset->page_no === null) {
                continue;
            }
            if (! in_array($asset->kind, [
                OmrTemplateAsset::KIND_RASTER_PAGE,
                OmrTemplateAsset::KIND_DIGITAL_PROOF,
                OmrTemplateAsset::KIND_REFERENCE_PAGE,
            ], true)) {
                continue;
            }
            $pageNo = (int) $asset->page_no;
            $current = $assetsByPage[$pageNo] ?? null;
            if ($current === null || $this->assetPriority($asset->kind) > $this->assetPriority($current->kind)) {
                $assetsByPage[$pageNo] = $asset;
            }
        }

        if ($assetsByPage !== []) {
            return $assetsByPage;
        }

        // Fallback to linked authoring draft raster_assets if version assets were not yet pinned
        $draft = OmrTemplateAuthoringDraft::query()
            ->where('compiled_version_id', $version->id)
            ->first();
        if ($draft !== null) {
            $rasterEntries = (array) data_get($draft->authoring_json, 'canonical.raster_assets', []);
            foreach ($rasterEntries as $entry) {
                $pageNo = (int) ($entry['page_no'] ?? 0);
                $assetId = (int) ($entry['asset_id'] ?? 0);
                if ($pageNo > 0 && $assetId > 0) {
                    $asset = OmrTemplateAsset::query()
                        ->where('authoring_draft_id', $draft->id)
                        ->find($assetId);
                    if ($asset instanceof OmrTemplateAsset) {
                        $assetsByPage[$pageNo] = $asset;
                    }
                }
            }
        }

        return $assetsByPage;
    }

    private function assetPriority(string $kind): int
    {
        return match ($kind) {
            OmrTemplateAsset::KIND_RASTER_PAGE => 3,
            OmrTemplateAsset::KIND_DIGITAL_PROOF => 2,
            OmrTemplateAsset::KIND_REFERENCE_PAGE => 1,
            default => 0,
        };
    }

    /**
     * @return array{0:string,1:string}
     */
    private function normalizeMime(string $mime): array
    {
        $normalized = strtolower(trim($mime));
        if (! in_array($normalized, self::ALLOWED_MIMES, true)) {
            throw new RuntimeException('omr_sheet_asset_mime_unsupported');
        }

        $extension = match ($normalized) {
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/webp' => '.webp',
            'image/svg+xml' => '.svg',
        };

        return [$normalized, $extension];
    }
}
