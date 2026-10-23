<?php

namespace App\Support\Media;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class MediaStorageLifecycle
{
    public function stage(string $disk, string $path, string $contents, string $mime): StagedMedia
    {
        try {
            $storage = Storage::disk($disk);
            $written = $storage->put($path, $contents);

            if ($written !== true || ! $storage->exists($path)) {
                throw new RuntimeException('Staged media could not be written.');
            }

            $storedContents = $storage->get($path);
            $sha256 = hash('sha256', $contents);

            if (! hash_equals($sha256, hash('sha256', $storedContents))) {
                throw new RuntimeException('Staged media checksum verification failed.');
            }

            return new StagedMedia(
                disk: $disk,
                path: $path,
                sha256: $sha256,
                byteSize: strlen($contents),
                mime: $mime,
            );
        } catch (Throwable $exception) {
            $this->deleteQuietly($disk, $path);

            throw new RuntimeException('Unable to stage media.', previous: $exception);
        }
    }

    public function commit(StagedMedia $staged, Closure $domainTransaction): mixed
    {
        try {
            $result = DB::transaction(fn (): mixed => $domainTransaction($staged));
        } catch (Throwable $exception) {
            $this->deleteQuietly($staged->disk, $staged->path);

            throw $exception;
        }

        foreach ($this->displacedPaths($result) as $path) {
            $this->deleteQuietly($staged->disk, $path);
        }

        return $result;
    }

    public function deleteQuietly(string $disk, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable) {
            // Best-effort cleanup must not mask the domain transaction outcome.
        }
    }

    /** @return list<string> */
    private function displacedPaths(mixed $result): array
    {
        if (is_string($result)) {
            return [$result];
        }

        if (! is_array($result)) {
            return [];
        }

        $paths = [];
        foreach (['displaced_paths', 'old_path'] as $key) {
            $value = $result[$key] ?? null;
            foreach (is_array($value) ? $value : [$value] as $path) {
                if (is_string($path) && $path !== '') {
                    $paths[] = $path;
                }
            }
        }

        return array_values(array_unique($paths));
    }
}
