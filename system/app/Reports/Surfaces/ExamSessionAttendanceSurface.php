<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\ExamSessionAttendanceDataQuery;

final class ExamSessionAttendanceSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly ExamSessionAttendanceDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'session_attendance_sheet';
    }

    public function title(): string
    {
        return 'Exam Session Attendance Logbook';
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Flow;
    }

    /** @return array<string, array<string, mixed>> */
    public function componentCatalog(): array
    {
        return [
            'header' => [
                'label' => 'Report header',
                'description' => 'Institution identity and session attendance sheet title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{session_title}} · {{room_name}} · {{exam_date}} ({{time_slot}})'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Session KPIs',
                'description' => 'Total assigned, present, absent, and turnout rate.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Examinees attendance table',
                'description' => 'Repeating rows of examinee seats, names, check-in times, and signatures.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'examinees', 'columns' => $this->columns()],
                'inspector_fields' => ['repeat_key', 'columns'],
            ],
            'signature' => [
                'label' => 'Signature',
                'description' => 'Named report preparer, verifier, or approver.',
                'default_w' => 56,
                'default_h' => 22,
                'default_props' => ['label' => 'Test Administrator Signature', 'binding' => 'prepared_by'],
                'inspector_fields' => ['label', 'binding'],
            ],
            'footer' => [
                'label' => 'Footer',
                'description' => 'Report footer text with timestamp and pagination.',
                'default_w' => 186,
                'default_h' => 8,
                'default_props' => ['text' => 'Generated on {{report_date}} · Page {{page_i}} of {{page_all}}', 'align' => 'center'],
                'inspector_fields' => ['text', 'align'],
            ],
        ];
    }

    /** @return array<string, mixed> */
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
        $fullWidth = $dimensions['width_mm'] - 24;

        return [
            'schema_version' => 1,
            'layout_paradigm' => 'flow',
            'page' => [
                'paper_size' => $paperSize,
                'orientation' => $orientation,
                'width_mm' => $dimensions['width_mm'],
                'height_mm' => $dimensions['height_mm'],
                'margin_mm' => 12,
            ],
            'components' => [
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{session_title}} · {{room_name}} · {{exam_date}} ({{time_slot}})']],
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['metrics' => $this->kpiMetrics()]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'examinees', 'columns' => $this->columns()]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 160, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Test Administrator Signature', 'binding' => 'prepared_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{report_date}} · Page {{page_i}} of {{page_all}}', 'align' => 'center']],
            ],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function columns(): array
    {
        return [
            ['key' => 'seat_number', 'label' => 'Seat'],
            ['key' => 'reference_code', 'label' => 'Reference'],
            ['key' => 'applicant_name', 'label' => 'Examinee Name'],
            ['key' => 'sex', 'label' => 'Sex'],
            ['key' => 'program_code', 'label' => 'Program Choice'],
            ['key' => 'attendance_status', 'label' => 'Status'],
            ['key' => 'check_in_time', 'label' => 'Time In'],
            ['key' => 'signature', 'label' => 'Candidate Signature'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Total Assigned', 'binding' => 'total_assigned'],
            ['label' => 'Present', 'binding' => 'present_count'],
            ['label' => 'Absent / No-Show', 'binding' => 'absent_count'],
            ['label' => 'Turnout Rate', 'binding' => 'turnout_rate'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'session_code', 'session_title', 'room_name', 'exam_date', 'time_slot',
            'total_assigned', 'present_count', 'absent_count', 'turnout_rate',
            'examinees.row_no', 'examinees.seat_number', 'examinees.reference_code',
            'examinees.applicant_name', 'examinees.sex', 'examinees.program_code',
            'examinees.attendance_status', 'examinees.check_in_time', 'examinees.signature',
            'prepared_by', 'verified_by', 'approved_by',
        ];

        return ['required' => $placeholders, 'recommended' => [], 'all' => $placeholders];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'institution_code' => 'SYN-INST-001',
            'academic_cycle' => 'Academic Year 2026–2027 · First Semester',
            'report_date' => '2026-10-14',
            'session_code' => 'SES-001',
            'session_title' => 'Batch A - Morning Session',
            'room_name' => 'Main Auditorium RM-101',
            'exam_date' => 'October 14, 2026',
            'time_slot' => '8:00 AM – 11:30 AM',
            'total_assigned' => 40,
            'present_count' => 38,
            'absent_count' => 2,
            'turnout_rate' => '95.0%',
            'examinees' => [
                ['row_no' => 1, 'seat_number' => 'Seat 1', 'reference_code' => 'AY26-00001', 'applicant_name' => 'Amara Santos', 'sex' => 'Female', 'program_code' => 'BSN', 'attendance_status' => 'Present', 'check_in_time' => '7:45 AM', 'signature' => '___________________'],
                ['row_no' => 2, 'seat_number' => 'Seat 2', 'reference_code' => 'AY26-00002', 'applicant_name' => 'Gabriel Reyes', 'sex' => 'Male', 'program_code' => 'BSIT', 'attendance_status' => 'Present', 'check_in_time' => '7:50 AM', 'signature' => '___________________'],
                ['row_no' => 3, 'seat_number' => 'Seat 3', 'reference_code' => 'AY26-00003', 'applicant_name' => 'Isabella Cruz', 'sex' => 'Female', 'program_code' => 'BSED', 'attendance_status' => 'Absent / No-Show', 'check_in_time' => '—', 'signature' => '___________________'],
            ],
            'prepared_by' => 'Test Administrator',
            'verified_by' => 'Chief Test Administrator',
            'approved_by' => 'Head of Guidance & Testing',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'examinees';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['examinees'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
