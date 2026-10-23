<?php

namespace App\DocumentTemplate\Rendering;

use App\DocumentTemplate\Enums\LayoutParadigm;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;

final class DocumentBlueprintCompiler
{
    public function __construct(private readonly ComponentRendererRegistry $renderers) {}

    /** @param array<string, mixed> $document */
    public function compile(array $document): string
    {
        $page = is_array($document['page'] ?? null) ? $document['page'] : [];
        $margin = (float) ($page['margin_mm'] ?? 0);
        $width = (float) ($page['width_mm'] ?? 210) - ($margin * 2);
        $height = (float) ($page['height_mm'] ?? 297) - ($margin * 2);
        $paradigm = LayoutParadigm::tryFrom((string) ($document['layout_paradigm'] ?? 'absolute')) ?? LayoutParadigm::Absolute;
        $components = [];
        $templates = is_array($document['component_templates'] ?? null) ? $document['component_templates'] : [];

        foreach (is_array($document['components'] ?? null) ? $document['components'] : [] as $component) {
            if (! is_array($component)) {
                continue;
            }
            $components[] = $this->compileComponent($component, $paradigm, $templates);
        }

        if ($paradigm === LayoutParadigm::Flow) {
            return '<div class="print-template document-flow-builder" style="position:relative;width:'.$width.'mm;margin:0 auto;display:block;">'.implode('', $components).'</div>';
        }

        return '<div class="print-template result-sheet-builder" style="position:relative;width:'.$width.'mm;min-height:'.$height.'mm;margin:0 auto;">'.implode('', $components).'</div>';
    }

    /** @param array<string, mixed> $document @return list<string> */
    public function usedBindings(array $document): array
    {
        $bindings = [];
        $walk = function (mixed $value) use (&$walk, &$bindings): void {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }

                return;
            }
            if (! is_string($value)) {
                return;
            }
            preg_match_all('/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/', $value, $matches);
            foreach ($matches[1] ?? [] as $binding) {
                $bindings[$binding] = true;
            }
        };
        $walk($document);

        foreach (is_array($document['components'] ?? null) ? $document['components'] : [] as $component) {
            if (! is_array($component) || ! is_string($component['type'] ?? null)) {
                continue;
            }
            $renderer = $this->renderers->get($component['type']);
            if ($renderer === null) {
                continue;
            }
            $props = is_array($component['props'] ?? null) ? $component['props'] : [];
            foreach ($renderer->implicitBindings($props) as $binding) {
                if ($binding !== '') {
                    $bindings[$binding] = true;
                }
            }
        }

        $keys = array_keys($bindings);
        sort($keys);

        return $keys;
    }

    /** @param array<string, mixed> $component @param array<string, array<string, mixed>> $templates */
    private function compileComponent(array $component, LayoutParadigm $paradigm, array $templates): string
    {
        $type = (string) ($component['type'] ?? '');
        $renderer = $this->renderers->get($type);
        if ($renderer === null) {
            return '';
        }
        $props = is_array($component['props'] ?? null) ? $component['props'] : [];
        $style = $this->componentStyle($component, $props, $paradigm, $type);
        $customTemplate = is_array($templates[$type] ?? null) ? trim((string) ($templates[$type]['html'] ?? '')) : '';
        if ($customTemplate !== '') {
            if (class_exists(ResultSheetBuilderSchema::class)
                && ResultSheetBuilderSchema::templateDiagnostics($customTemplate) !== []) {
                return '';
            }

            return '<div style="'.$style.'">'.$this->renderComponentTemplate($customTemplate, $component, $props).'</div>';
        }
        $component['_render_style'] = $style;

        return $renderer->renderHtml($component, $props, $paradigm);
    }

    /** @param array<string, mixed> $component @param array<string, mixed> $props */
    private function renderComponentTemplate(string $template, array $component, array $props): string
    {
        $slots = [
            '{{slot:title}}' => e((string) ($props['title'] ?? '{{institution_name}}')),
            '{{slot:subtitle}}' => e((string) ($props['subtitle'] ?? '{{institution_exam_name}}')),
            '{{slot:label}}' => e((string) ($props['label'] ?? $component['type'] ?? 'Field')),
            '{{slot:value}}' => e((string) (($props['binding'] ?? '') !== '' ? '{{'.$props['binding'].'}}' : 'Sample value')),
            '{{slot:text}}' => e((string) ($props['text'] ?? $props['title'] ?? 'Sample value')),
        ];

        return strtr($template, $slots);
    }

    /** @param array<string, mixed> $component @param array<string, mixed> $props */
    private function componentStyle(array $component, array $props, LayoutParadigm $paradigm, string $type): string
    {
        $typography = is_array($props['typography'] ?? null) ? $props['typography'] : [];
        $appearance = is_array($props['appearance'] ?? null) ? $props['appearance'] : [];
        $spacing = is_array($props['spacing'] ?? null) ? $props['spacing'] : [];
        $borderWidth = (float) ($appearance['border_width_px'] ?? 0);
        $border = $borderWidth > 0
            ? $borderWidth.'px '.e((string) ($appearance['border_style'] ?? 'solid')).' '.e((string) ($appearance['border_color'] ?? '#cbd5e1'))
            : '0 solid transparent';
        $appearanceStyle = 'padding:'.(float) ($spacing['padding_mm'] ?? 2).'mm;font-family:'.e((string) ($typography['font_family'] ?? 'Arial, Helvetica, sans-serif')).';font-size:'.(float) ($typography['font_size_pt'] ?? 10).'pt;font-weight:'.(int) ($typography['font_weight'] ?? 400).';color:'.e((string) ($typography['text_color'] ?? '#0f172a')).';text-align:'.e((string) ($typography['text_align'] ?? 'left')).';line-height:'.(float) ($typography['line_height'] ?? 1.2).';letter-spacing:'.(float) ($typography['letter_spacing_px'] ?? 0).'px;background-color:'.e((string) ($appearance['background_color'] ?? 'transparent')).';border:'.$border.';border-radius:'.(float) ($appearance['border_radius_mm'] ?? 0).'mm;overflow:hidden;';

        if ($paradigm === LayoutParadigm::Flow) {
            $special = match ($type) {
                'signature' => 'position:relative;box-sizing:border-box;width:100%;page-break-inside:avoid;margin-top:6mm;',
                'data_table' => 'position:relative;box-sizing:border-box;width:100%;margin-bottom:4mm;',
                'dynamic_qr' => 'position:relative;box-sizing:border-box;width:'.(float) ($component['w'] ?? 0).'mm;height:'.(float) ($component['h'] ?? 0).'mm;max-width:100%;margin:0 auto 4mm;page-break-inside:avoid;',
                default => 'position:relative;box-sizing:border-box;width:100%;margin-bottom:4mm;page-break-inside:avoid;',
            };

            return $special.$appearanceStyle;
        }

        if ($type === 'dynamic_qr') {
            return 'position:absolute;box-sizing:border-box;left:'.(float) ($component['x'] ?? 0).'mm;top:'.(float) ($component['y'] ?? 0).'mm;width:'.(float) ($component['w'] ?? 0).'mm;height:'.(float) ($component['h'] ?? 0).'mm;'.$appearanceStyle;
        }

        return 'position:absolute;box-sizing:border-box;left:'.(float) ($component['x'] ?? 0).'mm;top:'.(float) ($component['y'] ?? 0).'mm;width:'.(float) ($component['w'] ?? 0).'mm;min-height:'.(float) ($component['h'] ?? 0).'mm;'.$appearanceStyle;

    }
}
