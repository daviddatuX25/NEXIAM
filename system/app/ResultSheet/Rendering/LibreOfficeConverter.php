<?php

namespace App\ResultSheet\Rendering;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * DOCX/ODT → PDF via headless LibreOffice (legacy-proven; the conversion the
 * old system ran synchronously in the request now runs inside queue workers).
 * Each invocation uses a fresh profile dir so concurrent jobs never collide.
 */
final class LibreOfficeConverter implements DocxToPdfConverter
{
    public function isAvailable(): bool
    {
        $path = config('result-sheet.libreoffice_path');

        if ($path === '' || $path === null) {
            return false;
        }

        return is_executable($path);
    }

    public function convert(string $documentPath): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('libreoffice_unavailable');
        }

        if (! is_file($documentPath)) {
            throw new RuntimeException("Document not found: {$documentPath}");
        }

        $outputDir = $this->createTempDir('lo_out_');
        $profileDir = $this->createTempDir('lo_profile_');

        try {
            $process = new Process([
                config('result-sheet.libreoffice_path'),
                '--headless',
                '--norestore',
                '-env:UserInstallation=file://'.str_replace('\\', '/', $profileDir),
                '--convert-to', 'pdf',
                '--outdir', $outputDir,
                $documentPath,
            ], timeout: (float) config('result-sheet.conversion_timeout', 120));

            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(
                    'LibreOffice conversion failed: '.$process->getErrorOutput()
                );
            }

            $basename = pathinfo($documentPath, PATHINFO_FILENAME);
            $pdfPath = $outputDir.DIRECTORY_SEPARATOR.$basename.'.pdf';

            if (! is_file($pdfPath)) {
                throw new RuntimeException(
                    "Expected PDF output not found: {$pdfPath} — stderr: ".$process->getErrorOutput().' — stdout: '.$process->getOutput()
                );
            }

            return (string) file_get_contents($pdfPath);
        } finally {
            $this->removeDir($profileDir);
            $this->removeDir($outputDir);
        }
    }

    private function createTempDir(string $prefix): string
    {
        $base = config('result-sheet.temp_dir', storage_path('app/temp/libreoffice'));

        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        $dir = tempnam($base, $prefix);

        if ($dir === false) {
            throw new RuntimeException('Unable to allocate a conversion temp dir.');
        }

        unlink($dir);
        mkdir($dir, 0755, true);

        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
