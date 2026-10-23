<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $captures = DB::table('omr_template_verification_captures')
            ->select(['aligned_asset_id', 'overlay_asset_id', 'layout_result', 'summary'])
            ->where(function ($query): void {
                $query->whereNotNull('aligned_asset_id')
                    ->orWhereNotNull('overlay_asset_id');
            })
            ->get();

        foreach ($captures as $capture) {
            $layout = $this->jsonObject($capture->layout_result);
            $summary = $this->jsonObject($capture->summary);

            foreach ([
                'aligned' => $capture->aligned_asset_id,
                'overlay' => $capture->overlay_asset_id,
            ] as $role => $assetId) {
                if ($assetId === null) {
                    continue;
                }
                $image = $layout[$role.'_image']
                    ?? $summary['prepared_capture']['layout'][$role.'_image']
                    ?? $summary['evidence']['assets'][$role]
                    ?? null;
                if (! is_array($image)
                    || ! is_numeric($image['width'] ?? null)
                    || (int) $image['width'] < 1
                    || ! is_numeric($image['height'] ?? null)
                    || (int) $image['height'] < 1) {
                    continue;
                }

                $asset = DB::table('omr_template_assets')
                    ->select(['id', 'metadata'])
                    ->where('id', $assetId)
                    ->first();
                if ($asset === null) {
                    continue;
                }
                $metadata = $this->jsonObject($asset->metadata);
                if (is_numeric($metadata['width'] ?? null)
                    && (int) $metadata['width'] > 0
                    && is_numeric($metadata['height'] ?? null)
                    && (int) $metadata['height'] > 0) {
                    continue;
                }

                DB::table('omr_template_assets')
                    ->where('id', $asset->id)
                    ->update([
                        'metadata' => json_encode([
                            ...$metadata,
                            'width' => (int) $image['width'],
                            'height' => (int) $image['height'],
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    ]);
            }
        }
    }

    public function down(): void {}

    /** @return array<string, mixed> */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (! is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
