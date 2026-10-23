<?php

namespace App\DocumentTemplate\Contracts;

use App\DocumentTemplate\Enums\LayoutParadigm;

interface ComponentRendererInterface
{
    public function type(): string;

    /**
     * @param  array<string, mixed>  $component
     * @param  array<string, mixed>  $props
     */
    public function renderHtml(array $component, array $props, LayoutParadigm $paradigm): string;

    /**
     * @param  array<string, mixed>  $props
     * @return list<string>
     */
    public function implicitBindings(array $props): array;
}
