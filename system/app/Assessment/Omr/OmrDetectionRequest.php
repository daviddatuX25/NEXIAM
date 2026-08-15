<?php

namespace App\Assessment\Omr;

/**
 * Provider request envelope. The v2 boundary carries image/geometry identity
 * and expected observation labels, never the booklet answer key or roster
 * identity. Every hash is echoed by a successful provider response.
 */
final readonly class OmrDetectionRequest
{
    public const ENVELOPE_VERSION = 'nexiam.omr.detection.v2';

    /**
     * @param  array<string, int>  $areas  stable area code => max items
     * @param  list<OmrItemGeometry>  $items
     * @param  array<string, OmrRegion>  $identityRegions  field code => region
     * @param  list<string>  $expectedFieldLabels
     * @param  array<string, list<string>>  $expectedOptionCodes
     * @param  array<string, mixed>  $nativeTemplate
     * @param  array<string, mixed>  $nativeConfig
     * @param  array<string, mixed>  $nativeBundleMetadata
     * @param  array<string, mixed>  $reviewManifest
     */
    public function __construct(
        public string $envelopeVersion,
        public string $runId,
        public string $correlationId,
        public string $imageBytes,
        public string $inputHash,
        public string $imageMime,
        public ?int $imageWidth,
        public ?int $imageHeight,
        public array $areas,
        public array $items,
        public array $identityRegions,
        public string $nativeBundleHash,
        public string $manifestHash,
        public string $configHash,
        public string $bookletHash,
        public array $nativeTemplate,
        public array $nativeConfig,
        public array $nativeBundleMetadata,
        public array $reviewManifest,
        public string $engineName,
        public ?string $engineCommit,
        public string $patchIdentity,
        public array $expectedFieldLabels,
        public array $expectedOptionCodes,
        public float $providerDeadline,
        public int $pageNo = 1,
    ) {
    }

    public static function currentEnvelopeVersion(): string
    {
        return self::ENVELOPE_VERSION;
    }
}
