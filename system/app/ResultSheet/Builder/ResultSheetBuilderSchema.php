<?php

namespace App\ResultSheet\Builder;

use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Support\ResultSheetMacroScanner;

/**
 * Versioned, renderer-neutral result-sheet builder document.
 * Components describe page intent; the compiler owns HTML output.
 */
final class ResultSheetBuilderSchema
{
    public const VERSION = 1;

    public const COMPILER_VERSION = 'result-sheet-builder-2';

    public const SCORE_TABLE_FIXED_HEIGHT_MM = 12;

    public const SCORE_TABLE_ROW_HEIGHT_MM = 6;

    /**
     * @return array<string, mixed>
     */
    public static function defaultDocument(string $paperSize = 'a4', string $orientation = 'portrait'): array
    {
        $dimensions = self::pageDimensions($paperSize, $orientation);
        $margin = 12;

        return [
            'schema_version' => self::VERSION,
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $dimensions['width_mm'],
                'height_mm' => $dimensions['height_mm'],
                'margin_mm' => $margin,
            ],
            'components' => [
                self::component('header', 0, 0, $dimensions['width_mm'] - ($margin * 2), 24, [
                    'title' => '{{institution_name}}',
                    'subtitle' => '{{institution_exam_name}} ({{institution_exam_acronym}})',
                    'align' => 'center',
                ], 'header-1'),
                self::component('text', 0, 27, $dimensions['width_mm'] - ($margin * 2), 8, [
                    'text' => 'Date of Examination: {{exam_date}} ({{exam_time}}) · Room: {{room_name}}',
                    'align' => 'center',
                ], 'text-1'),
                self::component('identity', 0, 39, $dimensions['width_mm'] - ($margin * 2), 34, [], 'identity-1'),
                self::component('score_table', 0, 78, $dimensions['width_mm'] - ($margin * 2), 60, [
                    'title' => 'Record of Scores',
                ], 'score_table-1'),
                self::component('recommendation', 0, 175, $dimensions['width_mm'] - ($margin * 2), 34, [], 'recommendation-1'),
                self::component('signature', 0, 218, $dimensions['width_mm'] - ($margin * 2), 22, [
                    'label' => 'Counselor',
                    'binding' => 'counselor_name',
                ], 'signature-1'),
                self::component('footer', 0, 258, $dimensions['width_mm'] - ($margin * 2), 10, [
                    'text' => 'Generated from the NEXIAM result-sheet template.',
                    'align' => 'center',
                ], 'footer-1'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function normalize(array $document): array
    {
        $page = is_array($document['page'] ?? null) ? $document['page'] : [];
        $paperSize = in_array($page['paper_size'] ?? 'a4', ['a4', 'legal', 'letter'], true)
            ? (string) ($page['paper_size'] ?? 'a4')
            : 'a4';
        $orientation = in_array($page['orientation'] ?? 'portrait', ['portrait', 'landscape'], true)
            ? (string) ($page['orientation'] ?? 'portrait')
            : 'portrait';
        $dimensions = self::pageDimensions($paperSize, $orientation);
        $components = is_array($document['components'] ?? null) ? $document['components'] : [];

        $normalizedComponents = [];
        $existingIds = [];
        $maxOrdinals = [];

        foreach ($components as $comp) {
            $rawComponent = is_array($comp) ? $comp : [];
            $type = is_string($rawComponent['type'] ?? null) ? (string) $rawComponent['type'] : 'text';
            $id = isset($rawComponent['id']) && is_string($rawComponent['id']) && trim($rawComponent['id']) !== ''
                ? trim($rawComponent['id'])
                : null;
            if ($id !== null) {
                $prefix = $type.'-';
                if (str_starts_with($id, $prefix)) {
                    $remainder = substr($id, strlen($prefix));
                    if (is_numeric($remainder)) {
                        $num = (int) $remainder;
                        if ($num > ($maxOrdinals[$type] ?? 0)) {
                            $maxOrdinals[$type] = $num;
                        }
                    }
                }
            }
        }

        foreach ($components as $comp) {
            $rawComponent = is_array($comp) ? $comp : [];
            $type = is_string($rawComponent['type'] ?? null) ? (string) $rawComponent['type'] : 'text';
            $id = isset($rawComponent['id']) && is_string($rawComponent['id']) && trim($rawComponent['id']) !== ''
                ? trim($rawComponent['id'])
                : null;

            if ($id === null || in_array($id, $existingIds, true)) {
                $nextOrdinal = ($maxOrdinals[$type] ?? 0) + 1;
                $maxOrdinals[$type] = $nextOrdinal;
                $id = $type.'-'.$nextOrdinal;
            }

            $existingIds[] = $id;
            $normalizedComponents[] = self::normalizeComponent($rawComponent, $id);
        }

        return [
            'schema_version' => (int) ($document['schema_version'] ?? self::VERSION),
            'layout_paradigm' => in_array($document['layout_paradigm'] ?? 'absolute', ['absolute', 'flow'], true)
                ? (string) ($document['layout_paradigm'] ?? 'absolute')
                : 'absolute',
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $dimensions['width_mm'],
                'height_mm' => $dimensions['height_mm'],
                'margin_mm' => max(5, min(30, (float) ($page['margin_mm'] ?? 12))),
            ],
            'components' => $normalizedComponents,
            'component_templates' => self::normalizeComponentTemplates($document['component_templates'] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<array{code: string, message: string, component_id?: string}>
     */
    public static function diagnostics(array $document, ?int $scoreRowCount = null, ?array $allowedBindings = null): array
    {
        $allowedBindings ??= self::allowedBindings();
        $allowedBindings = array_values(array_unique(array_merge(
            $allowedBindings,
            ['page_i', 'page_all', 'pg_i', 'pg_all'],
        )));
        $normalized = self::normalize($document);
        $isFlow = ($normalized['layout_paradigm'] ?? 'absolute') === 'flow';
        $diagnostics = [];
        $rawComponents = is_array($document['components'] ?? null) ? $document['components'] : [];
        $invalidGeometry = [];
        $scoreRows = max(3, $scoreRowCount ?? count(self::sampleContext()['scores_rows'] ?? []));
        foreach ($rawComponents as $index => $rawComponent) {
            $normalizedComponent = $normalized['components'][$index] ?? [];
            $id = (string) ($normalizedComponent['id'] ?? 'component-'.((int) $index + 1));

            if (! is_array($rawComponent)) {
                $diagnostics[] = ['code' => 'component_invalid', 'message' => "Component [{$id}] is not an object.", 'component_id' => $id];

                continue;
            }

            if (! $isFlow) {
                foreach (['x', 'y', 'w', 'h'] as $key) {
                    $value = $rawComponent[$key] ?? null;
                    if (! array_key_exists($key, $rawComponent) || ! is_numeric($value) || ! is_finite((float) $value)) {
                        $diagnostics[] = ['code' => 'geometry', 'message' => "Component [{$id}] has invalid {$key} geometry.", 'component_id' => $id];
                        $invalidGeometry[$index] = true;

                        continue;
                    }

                    if (in_array($key, ['w', 'h'], true) && (float) $value <= 0) {
                        $diagnostics[] = ['code' => 'geometry', 'message' => "Component [{$id}] has non-positive {$key} geometry.", 'component_id' => $id];
                        $invalidGeometry[$index] = true;
                    }
                }
            }

            $type = is_string($rawComponent['type'] ?? null) ? (string) $rawComponent['type'] : 'text';
            $props = is_array($rawComponent['props'] ?? null) ? $rawComponent['props'] : [];
            $binding = is_string($props['binding'] ?? null) ? trim($props['binding']) : '';
            self::collectMacroDiagnostics($props, $id, $diagnostics, $allowedBindings);

            if ($type === 'field' && $binding === '') {
                $diagnostics[] = ['code' => 'binding_missing', 'message' => "Field component [{$id}] has no binding.", 'component_id' => $id];
            }

            foreach (self::compilerBindings($type, $props) as $binding) {
                if (! in_array($binding, $allowedBindings, true)) {
                    $diagnostics[] = ['code' => 'binding_unknown', 'message' => "Component [{$id}] uses unknown binding [{$binding}].", 'component_id' => $id];
                }
            }
        }

        $version = (int) ($normalized['schema_version'] ?? 0);
        if ($version !== self::VERSION) {
            $diagnostics[] = ['code' => 'schema_version', 'message' => "Unsupported builder schema version [{$version}]."];
        }
        foreach ($normalized['component_templates'] as $type => $template) {
            $templateHtml = (string) ($template['html'] ?? '');
            foreach (self::templateDiagnostics($templateHtml) as $message) {
                $diagnostics[] = ['code' => 'component_template', 'message' => "Component template [{$type}]: {$message}"];
            }

            $slotFreeHtml = preg_replace('/\{\{\s*slot:(?:title|subtitle|label|value|text)\s*\}\}/', '', $templateHtml) ?? $templateHtml;
            $scan = ResultSheetMacroScanner::scan($slotFreeHtml);
            foreach ($scan['found'] as $binding) {
                if (! in_array($binding, $allowedBindings, true)) {
                    $diagnostics[] = ['code' => 'binding_unknown', 'message' => "Component template [{$type}] uses unknown binding [{$binding}]."];
                }
            }
            foreach ($scan['unresolved'] as $fragment) {
                $diagnostics[] = ['code' => 'unresolved_macro', 'message' => "Component template [{$type}] contains unresolved macro [{$fragment}]."];
            }
        }

        if (! $isFlow) {
            $page = $normalized['page'];
            $margin = (float) $page['margin_mm'];
            $contentWidth = (float) $page['width_mm'] - ($margin * 2);
            $contentHeight = (float) $page['height_mm'] - ($margin * 2);

            foreach ($normalized['components'] as $index => $component) {
                $id = (string) $component['id'];
                $type = (string) $component['type'];
                if (! in_array($type, self::componentTypes(), true)) {
                    $diagnostics[] = ['code' => 'component_type', 'message' => "Unknown component type [{$type}].", 'component_id' => $id];

                    continue;
                }

                if (! ($invalidGeometry[$index] ?? false)
                    && ((float) $component['x'] < 0 || (float) $component['y'] < 0
                        || ((float) $component['x'] + (float) $component['w']) > $contentWidth + 0.01
                        || ((float) $component['y'] + (float) $component['h']) > $contentHeight + 0.01)) {
                    $diagnostics[] = ['code' => 'overflow', 'message' => "Component [{$id}] exceeds the printable bounds.", 'component_id' => $id];
                }

                if ($type === 'score_table') {
                    $requiredHeight = self::SCORE_TABLE_FIXED_HEIGHT_MM + ($scoreRows * self::SCORE_TABLE_ROW_HEIGHT_MM);
                    if ((float) $component['h'] + 0.01 < $requiredHeight) {
                        $diagnostics[] = [
                            'code' => 'dynamic_overflow',
                            'message' => "Score table [{$id}] needs at least {$requiredHeight}mm for {$scoreRows} sample rows.",
                            'component_id' => $id,
                        ];
                    }
                }
            }

            foreach ($normalized['components'] as $index => $component) {
                foreach (array_slice($normalized['components'], $index + 1) as $other) {
                    if (self::overlaps($component, $other)) {
                        $diagnostics[] = [
                            'code' => 'overlap',
                            'message' => "Components [{$component['id']}] and [{$other['id']}] overlap.",
                            'component_id' => (string) $component['id'],
                        ];
                    }
                }
            }
        } else {
            foreach ($normalized['components'] as $component) {
                $type = (string) $component['type'];
                if (! in_array($type, self::componentTypes(), true)) {
                    $diagnostics[] = ['code' => 'component_type', 'message' => "Unknown component type [{$type}].", 'component_id' => (string) $component['id']];
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<string, mixed>  $value
     * @param  list<array{code: string, message: string, component_id?: string}>  $diagnostics
     */
    private static function collectMacroDiagnostics(array $value, string $componentId, array &$diagnostics, array $allowedBindings): void
    {
        $walk = function (mixed $item) use (&$walk, $componentId, &$diagnostics, $allowedBindings): void {
            if (is_array($item)) {
                foreach ($item as $child) {
                    $walk($child);
                }

                return;
            }

            if (! is_string($item)) {
                return;
            }

            $scan = ResultSheetMacroScanner::scan($item);
            foreach ($scan['found'] as $binding) {
                if (! in_array($binding, $allowedBindings, true)) {
                    $diagnostics[] = ['code' => 'binding_unknown', 'message' => "Component [{$componentId}] uses unknown binding [{$binding}].", 'component_id' => $componentId];
                }
            }
            foreach ($scan['unresolved'] as $fragment) {
                $diagnostics[] = ['code' => 'unresolved_macro', 'message' => "Component [{$componentId}] contains unresolved macro [{$fragment}].", 'component_id' => $componentId];
            }
        };

        $walk($value);
    }

    /**
     * @param  array<string, mixed>  $props
     * @return list<string>
     */
    private static function compilerBindings(string $type, array $props): array
    {
        $binding = is_string($props['binding'] ?? null) ? trim($props['binding']) : '';

        return match ($type) {
            'identity' => ['applicant_reference', 'examinee_number', 'sequence_number', 'family_name', 'first_name', 'middle_name', 'suffix', 'sex', 'gwa', 'gwa_original', 'gwa_scale', 'gwa_display', 'course_applied_code', 'last_school_enrolled', 'strand'],
            'exam_details' => ['exam_date', 'exam_time', 'room_name', 'course_applied_code'],
            'score_table' => ['scores_rows'],
            'overall' => ['overall_pct'],
            'recommendation' => ['recommended_course', 'counselor_comments'],
            'signature' => [$binding ?: 'counselor_name'],
            'field' => array_filter([$binding]),
            'kpi_grid', 'data_table' => self::configuredColumnBindings($type, $props),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $props
     * @return list<string>
     */
    private static function configuredColumnBindings(string $type, array $props): array
    {
        $entries = $type === 'kpi_grid'
            ? (is_array($props['metrics'] ?? null) ? $props['metrics'] : [])
            : (is_array($props['columns'] ?? null) ? $props['columns'] : (is_array($props['items'] ?? null) ? $props['items'] : []));
        $bindings = [];
        foreach ($entries as $entry) {
            if (! is_array($entry) || ($entry['checked'] ?? true) === false) {
                continue;
            }

            $binding = $type === 'kpi_grid'
                ? ($entry['binding'] ?? null)
                : (($entry['binding'] ?? '') !== '' ? $entry['binding'] : ($entry['key'] ?? null));
            if (! is_string($binding) || trim($binding) === '') {
                continue;
            }

            $binding = trim($binding);
            if ($type === 'data_table' && ! str_contains($binding, '.')) {
                $repeatKey = trim((string) ($props['repeat_key'] ?? ''));
                if ($repeatKey !== '') {
                    $binding = $repeatKey.'.'.$binding;
                }
            }
            $bindings[] = $binding;
        }

        return $bindings;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function overlaps(array $a, array $b): bool
    {

        return (float) $a['x'] < ((float) $b['x'] + (float) $b['w'])
            && ((float) $a['x'] + (float) $a['w']) > (float) $b['x']
            && (float) $a['y'] < ((float) $b['y'] + (float) $b['h'])
            && ((float) $a['y'] + (float) $a['h']) > (float) $b['y'];
    }

    /**
     * @return list<string>
     */
    public static function allowedBindings(): array
    {
        return array_values(array_unique(array_merge(
            TemplateFieldAliasCatalog::staticKeys(),
            array_keys(TemplateFieldAliasCatalog::domainAliases()),
            ['scores_rows'],
        )));
    }

    /**
     * @return list<string>
     */
    public static function componentTypes(): array
    {
        return ['header', 'identity', 'exam_details', 'field', 'score_table', 'overall', 'recommendation', 'signature', 'note', 'footer', 'text', 'paragraph', 'metadata_grid', 'summary_metrics', 'image', 'divider', 'spacer', 'kpi_grid', 'data_table'];
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public static function componentCatalog(): array
    {
        return [
            'header' => ['label' => 'Institution header', 'description' => 'Institution and examination title.'],
            'identity' => ['label' => 'Applicant identity', 'description' => 'Applicant number, name, sex, and GWA.'],
            'exam_details' => ['label' => 'Examination details', 'description' => 'Date, time, room, and applied course.'],
            'field' => ['label' => 'Bound field', 'description' => 'One labeled applicant or institution field.'],
            'score_table' => ['label' => 'Dynamic score table', 'description' => 'Rows generated from active aptitude areas.'],
            'overall' => ['label' => 'Overall score', 'description' => 'Frozen overall percentage and rating.'],
            'recommendation' => ['label' => 'Recommendation', 'description' => 'Recommended program and notes.'],
            'signature' => ['label' => 'Signature', 'description' => 'Personnel name, credentials, and role.'],
            'note' => ['label' => 'Note', 'description' => 'Policy or operational note.'],
            'footer' => ['label' => 'Footer', 'description' => 'Footer or page marker.'],
            'text' => ['label' => 'Text', 'description' => 'Static or placeholder-bound text.'],
            'dynamic_image' => ['label' => 'Dynamic image / photo', 'description' => 'Applicant 2x2 photo or verified intake image.'],
            'dynamic_qr' => ['label' => 'Verification QR', 'description' => '300-DPI verification or candidate locator QR code.'],
            'image' => ['label' => 'Image', 'description' => 'Approved local image asset.'],
            'divider' => ['label' => 'Divider', 'description' => 'Horizontal rule.'],
            'spacer' => ['label' => 'Spacer', 'description' => 'Reserved vertical space.'],
            'kpi_grid' => ['label' => 'KPI grid', 'description' => 'A responsive grid of summary metrics.'],
            'data_table' => ['label' => 'Data table', 'description' => 'A repeating table of records that can span pages.'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function componentCapabilities(string $paperSize = 'a4', string $orientation = 'portrait'): array
    {
        $dimensions = self::pageDimensions($paperSize, $orientation);
        $maxDimensions = [
            'w' => $dimensions['width_mm'] - 24,
            'h' => $dimensions['height_mm'] - 24,
        ];

        return [
            'header' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 60, 'h' => 16], 'max_dimensions' => $maxDimensions],
            'identity' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 60, 'h' => 20], 'max_dimensions' => $maxDimensions],
            'exam_details' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 60, 'h' => 16], 'max_dimensions' => $maxDimensions],
            'field' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 30, 'h' => 8], 'max_dimensions' => $maxDimensions],
            'score_table' => [
                'aspect_ratio' => 'free',
                'resize_axes' => 'both',
                'min_dimensions' => ['w' => 80, 'h' => 30],
                'max_dimensions' => $maxDimensions,
                'dynamic_rows' => true,
                'dynamic_fixed_height_mm' => self::SCORE_TABLE_FIXED_HEIGHT_MM,
                'dynamic_row_height_mm' => self::SCORE_TABLE_ROW_HEIGHT_MM,
            ],
            'overall' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 60, 'h' => 20], 'max_dimensions' => $maxDimensions],
            'recommendation' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 60, 'h' => 16], 'max_dimensions' => $maxDimensions],
            'signature' => ['aspect_ratio' => 'free', 'resize_axes' => 'horizontal', 'min_dimensions' => ['w' => 40, 'h' => 16], 'max_dimensions' => $maxDimensions],
            'note' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 40, 'h' => 12], 'max_dimensions' => $maxDimensions],
            'footer' => ['aspect_ratio' => 'free', 'resize_axes' => 'horizontal', 'min_dimensions' => ['w' => 60, 'h' => 8], 'max_dimensions' => $maxDimensions],
            'text' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 20, 'h' => 6], 'max_dimensions' => $maxDimensions],
            'paragraph' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 20, 'h' => 6], 'max_dimensions' => $maxDimensions],
            'metadata_grid' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 40, 'h' => 16], 'max_dimensions' => $maxDimensions],
            'summary_metrics' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 40, 'h' => 20], 'max_dimensions' => $maxDimensions],
            'kpi_grid' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 40, 'h' => 20], 'max_dimensions' => $maxDimensions],
            'data_table' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 40, 'h' => 20], 'max_dimensions' => $maxDimensions, 'dynamic_rows' => true],
            'image' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 15, 'h' => 15], 'max_dimensions' => $maxDimensions],
            'dynamic_image' => ['aspect_ratio' => 'fixed', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 20, 'h' => 20], 'max_dimensions' => $maxDimensions],
            'dynamic_qr' => ['aspect_ratio' => 'fixed', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 15, 'h' => 15], 'max_dimensions' => $maxDimensions],
            'divider' => ['aspect_ratio' => 'free', 'resize_axes' => 'horizontal', 'min_dimensions' => ['w' => 20, 'h' => 2], 'max_dimensions' => $maxDimensions],
            'spacer' => ['aspect_ratio' => 'free', 'resize_axes' => 'both', 'min_dimensions' => ['w' => 10, 'h' => 4], 'max_dimensions' => $maxDimensions],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sampleContext(): array
    {
        $context = [
            'institution_name' => 'State University of Northern Philippines',
            'institution_exam_name' => 'Institutional College Aptitude Test',
            'institution_exam_acronym' => 'ICAT',
            'applicant_reference' => 'AY26-00412',
            'applicant_number' => 'AY26-00412',
            'examinee_number' => '00412',
            'sequence_number' => '412',
            'applicant_name' => 'Santos, Maria Clara D.',
            'family_name' => 'Santos',
            'first_name' => 'Maria Clara',
            'middle_name' => 'Dela Cruz',
            'suffix' => '',
            'sex' => 'Female',
            'gwa' => '92.40',
            'gwa_original' => '92.40',
            'gwa_scale' => '0–100%',
            'gwa_display' => '92.40',
            'course_applied' => 'BS Computer Science',
            'course_applied_code' => 'BSCS',
            'course_applied_name' => 'BS Computer Science',
            'last_school_enrolled' => 'Ilocos Sur College',
            'last_school_short' => 'ISC',
            'strand' => 'Humanities and Social Sciences',
            'strand_or_prev_course' => 'Humanities and Social Sciences',
            'strand_or_prev_course_short' => 'HUMSS',
            'school_short' => 'ISC',
            'history_short' => 'HUMSS',
            'exam_date' => 'October 15, 2026',
            'exam_time' => '08:30 AM',
            'room_name' => 'Room 204 (Science Bldg)',
            'counselor_name' => 'Dr. Eleanor Vance, RGC',
            'overall_pct' => '88.50%',
            'scores_rows' => [
                ['domain' => 'Verbal Reasoning', 'raw' => '82', 'converted' => '82'],
                ['domain' => 'Quantitative Reasoning', 'raw' => '91', 'converted' => '91'],
                ['domain' => 'Spatial Reasoning', 'raw' => '76', 'converted' => '76'],
            ],
            'composite_score' => '88.50',
            'scaled_score' => '88.50',
            'percentile_rank' => '91st',
            'stanine' => '7',
            'general_ability_raw' => '42',
            'general_ability_pct' => '84%',
            'verbal_raw' => '42',
            'verbal_pct' => '84%',
            'quantitative_raw' => '45',
            'quantitative_pct' => '90%',
            'inductive_raw' => '44',
            'inductive_pct' => '88%',
        ];
        foreach (TemplateFieldAliasCatalog::aliases() as $alias => $canonical) {
            if (array_key_exists($canonical, $context)) {
                $context[$alias] ??= $context[$canonical];
            }
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function normalizeProps(string $type, array $props = []): array
    {
        $rawTypography = is_array($props['typography'] ?? null) ? $props['typography'] : [];
        $rawAppearance = is_array($props['appearance'] ?? null) ? $props['appearance'] : [];
        $rawSpacing = is_array($props['spacing'] ?? null) ? $props['spacing'] : [];
        $typography = array_merge(self::typographyDefaults($type), $rawTypography);
        $appearance = array_merge(self::appearanceDefaults($type), $rawAppearance);
        $spacing = array_merge(self::spacingDefaults($type), $rawSpacing);

        if (array_key_exists('font_size', $props) && ! array_key_exists('font_size_pt', $rawTypography)) {
            $typography['font_size_pt'] = $props['font_size'];
        }
        if (array_key_exists('bold', $props) && ! array_key_exists('font_weight', $rawTypography)) {
            $typography['font_weight'] = $props['bold'] ? 700 : 400;
        }
        if (array_key_exists('background', $props) && ! array_key_exists('background_color', $rawAppearance)) {
            $appearance['background_color'] = $props['background'];
        }
        if (($props['border'] ?? false) && ! array_key_exists('border_width_px', $rawAppearance)) {
            $appearance['border_width_px'] = 1;
        }

        $fontFamilies = [
            'Arial, Helvetica, sans-serif',
            'Inter, Arial, sans-serif',
            'Georgia, serif',
            'Times New Roman, serif',
            'Verdana, sans-serif',
        ];
        $typography['font_family'] = in_array((string) ($typography['font_family'] ?? ''), $fontFamilies, true)
            ? (string) $typography['font_family']
            : self::typographyDefaults($type)['font_family'];
        $typography['font_size_pt'] = max(6, min(48, (float) ($typography['font_size_pt'] ?? 10)));
        $typography['font_weight'] = in_array((int) ($typography['font_weight'] ?? 400), [400, 500, 600, 700, 800], true) ? (int) $typography['font_weight'] : 400;
        $typography['text_color'] = self::safeColor($typography['text_color'] ?? '#0f172a', '#0f172a');
        $typography['text_align'] = in_array($typography['text_align'] ?? 'left', ['left', 'center', 'right', 'justify'], true) ? (string) $typography['text_align'] : 'left';
        $typography['line_height'] = max(0.8, min(2.4, (float) ($typography['line_height'] ?? 1.2)));
        $typography['letter_spacing_px'] = max(-2, min(8, (float) ($typography['letter_spacing_px'] ?? 0)));

        $appearance['background_color'] = self::safeColor($appearance['background_color'] ?? 'transparent', 'transparent');
        $appearance['border_color'] = self::safeColor($appearance['border_color'] ?? '#cbd5e1', '#cbd5e1');
        $appearance['border_width_px'] = max(0, min(4, (float) ($appearance['border_width_px'] ?? 0)));
        $appearance['border_style'] = in_array($appearance['border_style'] ?? 'solid', ['solid', 'dashed', 'dotted'], true) ? (string) $appearance['border_style'] : 'solid';
        $appearance['border_radius_mm'] = max(0, min(8, (float) ($appearance['border_radius_mm'] ?? 0)));
        $spacing['padding_mm'] = max(0, min(12, (float) ($spacing['padding_mm'] ?? 2)));
        if (in_array($type, ['kpi_grid', 'data_table'], true)) {
            $props['columns'] = array_values(array_map(
                static fn (array $column): array => array_filter([
                    ...$column,
                    'label' => is_string($column['label'] ?? null) ? trim($column['label']) : null,
                    'binding' => is_string($column['binding'] ?? null) ? trim($column['binding']) : null,
                ], static fn (mixed $value): bool => $value !== null),
                array_filter(
                    is_array($props['columns'] ?? null) ? $props['columns'] : (is_array($props['items'] ?? null) ? $props['items'] : []),
                    static fn (mixed $column): bool => is_array($column),
                ),
            ));
        }
        $textStyles = [];
        $rawTextStyles = is_array($props['text_styles'] ?? null) ? $props['text_styles'] : [];
        foreach (self::textPartOptions($type) as $part) {
            $fallback = self::textPartDefaults($type, $part);
            $textStyles[$part] = self::normalizeTypography(array_merge($fallback, is_array($rawTextStyles[$part] ?? null) ? $rawTextStyles[$part] : []), $fallback);
        }

        return array_replace($props, [
            'typography' => $typography,
            'text_styles' => $textStyles,
            'appearance' => $appearance,
            'spacing' => $spacing,
        ]);
    }

    /** @return array<string, mixed> */
    public static function typographyDefaults(string $type): array
    {
        $defaults = [
            'font_family' => 'Arial, Helvetica, sans-serif',
            'font_size_pt' => 10,
            'font_weight' => 400,
            'text_color' => '#0f172a',
            'text_align' => 'left',
            'line_height' => 1.2,
            'letter_spacing_px' => 0,
        ];

        return array_replace($defaults, match ($type) {
            'header' => ['font_size_pt' => 20, 'font_weight' => 700, 'text_align' => 'center'],
            'identity' => ['font_size_pt' => 10],
            'exam_details' => ['font_size_pt' => 10, 'text_align' => 'center'],
            'field' => ['font_size_pt' => 10.5],
            'score_table', 'data_table' => ['font_size_pt' => 9.5],
            'overall', 'kpi_grid' => ['font_size_pt' => 12, 'font_weight' => 700],
            'recommendation' => ['font_size_pt' => 10.5],
            'signature' => ['font_size_pt' => 10, 'text_align' => 'center'],
            'note' => ['font_size_pt' => 9.5],
            'footer' => ['font_size_pt' => 8.5, 'text_color' => '#64748b', 'text_align' => 'center'],
            'divider', 'spacer' => ['font_size_pt' => 10],
            default => [],
        });
    }

    /** @return array<string, array{revision: int, html: string}> */
    private static function normalizeComponentTemplates(mixed $templates): array
    {
        if (! is_array($templates)) {
            return [];
        }
        $normalized = [];
        foreach ($templates as $type => $template) {
            if (! in_array((string) $type, self::componentTypes(), true) || ! is_array($template)) {
                continue;
            }
            $normalized[(string) $type] = [
                'revision' => max(1, (int) ($template['revision'] ?? 1)),
                'html' => substr((string) ($template['html'] ?? ''), 0, 12000),
            ];
        }

        return $normalized;
    }

    /** @return list<string> */
    public static function templateDiagnostics(string $html): array
    {
        $errors = [];
        if (trim($html) === '') {
            $errors[] = 'HTML cannot be empty.';
        }
        if (strlen($html) > 12000) {
            $errors[] = 'HTML exceeds the 12,000 character limit.';
        }
        if (preg_match('/<\s*(script|iframe|object|embed|style|link)/i', $html) === 1) {
            $errors[] = 'Executable or external tags are not allowed.';
        }
        if (preg_match('/\son[a-z]+\s*=|javascript\s*:/i', $html) === 1) {
            $errors[] = 'Event handlers and javascript URLs are not allowed.';
        }
        $allowed = ['section', 'div', 'span', 'p', 'h1', 'h2', 'h3', 'strong', 'em', 'small', 'br', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];
        preg_match_all('/<\/?\s*([a-z0-9-]+)/i', $html, $matches);
        foreach (array_unique($matches[1] ?? []) as $tag) {
            if (! in_array(strtolower($tag), $allowed, true)) {
                $errors[] = "Tag <{$tag}> is not allowed.";
            }
        }

        return array_values(array_unique($errors));
    }

    /** @return list<string> */
    public static function textPartOptions(string $type): array
    {
        return match ($type) {
            'header' => ['title', 'subtitle'],
            'identity' => ['label', 'value'],
            'exam_details' => ['body'],
            'field' => ['label', 'value'],
            'score_table' => ['title', 'heading', 'body'],
            'data_table' => ['title', 'heading', 'body'],
            'kpi_grid' => ['label', 'value'],
            'overall' => ['label', 'value'],
            'recommendation' => ['heading', 'body', 'note'],
            'signature' => ['name', 'role'],
            'note', 'footer', 'text' => ['body'],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    public static function textPartDefaults(string $type, string $part): array
    {
        $base = self::typographyDefaults($type);

        return match ([$type, $part]) {
            ['header', 'subtitle'] => array_replace($base, ['font_size_pt' => 12, 'font_weight' => 500]),
            ['identity', 'label'] => array_replace($base, ['font_size_pt' => 8.5, 'font_weight' => 700, 'text_color' => '#334155']),
            ['identity', 'value'] => array_replace($base, ['font_size_pt' => 9.5]),
            ['field', 'label'] => array_replace($base, ['font_size_pt' => 9, 'font_weight' => 700]),
            ['field', 'value'] => array_replace($base, ['font_size_pt' => 10]),
            ['score_table', 'title'] => array_replace($base, ['font_size_pt' => 12, 'font_weight' => 700]),
            ['score_table', 'heading'] => array_replace($base, ['font_size_pt' => 8.5, 'font_weight' => 700, 'text_color' => '#334155']),
            ['score_table', 'body'] => array_replace($base, ['font_size_pt' => 9]),
            ['data_table', 'title'] => array_replace($base, ['font_size_pt' => 12, 'font_weight' => 700]),
            ['data_table', 'heading'] => array_replace($base, ['font_size_pt' => 8.5, 'font_weight' => 700, 'text_color' => '#334155']),
            ['data_table', 'body'] => array_replace($base, ['font_size_pt' => 9]),
            ['kpi_grid', 'label'] => array_replace($base, ['font_size_pt' => 8.5, 'font_weight' => 700, 'text_color' => '#64748b']),
            ['kpi_grid', 'value'] => array_replace($base, ['font_size_pt' => 14, 'font_weight' => 800]),
            ['overall', 'label'] => array_replace($base, ['font_size_pt' => 11, 'font_weight' => 700]),
            ['overall', 'value'] => array_replace($base, ['font_size_pt' => 12, 'font_weight' => 700]),
            ['recommendation', 'heading'] => array_replace($base, ['font_size_pt' => 10, 'font_weight' => 700]),
            ['recommendation', 'body'] => array_replace($base, ['font_size_pt' => 11, 'font_weight' => 600]),
            ['recommendation', 'note'] => array_replace($base, ['font_size_pt' => 9.5, 'text_color' => '#334155']),
            ['signature', 'name'] => array_replace($base, ['font_size_pt' => 10, 'font_weight' => 700, 'text_align' => 'center']),
            ['signature', 'role'] => array_replace($base, ['font_size_pt' => 8.5, 'text_color' => '#64748b', 'text_align' => 'center']),
            ['footer', 'body'] => array_replace($base, ['font_size_pt' => 8.5, 'text_color' => '#64748b', 'text_align' => 'center']),
            ['note', 'body'] => array_replace($base, ['font_size_pt' => 9.5]),
            default => $base,
        };
    }

    /** @param array<string, mixed> $raw @param array<string, mixed> $fallback @return array<string, mixed> */
    private static function normalizeTypography(array $raw, array $fallback): array
    {
        $fontFamilies = ['Arial, Helvetica, sans-serif', 'Inter, Arial, sans-serif', 'Georgia, serif', 'Times New Roman, serif', 'Verdana, sans-serif'];
        $fontFamily = in_array((string) ($raw['font_family'] ?? ''), $fontFamilies, true) ? (string) $raw['font_family'] : (string) $fallback['font_family'];

        return [
            'font_family' => $fontFamily,
            'font_size_pt' => max(6, min(48, (float) ($raw['font_size_pt'] ?? $fallback['font_size_pt']))),
            'font_weight' => in_array((int) ($raw['font_weight'] ?? $fallback['font_weight']), [400, 500, 600, 700, 800], true) ? (int) $raw['font_weight'] : (int) $fallback['font_weight'],
            'text_color' => self::safeColor($raw['text_color'] ?? $fallback['text_color'], (string) $fallback['text_color']),
            'text_align' => in_array($raw['text_align'] ?? $fallback['text_align'], ['left', 'center', 'right', 'justify'], true) ? (string) $raw['text_align'] : (string) $fallback['text_align'],
            'line_height' => max(0.8, min(2.4, (float) ($raw['line_height'] ?? $fallback['line_height']))),
            'letter_spacing_px' => max(-2, min(8, (float) ($raw['letter_spacing_px'] ?? $fallback['letter_spacing_px']))),
        ];
    }

    /** @return array<string, mixed> */
    public static function appearanceDefaults(string $type): array
    {
        $defaults = [
            'background_color' => 'transparent',
            'border_color' => '#cbd5e1',
            'border_width_px' => 0,
            'border_style' => 'solid',
            'border_radius_mm' => 0,
        ];

        return array_replace($defaults, match ($type) {
            'identity', 'score_table', 'overall' => ['background_color' => '#f8fafc', 'border_width_px' => 1],
            'recommendation' => ['background_color' => '#ecfdf5', 'border_color' => '#a7f3d0', 'border_width_px' => 1, 'border_radius_mm' => 1],
            default => [],
        });
    }

    /** @return array<string, mixed> */
    public static function spacingDefaults(string $type): array
    {
        return ['padding_mm' => match ($type) {
            'header' => 2.5,
            'identity', 'score_table', 'recommendation' => 3,
            'divider', 'spacer' => 0,
            default => 2,
        }];
    }

    private static function safeColor(mixed $value, string $fallback): string
    {
        $color = trim((string) $value);

        return $color === 'transparent' || preg_match('/^#[0-9a-f]{3,8}$/i', $color) === 1 ? $color : $fallback;
    }

    /**
     * @return array{width_mm: int, height_mm: int}
     */
    public static function pageDimensions(string $paperSize, string $orientation): array
    {
        $base = match ($paperSize) {
            'legal' => ['width_mm' => 216, 'height_mm' => 356],
            'letter' => ['width_mm' => 216, 'height_mm' => 279],
            default => ['width_mm' => 210, 'height_mm' => 297],
        };

        return $orientation === 'landscape'
            ? ['width_mm' => $base['height_mm'], 'height_mm' => $base['width_mm']]
            : $base;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    public static function component(string $type, float $x, float $y, float $w, float $h, array $props = [], ?string $id = null): array
    {
        return [
            'id' => $id ?? ($type.'-1'),
            'type' => $type,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'locked' => false,
            'props' => self::normalizeProps($type, $props),
        ];
    }

    /**
     * @param  array<string, mixed>  $component
     * @return array<string, mixed>
     */
    private static function normalizeComponent(array $component, string $id): array
    {
        $type = is_string($component['type'] ?? null) ? (string) $component['type'] : 'text';

        return [
            'id' => $id,
            'type' => $type,
            'x' => self::numericGeometry($component['x'] ?? null, 0),
            'y' => self::numericGeometry($component['y'] ?? null, 0),
            'w' => max(1, self::numericGeometry($component['w'] ?? null, 40)),
            'h' => max(1, self::numericGeometry($component['h'] ?? null, 10)),
            'locked' => (bool) ($component['locked'] ?? false),
            'props' => self::normalizeProps(
                $type,
                is_array($component['props'] ?? null) ? $component['props'] : [],
            ),
        ];
    }

    private static function numericGeometry(mixed $value, float $fallback): float
    {
        return is_numeric($value) && is_finite((float) $value) ? (float) $value : $fallback;
    }
}
