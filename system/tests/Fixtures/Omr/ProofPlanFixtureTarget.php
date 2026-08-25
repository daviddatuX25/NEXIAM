<?php

namespace Tests\Fixtures\Omr;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;

/**
 * Deterministic in-memory OmrDiagnosticTarget fixture for proof-plan and
 * challenge-renderer unit tests (35.17/35.20). Blocks mirror the native
 * worker field-block shape; the review manifest is derived from the same
 * blocks, exactly like NativeManifestDeriver.
 */
final class ProofPlanFixtureTarget implements OmrDiagnosticTarget
{
    /**
     * @param  list<array{page_no: int, blocks: array<string, array<string, mixed>>}>  $pages
     */
    public function __construct(
        private readonly array $pages,
        private readonly bool $identityMapped,
        private readonly string $artifactHash = '1111111111111111111111111111111111111111111111111111111111111111',
        private readonly string $bundleHash = '2222222222222222222222222222222222222222222222222222222222222222',
    ) {
    }

    public static function standard(bool $identityMapped = true, bool $multiPage = true): self
    {
        $pageOne = [
            'page_no' => 1,
            'blocks' => [
                'LOGIC' => [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => ['LOGIC_Q_1..LOGIC_Q_10'],
                    'origin' => [100, 200],
                    'bubblesGap' => 60,
                    'labelsGap' => 80,
                    'bubbleDimensions' => [40, 40],
                ],
            ],
        ];
        if ($identityMapped) {
            $pageOne['blocks']['APPLICANT_NUMBER'] = [
                'fieldType' => 'QTYPE_INT',
                'direction' => 'vertical',
                'fieldLabels' => ['APPLICANT_NUMBER_1..APPLICANT_NUMBER_10'],
                'origin' => [500, 300],
                'bubblesGap' => 70,
                'labelsGap' => 90,
                'bubbleDimensions' => [36, 36],
            ];
        }
        $pages = [$pageOne];
        if ($multiPage) {
            $pages[] = [
                'page_no' => 2,
                'blocks' => [
                    'VERBAL' => [
                        'fieldType' => 'QTYPE_MCQ5',
                        'direction' => 'horizontal',
                        'fieldLabels' => ['VERBAL_Q_1..VERBAL_Q_4'],
                        'origin' => [120, 300],
                        'bubblesGap' => 60,
                        'labelsGap' => 80,
                        'bubbleDimensions' => [40, 40],
                    ],
                ],
            ];
        }

        return new self($pages, $identityMapped);
    }

    public function kind(): string
    {
        return OmrDiagnosticTarget::KIND_COMPILED_VERSION;
    }

    public function targetId(): string
    {
        return 'fixture-target';
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function withArtifactHash(string $hash): self
    {
        return new self($this->pages, $this->identityMapped, $hash, $this->bundleHash);
    }

    public function withBundleHash(string $hash): self
    {
        return new self($this->pages, $this->identityMapped, $this->artifactHash, $hash);
    }

    /** @return array<string, mixed> */
    public function nativeTemplate(int $pageNo): array
    {
        return [
            'pageDimensions' => [2480, 3508],
            'bubbleDimensions' => [40, 40],
            'customLabels' => new \stdClass,
            'fieldBlocks' => $this->pageBlocks($pageNo),
            'preProcessors' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function nativeConfig(): array
    {
        return ['dimensions' => [2480, 3508], 'marker' => ['size' => 40, 'quiet_zone' => 6]];
    }

    /** @return array<string, string> */
    public function nativeAssets(): array
    {
        return ['fiducial.png' => 'fixture-marker-bytes'];
    }

    /** @return array<string, mixed> */
    public function reviewManifest(): array
    {
        $fields = [];
        $identityLabels = [];
        foreach ($this->pages as $page) {
            foreach ($page['blocks'] as $blockName => $block) {
                $values = self::values($block);
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    foreach (NativeBundle::expandLabel((string) $declaration) as $label) {
                        if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                            $identityLabels[] = $label;
                        }
                        foreach ($values as $option) {
                            $fields[] = [
                                'page' => $page['page_no'],
                                'block' => $blockName,
                                'label' => $label,
                                'option' => $option,
                                'pixel' => ['x' => 0, 'y' => 0, 'w' => 40, 'h' => 40],
                                'region' => ['x' => 0.05, 'y' => 0.05, 'w' => 0.02, 'h' => 0.02],
                            ];
                        }
                    }
                }
            }
        }

        return [
            'manifest_version' => 'nexiam.omr.review-manifest.v1',
            'template_code' => 'FIXTURE',
            'template_version' => 1,
            'bundle_hash' => $this->bundleHash,
            'file_hashes' => ['fiducial.png' => hash('sha256', 'fixture-marker-bytes')],
            'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40)],
            'page_count' => $this->pageCount(),
            'pages' => array_map(
                static fn (array $page): array => ['page_no' => $page['page_no'], 'page_dimensions' => ['width' => 2480, 'height' => 3508], 'field_count' => 0],
                $this->pages,
            ),
            'identity' => [
                'mode' => $this->identityMapped ? 'bubbled_applicant_number' : 'none',
                'labels' => $identityLabels,
                'symbols' => $identityLabels === [] ? [] : array_map('strval', range(0, 9)),
                'free_form_supported' => false,
                'birthdate' => null,
            ],
            'fields' => $fields,
        ];
    }

    public function markerAssetName(): ?string
    {
        return 'fiducial.png';
    }

    public function markerAssetHash(): ?string
    {
        return hash('sha256', 'fixture-marker-bytes');
    }

    public function markerProfileCode(): ?string
    {
        return 'fixture.marker';
    }

    public function markerProfileVersion(): ?int
    {
        return 1;
    }

    public function markerProfileQualified(): bool
    {
        return true;
    }

    public function artifactHash(): string
    {
        return $this->artifactHash;
    }

    public function nativeBundleHash(): string
    {
        return $this->bundleHash;
    }

    public function manifestHash(): string
    {
        return hash('sha256', json_encode($this->reviewManifest(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function configHash(): string
    {
        return hash('sha256', json_encode($this->nativeConfig(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public function mappingHash(): string
    {
        return '3333333333333333333333333333333333333333333333333333333333333333';
    }

    public function engineName(): string
    {
        return 'OMRChecker';
    }

    public function engineCommit(): ?string
    {
        return str_repeat('c', 40);
    }

    public function patchIdentity(): string
    {
        return 'nexiam-fixture-v1';
    }

    public function qualificationCapable(): bool
    {
        return true;
    }

    public function buildRequest(OmrDiagnosticRequestBuilder $builder, string $imageBytes, string $imageMime, int $pageNo): OmrDetectionRequest
    {
        throw new OmrDiagnosticTargetException('fixture_no_request', 'The proof fixture never builds worker requests.');
    }

    /** @return array<string, mixed> */
    private function pageBlocks(int $pageNo): array
    {
        foreach ($this->pages as $page) {
            if ($page['page_no'] === $pageNo) {
                return $page['blocks'];
            }
        }

        throw new OmrDiagnosticTargetException('diagnostic_page_out_of_range', "Fixture has no page {$pageNo}.");
    }

    /** @param  array<string, mixed>  $block  @return list<string> */
    private static function values(array $block): array
    {
        if (isset($block['bubbleValues']) && is_array($block['bubbleValues'])) {
            return array_values(array_map('strval', $block['bubbleValues']));
        }

        return match ((string) ($block['fieldType'] ?? '')) {
            'QTYPE_INT' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            'QTYPE_MCQ4' => ['A', 'B', 'C', 'D'],
            'QTYPE_MCQ5' => ['A', 'B', 'C', 'D', 'E'],
            default => throw new RuntimeException('fixture field type has no declared values'),
        };
    }
}
