<?php

namespace App\DocumentTemplate\Contracts;

use App\DocumentTemplate\Enums\LayoutParadigm;

interface DocumentSurfaceInterface
{
    public function surfaceKey(): string;

    public function title(): string;

    public function layoutParadigm(): LayoutParadigm;

    /**
     * @return array<string, mixed>
     */
    public function placeholderCatalog(): array;

    /**
     * @return array<string, mixed>
     */
    public function sampleContext(): array;

    public function repeatingRowKey(): ?string;

    /**
     * @return list<string>
     */
    public function repeatingRegions(): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function componentCatalog(): array;

    /**
     * @return array<string, mixed>
     */
    public function defaultBlueprint(string $paperSize = 'a4', string $orientation = 'portrait'): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function resolveData(array $filters): array;
}
