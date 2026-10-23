<?php

namespace App\DocumentTemplate\Rendering;

use App\DocumentTemplate\Contracts\ComponentRendererInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use Closure;

final class ComponentRendererRegistry
{
    /** @var array<string, ComponentRendererInterface> */
    private array $renderers = [];

    /** @var array<string, Closure(array<string, mixed>, array<string, mixed>, LayoutParadigm): string> */
    private array $closures = [];

    public function __construct(bool $loadDefaults = true)
    {
        if ($loadDefaults) {
            $this->loadDefaultRenderers();
        }
    }

    public static function withDefaults(): self
    {
        return new self(true);
    }

    private function loadDefaultRenderers(): void
    {
        $bindings = [
            'identity' => ['applicant_reference', 'family_name', 'first_name', 'middle_name', 'suffix', 'sex', 'gwa', 'course_applied_code', 'strand'],
            'exam_details' => ['exam_date', 'exam_time', 'room_name', 'course_applied_code'],
            'score_table' => ['scores_rows'],
            'overall' => ['overall_pct'],
            'recommendation' => ['recommended_course', 'counselor_comments'],
        ];
        $types = ['header', 'identity', 'exam_details', 'field', 'score_table', 'overall', 'recommendation', 'signature', 'note', 'footer', 'text', 'paragraph', 'metadata_grid', 'summary_metrics', 'image', 'dynamic_image', 'dynamic_qr', 'divider', 'spacer', 'kpi_grid', 'data_table'];

        foreach ($types as $type) {
            $this->register(new CallbackComponentRenderer(
                $type,
                static fn (array $component, array $props, LayoutParadigm $paradigm): string => self::renderDefault($type, $component, $props),
                static function (array $props) use ($type, $bindings): array {
                    $implicit = $bindings[$type] ?? [];
                    if ($type === 'signature') {
                        $implicit[] = (string) ($props['binding'] ?? 'counselor_name');
                    }
                    if ($type === 'field' && ($props['binding'] ?? '') !== '') {
                        $implicit[] = (string) $props['binding'];
                    }
                    if ($type === 'dynamic_image') {
                        $implicit[] = (string) ($props['binding'] ?? 'applicant_photo_2x2');
                    }
                    if ($type === 'dynamic_qr') {
                        $implicit[] = (string) ($props['binding'] ?? 'verification_qr');
                    }
                    if ($type === 'kpi_grid') {
                        $metrics = is_array($props['metrics'] ?? null)
                            ? $props['metrics']
                            : [['binding' => 'total_examinees'], ['binding' => 'total_passed'], ['binding' => 'passing_rate']];
                        $metrics = array_values(array_filter(
                            $metrics,
                            static fn ($item): bool => ! is_array($item) || ($item['checked'] ?? true) !== false,
                        ));
                        foreach ($metrics as $metric) {
                            if (is_array($metric) && is_string($metric['binding'] ?? null) && $metric['binding'] !== '') {
                                $implicit[] = $metric['binding'];
                            }
                        }
                    }
                    if ($type === 'data_table') {
                        $repeatKey = (string) ($props['repeat_key'] ?? 'repeat_key');
                        $columns = array_values(array_filter(
                            $props['columns'] ?? [],
                            static fn ($item): bool => ! is_array($item) || ($item['checked'] ?? true) !== false,
                        ));
                        foreach ($columns as $column) {
                            if (is_array($column)) {
                                $key = (string) ($column['key'] ?? $column['binding'] ?? '');
                                if ($key !== '') {
                                    $implicit[] = str_contains($key, '.') ? $key : $repeatKey.'.'.$key;
                                }
                            }
                        }
                    }

                    return array_values(array_unique($implicit));
                },
            ));
        }
    }

    public function register(ComponentRendererInterface $renderer): void
    {
        $this->renderers[$renderer->type()] = $renderer;
        unset($this->closures[$renderer->type()]);
    }

    /** @param Closure(array<string, mixed>, array<string, mixed>, LayoutParadigm): string $renderer */
    public function registerClosure(string $type, Closure $renderer): void
    {
        $this->closures[$type] = $renderer;
        unset($this->renderers[$type]);
    }

    public function get(string $type): ?ComponentRendererInterface
    {
        if ($this->renderers === [] && $this->closures === []) {
            $this->loadDefaultRenderers();
        }
        if (isset($this->renderers[$type])) {
            return $this->renderers[$type];
        }
        if (! isset($this->closures[$type])) {
            return null;
        }

        return new CallbackComponentRenderer($type, $this->closures[$type], static fn (array $props): array => []);
    }

    public function has(string $type): bool
    {
        return isset($this->renderers[$type]) || isset($this->closures[$type]);
    }

    /** @return list<string> */
    public function types(): array
    {
        $types = array_values(array_unique([...array_keys($this->renderers), ...array_keys($this->closures)]));
        sort($types);

        return $types;
    }

    /** @param array<string, mixed> $component @param array<string, mixed> $props */
    private static function renderDefault(string $type, array $component, array $props): string
    {
        $style = (string) ($component['_render_style'] ?? '');
        $border = e((string) (($props['appearance']['border_color'] ?? '#cbd5e1')));
        $cell = 'border:1px solid '.$border.';padding:1.5mm;';
        $text = static fn (string $value): string => e($value);

        return match ($type) {
            'header' => '<section class="result-sheet-header" style="'.$style.'"><h1 style="'.self::partStyle($props, 'title', 'margin:0;').'">'.$text((string) ($props['title'] ?? '{{institution_name}}')).'</h1><p style="'.self::partStyle($props, 'subtitle', 'margin:1.5mm 0 0;').'">'.$text((string) ($props['subtitle'] ?? '{{institution_exam_name}}')).'</p></section>',
            'identity' => '<section class="result-sheet-identity" style="'.$style.'"><table style="width:100%;border-collapse:collapse;"><tbody><tr><td style="'.$cell.'">Applicant No.</td><td style="'.$cell.'">'.$text('{{applicant_reference}}').'</td><td style="'.$cell.'">Name</td><td style="'.$cell.'">'.$text('{{family_name}}, {{first_name}} {{middle_name}} {{suffix}}').'</td></tr><tr><td style="'.$cell.'">Sex / GWA</td><td style="'.$cell.'">'.$text('{{sex}}').' / '.$text('{{gwa}}').'</td><td style="'.$cell.'">Course / Strand</td><td style="'.$cell.'">'.$text('{{course_applied_code}}').' / '.$text('{{strand}}').'</td></tr></tbody></table></section>',
            'exam_details' => '<p class="result-sheet-exam-details" style="'.$style.'">Date of Examination: '.$text('{{exam_date}}').' ('.$text('{{exam_time}}').') · Room: '.$text('{{room_name}}').' · Course: '.$text('{{course_applied_code}}').'</p>',
            'field' => '<div class="result-sheet-field" style="'.$style.'"><strong style="'.self::partStyle($props, 'label').'">'.$text((string) ($props['label'] ?? 'Field')).':</strong> <span style="'.self::partStyle($props, 'value').'">'.$text((string) (($props['binding'] ?? '') !== '' ? '{{'.$props['binding'].'}}' : '')).'</span></div>',
            'score_table' => '<section class="result-sheet-score-table" style="'.$style.'"><h2 style="'.self::partStyle($props, 'title', 'margin:0 0 2mm;').'">'.$text((string) ($props['title'] ?? 'Record of Scores')).'</h2><table style="width:100%;border-collapse:collapse;"><thead><tr><th style="'.$cell.'text-align:left;">Aptitude Factor</th><th style="'.$cell.'text-align:left;">{{score_table_raw_heading}}</th><th style="'.$cell.'text-align:left;">{{score_table_final_heading}}</th></tr></thead><tbody class="scores-rows-placeholder"></tbody></table></section>',
            'overall' => '<p class="result-sheet-overall" style="'.$style.'"><strong style="'.self::partStyle($props, 'label').'">{{overall_heading}}</strong> <span style="'.self::partStyle($props, 'value').'">'.$text('{{overall_pct}}').'</span></p>',
            'recommendation' => '<section class="result-sheet-recommendation" style="'.$style.'"><strong style="'.self::partStyle($props, 'heading').'">Recommended Program:</strong> <span style="'.self::partStyle($props, 'body').'">'.$text('{{recommended_course}}').'</span><p style="'.self::partStyle($props, 'note', 'margin:2mm 0 0;').'">'.$text('{{counselor_comments}}').'</p></section>',
            'signature' => '<div class="result-sheet-signature" style="'.$style.'text-align:center;"><div style="height:10mm;border-bottom:1px solid '.$border.';margin:0 auto 2mm;max-width:70%;"></div><strong>'.$text((string) (($props['binding'] ?? '') !== '' ? '{{'.$props['binding'].'}}' : '{{counselor_name}}')).'</strong><br><span>'.$text((string) ($props['label'] ?? 'Counselor')).'</span></div>',
            'note' => '<p class="result-sheet-note" style="'.$style.'font-style:italic;">'.$text((string) ($props['text'] ?? '')).'</p>',
            'footer' => '<p class="result-sheet-footer" style="'.$style.'">'.$text((string) ($props['text'] ?? '')).'</p>',
            'text', 'paragraph' => '<p class="result-sheet-text" style="'.$style.'">'.$text((string) ($props['text'] ?? '')).'</p>',
            'metadata_grid' => '<div class="result-sheet-metadata-grid" style="'.$style.'">'.$text((string) ($props['text'] ?? '')).'</div>',
            'summary_metrics' => self::kpiGrid($style, $props),
            'image' => self::image($style, $props),
            'dynamic_image' => self::dynamicImage($style, $props),
            'dynamic_qr' => self::dynamicQr($style, $props),
            'divider' => '<div class="result-sheet-divider" style="'.$style.'height:0;padding:0;border:0;border-top:'.max(1, (float) ($props['appearance']['border_width_px'] ?? 1)).'px '.e((string) ($props['appearance']['border_style'] ?? 'solid')).' '.$border.';"></div>',
            'spacer' => '<div class="result-sheet-spacer" style="'.$style.'"></div>',
            'kpi_grid' => self::kpiGrid($style, $props),
            'data_table' => self::dataTable($style, $props),
            default => '',
        };
    }

    /** @param array<string, mixed> $props */
    private static function partStyle(array $props, string $part, string $extra = ''): string
    {
        $typography = is_array($props['text_styles'][$part] ?? null)
            ? $props['text_styles'][$part]
            : (is_array($props['typography'] ?? null) ? $props['typography'] : []);

        return 'font-family:'.e((string) ($typography['font_family'] ?? 'Arial, Helvetica, sans-serif')).';font-size:'.(float) ($typography['font_size_pt'] ?? 10).'pt;font-weight:'.(int) ($typography['font_weight'] ?? 400).';color:'.e((string) ($typography['text_color'] ?? '#0f172a')).';text-align:'.e((string) ($typography['text_align'] ?? 'left')).';line-height:'.(float) ($typography['line_height'] ?? 1.2).';letter-spacing:'.(float) ($typography['letter_spacing_px'] ?? 0).'px;'.$extra;
    }

    /** @param array<string, mixed> $props */
    private static function image(string $style, array $props): string
    {
        $src = (string) ($props['src'] ?? '');
        if ($src === '' || ! preg_match('/^(data:image\/(png|jpe?g|webp);base64,|https:\/\/)/i', $src)) {
            return '<div style="'.$style.'border:1px dashed #999;text-align:center;">Image</div>';
        }

        return '<img style="'.$style.'object-fit:contain;" src="'.e($src).'" alt="'.e((string) ($props['alt'] ?? 'Template image')).'">';
    }

    /** @param array<string, mixed> $props */
    private static function dynamicImage(string $style, array $props): string
    {
        $binding = (string) ($props['binding'] ?? 'applicant_photo_2x2');
        $alt = e((string) ($props['alt'] ?? 'Candidate Photo'));
        $fit = (string) ($props['fit'] ?? 'cover');
        $fallback = (string) ($props['fallback'] ?? 'affix_box');

        return '<div class="component-dynamic-image" style="'.$style.'overflow:hidden;text-align:center;">'
            .'<img style="width:100%;height:100%;object-fit:'.e($fit).';" src="{{'.$binding.'}}" alt="'.$alt.'" data-fallback="'.e($fallback).'">'
            .'</div>';
    }

    /** @param array<string, mixed> $props */
    private static function dynamicQr(string $style, array $props): string
    {
        $binding = (string) ($props['binding'] ?? 'verification_qr');
        $alt = e((string) ($props['alt'] ?? 'Verification QR Code'));

        return '<div class="component-dynamic-qr" style="'.$style.'text-align:center;overflow:hidden;">'
            .'<img class="applicant-qr-image" style="display:block;width:100%;height:100%;aspect-ratio:1/1;object-fit:contain;" src="{{'.$binding.'}}" alt="'.$alt.'">'
            .'</div>';
    }

    /** @param array<string, mixed> $props */
    private static function kpiGrid(string $style, array $props): string
    {
        $metrics = is_array($props['metrics'] ?? null)
            ? $props['metrics']
            : [['label' => 'Total Examinees', 'binding' => 'total_examinees'], ['label' => 'Total Passed', 'binding' => 'total_passed'], ['label' => 'Passing Rate', 'binding' => 'passing_rate']];
        $metrics = array_values(array_filter(
            $metrics,
            static fn ($item): bool => ! is_array($item) || ($item['checked'] ?? true) !== false,
        ));
        $boxes = '';
        foreach ($metrics as $metric) {
            if (! is_array($metric)) {
                continue;
            }
            $binding = (string) ($metric['binding'] ?? '');
            $boxes .= '<td style="border:1px solid #cbd5e1;padding:3mm;text-align:center;"><strong style="'.self::partStyle($props, 'label', 'display:block;margin-bottom:1mm;').'">'.e((string) ($metric['label'] ?? '')).'</strong><div style="'.self::partStyle($props, 'value').'">'.e($binding === '' ? '' : '{{'.$binding.'}}').'</div></td>';
        }

        return '<section class="report-kpi-grid" style="'.$style.'"><table style="width:100%;border-collapse:collapse;"><tbody><tr>'.$boxes.'</tr></tbody></table></section>';
    }

    /** @param array<string, mixed> $props */
    private static function dataTable(string $style, array $props): string
    {
        $columns = array_values(array_filter(
            is_array($props['columns'] ?? null) ? $props['columns'] : [],
            static fn ($item): bool => ! is_array($item) || ($item['checked'] ?? true) !== false,
        ));
        $repeatKey = (string) ($props['repeat_key'] ?? 'repeat_key');
        $headers = '';
        $cells = '';
        foreach ($columns as $column) {
            if (! is_array($column)) {
                continue;
            }
            $key = (string) ($column['key'] ?? $column['binding'] ?? '');
            $headers .= '<th style="border:1px solid #cbd5e1;padding:1.5mm;text-align:left;'.self::partStyle($props, 'heading').'">'.e((string) ($column['label'] ?? $key)).'</th>';
            $placeholder = $key === '' ? '' : (str_contains($key, '.') ? '{{'.$key.'}}' : '{{'.$repeatKey.'.'.$key.'}}');
            $cells .= '<td style="border:1px solid #cbd5e1;padding:1.5mm;'.self::partStyle($props, 'body').'">'.e($placeholder).'</td>';
        }

        $title = trim((string) ($props['title'] ?? ''));
        $titleHtml = $title === ''
            ? ''
            : '<h3 style="'.self::partStyle($props, 'title', 'margin:0 0 2mm;').'">'.e($title).'</h3>';

        return '<section class="report-data-table" style="'.$style.'">'.$titleHtml.'<table style="width:100%;border-collapse:collapse;"><thead style="display:table-header-group;"><tr>'.$headers.'</tr></thead><tbody><tr style="page-break-inside:avoid;">'.$cells.'</tr></tbody></table></section>';
    }
}

/** @internal */
final class CallbackComponentRenderer implements ComponentRendererInterface
{
    /** @param Closure(array<string, mixed>, array<string, mixed>, LayoutParadigm): string $render @param Closure(array<string, mixed>): list<string> $bindings */
    public function __construct(private readonly string $componentType, private readonly Closure $render, private readonly Closure $bindings) {}

    public function type(): string
    {
        return $this->componentType;
    }

    public function renderHtml(array $component, array $props, LayoutParadigm $paradigm): string
    {
        return ($this->render)($component, $props, $paradigm);
    }

    public function implicitBindings(array $props): array
    {
        return ($this->bindings)($props);
    }
}
