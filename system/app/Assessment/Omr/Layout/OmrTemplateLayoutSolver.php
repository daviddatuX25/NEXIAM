<?php

namespace App\Assessment\Omr\Layout;

use App\Assessment\Omr\Fiducial\OmrFiducial;
use App\Assessment\Omr\OmrCapturePolicy;

use function array_key_exists;
use function chr;
use function array_map;
use function array_merge;
use function array_values;
use function count;
use function hash;
use function intdiv;
use function is_array;
use function is_bool;
use function is_int;
use function json_encode;
use function ksort;
use function max;
use function preg_match;
use function sprintf;

/**
 * Server-owned semantic layout solver. Turns a semantic blueprint into
 * canonical OMRChecker fieldBlocks with deterministic integer geometry.
 * Pure and deterministic: same blueprint → same bytes, same hash.
 */
final class OmrTemplateLayoutSolver
{
    public const SCHEMA = 'nexiam.omr.layout-blueprint.v1';
    public const SOLVER_VERSION = 'nexiam.omr.layout-solver.v1';
    public const PPI = 242;
    public const BUBBLE = 56;
    public const MARGIN = 80;
    public const ROW_PITCH = 80;
    public const MCQ4_OPTION_GAP = 66;
    public const COMPACT_BUBBLE = 40;
    public const COMPACT_MCQ4_OPTION_GAP = 52;
    public const IDENTITY_LABELS_GAP = 66;
    public const IDENTITY_BUBBLES_GAP = 60;
    public const FLOW_TOP = 900;
    public const COMPACT_FLOW_TOP = 940;
    public const FLOW_BOTTOM_RESERVE = 230;
    // Marker-bearing frame mode: the identity grid is re-derived inside the
    // fiducial-center frame (smaller vertical pitch so the 25-row flow still
    // fits the frame budget), and the bottom reserve shrinks because marker
    // profiles print no calibration/footer content below the answer frame.
    public const MARKER_FRAME_FLOW_BOTTOM_RESERVE = 180;
    public const MARKER_FRAME_ROW_PITCH = 76;
    public const MARKER_FRAME_IDENTITY_LABELS_GAP = 52;
    public const MARKER_FRAME_IDENTITY_BUBBLES_GAP = 46;
    public const MIN_COLUMN_GAP = 16;
    public const MIN_MARKER_SIZE = 72;

    /** @var array<string, array{0: int, 1: int}> */
    private const PAPERS = [
        'long' => [2060, 3144],
        'legal' => [2060, 3392],
    ];

    /**
     * Versioned column origins (px). The 4-column set matches the shipped
     * v4/v5 long-bond sheets exactly; 3- and 2-column sets use the same
     * 400px band + 40px gap geometry centered in the printable width. The
     * six-column set is the compact one-page profile: 40px bubbles, 52px
     * option pitch, and 16px inter-block clearance inside the printable area.
     *
     * @var array<int, list<int>>
     */
    private const COLUMN_X = [
        6 => [120, 452, 784, 1116, 1448, 1780],
        4 => [180, 620, 1060, 1500],
        3 => [380, 820, 1260],
        2 => [610, 1050],
    ];

    private const IDENTITY_APPLICANT = ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['APPLICANT_NUMBER_1..10'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [200, 260]];
    private const IDENTITY_BIRTHDATE = ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['BIRTHDATE_1..6'], 'bubblesGap' => 60, 'labelsGap' => 66, 'origin' => [1000, 260]];
    // Keep compact native identity grids clear of the EDQ overlay and answer flow.
    private const COMPACT_IDENTITY_APPLICANT = ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['APPLICANT_NUMBER_1..10'], 'bubblesGap' => 52, 'labelsGap' => 66, 'origin' => [200, 300]];
    private const COMPACT_IDENTITY_BIRTHDATE = ['fieldType' => 'QTYPE_INT', 'fieldLabels' => ['BIRTHDATE_1..6'], 'bubblesGap' => 52, 'labelsGap' => 66, 'origin' => [1000, 300]];

    /**
     * Validate a blueprint without solving. Returns an empty list when valid.
     *
     * @param  array<string, mixed>  $blueprint
     * @return list<LayoutDiagnostic>
     */
    public function validate(array $blueprint): array
    {
        $diagnostics = [];
        $this->normalize($blueprint, $diagnostics);

        return $diagnostics;
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @throws OmrTemplateLayoutException when the blueprint cannot be solved
     */
    public function solve(array $blueprint): SolvedOmrTemplateLayout
    {
        $diagnostics = [];
        $normalized = $this->normalize($blueprint, $diagnostics);
        if ($diagnostics !== []) {
            throw new OmrTemplateLayoutException($diagnostics);
        }

        [$width, $height] = self::PAPERS[$normalized['paper']];
        $columnCount = (int) $normalized['columns'];
        $geometry = $this->geometryForColumns($columnCount);
        $markerLayout = $this->markerLayout($normalized, $width, $height, $columnCount, $geometry, $diagnostics);
        if ($diagnostics !== []) {
            throw new OmrTemplateLayoutException($diagnostics);
        }
        $columns = $markerLayout !== null ? $markerLayout['columns'] : self::COLUMN_X[$columnCount];
        $flowTop = $markerLayout !== null ? $markerLayout['flow_top'] : ($columnCount === 6 ? self::COMPACT_FLOW_TOP : self::FLOW_TOP);
        $flowBottom = $markerLayout !== null ? $markerLayout['flow_bottom'] : ($height - self::FLOW_BOTTOM_RESERVE);
        $rowPitch = $markerLayout !== null ? $markerLayout['row_pitch'] : self::ROW_PITCH;
        $pageWidth = $markerLayout !== null ? $markerLayout['frame'][0] : $width;
        $pageHeight = $markerLayout !== null ? $markerLayout['frame'][1] : $height;
        $pageCount = 1;
        $column = 0;
        $y = $flowTop;
        $placed = [];

        foreach ($normalized['sections'] as $sectionIndex => $section) {
            $rowCount = $section['question_to'] - $section['question_from'] + 1;
            $break = $section['break_before'];

            if ($break !== null) {
                if ($break === 'next_column') {
                    if ($column < $columnCount - 1) {
                        $column++;
                        $y = $flowTop;
                    } else {
                        $pageCount++;
                        $column = 0;
                        $y = $flowTop;
                    }
                } elseif ($break === 'next_page') {
                    $pageCount++;
                    $column = 0;
                    $y = $flowTop;
                } else {
                    $target = $break['column'] - 1; // authoring is 1-based
                    if ($target < $column) {
                        throw new OmrTemplateLayoutException([
                            new LayoutDiagnostic('pin_moves_backward', sprintf('Section %d pin moves backward: target column %d is before the current column %d.', $sectionIndex + 1, $target + 1, $column + 1), ['section' => $sectionIndex + 1, 'column' => $target + 1]),
                        ]);
                    }
                    if ($target > $column) {
                        $column = $target;
                        $y = $flowTop;
                    }
                    $available = $flowBottom - $y - $geometry['bubble'];
                    if (($rowCount - 1) * self::ROW_PITCH > $available) {
                        throw new OmrTemplateLayoutException([
                            new LayoutDiagnostic('section_does_not_fit_pinned_column', sprintf('Section %d (%s_Q_%d..%d) does not fit the remaining capacity of column %d.', $sectionIndex + 1, $section['area_code'], $section['question_from'], $section['question_to'], $target + 1), ['section' => $sectionIndex + 1, 'column' => $target + 1, 'rows' => $rowCount]),
                        ]);
                    }
                }
            }

            for ($question = $section['question_from']; $question <= $section['question_to']; $question++) {
                if ($y + $geometry['bubble'] > $flowBottom) {
                    if ($column < $columnCount - 1) {
                        $column++;
                        $y = $flowTop;
                    } else {
                        $pageCount++;
                        $column = 0;
                        $y = $flowTop;
                    }
                }

                $placed[] = [
                    'page' => $pageCount,
                    'column' => $column,
                    'x' => $columns[$column],
                    'y' => $y,
                    'area_code' => $section['area_code'],
                    'question_no' => $question,
                ];
                $y += $rowPitch;
            }
        }

        $pages = $this->buildPages($normalized, $placed, $columns, $pageWidth, $pageHeight, $geometry, $markerLayout);

        // The compact six-column profile is a one-page contract: the solver
        // must never silently emit a second page (founder decision 2026-08-19).
        if ($columnCount === 6 && count($pages) !== 1) {
            throw new OmrTemplateLayoutException([
                new LayoutDiagnostic('compact_profile_multi_page', sprintf('The compact six-column profile must fit one page; it solved to %d pages.', count($pages))),
            ]);
        }

        $template = $this->sortRecursive($this->canonicalTemplate($pages, $pageWidth, $pageHeight, $geometry['bubble']));
        $canonicalJson = $this->encode($template);

        $fiducial = null;
        if ($markerLayout !== null) {
            $fiducial = [
                'name' => OmrFiducial::ASSET_NAME,
                'mime' => 'image/jpeg',
                'bytes' => OmrFiducial::assetBytes(),
                'asset_hash' => OmrFiducial::assetHash(),
            ];
        }

        return new SolvedOmrTemplateLayout(
            normalizedBlueprint: $normalized,
            blueprintHash: hash('sha256', $this->encode($normalized)),
            template: $template,
            canonicalJson: $canonicalJson,
            templateJsonHash: hash('sha256', $canonicalJson),
            pages: $pages,
            fiducial: $fiducial,
        );
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  list<LayoutDiagnostic>  $diagnostics
     * @return array<string, mixed>
     */
    private function normalize(array $blueprint, array &$diagnostics): array
    {
        $schema = $blueprint['schema'] ?? null;
        if ($schema !== self::SCHEMA) {
            $diagnostics[] = new LayoutDiagnostic('unsupported_schema', sprintf('Unsupported blueprint schema "%s". Expected "%s".', is_string($schema) ? $schema : get_debug_type($schema), self::SCHEMA));

            return [];
        }

        $paper = $blueprint['paper'] ?? 'long';
        if (! array_key_exists($paper, self::PAPERS)) {
            $diagnostics[] = new LayoutDiagnostic('unsupported_paper', sprintf('Unsupported paper "%s". Supported: %s.', (string) $paper, implode(', ', array_keys(self::PAPERS))));
        }

        $columns = $blueprint['columns'] ?? 4;
        if (! in_array($columns, [2, 3, 4, 6], true)) {
            $diagnostics[] = new LayoutDiagnostic('unsupported_columns', sprintf('Unsupported column count "%s". Supported: 2, 3, 4, 6.', (string) $columns));
        }

        $identity = is_array($blueprint['identity'] ?? null) ? $blueprint['identity'] : [];
        $applicantNumber = $identity['applicant_number'] ?? true;
        $birthdate = $identity['birthdate'] ?? false;
        $repeatEveryPage = $identity['repeat_on_every_page'] ?? true;
        foreach ([['applicant_number', $applicantNumber], ['birthdate', $birthdate], ['repeat_on_every_page', $repeatEveryPage]] as [$key, $value]) {
            if (! is_bool($value)) {
                $diagnostics[] = new LayoutDiagnostic('invalid_identity', sprintf('Identity option "%s" must be a boolean.', $key));
            }
        }

        $sections = $blueprint['sections'] ?? null;
        if (! is_array($sections) || $sections === []) {
            $diagnostics[] = new LayoutDiagnostic('empty_sections', 'The blueprint needs at least one question section.');
            $sections = [];
        }

        $normalizedSections = [];
        foreach (array_values($sections) as $index => $section) {
            if (! is_array($section)) {
                $diagnostics[] = new LayoutDiagnostic('invalid_section', sprintf('Section %d must be an object.', $index + 1), ['section' => $index + 1]);

                continue;
            }

            $areaCode = $section['area_code'] ?? null;
            if (! is_string($areaCode) || preg_match('/^[A-Z][A-Z0-9_]{0,19}$/', $areaCode) !== 1) {
                $diagnostics[] = new LayoutDiagnostic('invalid_area_code', sprintf('Section %d has an invalid area code "%s".', $index + 1, (string) $areaCode), ['section' => $index + 1]);
            }

            $from = $section['question_from'] ?? null;
            $to = $section['question_to'] ?? null;
            if (! is_int($from) || ! is_int($to) || $from < 1 || $to < $from) {
                $diagnostics[] = new LayoutDiagnostic('invalid_range', sprintf('Section %d has an invalid question range (%s..%s).', $index + 1, (string) $from, (string) $to), ['section' => $index + 1, 'from' => $from, 'to' => $to]);
            }

            $optionCount = $section['option_count'] ?? 4;
            if ($optionCount !== 4) {
                $diagnostics[] = new LayoutDiagnostic('unsupported_option_count', sprintf('Section %d uses %d options; only 4-option MCQ blocks are supported in v1.', $index + 1, (int) $optionCount), ['section' => $index + 1]);
            }

            $break = $section['break_before'] ?? null;
            $normalizedBreak = $break;
            if (is_array($break)) {
                if (($break['kind'] ?? null) !== 'column' || ! is_int($break['column'] ?? null) || $break['column'] < 1 || $break['column'] > (is_int($columns) ? $columns : 4)) {
                    $diagnostics[] = new LayoutDiagnostic('invalid_break', sprintf('Section %d has an invalid column pin.', $index + 1), ['section' => $index + 1]);

                    $normalizedBreak = null;
                }
            } elseif ($break !== null && ! in_array($break, ['next_column', 'next_page'], true)) {
                $diagnostics[] = new LayoutDiagnostic('invalid_break', sprintf('Section %d has an unsupported break "%s".', $index + 1, (string) $break), ['section' => $index + 1]);

                $normalizedBreak = null;
            }

            $normalizedSections[] = [
                'area_code' => (string) $areaCode,
                'question_from' => (int) $from,
                'question_to' => (int) $to,
                'option_count' => $optionCount,
                'break_before' => $normalizedBreak,
            ];
        }

        // Duplicate ranges: same area with overlapping question numbers.
        foreach ($normalizedSections as $index => $section) {
            foreach ($normalizedSections as $otherIndex => $other) {
                if ($otherIndex <= $index || $other['area_code'] !== $section['area_code']) {
                    continue;
                }
                if ($section['question_from'] <= $other['question_to'] && $other['question_from'] <= $section['question_to']) {
                    $diagnostics[] = new LayoutDiagnostic(
                        'duplicate_question_range',
                        sprintf('Sections %d and %d overlap on %s questions %d..%d.', $index + 1, $otherIndex + 1, $section['area_code'], max($section['question_from'], $other['question_from']), min($section['question_to'], $other['question_to'])),
                        ['sections' => [$index + 1, $otherIndex + 1], 'area_code' => $section['area_code']],
                    );
                }
            }
        }

        // Marker-bearing frame contract. The alignment fiducial is OMRChecker's
        // own pinned bullseye asset — it is not a template identifier, so no
        // version-derived pattern is involved here.
        $markers = $blueprint['markers'] ?? null;
        $normalizedMarkers = null;
        if ($markers !== null) {
            if (! is_array($markers)) {
                $diagnostics[] = new LayoutDiagnostic('invalid_markers', 'The markers option must be an object.');
            } else {
                $enabled = $markers['enabled'] ?? true;
                if (! is_bool($enabled)) {
                    $diagnostics[] = new LayoutDiagnostic('invalid_markers', 'The markers.enabled option must be a boolean.');
                }
                if ($enabled) {
                    $normalizedMarkers = ['enabled' => true];
                }
            }
        }

        return [
            'schema' => self::SCHEMA,
            'paper' => (string) $paper,
            'columns' => (int) $columns,
            'identity' => [
                'applicant_number' => $applicantNumber,
                'birthdate' => $birthdate,
                'repeat_on_every_page' => $repeatEveryPage,
            ],
            'markers' => $normalizedMarkers,
            'sections' => $normalizedSections,
        ];
    }

    /**
     * Derive the marker-bearing frame layout (invariant #1: frame anchoring).
     *
     * The engine warps by the four fiducial CENTERS and then resizes the
     * result to `pageDimensions`, so pageDimensions MUST equal the
     * fiducial-center rectangle and every block origin MUST be re-derived
     * inside that frame. The physical paper is the frame plus an inset ring
     * carrying the four fiducials. Inter-column gaps shrink before the marker
     * shrinks below its matchable minimum.
     *
     * @param  array<string, mixed>  $normalized
     * @param  array{bubble: int, mcq_option_gap: int}  $geometry
     * @param  list<LayoutDiagnostic>  $diagnostics
     * @return array<string, mixed>|null
     */
    private function markerLayout(array $normalized, int $paperW, int $paperH, int $columnCount, array $geometry, array &$diagnostics): ?array
    {
        if (($normalized['markers'] ?? null) === null) {
            return null;
        }

        $frozen = OmrCapturePolicy::frozen();
        $anchor = $frozen['frame_anchor'];
        $marker = (int) $anchor['marker_size'];
        $quiet = (int) $anchor['marker_quiet_zone'];
        $unprintable = (int) $anchor['printer_unprintable_margin'];
        $inset = $unprintable + $marker + $quiet;
        $frameW = $paperW - 2 * $inset;
        $frameH = $paperH - 2 * $inset;
        $band = $geometry['bubble'] + 3 * $geometry['mcq_option_gap'];
        $gap = intdiv($frameW - 2 * self::MARGIN - $columnCount * $band, max(1, $columnCount - 1));

        // Shrink inter-column gaps first; only below the minimum clearance do
        // we shrink the marker (never below its matchable floor).
        while ($gap < self::MIN_COLUMN_GAP && $marker > self::MIN_MARKER_SIZE) {
            $marker -= 4;
            $inset = $unprintable + $marker + $quiet;
            $frameW = $paperW - 2 * $inset;
            $frameH = $paperH - 2 * $inset;
            $gap = intdiv($frameW - 2 * self::MARGIN - $columnCount * $band, max(1, $columnCount - 1));
        }
        if ($gap < self::MIN_COLUMN_GAP) {
            $diagnostics[] = new LayoutDiagnostic('marker_frame_does_not_fit', sprintf(
                'Marker frame %d×%d cannot fit %d columns of %dpx bubbles with %dpx option pitch and an %dpx margin.',
                $frameW,
                $frameH,
                $columnCount,
                $geometry['bubble'],
                $geometry['mcq_option_gap'],
                self::MARGIN,
            ));

            return null;
        }

        // Quadrant geometry (invariant #2): the engine splits the processing
        // frame at w/2 × h/3; corner placement must put two fiducials in the
        // top third and two in the bottom two-thirds, one per quadrant. With
        // frame = paper − 2×inset this holds exactly when inset < paper/3.
        if ($inset * 3 >= min($paperW, $paperH)) {
            $diagnostics[] = new LayoutDiagnostic('marker_quadrant_invalid', sprintf(
                'Inset ring %dpx does not satisfy the w/2 × h/3 quadrant contract on %d×%d paper.',
                $inset,
                $paperW,
                $paperH,
            ));

            return null;
        }
        OmrCapturePolicy::assertQuadrantPlacement([$paperW, $paperH], [$frameW, $frameH], $inset);

        $columns = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $columns[] = self::MARGIN + $index * ($band + $gap);
        }

        // Re-derive identity and flow inside the frame: clear the marker's
        // intrusion + quiet zone, then the compact identity grid, then a
        // heading band (area codes above the answer columns), then flow.
        $clearance = intdiv($marker, 2) + $quiet + 10;
        $identityHeight = 9 * self::MARKER_FRAME_IDENTITY_BUBBLES_GAP + $geometry['bubble'];
        $flowTop = $clearance + $identityHeight + 100;
        $flowBottom = $frameH - self::MARKER_FRAME_FLOW_BOTTOM_RESERVE;

        foreach ($normalized['sections'] as $index => $section) {
            $rows = (int) $section['question_to'] - (int) $section['question_from'] + 1;
            if (($rows - 1) * self::MARKER_FRAME_ROW_PITCH + $geometry['bubble'] > $flowBottom - $flowTop) {
                $diagnostics[] = new LayoutDiagnostic('marker_frame_vertical_overflow', sprintf(
                    'Section %d (%d rows at %dpx pitch) overflows the marker frame flow (%d..%d of %dpx height).',
                    $index + 1,
                    $rows,
                    self::MARKER_FRAME_ROW_PITCH,
                    $flowTop,
                    $flowBottom,
                    $frameH,
                ));

                return null;
            }
        }

        $centers = [
            [$inset, $inset],
            [$paperW - $inset, $inset],
            [$inset, $paperH - $inset],
            [$paperW - $inset, $paperH - $inset],
        ];

        return [
            'inset' => $inset,
            'marker' => $marker,
            'quiet' => $quiet,
            'frame' => [$frameW, $frameH],
            'paper' => [$paperW, $paperH],
            'columns' => $columns,
            'flow_top' => $flowTop,
            'flow_bottom' => $flowBottom,
            'row_pitch' => self::MARKER_FRAME_ROW_PITCH,
            'clearance' => $clearance,
            'centers' => $centers,
            'quadrants' => [0, 1, 2, 3],
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  list<array<string, mixed>>  $placed
     * @param  list<int>  $columns
     * @param  array{bubble: int, mcq_option_gap: int}  $geometry
     * @param  array<string, mixed>|null  $markerLayout
     * @return list<array<string, mixed>>
     */
    private function buildPages(array $normalized, array $placed, array $columns, int $width, int $height, array $geometry, ?array $markerLayout = null): array
    {
        $pageCount = 0;
        foreach ($placed as $row) {
            $pageCount = max($pageCount, (int) $row['page']);
        }
        $pageCount = max(1, $pageCount);

        $repeatIdentity = (bool) $normalized['identity']['repeat_on_every_page'];
        $pages = [];
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $blocks = [];
            if ($markerLayout !== null) {
                // In-frame re-derivation: the compact identity grid moves inside
                // the fiducial-center frame, clear of the marker ring.
                $clearance = (int) $markerLayout['clearance'];
                $identityApplicant = [
                    'fieldType' => 'QTYPE_INT',
                    'fieldLabels' => ['APPLICANT_NUMBER_1..10'],
                    'bubblesGap' => self::MARKER_FRAME_IDENTITY_BUBBLES_GAP,
                    'labelsGap' => self::MARKER_FRAME_IDENTITY_LABELS_GAP,
                    'origin' => [$clearance, $clearance],
                ];
                $identityBirthdate = [
                    'fieldType' => 'QTYPE_INT',
                    'fieldLabels' => ['BIRTHDATE_1..6'],
                    'bubblesGap' => self::MARKER_FRAME_IDENTITY_BUBBLES_GAP,
                    'labelsGap' => self::MARKER_FRAME_IDENTITY_LABELS_GAP,
                    'origin' => [$clearance + 9 * self::MARKER_FRAME_IDENTITY_LABELS_GAP + $geometry['bubble'] + 40, $clearance],
                ];
            } else {
                $identityApplicant = $geometry['bubble'] === self::COMPACT_BUBBLE ? self::COMPACT_IDENTITY_APPLICANT : self::IDENTITY_APPLICANT;
                $identityBirthdate = $geometry['bubble'] === self::COMPACT_BUBBLE ? self::COMPACT_IDENTITY_BIRTHDATE : self::IDENTITY_BIRTHDATE;
            }
            if ($normalized['identity']['applicant_number'] && ($repeatIdentity || $pageNo === 1)) {
                $blocks['ApplicantNumber'] = $identityApplicant;
            }
            if ($normalized['identity']['birthdate'] && ($repeatIdentity || $pageNo === 1)) {
                $blocks['Birthdate'] = $identityBirthdate;
            }

            $pageRows = array_values(array_filter($placed, static fn (array $row): bool => (int) $row['page'] === $pageNo));
            $rowPitch = $markerLayout !== null ? self::MARKER_FRAME_ROW_PITCH : self::ROW_PITCH;
            $groups = [];
            foreach ($pageRows as $row) {
                $group = $groups[count($groups) - 1] ?? null;
                $consecutive = $group !== null
                    && $group['column'] === $row['column']
                    && $group['area_code'] === $row['area_code']
                    && $row['question_no'] === $group['question_to'] + 1
                    && $row['y'] === $group['y'] + ($group['question_to'] - $group['question_from'] + 1) * $rowPitch;
                if ($consecutive) {
                    $group['question_to'] = (int) $row['question_no'];
                    $groups[count($groups) - 1] = $group;
                } else {
                    $groups[] = [
                        'column' => (int) $row['column'],
                        'x' => (int) $row['x'],
                        'y' => (int) $row['y'],
                        'area_code' => (string) $row['area_code'],
                        'question_from' => (int) $row['question_no'],
                        'question_to' => (int) $row['question_no'],
                    ];
                }
            }

            foreach ($groups as $groupIndex => $group) {
                $blocks[sprintf('%sPart%s', $group['area_code'], chr(65 + $groupIndex))] = [
                    'fieldType' => 'QTYPE_MCQ4',
                    'direction' => 'horizontal',
                    'fieldLabels' => [sprintf('%s_Q_%03d..%03d', $group['area_code'], $group['question_from'], $group['question_to'])],
                    'bubblesGap' => $geometry['mcq_option_gap'],
                    'labelsGap' => $rowPitch,
                    'origin' => [$group['x'], $group['y']],
                ];
            }

            $page = [
                'page_no' => $pageNo,
                'pageDimensions' => [$width, $height],
                'fieldBlocks' => $blocks,
                'preProcessors' => $markerLayout !== null
                    ? [[
                        'name' => 'CropOnMarkers',
                        'options' => [
                            // Mirror the pinned OMRChecker mobile recipe
                            // (samples/sample5): marker-only chain with the
                            // official bullseye at the upstream ratio.
                            'relativePath' => OmrFiducial::ASSET_NAME,
                            'sheetToMarkerWidthRatio' => OmrFiducial::SHEET_TO_MARKER_WIDTH_RATIO,
                        ],
                    ]]
                    : [['name' => 'CropPage', 'options' => ['morphKernel' => [5, 5]]]],
            ];
            if ($markerLayout !== null) {
                $frozen = OmrCapturePolicy::frozen();
                $page['markers'] = [
                    'family' => $frozen['fiducial']['family'],
                    'asset_name' => OmrFiducial::ASSET_NAME,
                    'size' => $markerLayout['marker'],
                    'quiet_zone' => $markerLayout['quiet'],
                    'inset' => $markerLayout['inset'],
                    'paper' => $markerLayout['paper'],
                    'centers' => $markerLayout['centers'],
                    'quadrants' => $markerLayout['quadrants'],
                ];
            }

            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private function canonicalTemplate(array $pages, int $width, int $height, int $bubble): array
    {
        return [
            'bubbleDimensions' => [$bubble, $bubble],
            'pageDimensions' => [$width, $height],
            'pages' => $pages,
        ];
    }

    /**
     * @return array{bubble: int, mcq_option_gap: int}
     */
    private function geometryForColumns(int $columnCount): array
    {
        return $columnCount === 6
            ? ['bubble' => self::COMPACT_BUBBLE, 'mcq_option_gap' => self::COMPACT_MCQ4_OPTION_GAP]
            : ['bubble' => self::BUBBLE, 'mcq_option_gap' => self::MCQ4_OPTION_GAP];
    }

    /**
     * Canonical encoding: recursively sorted object keys, integer geometry,
     * fixed JSON flags, exactly the bytes that would be written to a bundle.
     *
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return json_encode($this->sortRecursive($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * @return array<string, mixed>
     */
    private function sortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->isList($item) ? array_map(fn (mixed $v): mixed => is_array($v) ? $this->sortRecursive($v) : $v, $item) : $this->sortRecursive($item);
            }
        }

        return $value;
    }

    /** @param array<mixed> $value */
    private function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
