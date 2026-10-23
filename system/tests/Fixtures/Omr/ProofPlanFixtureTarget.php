<?php

namespace Tests\Fixtures\Omr;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticTargetException;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDiagnosticRequestBuilder;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrOptionGeometry;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegion;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;

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
    ) {}

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

    /**
     * Six-area deterministic fixture with enough rows to prove the
     * first/middle/last hand-shaded sample and A-D balancing.
     */
    public static function sixAreas(int $itemsPerArea = 5, bool $identityMapped = true): self
    {
        $blocks = [];
        for ($area = 1; $area <= 6; $area++) {
            $code = 'AREA_'.$area;
            $blocks[$code] = [
                'fieldType' => 'QTYPE_MCQ4',
                'direction' => 'horizontal',
                'fieldLabels' => [$code.'_Q_1..'.$code.'_Q_'.$itemsPerArea],
                'origin' => [100, 150 + (($area - 1) * 260)],
                'bubblesGap' => 60,
                'labelsGap' => 80,
                'bubbleDimensions' => [40, 40],
            ];
        }
        if ($identityMapped) {
            $blocks['APPLICANT_NUMBER'] = [
                'fieldType' => 'QTYPE_INT',
                'direction' => 'vertical',
                'fieldLabels' => ['APPLICANT_NUMBER_1..APPLICANT_NUMBER_10'],
                'origin' => [1200, 300],
                'bubblesGap' => 70,
                'labelsGap' => 90,
                'bubbleDimensions' => [36, 36],
            ];
        }

        return new self([
            ['page_no' => 1, 'blocks' => $blocks],
        ], $identityMapped);
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

    public function registrationMode(): OmrRegistrationMode
    {
        return OmrRegistrationMode::FiducialAnchored;
    }

    public function qrMode(): OmrQrMode
    {
        return OmrQrMode::Signed;
    }

    /** @return list<array{page_no:int}> */
    public function compiledPages(): array
    {
        return array_map(static fn (array $page): array => ['page_no' => (int) $page['page_no']], $this->pages);
    }

    /** @return array{width_mm:float,height_mm:float} */
    public function paperDimensionsMm(int $pageNo): array
    {
        $this->pageBlocks($pageNo);

        return ['width_mm' => 210.0, 'height_mm' => 297.0];
    }

    /** @return list<array<string,mixed>> */
    public function visualComponents(int $pageNo): array
    {
        $this->pageBlocks($pageNo);

        return [];
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
                $direction = (string) ($block['direction'] ?? 'vertical');
                $origin = (array) ($block['origin'] ?? [0, 0]);
                $bubblesGap = (float) ($block['bubblesGap'] ?? 0);
                $labelsGap = (float) ($block['labelsGap'] ?? 0);
                $bubbleDimensions = (array) ($block['bubbleDimensions'] ?? [40, 40]);
                $labels = [];
                foreach ((array) ($block['fieldLabels'] ?? []) as $declaration) {
                    $labels = [...$labels, ...NativeBundle::expandLabel((string) $declaration)];
                }
                foreach ($labels as $labelIndex => $label) {
                    if (str_starts_with($label, 'APPLICANT_NUMBER_')) {
                        $identityLabels[] = $label;
                    }
                    foreach ($values as $valueIndex => $option) {
                        $x = (float) ($origin[0] ?? 0);
                        $y = (float) ($origin[1] ?? 0);
                        if ($direction === 'vertical') {
                            $x += $labelIndex * $labelsGap;
                            $y += $valueIndex * $bubblesGap;
                        } else {
                            $x += $valueIndex * $bubblesGap;
                            $y += $labelIndex * $labelsGap;
                        }
                        $w = (int) ($bubbleDimensions[0] ?? 40);
                        $h = (int) ($bubbleDimensions[1] ?? 40);
                        $fields[] = [
                            'page' => $page['page_no'],
                            'block' => $blockName,
                            'label' => $label,
                            'option' => (string) $option,
                            'pixel' => [
                                'x' => (int) round($x),
                                'y' => (int) round($y),
                                'w' => $w,
                                'h' => $h,
                            ],
                            'region' => ['x' => $x / 2480.0, 'y' => $y / 3508.0, 'w' => (float) $w / 2480.0, 'h' => (float) $h / 3508.0],
                        ];
                    }
                }
            }
        }
        $identityLabels = array_values(array_unique($identityLabels));

        return [
            'manifest_version' => 'nexiam.omr.review-manifest.v1',
            'template_code' => 'FIXTURE',
            'template_version' => 1,
            'bundle_hash' => $this->bundleHash,
            'file_hashes' => ['fiducial.png' => hash('sha256', 'fixture-marker-bytes')],
            'engine' => ['name' => 'OMRChecker', 'commit' => str_repeat('c', 40)],
            'registration_mode' => $this->registrationMode()->value,
            'qr_mode' => $this->qrMode()->value,
            'compiled_pages' => $this->compiledPages(),
            'paper' => ['width_mm' => 210.0, 'height_mm' => 297.0],
            'insets_px' => ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0],
            'visual_components' => [],
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
        $manifest = $this->reviewManifest();
        $items = [];
        $position = 1;
        $grouped = [];
        foreach ($manifest['fields'] as $field) {
            if ((int) $field['page'] === $pageNo && ! str_starts_with($field['label'], 'APPLICANT_NUMBER_')) {
                $grouped[$field['label']][] = $field;
            }
        }
        foreach ($grouped as $label => $fields) {
            $options = [];
            $order = 1;
            foreach ($fields as $field) {
                $options[] = new OmrOptionGeometry(
                    code: (string) $field['option'],
                    region: new OmrRegion(
                        (float) $field['region']['x'],
                        (float) $field['region']['y'],
                        (float) $field['region']['w'],
                        (float) $field['region']['h'],
                    ),
                    displayOrder: $order++,
                );
            }
            $areaCode = preg_match('/^([A-Z0-9_]+)_Q_\d+$/', (string) $label, $m) ? $m[1] : 'DEFAULT';
            $qNo = preg_match('/_Q_(\d+)$/', (string) $label, $m) ? (int) $m[1] : $position;
            $items[] = new OmrItemGeometry(
                position: $position++,
                areaCode: $areaCode,
                questionNo: $qNo,
                nativeLabel: (string) $label,
                region: new OmrRegion(
                    (float) $fields[0]['region']['x'],
                    (float) $fields[0]['region']['y'],
                    (float) ($fields[count($fields) - 1]['region']['x'] + $fields[count($fields) - 1]['region']['w'] - $fields[0]['region']['x']),
                    (float) $fields[0]['region']['h'],
                ),
                options: $options,
            );
        }

        $sourceImage = OmrSourceImage::fromBytes($imageBytes);

        return new OmrDetectionRequest(
            registrationMode: $this->registrationMode(),
            templateVersionId: 1,
            candidateTargetId: null,
            sourceImage: $sourceImage,
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: 'fixture-run',
            correlationId: 'omr:fixture',
            imageBytes: $imageBytes,
            inputHash: $sourceImage->inputHash,
            imageMime: $imageMime,
            imageWidth: $sourceImage->width,
            imageHeight: $sourceImage->height,
            areas: [],
            items: $items,
            identityRegions: [],
            nativeBundleHash: $this->nativeBundleHash(),
            manifestHash: $this->manifestHash(),
            configHash: $this->configHash(),
            bookletHash: hash('sha256', 'fixture-booklet'),
            nativeTemplate: $this->nativeTemplate($pageNo),
            nativeConfigJson: json_encode($this->nativeConfig(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            nativeBundleMetadata: [
                'files' => ['config.json' => $this->configHash()],
                'mapping_hash' => $this->mappingHash(),
                'registration_mode' => $this->registrationMode()->value,
                'qr_mode' => $this->qrMode()->value,
                'compiled_pages' => $this->compiledPages(),
            ],
            reviewManifest: $manifest,
            engineName: 'OMRChecker',
            engineCommit: str_repeat('c', 40),
            patchIdentity: 'nexiam-fixture-v1',
            expectedFieldLabels: array_keys($grouped),
            expectedOptionCodes: [],
            providerDeadline: 30.0,
            pageNo: $pageNo,
        );
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
