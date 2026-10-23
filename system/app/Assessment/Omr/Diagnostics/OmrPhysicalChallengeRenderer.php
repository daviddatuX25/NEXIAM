<?php

namespace App\Assessment\Omr\Diagnostics;

use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrPageDimensionMath;
use App\Assessment\Omr\Canonical\OmrPdfPageRasterizer;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\Assessment\Omr\Canonical\OmrQrReservationResolver;
use App\Printing\PrintRenderer;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

use function array_is_list;
use function array_map;
use function array_values;
use function config;
use function count;
use function file_get_contents;
use function file_put_contents;
use function hash;
use function htmlspecialchars;
use function imagesx;
use function imagesy;
use function implode;
use function intdiv;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function ord;
use function preg_match;
use function str_split;
use function strpos;
use function substr;
use function tempnam;
use function unlink;

/**
 * Physical challenge pack renderer (OpenSpec 35.19, AD-7).
 *
 * Produces TWO separate artifacts from one proof plan:
 *
 *  (a) the exact printable challenge sheet — the canonical page raster
 *      machine-filled with the plan's expected vector (and calibration
 *      digits when an ID grid is mapped), plus the signed calibration-purpose
 *      QR and checksummed display code. Rendered server-side with GD at
 *      300 PPI; NO browser geometry, HTML, or CSS enters the sheet.
 *
 *  (b) a grouped human shading guide (HTML + PDF via the shared print
 *      renderer). Quick mode lists only the first/middle/last labels per
 *      section plus the full option legend, section list, and ID columns;
 *      full mode lists every expanded label.
 *
 * Repeated rendering of the same target/purpose produces byte-identical
 * sheets and guides (35.20 golden stability).
 */
final class OmrPhysicalChallengeRenderer implements OmrPhysicalChallengeRendererPort
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function __construct(
        private readonly OmrProofPlanner $planner,
        private readonly OmrPdfPageRasterizer $rasterizer,
        private readonly OmrQrCodeRenderer $qrRenderer,
        private readonly OmrCalibrationQrPayload $calibrationQr,
        private readonly PrintRenderer $pdf,
        private readonly OmrManifestBubbleShader $shader = new OmrManifestBubbleShader,
    ) {}

    /**
     * @param  array<string, int>  $insetsPx  full-paper inset ring (left/top/right/bottom px) mapping the marker frame to the printed paper
     * @return array{
     *     plan: OmrProofPlan,
     *     sheet: array{
     *         pdf_bytes: string,
     *         pdf_sha256: string,
     *         pages: list<array{page_no:int,bytes:string,sha256:string,width:int,height:int}>,
     *         qr_payloads: list<array{page_no:int,payload:string,payload_hash:string}>,
     *         challenge_code: string,
     *         display_code: string,
     *         sheet_hash: string,
     *     },
     *     guide: array{html:string,pdf:string,sha256:string,mode:string,sections:list<array{page_no:int,area:string,options:list<string>,labels:list<array{label:string,option:string}>}>,json:array<string,mixed>},
     *     sections: list<array{page_no:int,area:string,options:list<string>,labels:list<array{label:string,option:string}>}>,
     *     guide_json: array<string,mixed>,
     *     scenario_manifest: array<string,mixed>,
     *     scenario_manifest_hash: string,
     *     coverage_report: array<string,mixed>,
     *     coverage_hash: string,
     *     renderer_identity: array{version:string,hash:string},
     *     profile_identity: array<string,mixed>,
     *     geometry_identity: string,
     *     source_artifact_hashes: array<string,mixed>,
     *     transformed_artifact_hashes: array<string,mixed>,
     *     scenario_outcomes: array<string,array<string,mixed>>,
     * }
     */
    public function render(
        OmrDiagnosticTarget $target,
        ProofPurpose $purpose,
        string $pdfBytes,
        array $insetsPx = [],
        string $planVersion = OmrProofPlanner::CURRENT_PLAN_VERSION,
        ?string $method = null,
    ): array {
        $plan = $this->planner->plan($target, $purpose, $planVersion, $method);
        $full = $purpose !== ProofPurpose::QuickRehearsal;
        $manifest = $this->resolveScenarioManifest($target, $plan);
        $coverageReport = $manifest?->coverage()->report();

        $sheet = $this->renderSheet($target, $plan, $pdfBytes, $insetsPx, $manifest);
        $guide = $this->renderGuide($target, $plan, $full, $manifest);
        $rendererVersion = OmrManifestBubbleShader::RENDERER_VERSION;
        $rendererHash = hash('sha256', $rendererVersion);
        $profileIdentity = (array) ($manifest?->provenance['profile'] ?? []);
        $profileIdentity['hash'] = (string) ($manifest?->provenance['profile_hash'] ?? ($profileIdentity['hash'] ?? ''));
        $profileIdentity['version'] = isset($profileIdentity['version'])
            ? (int) $profileIdentity['version']
            : null;
        $pageHashes = [];
        foreach ($sheet['pages'] as $page) {
            $pageHashes[(string) $page['page_no']] = (string) $page['sha256'];
        }
        $sourceArtifactHashes = [
            'target_artifact' => (string) ($manifest?->provenance['artifact_hash'] ?? ''),
            'input_pdf' => hash('sha256', $pdfBytes),
        ];
        $transformedArtifactHashes = [
            'challenge_sheet' => (string) $sheet['sheet_hash'],
            'challenge_sheet_pdf' => (string) $sheet['pdf_sha256'],
            'challenge_sheet_pages' => $pageHashes,
            'guide_html' => (string) $guide['sha256'],
            'guide_pdf' => hash('sha256', (string) $guide['pdf']),
        ];
        $scenarioOutcomes = [];
        foreach ($manifest?->scenarios ?? [] as $scenario) {
            $scenarioOutcomes[(string) $scenario['id']] = [
                'status' => 'untested',
                'machine_state' => null,
                'capture_id' => null,
                'input_hash' => null,
                'evidence_hash' => null,
            ];
        }

        return [
            'plan' => $plan,
            'sheet' => $sheet,
            'guide' => $guide,
            'sections' => $guide['sections'],
            'guide_json' => $guide['json'],
            'scenario_manifest' => $manifest?->toArray(),
            'scenario_manifest_hash' => $manifest?->hash(),
            'coverage_report' => $coverageReport,
            'coverage_hash' => $coverageReport === null
                ? null
                : hash('sha256', json_encode($coverageReport, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'renderer_identity' => [
                'version' => $rendererVersion,
                'hash' => $rendererHash,
            ],
            'profile_identity' => $profileIdentity === ['hash' => '', 'version' => null] ? null : $profileIdentity,
            'geometry_identity' => $manifest === null ? null : (string) ($manifest->provenance['geometry_hash'] ?? ''),
            'source_artifact_hashes' => $sourceArtifactHashes,
            'transformed_artifact_hashes' => $transformedArtifactHashes,
            'scenario_outcomes' => $scenarioOutcomes,
        ];
    }

    /**
     * @return array{
     *     pages: list<array{page_no:int,bytes:string,sha256:string,width:int,height:int}>,
     *     qr_payloads: list<array{page_no:int,payload:string,payload_hash:string}>,
     *     challenge_code: string,
     *     display_code: string,
     *     sheet_hash: string,
     * }
     */
    private function renderSheet(
        OmrDiagnosticTarget $target,
        OmrProofPlan $plan,
        string $pdfBytes,
        array $insetsPx,
        ?OmrDiagnosticScenarioManifest $manifest,
    ): array {
        $geometryIndex = $this->shader->index($target->reviewManifest());

        foreach ($plan->calibrationDigits as $calLabel => $calDigit) {
            $found = false;
            foreach ($geometryIndex as $pageLabels) {
                if (isset($pageLabels[$calLabel])) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new OmrDiagnosticTargetException(
                    'challenge_geometry_missing',
                    "Manifest geometry missing for calibration label '{$calLabel}' on all pages."
                );
            }
        }

        for ($p = 1; $p <= $target->pageCount(); $p++) {
            $expectedForPage = $plan->expectedVectorForPage($p);
            foreach ($expectedForPage as $lbl => $opt) {
                // Validate every server-selected answer position even for the
                // hand method, but never send those answer marks to the
                // renderer. Hand sheets are intentionally blank for manual
                // shading; calibration digits remain machine-marked below.
                $this->shader->resolve($geometryIndex, $p, (string) $lbl, (string) $opt, false);
            }
        }

        $pdfPath = tempnam(sys_get_temp_dir(), 'nexiam-challenge-sheet-');
        if ($pdfPath === false || file_put_contents($pdfPath, $pdfBytes) === false) {
            if ($pdfPath !== false) {
                @unlink($pdfPath);
            }
            throw new RuntimeException('challenge_sheet_pdf_write_failed');
        }
        try {
            $rasterPages = $this->rasterizer->rasterize($pdfPath, ['ppi' => (int) config('omr.canonical.ppi', 300)]);
        } finally {
            @unlink($pdfPath);
        }
        if ($rasterPages === []) {
            throw new RuntimeException('challenge_sheet_raster_empty');
        }

        $keyId = (string) config('omr.qr-keyring.current', 'dev-v1');
        $pageCount = $plan->requiredPageCount;
        $ppi = (float) config('omr.canonical.ppi', 300);
        $displayCode = $this->displayCode($plan->challengeCode);
        $sheetPages = [];
        $qrPayloads = [];

        try {
            $expectedPageNos = array_map(
                static fn (array $page): int => (int) $page['page_no'],
                $plan->compiledPages,
            );
            $rasterPageNos = [];
            if (! array_is_list($rasterPages)) {
                throw new RuntimeException('challenge_sheet_page_set_mismatch');
            }
            foreach ($rasterPages as $page) {
                if (! is_array($page) || ! is_int($page['page_no'] ?? null)) {
                    throw new RuntimeException('challenge_sheet_page_set_mismatch');
                }
                $rasterPageNos[] = $page['page_no'];
            }
            if ($rasterPageNos !== $expectedPageNos) {
                throw new RuntimeException('challenge_sheet_page_set_mismatch');
            }
            foreach ($rasterPages as $page) {
                $pageNo = $page['page_no'];
                $sourcePath = (string) ($page['path'] ?? '');
                $pageWidth = (int) ($page['width'] ?? 0);
                $pageHeight = (int) ($page['height'] ?? 0);
                if ($sourcePath === '' || $pageWidth <= 0 || $pageHeight <= 0) {
                    throw new RuntimeException('challenge_sheet_raster_page_invalid');
                }

                $reservation = $plan->diagnosticQrReservation[$pageNo - 1] ?? null;
                if (! is_array($reservation) || ! is_int($reservation['page_no'] ?? null) || $reservation['page_no'] !== $pageNo) {
                    throw new RuntimeException('challenge_sheet_qr_reservation_missing');
                }
                foreach ([
                    'x_mm', 'y_mm', 'width_mm', 'height_mm', 'qr_x_mm', 'qr_y_mm',
                    'qr_size_mm', 'display_code_x_mm', 'display_code_y_mm', 'paper_width_mm', 'paper_height_mm',
                ] as $key) {
                    if (! is_numeric($reservation[$key] ?? null)) {
                        throw new RuntimeException('challenge_sheet_qr_reservation_missing');
                    }
                }

                $qrReviewManifest = $target->reviewManifest();
                $qrReviewManifest['qr_reservation'] = [
                    'x_mm' => (float) $reservation['qr_x_mm'],
                    'y_mm' => (float) $reservation['qr_y_mm'],
                    'width_mm' => (float) $reservation['qr_size_mm'],
                    'height_mm' => (float) $reservation['qr_size_mm'],
                ];
                $qrPlacement = OmrQrReservationResolver::resolvePx(
                    $qrReviewManifest,
                    $pageWidth,
                    $pageHeight,
                    $insetsPx,
                    $ppi,
                );

                $boxX = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['x_mm'], $ppi);
                $boxY = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['y_mm'], $ppi);
                $boxWidth = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['width_mm'], $ppi);
                $boxHeight = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['height_mm'], $ppi);
                $qrX = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['qr_x_mm'], $ppi);
                $qrY = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['qr_y_mm'], $ppi);
                $qrSize = OmrPageDimensionMath::pixelsFromMillimetres((float) $reservation['qr_size_mm'], $ppi);
                $captionWidth = max(1, strlen($displayCode) * 8);
                $captionHeight = max(8, (int) round(2.4 * $ppi / OmrQrReservationResolver::MM_PER_INCH));
                $captionRight = $qrPlacement['display_code_x'] + $captionWidth;
                $captionBottom = $qrPlacement['display_code_y'] + $captionHeight;
                if (! $qrPlacement['has_reservation']
                    || $qrPlacement['qr_x'] !== $qrX
                    || $qrPlacement['qr_y'] !== $qrY
                    || $qrPlacement['qr_size'] !== $qrSize
                    || $boxX < 0
                    || $boxY < 0
                    || $boxWidth <= 0
                    || $boxHeight <= 0
                    || $boxX + $boxWidth > $pageWidth
                    || $boxY + $boxHeight > $pageHeight
                    || $qrX < $boxX
                    || $qrY < $boxY
                    || $qrSize < 64
                    || $qrX + $qrSize > $boxX + $boxWidth
                    || $qrY + $qrSize > $boxY + $boxHeight
                    || $qrPlacement['display_code_x'] < $boxX
                    || $qrPlacement['display_code_y'] < $qrY + $qrSize
                    || $captionRight > $boxX + $boxWidth
                    || $captionBottom > $boxY + $boxHeight
                    || $captionRight > $pageWidth
                    || $captionBottom > $pageHeight) {
                    throw new RuntimeException('challenge_sheet_qr_reservation_out_of_bounds');
                }

                $qrResolution = max(320, $qrPlacement['qr_size']);
                $payload = $this->calibrationQr->sign($plan->challengeCode, $pageNo, $pageCount, $keyId);
                $qrBytes = $this->qrRenderer->png($payload, $qrResolution);
                $pageResult = $this->renderPageInChildProcess(
                    $sourcePath,
                    $pageNo,
                    $geometryIndex,
                    $plan->method === OmrProofPlan::METHOD_HAND_SHADED
                        ? []
                        : $plan->expectedVectorForPage($pageNo),
                    $plan->calibrationDigits,
                    $insetsPx,
                    $qrBytes,
                    $displayCode,
                    $qrReviewManifest,
                    $plan->method === OmrProofPlan::METHOD_HAND_SHADED || $plan->purpose !== ProofPurpose::QuickRehearsal
                        ? []
                        : array_values(array_filter(
                            $manifest?->scenarios ?? [],
                            static fn (array $scenario): bool => (int) ($scenario['item']['page_no'] ?? 0) === $pageNo,
                        )),
                );

                $sheetPages[] = [
                    'page_no' => $pageNo,
                    'bytes' => $pageResult['bytes'],
                    'sha256' => hash('sha256', $pageResult['bytes']),
                    'width' => $pageResult['width'],
                    'height' => $pageResult['height'],
                    'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
                ];
                $qrPayloads[] = [
                    'page_no' => $pageNo,
                    'payload' => $payload,
                    'payload_hash' => $this->calibrationQr->payloadHash($payload),
                ];
            }
        } finally {
            foreach ($rasterPages as $page) {
                if (isset($page['path']) && is_string($page['path'])) {
                    @unlink($page['path']);
                }
            }
        }
        $sheetHash = hash('sha256', json_encode([
            'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
            'scenario_manifest_hash' => $manifest?->hash(),
            'plan_hash' => $plan->planHash(),
            'pages' => array_map(
                static fn (array $page): array => ['page_no' => $page['page_no'], 'sha256' => $page['sha256']],
                $sheetPages,
            ),
            'qr_payloads' => array_map(
                static fn (array $entry): array => ['page_no' => $entry['page_no'], 'payload_hash' => $entry['payload_hash']],
                $qrPayloads,
            ),
            'display_code' => $displayCode,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $paperWidthMm = (float) ($target->reviewManifest()['paper']['width_mm'] ?? $target->reviewManifest()['paper_width_mm'] ?? 0);
        $paperHeightMm = (float) ($target->reviewManifest()['paper']['height_mm'] ?? $target->reviewManifest()['paper_height_mm'] ?? 0);
        $sheetPdfBytes = $this->imageBackedPdf($sheetPages, $paperWidthMm > 0 ? $paperWidthMm : null, $paperHeightMm > 0 ? $paperHeightMm : null);
        $sheetPdfSha256 = hash('sha256', $sheetPdfBytes);

        return [
            'pdf_bytes' => $sheetPdfBytes,
            'pdf_sha256' => $sheetPdfSha256,
            'pages' => $sheetPages,
            'qr_payloads' => $qrPayloads,
            'challenge_code' => $plan->challengeCode,
            'display_code' => $displayCode,
            'sheet_hash' => $sheetHash,
            'renderer_version' => OmrManifestBubbleShader::RENDERER_VERSION,
        ];
    }

    /**
     * @param  array<int, array<string, array<string, array{x: int, y: int, w: int, h: int}>>>  $geometryIndex
     * @param  array<string, string>  $expectedVector
     * @param  array<string, int>  $calibrationDigits
     * @param  array<string, int>  $insetsPx
     * @return array{bytes: string, width: int, height: int}
     */
    private function renderPageInChildProcess(
        string $sourcePath,
        int $pageNo,
        array $geometryIndex,
        array $expectedVector,
        array $calibrationDigits,
        array $insetsPx,
        string $qrBytes,
        string $displayCode,
        array $reviewManifest = [],
        array $scenarios = [],
    ): array {
        $outputPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-shade-out-');
        $optionsPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-shade-opt-');
        $qrPath = tempnam(sys_get_temp_dir(), 'nexiam-omr-shade-qr-');

        if ($outputPath === false || $optionsPath === false || $qrPath === false) {
            if ($outputPath !== false) {
                @unlink($outputPath);
            }
            if ($optionsPath !== false) {
                @unlink($optionsPath);
            }
            if ($qrPath !== false) {
                @unlink($qrPath);
            }
            throw new RuntimeException('challenge_sheet_temp_create_failed');
        }

        try {
            if (file_put_contents($qrPath, $qrBytes) === false) {
                throw new RuntimeException('challenge_sheet_qr_write_failed');
            }

            $optionsJson = json_encode([
                'page_no' => $pageNo,
                'geometry_index' => $geometryIndex,
                'expected_vector' => $expectedVector,
                'calibration_digits' => $calibrationDigits,
                'insets_px' => $insetsPx,
                'qr_path' => $qrPath,
                'display_code' => $displayCode,
                'review_manifest' => $reviewManifest,
                'scenarios' => $scenarios,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            if (file_put_contents($optionsPath, $optionsJson) === false) {
                throw new RuntimeException('challenge_sheet_options_write_failed');
            }

            $process = new Process([
                PHP_BINARY,
                '-d',
                'memory_limit=512M',
                base_path('bin/omr-shade-page.php'),
                $sourcePath,
                $outputPath,
                $optionsPath,
            ]);

            $timeout = (float) config('omr.canonical.limits.conversion_timeout_seconds_per_page', 300);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                $errorOutput = trim($process->getErrorOutput());
                if (preg_match('/render_target_exception:\s*([a-z0-9_]+):\s*(?:[a-z0-9_]+:\s*)?(.+)$/s', $errorOutput, $matches)) {
                    throw new OmrDiagnosticTargetException($matches[1], $matches[2]);
                }
                throw new RuntimeException('challenge_sheet_render_process_failed: '.($errorOutput !== '' ? $errorOutput : 'exit '.$process->getExitCode()));
            }

            if (! is_file($outputPath) || filesize($outputPath) === 0) {
                throw new RuntimeException('challenge_sheet_render_output_missing');
            }

            $imageInfo = @getimagesize($outputPath);
            if ($imageInfo === false || ($imageInfo['mime'] ?? null) !== 'image/png' || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
                throw new RuntimeException('challenge_sheet_render_output_invalid');
            }

            $outputBytes = file_get_contents($outputPath);
            if (! is_string($outputBytes) || $outputBytes === '') {
                throw new RuntimeException('challenge_sheet_render_output_empty');
            }

            return [
                'bytes' => $outputBytes,
                'width' => (int) $imageInfo[0],
                'height' => (int) $imageInfo[1],
            ];
        } finally {
            @unlink($outputPath);
            @unlink($optionsPath);
            @unlink($qrPath);
        }
    }

    /** @return array{0: int, 1: int} */
    public static function qrPlacement(\GdImage|int $imageOrWidth, int $size, ?int $height = null, array|OmrDiagnosticTarget|null $source = null, ?array $insetsPx = null): array
    {
        $width = $imageOrWidth instanceof \GdImage ? imagesx($imageOrWidth) : (int) $imageOrWidth;
        $h = $imageOrWidth instanceof \GdImage ? imagesy($imageOrWidth) : (int) ($height ?? 3508);
        $resolved = OmrQrReservationResolver::resolvePx($source, $width, $h, $insetsPx);

        return [$resolved['qr_x'], $resolved['qr_y']];
    }

    /**
     * @return array{html:string,pdf:string,sha256:string,mode:string,sections:list<array{page_no:int,area:string,options:list<string>,labels:list<array{label:string,option:string}>}>}
     */
    public function renderGuide(
        OmrDiagnosticTarget $target,
        OmrProofPlan $plan,
        bool $full,
        ?OmrDiagnosticScenarioManifest $manifest,
    ): array {
        $sections = $this->guideSections($target, $plan, $full, $manifest);
        $purposeLabel = match ($plan->purpose) {
            ProofPurpose::Digital => 'Digital proof',
            ProofPurpose::QuickRehearsal => 'Quick rehearsal',
            ProofPurpose::FullQualification => 'Full qualification',
        };
        $mode = $full ? 'full' : 'quick';
        $handShaded = $plan->method === OmrProofPlan::METHOD_HAND_SHADED;
        $title = $handShaded ? 'Shading instructions' : 'NEXIAM OMR Shading Guide';
        $lines = [];
        $lines[] = '<!doctype html>';
        $lines[] = '<html lang="en"><head><meta charset="utf-8">';
        $lines[] = '<title>'.htmlspecialchars($title).'</title>';
        $lines[] = '<style>'
            .'body{font-family:Helvetica,Arial,sans-serif;font-size:10pt;padding:14mm;color:#111;}'
            .'h1{font-size:15pt;margin:0 0 2mm;}'
            .'h2{font-size:12pt;margin:6mm 0 1mm;border-bottom:1px solid #999;padding-bottom:1mm;}'
            .'h3{font-size:10.5pt;margin:3mm 0 0.5mm;}'
            .'p,li{font-size:9.5pt;}'
            .'code{font-family:Menlo,Consolas,monospace;font-size:9pt;}'
            .'table{border-collapse:collapse;margin:1mm 0 3mm;width:100%;}'
            .'th,td{border:1px solid #bbb;padding:0.8mm 2mm;font-size:9pt;text-align:left;vertical-align:top;}'
            .'th{background:#f0f0f0;}'
            .'</style></head><body>';
        $lines[] = '<h1>'.htmlspecialchars($title).'</h1>';
        $lines[] = '<p><strong>Challenge:</strong> <code>'.htmlspecialchars($plan->challengeCode).'</code>'
            .' &nbsp; <strong>Purpose:</strong> '.htmlspecialchars($purposeLabel)
            .' &nbsp; <strong>Mode:</strong> '.htmlspecialchars($mode)
            .($plan->method !== null ? ' &nbsp; <strong>Method:</strong> '.htmlspecialchars($plan->method) : '')
            .($manifest !== null ? ' &nbsp; <strong>Scenario Manifest:</strong> <code>'.htmlspecialchars(substr($manifest->hash(), 0, 16)).'…</code>' : '')
            .'</p>';
        $lines[] = '<p><strong>Plan hash:</strong> <code>'.htmlspecialchars($plan->planHash()).'</code></p>';
        $lines[] = '<p><strong>Instruction:</strong> '.htmlspecialchars($this->instructionOf($plan)).'</p>';
        if ($handShaded) {
            $lines[] = '<p><strong>Follow every listed scenario exactly.</strong> The unusual marks are deliberate detector checks, not ordinary answer-sheet instructions. Record a deviation only when your physical mark differs from the row instruction.</p>';
            $lines[] = '<p><strong>Leave every unlisted answer row blank.</strong> Those rows are intentionally unkeyed controls: no answer is expected. A listed row that says “leave blank” is different—it is an expected-answer row used to test missing-answer review.</p>';
            $lines[] = '<p><strong>Medium:</strong> HB/#2 pencil or dark black pen. Record the selected medium with the capture.</p>';
            $lines[] = '<h3>Physical capture evidence checklist</h3>';
            $lines[] = '<table><thead><tr><th>Evidence field</th><th>Recorded value</th><th>Qualification requirement</th></tr></thead><tbody>';
            $lines[] = '<tr><td>Printer</td><td>________________________________________</td><td>Required for qualification</td></tr>';
            $lines[] = '<tr><td>Paper</td><td>________________________________________</td><td>Required for qualification</td></tr>';
            $lines[] = '<tr><td>Capture device</td><td>________________________________________</td><td>Required for qualification</td></tr>';
            $lines[] = '<tr><td>Method / Medium</td><td>HB/#2 pencil &nbsp; [ ] &nbsp; Dark black pen &nbsp; [ ]</td><td>HB/#2 pencil or black pen only</td></tr>';
            $lines[] = '<tr><td>Execution deviation</td><td>Check below if any item was marked differently from instruction</td><td>Audited deviation (not detector failure)</td></tr>';
            $lines[] = '</tbody></table>';
        } else {
            $lines[] = '<p><strong>Generated diagnostic sheet:</strong> marks intentionally include dark, faint, partial, multiple, mismatched, and blank expected-answer cases. Do not add or alter marks before capture.</p>';
        }
        $lines[] = '<p><strong>Print at 100% / actual size — do not fit to page.</strong></p>';

        foreach ($sections as $index => $section) {
            if ($index === 0 || $sections[$index - 1]['page_no'] !== $section['page_no']) {
                $lines[] = '<h2>Page '.$section['page_no'].'</h2>';
            }
            $lines[] = '<h3>Section '.htmlspecialchars($section['area']).'</h3>';
            $lines[] = '<p>Options: '.implode(', ', array_map('htmlspecialchars', $section['options'])).'</p>';
            $instructionHeading = $handShaded ? 'What to mark' : 'Generated diagnostic mark';
            $lines[] = '<table><thead><tr><th>Field</th><th>Scenario ID</th><th>'.htmlspecialchars($instructionHeading).'</th><th>Expected review result</th>'.($handShaded ? '<th>Deviation</th>' : '').'</tr></thead><tbody>';
            foreach ($section['labels'] as $entry) {
                $lines[] = '<tr><td><code>'.htmlspecialchars($entry['label']).'</code></td>'
                    .'<td><code>'.htmlspecialchars((string) ($entry['scenario_id'] ?? '')).'</code></td>'
                    .'<td>'.htmlspecialchars((string) ($entry['instruction'] ?? '')).'</td>'
                    .'<td>'.htmlspecialchars((string) ($entry['expected_result'] ?? '')).'</td>'
                    .($handShaded ? '<td>[ ] Deviated</td>' : '')
                    .'</tr>';
            }
            $lines[] = '</tbody></table>';
        }
        $identity = $this->guideIdentity($target, $plan);
        $identityColumns = [];
        if ($identity !== []) {
            $lines[] = '<h2>Applicant number columns (calibration)</h2>';
            $lines[] = '<p>Shade one digit per column. This is a calibration-only expectation — no applicant is identified.</p>';
            $lines[] = '<table><thead><tr><th>Column</th><th>Digit</th></tr></thead><tbody>';
            foreach ($identity as $label => $digit) {
                $lines[] = '<tr><td><code>'.htmlspecialchars($label).'</code></td><td>'.htmlspecialchars((string) $digit).'</td></tr>';
                $identityColumns[] = [
                    'label' => (string) $label,
                    'digit' => (int) $digit,
                ];
            }
            $lines[] = '</tbody></table>';
        }

        $lines[] = '</body></html>';
        $html = implode("\n", $lines);
        $pdfBytes = $this->pdf->render($html, ['format' => 'a4', 'orientation' => 'portrait']);

        $optionCounts = [];
        foreach ($plan->expectedVector as $option) {
            $optionCounts[(string) $option] = ($optionCounts[(string) $option] ?? 0) + 1;
        }
        ksort($optionCounts);
        $guideJson = [
            'schema_version' => 'v2',
            'title' => $title,
            'mode' => $mode,
            'method' => $plan->method,
            'challenge_code' => $plan->challengeCode,
            'plan_hash' => $plan->planHash(),
            'expected_vector_hash' => $plan->expectedVectorHash,
            'scenario_manifest_hash' => $manifest?->hash(),
            'manifest_version' => $manifest?->manifestVersion ?? OmrDiagnosticScenarioManifest::MANIFEST_VERSION,
            'lane' => $manifest?->lane ?? ($handShaded ? OmrDiagnosticScenarioManifest::LANE_HAND_SHADED : OmrDiagnosticScenarioManifest::LANE_GENERATED),
            'required_page_count' => $plan->requiredPageCount,
            'sample_count' => count($plan->sampleManifest),
            'sample_manifest' => $plan->sampleManifest,
            'allowed_media' => $plan->allowedMedia,
            'option_counts' => $optionCounts,
            'sections' => $sections,
            'unsampled_rows' => [
                'expected_answer' => null,
                'instruction' => 'Leave blank — intentionally unkeyed control.',
            ],
            'evidence_fields' => ['printer', 'paper', 'device', 'method', 'execution_deviation'],
            'qualification_requirements' => [
                'printer' => 'required',
                'paper' => 'required',
                'device' => 'required',
                'medium' => $handShaded ? 'required' : 'none',
            ],
            'instructions' => $this->instructionOf($plan),
            'calibration_disclaimer' => 'Applicant number columns are for calibration only — no applicant is identified.',
            'identity_columns' => $identityColumns,
        ];

        return [
            'html' => $html,
            'pdf' => $pdfBytes,
            'sha256' => hash('sha256', $html),
            'mode' => $mode,
            'sections' => $sections,
            'json' => $guideJson,
        ];
    }

    /**
     * @param  list<array{page_no:int,bytes:string,width:int,height:int}>  $pages
     */
    public function imageBackedPdf(array $pages, ?float $widthMm = null, ?float $heightMm = null): string
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->SetAutoPageBreak(false);
        $temporary = [];
        try {
            foreach ($pages as $page) {
                $path = tempnam(sys_get_temp_dir(), 'nexiam-challenge-sheet-pdf-');
                if ($path === false || file_put_contents($path, $page['bytes']) === false) {
                    if ($path !== false) {
                        @unlink($path);
                    }
                    throw new RuntimeException('challenge_sheet_pdf_image_write_failed');
                }
                $temporary[] = $path;
                $pageWidthMm = $widthMm ?? OmrPageDimensionMath::millimetresFromPixels($page['width'], 300);
                $pageHeightMm = $heightMm ?? OmrPageDimensionMath::millimetresFromPixels($page['height'], 300);
                $orientation = $pageWidthMm > $pageHeightMm ? 'L' : 'P';
                $pdf->AddPage($orientation, [$pageWidthMm, $pageHeightMm]);
                $pdf->Image($path, 0, 0, $pageWidthMm, $pageHeightMm, 'PNG');
            }

            $pdfBytes = (string) $pdf->Output('S');

            return (string) preg_replace('/\/CreationDate \(D:[^)]+\)/', "/CreationDate (D:20000101000000+00'00')", $pdfBytes);
        } finally {
            foreach ($temporary as $path) {
                @unlink($path);
            }
        }
    }

    /**
     * Grouped shading entries: one section per page + area. Quick mode keeps
     * only the first/middle/last labels plus every section, its full option
     * legend, and every ID column; full mode lists every expanded label.
     *
     * @return list<array{page_no:int,area:string,options:list<string>,labels:list<array<string,mixed>>}>
     */
    private function guideSections(OmrDiagnosticTarget $target, OmrProofPlan $plan, bool $full, ?OmrDiagnosticScenarioManifest $manifest = null): array
    {
        $scenarioIndex = [];
        if ($manifest !== null) {
            foreach ($manifest->scenarios as $scenario) {
                $label = (string) ($scenario['item']['label'] ?? '');
                $pageNo = (int) ($scenario['item']['page_no'] ?? 0);
                $scenarioIndex[$pageNo.'|'.$label] = $scenario;
            }
        }
        $optionsByLabel = [];
        foreach ((array) ($target->reviewManifest()['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['label'] ?? null) || ! is_string($field['option'] ?? null)) {
                continue;
            }
            $optionsByLabel[(string) $field['label']][] = (string) $field['option'];
        }

        $sections = [];
        foreach ($plan->pageInstructions as $instruction) {
            $pageNo = (int) $instruction['page_no'];
            $byArea = [];
            foreach ((array) ($instruction['labels'] ?? []) as $label) {
                if (preg_match('/^([A-Z0-9_]+)_Q_\d+$/', (string) $label, $matches) === 1) {
                    $byArea[$matches[1]][] = (string) $label;
                }
            }
            foreach ($byArea as $area => $labels) {
                $picked = $full ? $labels : $this->firstMiddleLast($labels);
                $options = $optionsByLabel[$labels[0] ?? ''] ?? [];
                $entries = [];
                foreach ($picked as $label) {
                    $scenario = $scenarioIndex[$pageNo.'|'.$label] ?? null;
                    $expectedOption = $scenario !== null
                        ? ($scenario['answer']['expected_option'] ?? null)
                        : ($plan->expectedVector[$label] ?? null);
                    $entries[] = [
                        'label' => $label,
                        'option' => (string) ($expectedOption ?? ''),
                        'scenario_id' => $scenario !== null ? (string) $scenario['id'] : null,
                        'mark_style' => $scenario !== null ? (string) ($scenario['mark']['style'] ?? '') : OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK,
                        'answer_condition' => $scenario !== null ? (string) ($scenario['answer']['condition'] ?? '') : OmrDiagnosticScenarioManifest::ANSWER_EXPECTED_MATCH,
                        'candidates' => $scenario !== null ? array_values((array) ($scenario['answer']['candidates'] ?? [])) : [(string) ($expectedOption ?? '')],
                        'expected_machine_class' => $scenario !== null ? (string) ($scenario['expected']['machine_class'] ?? '') : OmrDiagnosticScenarioManifest::EXPECTED_EXACT,
                        'instruction' => $this->scenarioInstruction($scenario, $expectedOption),
                        'expected_result' => $this->scenarioExpectedResult($scenario, $expectedOption),
                        'execution_deviation' => null,
                    ];
                }
                $sections[] = [
                    'page_no' => $pageNo,
                    'area' => $area,
                    'options' => array_values($options),
                    'labels' => $entries,
                ];
            }
        }

        return $sections;
    }

    /** @param array<string, mixed>|null $scenario */
    private function scenarioInstruction(?array $scenario, ?string $expectedOption): string
    {
        if ($scenario === null) {
            return $expectedOption === null
                ? 'Leave blank — no answer is expected for this unkeyed control.'
                : 'Shade '.$expectedOption.' fully and dark.';
        }

        $condition = (string) ($scenario['answer']['condition'] ?? '');
        $candidates = array_values(array_map('strval', (array) ($scenario['answer']['candidates'] ?? [])));
        if ($condition === OmrDiagnosticScenarioManifest::ANSWER_BLANK) {
            return 'Leave this row blank. Answer '.$expectedOption.' is expected; this intentionally tests a missing answer.';
        }

        $candidateText = implode(' and ', $candidates);
        $style = (string) ($scenario['mark']['style'] ?? OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK);
        $markInstruction = match ($style) {
            OmrDiagnosticScenarioManifest::MARK_NORMAL_DARK => 'fill each named bubble completely and dark',
            OmrDiagnosticScenarioManifest::MARK_FAINT => 'shade very lightly, only just visible',
            OmrDiagnosticScenarioManifest::MARK_PARTIAL_SMALL => 'make only a small partial mark inside each named bubble',
            OmrDiagnosticScenarioManifest::MARK_OFF_CENTER => 'shade off-center while still touching each named bubble',
            OmrDiagnosticScenarioManifest::MARK_OVERFILLED => 'overfill beyond each bubble edge',
            OmrDiagnosticScenarioManifest::MARK_CHECK_CROSS => 'draw a check or cross instead of filling',
            OmrDiagnosticScenarioManifest::MARK_ERASED => 'shade, erase, and leave a visible residue',
            OmrDiagnosticScenarioManifest::MARK_SMUDGED => 'make a visibly smudged mark',
            OmrDiagnosticScenarioManifest::MARK_STRAY => 'make a small stray mark near each named bubble',
            default => 'follow the declared mark style',
        };
        $intent = match ($condition) {
            OmrDiagnosticScenarioManifest::ANSWER_DEFINITE_MISMATCH => '; this is a deliberate mismatch from expected answer '.$expectedOption,
            OmrDiagnosticScenarioManifest::ANSWER_DOUBLE_WITH_EXPECTED => '; this deliberate double includes expected answer '.$expectedOption,
            OmrDiagnosticScenarioManifest::ANSWER_DOUBLE_WITHOUT_EXPECTED => '; this deliberate double excludes expected answer '.$expectedOption,
            OmrDiagnosticScenarioManifest::ANSWER_TRIPLE => '; this is a deliberate triple mark',
            OmrDiagnosticScenarioManifest::ANSWER_UNREADABLE => '; make the result intentionally hard to resolve',
            OmrDiagnosticScenarioManifest::ANSWER_BETWEEN_BUBBLE => '; keep the mark visibly between the two bubbles',
            default => '',
        };

        return 'Mark '.$candidateText.': '.$markInstruction.$intent.'.';
    }

    /** @param array<string, mixed>|null $scenario */
    private function scenarioExpectedResult(?array $scenario, ?string $expectedOption): string
    {
        if ($expectedOption === null) {
            return 'No expected answer — intentionally unkeyed control';
        }
        if ($scenario === null) {
            return 'Exact match to expected answer '.$expectedOption;
        }

        $machineClass = str_replace('_', ' ', (string) ($scenario['expected']['machine_class'] ?? ''));
        $condition = str_replace('_', ' ', (string) ($scenario['answer']['condition'] ?? ''));

        return ucfirst($machineClass).' · '.$condition.' · expected '.$expectedOption;
    }

    /** @return array<string, int> */
    private function guideIdentity(OmrDiagnosticTarget $target, OmrProofPlan $plan): array
    {
        if ($plan->calibrationDigits === []) {
            return [];
        }
        $mapped = [];
        foreach ((array) ($target->reviewManifest()['identity']['labels'] ?? []) as $label) {
            $mapped[(string) $label] = true;
        }
        if ($mapped === []) {
            return [];
        }

        return array_filter($plan->calibrationDigits, static fn (string $label): bool => isset($mapped[$label]), ARRAY_FILTER_USE_KEY);
    }

    /** @param list<string> $labels @return list<string> */
    private function firstMiddleLast(array $labels): array
    {
        $count = count($labels);
        if ($count <= 2) {
            return $labels;
        }
        $middle = $labels[intdiv($count - 1, 2)];
        $picked = [$labels[0], $middle, $labels[$count - 1]];

        return array_values(array_unique($picked));
    }

    private function instructionOf(OmrProofPlan $plan): string
    {
        $instruction = $plan->pageInstructions[0]['instruction'] ?? '';
        if (! is_string($instruction) || $instruction === '') {
            return OmrProofPlanner::PLAN_INSTRUCTION_DIGITAL;
        }

        return $instruction;
    }

    private function displayCode(string $challengeCode): string
    {
        $base = substr($this->crockford(hash('sha256', $challengeCode)), 0, 6);

        return 'CH-'.$base.$this->checkDigit($base);
    }

    private function crockford(string $bytes): string
    {
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split($bytes) as $character) {
            $buffer = ($buffer << 8) | ord($character);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::CROCKFORD[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $output .= self::CROCKFORD[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private function checkDigit(string $base): string
    {
        $sum = 0;
        foreach (str_split($base) as $character) {
            $sum += strpos(self::CROCKFORD, $character);
        }

        return self::CROCKFORD[$sum % strlen(self::CROCKFORD)];
    }

    private function resolveScenarioManifest(OmrDiagnosticTarget $target, OmrProofPlan $plan): ?OmrDiagnosticScenarioManifest
    {
        $lane = $plan->method === OmrProofPlan::METHOD_HAND_SHADED
            ? OmrDiagnosticScenarioManifest::LANE_HAND_SHADED
            : OmrDiagnosticScenarioManifest::LANE_GENERATED;

        if ($plan->purpose === ProofPurpose::Digital) {
            return null;
        }

        try {
            if ($plan->purpose === ProofPurpose::QuickRehearsal) {
                return OmrDiagnosticScenarioManifest::quickRehearsalV2(
                    targetOrContext: $target,
                    sampleManifest: $plan->sampleManifest,
                    expectedVector: $plan->expectedVector,
                    lane: $lane,
                );
            }

            return OmrDiagnosticScenarioManifest::fullDiagnosticV1($target, $lane);
        } catch (\Throwable) {
            return null;
        }
    }
}
