<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function config;
use function hash;
use function is_string;

final class OmrTemplateAssetStore
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function putDraftAsset(
        OmrTemplateAuthoringDraft $draft,
        string $kind,
        string $bytes,
        string $mime,
        ?int $pageNo = null,
        array $metadata = [],
    ): OmrTemplateAsset {
        $this->assertKind($kind);
        $sha256 = hash('sha256', $bytes);
        $extension = $this->extensionForMime($mime);
        $disk = (string) config('omr.canonical.storage.disk', 'local');
        $path = sprintf('omr-canonical/drafts/%d/%s-%s%s', $draft->id, $kind, $sha256, $extension);

        return DB::transaction(function () use ($draft, $kind, $bytes, $mime, $pageNo, $metadata, $sha256, $disk, $path): OmrTemplateAsset {
            Storage::disk($disk)->put($path, $bytes);

            $existing = OmrTemplateAsset::query()
                ->where('disk', $disk)
                ->where('path', $path)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            try {
                return DB::transaction(fn (): OmrTemplateAsset => OmrTemplateAsset::query()->create([
                    'authoring_draft_id' => $draft->id,
                    'kind' => $kind,
                    'page_no' => $pageNo,
                    'disk' => $disk,
                    'path' => $path,
                    'mime' => $mime,
                    'bytes' => strlen($bytes),
                    'sha256' => $sha256,
                    'metadata' => $metadata,
                ]));
            } catch (QueryException $exception) {
                $existing = OmrTemplateAsset::query()
                    ->where('disk', $disk)
                    ->where('path', $path)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
                Storage::disk($disk)->delete($path);
                throw $exception;
            } catch (RuntimeException $exception) {
                Storage::disk($disk)->delete($path);
                throw $exception;
            }
        });
    }

    /**
     * Copy an immutable asset to version ownership during compile.
     */
    public function pinToVersion(OmrTemplateAsset $asset, OmrAnswerSheetTemplateVersion $version): OmrTemplateAsset
    {
        if ($asset->authoring_draft_id === null || $asset->template_version_id !== null) {
            throw new RuntimeException('asset_owner_violation');
        }
        $bytes = Storage::disk($asset->disk)->get($asset->path);
        if (! is_string($bytes) || hash('sha256', $bytes) !== $asset->sha256) {
            throw new RuntimeException('asset_integrity_failure');
        }
        $path = sprintf('omr-canonical/versions/%d/%s', $version->id, basename($asset->path));
        Storage::disk($asset->disk)->put($path, $bytes);

        return OmrTemplateAsset::query()->create([
            'template_version_id' => $version->id,
            'kind' => $asset->kind,
            'page_no' => $asset->page_no,
            'disk' => $asset->disk,
            'path' => $path,
            'mime' => $asset->mime,
            'bytes' => $asset->bytes,
            'sha256' => $asset->sha256,
            'metadata' => [...(array) $asset->metadata, 'pinned_from_asset_id' => $asset->id],
        ]);
    }

    private function assertKind(string $kind): void
    {
        if (! in_array($kind, OmrTemplateAsset::KINDS, true)) {
            throw new RuntimeException('asset_kind_invalid');
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match (strtolower($mime)) {
            'application/pdf' => '.pdf',
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/svg+xml' => '.svg',
            'application/json' => '.json',
            'text/html' => '.html',
            default => '.bin',
        };
    }
}
