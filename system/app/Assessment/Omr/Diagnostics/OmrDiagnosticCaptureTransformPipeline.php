<?php

declare(strict_types=1);

namespace App\Assessment\Omr\Diagnostics;

use RuntimeException;
use Symfony\Component\Process\Process;

use function base_path;
use function file_get_contents;
use function file_put_contents;
use function getimagesize;
use function hash;
use function hash_file;
use function is_array;
use function is_file;
use function is_string;
use function json_encode;
use function sys_get_temp_dir;
use function tempnam;
use function trim;
use function unlink;

/**
 * Capture variant transform pipeline (OpenSpec 10.5).
 *
 * Applies manifest-recorded capture transforms (phone, rotation, perspective,
 * uneven lighting, low contrast, blur, shadow, partial page, and composite
 * transforms) to challenge rasters deterministically.
 */
final class OmrDiagnosticCaptureTransformPipeline
{
    /**
     * Apply a capture transform to a source image.
     *
     * @param  string  $sourcePathOrBytes  local file path or raw image bytes
     * @param  array<string, mixed>  $scenarioOrTransform  scenario, capture variant, or transform definition
     * @param  array<string, mixed>  $context  optional marker/inset context
     * @return array{
     *     bytes: string,
     *     width: int,
     *     height: int,
     *     input_hash: string,
     *     source_hash: string,
     *     condition: string,
     *     transform: array<string, mixed>,
     *     generator: array{name: string, path: string, sha256: ?string}
     * }
     */
    public function transform(string $sourcePathOrBytes, array $scenarioOrTransform, array $context = []): array
    {
        $transform = $this->resolveTransform($scenarioOrTransform);
        $condition = (string) ($transform['condition'] ?? $transform['kind'] ?? 'clean_scanner');
        $isPath = is_file($sourcePathOrBytes);
        $sourceBytes = $isPath ? (file_get_contents($sourcePathOrBytes) ?: '') : $sourcePathOrBytes;
        if ($sourceBytes === '') {
            throw new RuntimeException('capture_pipeline_source_empty');
        }
        $sourceHash = hash('sha256', $sourceBytes);

        // Identity transforms return the original bytes untouched
        if (in_array($condition, [
            OmrDiagnosticScenarioManifest::CAPTURE_CLEAN_SCANNER,
            'clean',
            'identity',
        ], true) && ($transform['kind'] ?? '') === 'identity') {
            $info = @getimagesizefromstring($sourceBytes);
            $width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
            $height = is_array($info) ? (int) ($info[1] ?? 0) : 0;

            return [
                'bytes' => $sourceBytes,
                'width' => $width,
                'height' => $height,
                'input_hash' => $sourceHash,
                'source_hash' => $sourceHash,
                'condition' => $condition,
                'transform' => $transform,
                'generator' => [
                    'name' => 'nexiam.omr.variant',
                    'path' => 'bin/omr-variant.php',
                    'sha256' => hash_file('sha256', base_path('bin/omr-variant.php')) ?: null,
                ],
            ];
        }

        $sourcePath = $isPath ? $sourcePathOrBytes : tempnam(sys_get_temp_dir(), 'nexiam-pipe-src-');
        $outputPath = tempnam(sys_get_temp_dir(), 'nexiam-pipe-out-');
        $optionsPath = tempnam(sys_get_temp_dir(), 'nexiam-pipe-opt-');

        if ($sourcePath === false || $outputPath === false || $optionsPath === false) {
            if (! $isPath && $sourcePath !== false) {
                @unlink($sourcePath);
            }
            if ($outputPath !== false) {
                @unlink($outputPath);
            }
            if ($optionsPath !== false) {
                @unlink($optionsPath);
            }
            throw new RuntimeException('capture_pipeline_temp_file_failed');
        }

        try {
            if (! $isPath && file_put_contents($sourcePath, $sourceBytes) === false) {
                throw new RuntimeException('capture_pipeline_source_write_failed');
            }

            $options = [
                'variant' => $condition,
                'transform' => $transform,
                'severity' => (string) ($scenarioOrTransform['severity'] ?? $context['severity'] ?? 'mild'),
                'markers' => (array) ($scenarioOrTransform['markers'] ?? $context['markers'] ?? []),
                'insets_px' => (array) ($scenarioOrTransform['insets_px'] ?? $context['insets_px'] ?? []),
            ];

            if (file_put_contents($optionsPath, json_encode($options, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) === false) {
                throw new RuntimeException('capture_pipeline_options_write_failed');
            }

            $process = new Process([
                PHP_BINARY,
                '-d',
                'memory_limit=512M',
                base_path('bin/omr-variant.php'),
                $sourcePath,
                $outputPath,
                $optionsPath,
            ]);
            $process->setTimeout(60.0);
            $process->run();

            if (! $process->isSuccessful()) {
                $err = trim($process->getErrorOutput());
                throw new RuntimeException('capture_pipeline_process_failed: '.($err !== '' ? $err : 'exit '.$process->getExitCode()));
            }

            if (! is_file($outputPath) || filesize($outputPath) === 0) {
                throw new RuntimeException('capture_pipeline_output_missing');
            }

            $outputBytes = file_get_contents($outputPath);
            if (! is_string($outputBytes) || $outputBytes === '') {
                throw new RuntimeException('capture_pipeline_output_empty');
            }

            $info = @getimagesize($outputPath);
            $width = is_array($info) ? (int) ($info[0] ?? 0) : 0;
            $height = is_array($info) ? (int) ($info[1] ?? 0) : 0;

            return [
                'bytes' => $outputBytes,
                'width' => $width,
                'height' => $height,
                'input_hash' => hash('sha256', $outputBytes),
                'source_hash' => $sourceHash,
                'condition' => $condition,
                'transform' => $transform,
                'generator' => [
                    'name' => 'nexiam.omr.variant',
                    'path' => 'bin/omr-variant.php',
                    'sha256' => hash_file('sha256', base_path('bin/omr-variant.php')) ?: null,
                ],
            ];
        } finally {
            if (! $isPath && is_file($sourcePath)) {
                @unlink($sourcePath);
            }
            if (is_file($outputPath)) {
                @unlink($outputPath);
            }
            if (is_file($optionsPath)) {
                @unlink($optionsPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $scenarioOrTransform
     * @return array<string, mixed>
     */
    private function resolveTransform(array $scenarioOrTransform): array
    {
        if (isset($scenarioOrTransform['capture']['transform']) && is_array($scenarioOrTransform['capture']['transform'])) {
            $transform = $scenarioOrTransform['capture']['transform'];
            $transform['condition'] = (string) ($scenarioOrTransform['capture']['condition'] ?? $transform['kind'] ?? '');

            return $transform;
        }

        if (isset($scenarioOrTransform['transform']) && is_array($scenarioOrTransform['transform'])) {
            $transform = $scenarioOrTransform['transform'];
            if (! isset($transform['condition']) && isset($scenarioOrTransform['condition'])) {
                $transform['condition'] = (string) $scenarioOrTransform['condition'];
            }

            return $transform;
        }

        if (isset($scenarioOrTransform['condition'])) {
            $condition = (string) $scenarioOrTransform['condition'];

            return [
                'kind' => $condition,
                'condition' => $condition,
            ];
        }

        if (isset($scenarioOrTransform['variant'])) {
            $variant = (string) $scenarioOrTransform['variant'];

            return [
                'kind' => $variant,
                'condition' => $variant,
            ];
        }

        return [
            'kind' => 'identity',
            'condition' => OmrDiagnosticScenarioManifest::CAPTURE_CLEAN_SCANNER,
        ];
    }
}
