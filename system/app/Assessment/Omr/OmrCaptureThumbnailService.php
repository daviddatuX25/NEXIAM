<?php

namespace App\Assessment\Omr;

use App\Support\Media\ImageThumbnailScaler;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

final class OmrCaptureThumbnailService
{
    private const FAILURE_MESSAGE = 'OMR thumbnail is unavailable.';

    public function __construct(
        private readonly ImageThumbnailScaler $scaler,
        private readonly SensitiveAssetStore $assetStore,
    ) {}

    public function thumbnail(string $sourcePath, string $expectedSourceHmac, string $sourceMime): string
    {
        try {
            return Cache::store('database')
                ->funnel('omr-thumbnail-source-decode')
                ->limit(1)
                ->releaseAfter(30)
                ->block(1)
                ->then(fn (): string => $this->assetStore->rememberDerivative(
                    $sourcePath,
                    $expectedSourceHmac,
                    fn (string $source): string => $this->scaler->scale($source, $sourceMime),
                ));
        } catch (Throwable) {
            throw new RuntimeException(self::FAILURE_MESSAGE);
        }
    }
}
