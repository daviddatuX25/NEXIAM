<?php

namespace App\ResultSheet\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Models\Admission\AdmissionAttempt;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Data\ResultSheetDataProvider;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;

final class ResultSheetSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly ResultSheetDataProvider $dataProvider) {}

    public function surfaceKey(): string
    {
        return 'result_sheet';
    }

    public function title(): string
    {
        return 'Result Sheet';
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Absolute;
    }

    /**
     * @return array<string, mixed>
     */
    public function placeholderCatalog(): array
    {
        return [
            'required' => TemplateFieldAliasCatalog::required(),
            'recommended' => TemplateFieldAliasCatalog::recommended(),
            'all' => array_values(array_unique(array_merge(
                ResultSheetBuilderSchema::allowedBindings(),
                TemplateFieldAliasCatalog::staticKeys(),
                array_keys(TemplateFieldAliasCatalog::domainAliases()),
            ))),
            'aliases' => TemplateFieldAliasCatalog::aliases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleContext(): array
    {
        return ResultSheetBuilderSchema::sampleContext();
    }

    public function repeatingRowKey(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function repeatingRegions(): array
    {
        return [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function componentCatalog(): array
    {
        return ResultSheetBuilderSchema::componentCatalog();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultBlueprint(string $paperSize = 'a4', string $orientation = 'portrait'): array
    {
        return ResultSheetBuilderSchema::defaultDocument($paperSize, $orientation);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function resolveData(array $filters): array
    {
        $attemptId = $filters['attempt_id'] ?? $filters['admission_attempt_id'] ?? null;
        if (! is_numeric($attemptId) || (int) $attemptId < 1) {
            return $this->sampleContext();
        }

        $attempt = AdmissionAttempt::query()->find((int) $attemptId);

        return $attempt === null ? $this->sampleContext() : $this->dataProvider->forAttempt($attempt);
    }
}
