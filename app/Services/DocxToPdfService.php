<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;

class DocxToPdfService
{
    public function __construct(
        protected LoggerInterface $logger,
    ) {}

    public function isAvailable(): bool
    {
        $path = config('docx.libreoffice_path');

        if ($path === '' || $path === null) {
            return false;
        }

        return PHP_OS_FAMILY === 'Windows' ? is_file($path) : is_executable($path);
    }

    public function convert(string $docxPath): string
    {
        if (! file_exists($docxPath)) {
            throw new \InvalidArgumentException("DOCX file not found: {$docxPath}");
        }

        $outputDir = $this->createTempDir();
        $profileDir = $this->createTempDir('lo_profile_');

        try {
            $profileUri = 'file:///'.str_replace('\\', '/', $profileDir);

            $command = [
                config('docx.libreoffice_path'),
                '--headless',
                '--norestore',
                '-env:UserInstallation='.$profileUri,
                '--convert-to', 'pdf',
                '--outdir', $outputDir,
                $docxPath,
            ];

            $timeout = config('docx.conversion_timeout', 120);

            $process = $this->runCommand($command, $timeout);

            if (! $process->isSuccessful()) {
                $this->logger->error('LibreOffice conversion failed', [
                    'command' => $process->getCommandLine(),
                    'exitCode' => $process->getExitCode(),
                    'stdout' => $process->getOutput(),
                    'stderr' => $process->getErrorOutput(),
                ]);

                throw new \RuntimeException(
                    'LibreOffice conversion failed: '.$process->getErrorOutput()
                );
            }

            $basename = pathinfo($docxPath, PATHINFO_FILENAME);
            $pdfPath = $outputDir.DIRECTORY_SEPARATOR.$basename.'.pdf';

            if (! file_exists($pdfPath)) {
                throw new \RuntimeException(
                    "Expected PDF output not found: {$pdfPath}"
                );
            }

            return file_get_contents($pdfPath);
        } finally {
            $this->removeDir($profileDir);
            $this->removeDir($outputDir);
        }
    }

    /**
     * @param  string[]  $docxPaths
     */
    public function convertBatch(array $docxPaths, int $copies = 1): string
    {
        if (empty($docxPaths)) {
            throw new \InvalidArgumentException('docxPaths must not be empty.');
        }

        if ($copies < 1) {
            throw new \InvalidArgumentException('copies must be at least 1.');
        }

        foreach ($docxPaths as $docxPath) {
            if (! file_exists($docxPath)) {
                throw new \InvalidArgumentException("DOCX file not found: {$docxPath}");
            }
        }

        // Convert all DOCX files in batches to avoid launching LibreOffice multiple times
        $pdfs = $this->convertMultiple($docxPaths);

        $allPdfs = [];
        for ($i = 0; $i < $copies; $i++) {
            foreach ($pdfs as $pdf) {
                $allPdfs[] = $pdf;
            }
        }

        if (count($allPdfs) === 1) {
            return $allPdfs[0];
        }

        $pdfunitePath = config('docx.pdfunite_path');

        if ($pdfunitePath && (PHP_OS_FAMILY === 'Windows' ? is_file($pdfunitePath) : is_executable($pdfunitePath))) {
            return $this->mergeWithPdfunite($allPdfs, $pdfunitePath);
        }

        return $this->mergeFallback($allPdfs);
    }

    /**
     * Convert multiple DOCX files to PDF in batches using single LibreOffice invocations.
     *
     * @param  string[]  $docxPaths
     * @return string[]  Array of PDF file contents in the same order as $docxPaths
     */
    public function convertMultiple(array $docxPaths): array
    {
        if (empty($docxPaths)) {
            return [];
        }

        // We chunk the files to avoid command line limits (Windows command limit is 8191 characters).
        // A chunk size of 40 is extremely safe and delivers massive speedup (95%+ reduction in executions).
        $chunkSize = 40;
        $chunks = array_chunk($docxPaths, $chunkSize);
        $pdfContents = [];

        foreach ($chunks as $chunk) {
            $outputDir = $this->createTempDir();
            $profileDir = $this->createTempDir('lo_profile_');

            try {
                $profileUri = 'file:///'.str_replace('\\', '/', $profileDir);

                $command = [
                    config('docx.libreoffice_path'),
                    '--headless',
                    '--norestore',
                    '-env:UserInstallation='.$profileUri,
                    '--convert-to', 'pdf',
                    '--outdir', $outputDir,
                ];

                foreach ($chunk as $path) {
                    $command[] = $path;
                }

                $timeout = config('docx.conversion_timeout', 120);

                $process = $this->runCommand($command, $timeout);

                if (! $process->isSuccessful()) {
                    $this->logger->error('LibreOffice batch conversion failed', [
                        'command' => $process->getCommandLine(),
                        'exitCode' => $process->getExitCode(),
                        'stdout' => $process->getOutput(),
                        'stderr' => $process->getErrorOutput(),
                    ]);

                    throw new \RuntimeException(
                        'LibreOffice batch conversion failed: '.$process->getErrorOutput()
                    );
                }

                // Read files in the original order of the chunk
                foreach ($chunk as $docxPath) {
                    $basename = pathinfo($docxPath, PATHINFO_FILENAME);
                    $pdfPath = $outputDir.DIRECTORY_SEPARATOR.$basename.'.pdf';

                    if (! file_exists($pdfPath)) {
                        throw new \RuntimeException(
                            "Expected PDF output not found: {$pdfPath}"
                        );
                    }

                    $pdfContents[] = file_get_contents($pdfPath);
                }
            } finally {
                $this->removeDir($profileDir);
                $this->removeDir($outputDir);
            }
        }

        return $pdfContents;
    }


    /**
     * @param  string[]  $pdfContents
     */
    protected function mergeWithPdfunite(array $pdfContents, string $pdfunitePath): string
    {
        $tempDir = $this->createTempDir('pdfunite_');
        $inputFiles = [];

        try {
            foreach ($pdfContents as $i => $content) {
                $inputPath = $tempDir.DIRECTORY_SEPARATOR."input_{$i}.pdf";
                file_put_contents($inputPath, $content);
                $inputFiles[] = $inputPath;
            }

            $outputPath = $tempDir.DIRECTORY_SEPARATOR.'merged.pdf';

            $command = array_merge([$pdfunitePath], $inputFiles, [$outputPath]);

            $process = $this->runCommand($command, (int) config('docx.conversion_timeout', 120));

            if (! $process->isSuccessful()) {
                $this->logger->error('pdfunite merge failed', [
                    'command' => $process->getCommandLine(),
                    'exitCode' => $process->getExitCode(),
                    'stdout' => $process->getOutput(),
                    'stderr' => $process->getErrorOutput(),
                ]);

                throw new \RuntimeException('pdfunite merge failed: '.$process->getErrorOutput());
            }

            return file_get_contents($outputPath);
        } finally {
            $this->removeDir($tempDir);
        }
    }

    /**
     * @param  string[]  $pdfContents
     */
    protected function mergeFallback(array $pdfContents): string
    {
        if (count($pdfContents) === 1) {
            return $pdfContents[0];
        }

        $tempDir = $this->createTempDir('fpdi_merge_');

        try {
            $pdf = new Fpdi;
            $pdf->SetAutoPageBreak(false);

            foreach ($pdfContents as $i => $content) {
                $tempPath = $tempDir.DIRECTORY_SEPARATOR."input_{$i}.pdf";
                file_put_contents($tempPath, $content);

                $pageCount = $pdf->setSourceFile($tempPath);

                for ($page = 1; $page <= $pageCount; $page++) {
                    $templateId = $pdf->importPage($page);
                    $size = $pdf->getTemplateSize($templateId);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            $outputPath = $tempDir.DIRECTORY_SEPARATOR.'merged.pdf';
            $pdf->Output('F', $outputPath);

            return file_get_contents($outputPath);
        } catch (\Throwable $e) {
            $this->logger->error('FPDI PDF merge failed', [
                'error' => $e->getMessage(),
                'pdfCount' => count($pdfContents),
            ]);

            throw new \RuntimeException('PDF merge failed: '.$e->getMessage(), 0, $e);
        } finally {
            $this->removeDir($tempDir);
        }
    }

    protected function createTempDir(string $prefix = 'lo_'): string
    {
        $baseDir = config('docx.temp_dir');

        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }

        $path = $baseDir.DIRECTORY_SEPARATOR.uniqid($prefix, true);

        mkdir($path, 0755, true);

        return $path;
    }

    protected function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    /**
     * Run an external command using Symfony Process.
     *
     * @param  string[]  $command
     */
    protected function runCommand(array $command, int $timeout): Process
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }
}
