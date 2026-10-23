<?php

namespace App\DocumentTemplate\Packaging\Contracts;

interface DocumentTemplateBundleAdapterInterface
{
    public function surfaceKey(): string;

    /**
     * @return array{manifest: array<string, mixed>, files: array<string, string>}
     */
    public function export(int|string $templateId): array;

    /**
     * @param  array<string, mixed>  $bundle
     * @param  array<string, mixed>  $options
     * @return array{template_id: int|string, is_active: bool}
     */
    public function import(array $bundle, array $options = []): array;
}
