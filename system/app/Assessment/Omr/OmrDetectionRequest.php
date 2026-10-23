<?php

namespace App\Assessment\Omr;

use InvalidArgumentException;
use JsonException;

use function array_is_list;
use function hash;
use function hash_equals;
use function is_array;
use function is_finite;
use function is_float;
use function json_decode;
use function preg_match;

/**
 * Provider request envelope. The v5 boundary carries exact immutable config
 * bytes alongside target, mode, page, and source-image identity; it never
 * carries the booklet answer key or roster identity.
 */
final readonly class OmrDetectionRequest
{
    public const ENVELOPE_VERSION = 'nexiam.omr.detection.v5';

    public array $nativeConfig;

    /**
     * @param  array<string, int>  $areas  stable area code => max items
     * @param  list<OmrItemGeometry>  $items
     * @param  array<string, OmrRegion>  $identityRegions  field code => region
     * @param  list<string>  $expectedFieldLabels
     * @param  array<string, list<string>>  $expectedOptionCodes
     * @param  array<string, mixed>  $nativeTemplate
     * @param  array<string, mixed>  $nativeBundleMetadata
     * @param  string  $nativeConfigJson  exact verified UTF-8 config.json bytes
     * @param  array<string, mixed>  $reviewManifest
     * @param  array<string, string>  $nativeAssets  bounded marker assets (name => bytes) the worker materializes next to template.json
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
        public string $nativeConfigJson,
        public array $nativeBundleMetadata,
        public array $reviewManifest,
        public string $engineName,
        public ?string $engineCommit,
        public string $patchIdentity,
        public array $expectedFieldLabels,
        public array $expectedOptionCodes,
        public OmrRegistrationMode $registrationMode,
        public ?int $templateVersionId,
        public ?string $candidateTargetId,
        public OmrSourceImage $sourceImage,
        public int $pageNo,
        public float $providerDeadline,
        public ?OmrClientQuad $clientQuad = null,
        public array $nativeAssets = [],
        public ?string $cropPolicy = 'auto_document_quad',
    ) {
        $hasVersion = $templateVersionId !== null && $templateVersionId > 0;
        $hasCandidate = is_string($candidateTargetId)
            && preg_match('/^candidate:[a-f0-9]{64}$/', $candidateTargetId) === 1;
        if ($hasVersion === $hasCandidate
            || ($templateVersionId !== null && ! $hasVersion)
            || ($candidateTargetId !== null && ! $hasCandidate)
            || $pageNo < 1 || $pageNo > 4
            || $sourceImage->inputHash !== $inputHash
            || ($imageWidth !== null && $sourceImage->width !== $imageWidth)
            || ($imageHeight !== null && $sourceImage->height !== $imageHeight)
            || ($clientQuad !== null && ! $clientQuad->isBoundTo($sourceImage, $pageNo))) {
            throw new InvalidArgumentException('The OMR detection request identity is invalid.');
        }
        try {
            $decodedConfig = json_decode($this->nativeConfigJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The OMR detection config JSON is invalid.', previous: $exception);
        }
        if (! is_array($decodedConfig)
            || array_is_list($decodedConfig)
            || self::containsNonFiniteFloat($decodedConfig)
            || ! hash_equals($this->configHash, hash('sha256', $this->nativeConfigJson))
            || ($this->nativeBundleMetadata['files']['config.json'] ?? null) !== $this->configHash) {
            throw new InvalidArgumentException('The OMR detection request config identity is invalid.');
        }
        $this->nativeConfig = $decodedConfig;
    }

    private static function containsNonFiniteFloat(array $values): bool
    {
        foreach ($values as $value) {
            if (is_array($value) && self::containsNonFiniteFloat($value)) {
                return true;
            }
            if (is_float($value) && ! is_finite($value)) {
                return true;
            }
        }

        return false;
    }

    public static function currentEnvelopeVersion(): string
    {
        return self::ENVELOPE_VERSION;
    }

    public function withCropPolicy(?string $cropPolicy): self
    {
        return new self(
            envelopeVersion: $this->envelopeVersion,
            runId: $this->runId,
            correlationId: $this->correlationId,
            imageBytes: $this->imageBytes,
            inputHash: $this->inputHash,
            imageMime: $this->imageMime,
            imageWidth: $this->imageWidth,
            imageHeight: $this->imageHeight,
            areas: $this->areas,
            items: $this->items,
            identityRegions: $this->identityRegions,
            nativeBundleHash: $this->nativeBundleHash,
            manifestHash: $this->manifestHash,
            configHash: $this->configHash,
            bookletHash: $this->bookletHash,
            nativeTemplate: $this->nativeTemplate,
            nativeConfigJson: $this->nativeConfigJson,
            nativeBundleMetadata: $this->nativeBundleMetadata,
            reviewManifest: $this->reviewManifest,
            engineName: $this->engineName,
            engineCommit: $this->engineCommit,
            patchIdentity: $this->patchIdentity,
            expectedFieldLabels: $this->expectedFieldLabels,
            expectedOptionCodes: $this->expectedOptionCodes,
            registrationMode: $this->registrationMode,
            templateVersionId: $this->templateVersionId,
            candidateTargetId: $this->candidateTargetId,
            sourceImage: $this->sourceImage,
            pageNo: $this->pageNo,
            providerDeadline: $this->providerDeadline,
            clientQuad: $this->clientQuad,
            nativeAssets: $this->nativeAssets,
            cropPolicy: $cropPolicy ?? 'auto_document_quad',
        );
    }
}
