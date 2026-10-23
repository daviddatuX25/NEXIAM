<?php

namespace App\DocumentTemplate\Actions;

use App\DocumentTemplate\Packaging\Adapters\ReportTemplateBundleAdapter;
use App\DocumentTemplate\Packaging\Adapters\ResultSheetBundleAdapter;
use App\DocumentTemplate\Packaging\Contracts\DocumentTemplateBundleAdapterInterface;
use App\DocumentTemplate\Packaging\DocumentBundlePackage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExportDocumentTemplateBundle
{
    /** @var array<string, DocumentTemplateBundleAdapterInterface> */
    private array $adapters;

    public function __construct(
        private DocumentBundlePackage $package,
        ResultSheetBundleAdapter $resultSheetAdapter,
        ReportTemplateBundleAdapter $reportTemplateAdapter,
    ) {
        $this->adapters = [
            $resultSheetAdapter->surfaceKey() => $resultSheetAdapter,
            $reportTemplateAdapter->surfaceKey() => $reportTemplateAdapter,
        ];
    }

    /**
     * @return array{zip_path: string, filename: string}
     */
    public function handle(string $surfaceKey, int|string $templateId): array
    {
        $adapter = $this->adapters[$surfaceKey] ?? throw new InvalidArgumentException('Unsupported document template bundle surface.');
        $export = $adapter->export($templateId);
        $zipPath = $this->package->createArchive($export['files'], $export['manifest']);
        $name = (string) ($export['manifest']['template']['name'] ?? 'template');
        $filename = Str::slug($surfaceKey.'-'.$name).'.zip';

        return ['zip_path' => $zipPath, 'filename' => $filename];
    }
}
