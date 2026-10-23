<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\DocumentTemplate\MasterDataFieldCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\ProgramQuota;

final class CustomDocumentSurface implements DocumentSurfaceInterface
{
    public function surfaceKey(): string
    {
        return 'custom_document';
    }

    public function title(): string
    {
        return 'Custom Office Document';
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Flow;
    }

    public function repeatingRowKey(): ?string
    {
        return 'custom_rows';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['custom_rows', 'qualifiers_or_clearance_roster', 'passing_or_score_roster', 'session_examinee_roster', 'program_quota_rows'];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        return [
            'required' => [],
            'recommended' => [],
            'aliases' => [
                'school_short' => 'last_school_short',
                'history_short' => 'strand_or_prev_course_short',
            ],
            'all' => array_values(array_unique([
                ...MasterDataFieldCatalog::leafIds(),
                'qualifiers.row_no',
                'qualifiers.reference_code',
                'qualifiers.applicant_name',
                'qualifiers.strand',
                'qualifiers.gwa',
                'qualifiers.program_code',
                'qualifiers.admission_status',
                'qualifiers.accepted_at',
                'passers.row_no',
                'passers.reference_code',
                'passers.applicant_name',
                'passers.exam_date',
                'passers.room_name',
                'passers.overall_score',
                'passers.stanine',
                'passers.status',
                'session_examinees.row_no',
                'session_examinees.reference_code',
                'session_examinees.applicant_name',
                'session_examinees.exam_date',
                'session_examinees.room_name',
                'session_examinees.seat_label',
                'program_quota_rows.program_code',
                'program_quota_rows.program_name',
                'program_quota_rows.department_code',
                'program_quota_rows.quota_capacity',
                'program_quota_rows.qualified_count',
                'program_quota_rows.remaining_slots',
                'custom_note',
                'custom_heading',
                'document_title',
            ])),
        ];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        return [
            'institution_name' => 'NEXIAM State College',
            'institution_code' => 'NSC',
            'academic_cycle_name' => 'Academic Year 2026–2027',
            'academic_cycle' => 'Academic Year 2026–2027',
            'generated_at' => 'October 13, 2026',
            'report_date' => 'October 13, 2026',
            'document_title' => 'Office Document',
            'custom_heading' => 'Office Notice',
            'custom_note' => 'Sample office document note.',
            'applicant_name' => 'Jordan Reyes',
            'applicant_number' => 'AY26-00042',
            'examinee_number' => '00042',
            'sequence_number' => '42',
            'program_name' => 'Bachelor of Science in Computer Science',
            'session_name' => 'Morning Examination Session',
            'session_date' => 'October 13, 2026',
            'prepared_by_name' => 'Alex Santos',
            'qualified_count' => '24',
            'total_examinees' => '32',
            'total_passed' => '24',
            'remaining_quota_slots' => '6',
            'page_i' => '1',
            'page_all' => '1',
            'custom_rows' => [
                ['custom_note' => 'Sample office document note.'],
            ],
        ];
    }

    public function componentCatalog(): array
    {
        return [
            'header' => ['label' => 'Document header', 'description' => 'Institution identity and document title.', 'default_w' => 186, 'default_h' => 24, 'default_props' => ['title' => '{{institution_name}}', 'subtitle' => 'Official Office Document'], 'inspector_fields' => ['title', 'subtitle', 'align']],
            'metadata_grid' => ['label' => 'Metadata grid', 'description' => 'Document reference and record details.', 'default_w' => 186, 'default_h' => 24, 'default_props' => ['items' => []], 'inspector_fields' => ['items']],
            'summary_metrics' => ['label' => 'Summary metrics', 'description' => 'Key aggregate values.', 'default_w' => 186, 'default_h' => 24, 'default_props' => ['items' => []], 'inspector_fields' => ['items']],
            'paragraph' => ['label' => 'Paragraph', 'description' => 'Custom document text and merge fields.', 'default_w' => 186, 'default_h' => 36, 'default_props' => ['text' => ''], 'inspector_fields' => ['text', 'align']],
            'text' => ['label' => 'Text', 'description' => 'Custom document text and merge fields.', 'default_w' => 186, 'default_h' => 36, 'default_props' => ['text' => ''], 'inspector_fields' => ['text', 'align']],
            'data_table' => ['label' => 'Data table', 'description' => 'Repeating rows of authorized records.', 'default_w' => 186, 'default_h' => 64, 'default_props' => ['repeat_key' => 'custom_rows', 'columns' => []], 'inspector_fields' => ['repeat_key', 'columns']],
            'signature' => ['label' => 'Signature', 'description' => 'Named preparer or authorized signatory.', 'default_w' => 56, 'default_h' => 22, 'default_props' => ['label' => 'Prepared by', 'binding' => 'prepared_by_name', 'role' => 'Authorized Staff'], 'inspector_fields' => ['label', 'binding', 'role']],
            'footer' => ['label' => 'Footer', 'description' => 'Document footer and pagination.', 'default_w' => 186, 'default_h' => 8, 'default_props' => ['text' => ''], 'inspector_fields' => ['text', 'align']],
            'spacer' => ['label' => 'Spacer', 'description' => 'Vertical space between sections.', 'default_w' => 186, 'default_h' => 8, 'default_props' => [], 'inspector_fields' => []],
            'divider' => ['label' => 'Divider', 'description' => 'Horizontal section divider.', 'default_w' => 186, 'default_h' => 2, 'default_props' => [], 'inspector_fields' => ['style']],
        ];
    }

    public function defaultBlueprint(string $paperSize = 'a4', string $orientation = 'portrait'): array
    {
        $paperSize = in_array($paperSize, ['a4', 'legal', 'letter'], true) ? $paperSize : 'a4';
        $orientation = in_array($orientation, ['portrait', 'landscape'], true) ? $orientation : 'portrait';
        $dimensions = match ($paperSize) {
            'legal' => ['width_mm' => 216, 'height_mm' => 356],
            'letter' => ['width_mm' => 216, 'height_mm' => 279],
            default => ['width_mm' => 210, 'height_mm' => 297],
        };
        if ($orientation === 'landscape') {
            $dimensions = ['width_mm' => $dimensions['height_mm'], 'height_mm' => $dimensions['width_mm']];
        }
        $width = $dimensions['width_mm'] - 24;

        return [
            'schema_version' => 1,
            'layout_paradigm' => 'flow',
            'page' => ['paper_size' => $paperSize, 'orientation' => $orientation, ...$dimensions, 'margin_mm' => 12],
            'components' => [
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $width, 'h' => 24, 'props' => ['title' => '{{institution_name}}', 'subtitle' => 'Official Office Document']],
                ['id' => 'paragraph-1', 'type' => 'text', 'x' => 0, 'y' => 30, 'w' => $width, 'h' => 36, 'props' => ['text' => 'Enter your custom office document content, notices, or merge fields here.']],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 78, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Prepared by', 'binding' => 'prepared_by_name', 'role' => 'Authorized Staff']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $width, 'h' => 8, 'props' => ['text' => 'Generated on {{generated_at}} · Page {{page_i}} of {{page_all}}']],
            ],
        ];
    }

    public function resolveData(array $filters): array
    {
        $institution = Institution::current();
        $cycleId = isset($filters['academic_cycle_id'])
            ? (int) $filters['academic_cycle_id']
            : AcademicCycle::active()?->id;
        $cycle = $cycleId === null
            ? null
            : AcademicCycle::query()
                ->where('institution_id', $institution->id)
                ->with('institution')
                ->find($cycleId);
        $quotaRows = $cycle === null
            ? collect()
            : ProgramQuota::query()
                ->with(['program.department'])
                ->where('academic_cycle_id', $cycle->id)
                ->orderBy('program_id')
                ->get();
        $qualifiedCounts = $quotaRows->isEmpty() ? [] : AdmissionAttempt::query()
            ->forPracticeSet(null)
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->selectRaw('preferred_program_code, COUNT(*) as aggregate_count')
            ->groupBy('preferred_program_code')
            ->pluck('aggregate_count', 'preferred_program_code');
        $rows = $quotaRows->map(static function (ProgramQuota $quota) use ($qualifiedCounts): array {
            $program = $quota->program;
            $qualified = (int) ($qualifiedCounts[$program?->code ?? ''] ?? 0);
            $capacity = (int) $quota->slots;

            return [
                'program_code' => (string) ($program?->code ?? ''),
                'program_name' => (string) ($program?->name ?? ''),
                'department_code' => (string) ($program?->department?->code ?? ''),
                'quota_capacity' => $capacity,
                'qualified_count' => $qualified,
                'remaining_slots' => max(0, $capacity - $qualified),
            ];
        })->values()->all();
        $capacity = array_sum(array_column($rows, 'quota_capacity'));
        $qualified = array_sum(array_column($rows, 'qualified_count'));

        return [
            'institution_name' => (string) $institution->name,
            'institution_code' => (string) $institution->code,
            'academic_cycle_name' => (string) ($cycle?->staffLabel() ?? ''),
            'generated_at' => now()->format('Y-m-d H:i'),
            'quota_capacity' => $capacity,
            'qualified_count' => $qualified,
            'remaining_quota_slots' => max(0, $capacity - $qualified),
            'program_quota_rows' => $rows,
            'prepared_by_name' => 'Authorized Staff',
        ];
    }
}
