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
use function preg_replace;
use function sprintf;
use function strlen;

final class OmrTemplateAssetStore
{
    /**
     * @param  array<string, mixed>  $metadata
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
        $pageSuffix = $pageNo === null ? '' : sprintf('-p%d', $pageNo);
        $path = sprintf('omr-canonical/drafts/%d/%s%s%s-%s%s', $draft->id, $kind, $this->evidenceRoleSuffix($kind, $metadata), $pageSuffix, $sha256, $extension);

        return DB::transaction(function () use ($draft, $kind, $bytes, $mime, $pageNo, $metadata, $sha256, $disk, $path): OmrTemplateAsset {
            Storage::disk($disk)->put($path, $bytes);

            $existing = OmrTemplateAsset::query()
                ->where('authoring_draft_id', $draft->id)
                ->where('kind', $kind)
                ->where('sha256', $sha256)
                ->where('page_no', $pageNo)
                ->first();
            if ($existing !== null) {
                if ($metadata !== [] && $metadata !== (array) $existing->metadata) {
                    DB::table('omr_template_assets')
                        ->where('id', $existing->id)
                        ->update(['metadata' => json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)]);
                    $existing->metadata = $metadata;
                }

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
                    ->where('authoring_draft_id', $draft->id)
                    ->where('kind', $kind)
                    ->where('sha256', $sha256)
                    ->where('page_no', $pageNo)
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

    /**
     * Store a NEW immutable asset owned by a compiled template version
     * (OpenSpec 35.27/35.28, AD-10). Used by the version-scoped physical
     * challenge flow for sheet/guide/forensic/aligned/overlay assets that
     * cannot be owned by a draft (compiled drafts are immutable).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function putVersionAsset(
        OmrAnswerSheetTemplateVersion $version,
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
        $path = sprintf('omr-canonical/versions/%d/run-%s%s-%s%s', $version->id, $kind, $this->evidenceRoleSuffix($kind, $metadata), $sha256, $extension);

        return DB::transaction(function () use ($version, $kind, $bytes, $mime, $pageNo, $metadata, $sha256, $disk, $path): OmrTemplateAsset {
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
                    'template_version_id' => $version->id,
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
     * Purge all files and directories associated with an authoring draft upon discard.
     */
    public function purgeDraftStorage(OmrTemplateAuthoringDraft $draft): void
    {
        $disk = (string) config('omr.canonical.storage.disk', 'local');
        $directory = sprintf('omr-canonical/drafts/%d', $draft->id);
        Storage::disk($disk)->deleteDirectory($directory);
    }

    /** @param array<string, mixed> $metadata */
    private function evidenceRoleSuffix(string $kind, array $metadata): string
    {
        if ($kind === OmrTemplateAsset::KIND_DIGITAL_PROOF && isset($metadata['run_id'])) {
            return sprintf('-run-%d-attempt-%d', (int) $metadata['run_id'], (int) ($metadata['attempt'] ?? 1));
        }
        if ($kind !== OmrTemplateAsset::KIND_EVIDENCE || ! is_string($metadata['role'] ?? null)) {
            return '';
        }
        $role = preg_replace('/[^a-z0-9_-]+/i', '-', trim($metadata['role']));

        return is_string($role) && $role !== '' ? '-'.$role : '';
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
