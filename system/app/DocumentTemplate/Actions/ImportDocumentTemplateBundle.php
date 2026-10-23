<?php

namespace App\DocumentTemplate\Actions;

use App\DocumentTemplate\Packaging\Adapters\ReportTemplateBundleAdapter;
use App\DocumentTemplate\Packaging\Adapters\ResultSheetBundleAdapter;
use App\DocumentTemplate\Packaging\Contracts\DocumentTemplateBundleAdapterInterface;
use App\DocumentTemplate\Packaging\DocumentBundlePackage;
use InvalidArgumentException;

class ImportDocumentTemplateBundle
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
     * @param  array<string, mixed>  $options
     * @return array{template_id: int|string, is_active: false}
     */
    public function handle(string $zipPath, array $options = []): array
    {
        $bundle = $this->package->extractAndValidate($zipPath);
        $surfaceKey = $bundle['manifest']['surface_key'] ?? null;
        if (! is_string($surfaceKey) || ! isset($this->adapters[$surfaceKey])) {
            throw new InvalidArgumentException('Unsupported document template bundle surface.');
        }

        $result = $this->adapters[$surfaceKey]->import($bundle, $options);

        return ['template_id' => $result['template_id'], 'is_active' => false];
    }
}
