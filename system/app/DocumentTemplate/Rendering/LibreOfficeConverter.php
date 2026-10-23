<?php

namespace App\DocumentTemplate\Rendering;

use RuntimeException;
use Symfony\Component\Process\Process;

class LibreOfficeConverter
{
    public function isAvailable(): bool
    {
        $path = config('result-sheet.libreoffice_path');

        return is_string($path) && $path !== '' && is_executable($path);
    }

    public function convertToPdf(string $documentPath, ?float $timeoutSeconds = null): string
    {
        if ($timeoutSeconds !== null && (! is_finite($timeoutSeconds) || $timeoutSeconds <= 0)) {
            throw new \InvalidArgumentException('Conversion timeout must be a positive finite number.');
        }

        if (! $this->isAvailable()) {
            throw new RuntimeException('libreoffice_unavailable');
        }

        if (! is_file($documentPath)) {
            throw new RuntimeException("Document not found: {$documentPath}");
        }

        $outputDir = null;
        $profileDir = null;

        try {
            $outputDir = $this->createTempDir('lo_out_');
            $profileDir = $this->createTempDir('lo_profile_');
            $process = new Process([
                config('result-sheet.libreoffice_path'),
                '--headless',
                '--norestore',
                '-env:UserInstallation=file://'.str_replace('\\', '/', $profileDir),
                '--convert-to', 'pdf',
                '--outdir', $outputDir,
                $documentPath,
            ], timeout: $timeoutSeconds ?? (float) config('result-sheet.conversion_timeout', 120));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('LibreOffice conversion failed: '.$process->getErrorOutput());
            }

            $pdfPath = $outputDir.DIRECTORY_SEPARATOR.pathinfo($documentPath, PATHINFO_FILENAME).'.pdf';
            if (! is_file($pdfPath)) {
                throw new RuntimeException('Expected PDF output not found: '.$pdfPath);
            }

            $contents = file_get_contents($pdfPath);
            if (! is_string($contents)) {
                throw new RuntimeException('Unable to read converted PDF.');
            }

            return $contents;
        } finally {
            if ($profileDir !== null) {
                $this->removeDir($profileDir);
            }
            if ($outputDir !== null) {
                $this->removeDir($outputDir);
            }
        }
    }

    protected function createTempDir(string $prefix): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $path = $base.DIRECTORY_SEPARATOR.$prefix.bin2hex(random_bytes(8));

        if (! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create LibreOffice temp directory.');
        }

        return $path;
    }

    private function removeDir(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir() && ! $entry->isLink()) {
                $this->removeDir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($directory);
    }
}
