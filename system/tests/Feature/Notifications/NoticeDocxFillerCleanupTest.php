<?php

use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Support\DocxMacroRepairer;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;

function noticeFillerCleanupDirectory(): string
{
    $directory = sys_get_temp_dir().'/notice_filler_cleanup_'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    config(['result-sheet.temp_dir' => $directory]);

    return $directory;
}

test('DOCX filler creates only the returned private artifact and leaves original bytes unchanged', function (): void {
    $directory = noticeFillerCleanupDirectory();
    $source = base_path('database/seed-assets/result-sheets/generic_results.docx');
    $original = hash_file('sha256', $source);
    $path = null;
    try {
        $path = (new DocxTemplateFiller(new DocxMacroRepairer))->fill($source, ['applicant_name' => 'Synthetic Applicant']);
        expect(glob($directory.'/*'))->toBe([$path])->and(pathinfo($path, PATHINFO_EXTENSION))->toBe('docx')
            ->and(hash_file('sha256', $source))->toBe($original);
    } finally {
        if ($path !== null) {
            @unlink($path);
        }
        @rmdir($directory);
    }
});

test('DOCX filler removes the allocated filled artifact when source repair fails', function (): void {
    $directory = noticeFillerCleanupDirectory();
    try {
        expect(fn () => (new DocxTemplateFiller(new DocxMacroRepairer))->fill($directory.'/missing.docx', []))
            ->toThrow(RuntimeException::class, 'Unable to open document archive');
        expect(glob($directory.'/*'))->toBe([]);
    } finally {
        @rmdir($directory);
    }
});

test('notice PDF failed or partial writes remove the partially created file and owned directory', function (bool $returnFalse): void {
    $compiler = new class(Mockery::mock(DocxTemplateFiller::class), Mockery::mock(LibreOfficeConverter::class)) extends NoticeDocumentAttachmentCompiler
    {
        public ?string $attemptedPath = null;

        public bool $returnFalse = false;

        protected function writePdfBytes(string $path, string $contents): int|false
        {
            $this->attemptedPath = $path;
            $written = file_put_contents($path, 'partial', LOCK_EX);

            return $this->returnFalse ? false : $written;
        }
    };
    $compiler->returnFalse = $returnFalse;
    $write = new ReflectionMethod(NoticeDocumentAttachmentCompiler::class, 'writeVerifiedPdf');
    expect(fn () => $write->invoke($compiler, '%PDF-1.4 complete expected contents', 'partial.pdf', null))
        ->toThrow(RuntimeException::class, $returnFalse ? 'Unable to write compiled notice PDF' : 'checksum verification failed');
    expect($compiler->attemptedPath)->not->toBeNull()
        ->and(file_exists($compiler->attemptedPath))->toBeFalse()
        ->and(is_dir(dirname($compiler->attemptedPath)))->toBeFalse();
})->with([true, false]);

test('LibreOffice profile allocation failure removes already allocated output directory and intermediates', function (): void {
    $root = noticeFillerCleanupDirectory();
    $source = $root.'/source.docx';
    file_put_contents($source, 'synthetic source');
    $converter = new class($root) extends LibreOfficeConverter
    {
        public ?string $allocatedOutput = null;

        public function __construct(private readonly string $root) {}

        public function isAvailable(): bool
        {
            return true;
        }

        protected function createTempDir(string $prefix): string
        {
            if ($prefix === 'lo_profile_') {
                throw new RuntimeException('injected profile allocation failure');
            }
            $path = $this->root.'/'.$prefix.'owned';
            mkdir($path, 0700);
            file_put_contents($path.'/partial-output.pdf', '%PDF-1.4 partial');
            $this->allocatedOutput = $path;

            return $path;
        }
    };
    try {
        expect(fn () => $converter->convertToPdf($source))->toThrow(RuntimeException::class, 'injected profile allocation failure');
        expect($converter->allocatedOutput)->not->toBeNull()
            ->and(is_dir($converter->allocatedOutput))->toBeFalse()
            ->and(file_get_contents($source))->toBe('synthetic source');
    } finally {
        @unlink($source);
        @rmdir($root);
    }
});
