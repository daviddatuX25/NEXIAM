<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Email;

use App\Models\Notifications\CommunicationTemplateDocument;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class NoticeEmailAssetVerifier
{
    public const MAX_IMAGES = 20;

    public const MAX_BYTES = 2097152;

    public const MAX_PIXELS = 20000000;

    /** @return list<string> */
    public static function assetIds(array $blueprint): array
    {
        $ids = [];
        $walk = function (array $node) use (&$walk, &$ids): void {
            if (($node['type'] ?? null) === 'image') {
                $ids[] = (string) ($node['asset_id'] ?? '');
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($blueprint);
        if (count($ids) > self::MAX_IMAGES) {
            throw new RuntimeException('email_asset_limit');
        }

        return array_values(array_unique($ids));
    }

    /**
     * Expected metadata is frozen delivery metadata, never caller-authored preview input.
     *
     * @return list<array{id:string,sha256:string,cid:string,mime:string,filename:string,bytes:string}>
     */
    public function verify(int $institutionId, string $familyId, array $blueprint, ?array $expected = null): array
    {
        $assets = [];
        $pins = [];
        $cids = [];
        foreach ($expected ?? [] as $pin) {
            if (! is_array($pin) || ! is_string($pin['id'] ?? null) || isset($pins[$pin['id']])) {
                throw new RuntimeException('email_asset_pin_invalid');
            }
            $pins[$pin['id']] = $pin;
        }
        foreach (self::assetIds($blueprint) as $id) {
            $document = CommunicationTemplateDocument::query()
                ->whereKey($id)->where('institution_id', $institutionId)
                ->where('communication_template_id', $familyId)->first();
            if ($document === null || ! in_array($document->mime_type, ['image/png', 'image/jpeg'], true)
                || $document->file_size_bytes < 1 || $document->file_size_bytes > self::MAX_BYTES) {
                throw new RuntimeException('email_asset_invalid');
            }
            $disk = Storage::disk('local');
            $root = realpath($disk->path(''));
            $path = realpath($disk->path($document->storage_path));
            if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR)
                || ! str_starts_with($document->storage_path, 'communication-templates/'.$familyId.'/')
                || str_contains($document->storage_path, '..')
                || ! is_file($path) || filesize($path) > self::MAX_BYTES) {
                throw new RuntimeException('email_asset_unavailable');
            }
            $bytes = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
            if (! is_string($bytes) || strlen($bytes) !== (int) $document->file_size_bytes
                || ! hash_equals($document->checksum, hash('sha256', $bytes))) {
                throw new RuntimeException('email_asset_checksum');
            }
            $image = @getimagesizefromstring($bytes);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if ($image === false || $mime !== $document->mime_type || ($image['mime'] ?? null) !== $mime
                || $image[0] < 1 || $image[1] < 1 || $image[0] * $image[1] > self::MAX_PIXELS) {
                throw new RuntimeException('email_asset_raster_invalid');
            }
            $pin = $pins[$id] ?? null;
            if ($expected !== null && ($pin === null || ($pin['sha256'] ?? null) !== $document->checksum
                || ($pin['mime'] ?? null) !== $mime)) {
                throw new RuntimeException('email_asset_pin_invalid');
            }
            $cid = $pin['cid'] ?? 'notice-'.$id.'@nexiam.inline';
            if (! is_string($cid) || ! str_contains($cid, '@') || isset($cids[$cid])
                || ! preg_match('/^[a-zA-Z0-9._@-]{1,150}$/D', $cid)) {
                throw new RuntimeException('email_asset_cid_invalid');
            }
            $cids[$cid] = true;
            $assets[] = ['id' => $id, 'sha256' => $document->checksum, 'cid' => $cid, 'mime' => $mime,
                'filename' => $id.($mime === 'image/png' ? '.png' : '.jpg'), 'bytes' => $bytes];
        }
        if ($expected !== null && count($pins) !== count($assets)) {
            throw new RuntimeException('email_asset_pin_invalid');
        }

        return $assets;
    }
}
