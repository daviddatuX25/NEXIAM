<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\ExamAttendanceDataQuery;

final class ExamAttendanceSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly ExamAttendanceDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'exam_attendance';
    }

    public function title(): string
    {
        return 'Exam Attendance & Absenteeism Register';
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
                'description' => 'Institution identity and exam attendance register title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Attendance KPIs',
                'description' => 'Total sessions, registered, present, and attendance rate.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Sessions table',
                'description' => 'Repeating rows of exam sessions, venues, turnout, and absenteeism.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'sessions', 'columns' => $this->columns()],
                'inspector_fields' => ['repeat_key', 'columns'],
            ],
            'signature' => [
                'label' => 'Signature',
                'description' => 'Named report preparer, verifier, or approver.',
                'default_w' => 56,
                'default_h' => 22,
                'default_props' => ['label' => 'Prepared by', 'binding' => 'prepared_by'],
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
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}']],
                ['id' => 'kpi_grid-1', 'type' => 'kpi_grid', 'x' => 0, 'y' => 28, 'w' => $fullWidth, 'h' => 24, 'props' => ['metrics' => $this->kpiMetrics()]],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'sessions', 'columns' => $this->columns()]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 160, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Prepared by', 'binding' => 'prepared_by']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 190, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{report_date}} · Page {{page_i}} of {{page_all}}', 'align' => 'center']],
            ],
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function columns(): array
    {
        return [
            ['key' => 'row_no', 'label' => '#'],
            ['key' => 'session_code', 'label' => 'Session Code'],
            ['key' => 'room_name', 'label' => 'Room / Venue'],
            ['key' => 'exam_date', 'label' => 'Date'],
            ['key' => 'time_slot', 'label' => 'Schedule'],
            ['key' => 'registered_count', 'label' => 'Registered'],
            ['key' => 'present_count', 'label' => 'Present'],
            ['key' => 'absent_count', 'label' => 'No-Show'],
            ['key' => 'attendance_rate', 'label' => 'Turnout %'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Total Exam Sessions', 'binding' => 'total_sessions'],
            ['label' => 'Total Registered', 'binding' => 'total_registered'],
            ['label' => 'Present Turnout', 'binding' => 'total_present'],
            ['label' => 'Turnout Rate', 'binding' => 'overall_attendance_rate'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'total_sessions', 'total_registered', 'total_present', 'total_absent', 'overall_attendance_rate',
            'sessions.row_no', 'sessions.session_code', 'sessions.session_title', 'sessions.room_name',
            'sessions.exam_date', 'sessions.time_slot', 'sessions.capacity', 'sessions.registered_count',
            'sessions.present_count', 'sessions.absent_count', 'sessions.attendance_rate', 'sessions.status',
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
            'total_sessions' => 12,
            'total_registered' => 450,
            'total_present' => 415,
            'total_absent' => 35,
            'overall_attendance_rate' => '92.2%',
            'sessions' => [
                ['row_no' => 1, 'session_code' => 'SES-001', 'session_title' => 'Batch A - Morning Session', 'room_name' => 'Main Audi-101', 'exam_date' => '2026-09-15', 'time_slot' => '8:00 AM – 11:30 AM', 'capacity' => 50, 'registered_count' => 48, 'present_count' => 45, 'absent_count' => 3, 'attendance_rate' => '93.8%', 'status' => 'Completed'],
                ['row_no' => 2, 'session_code' => 'SES-002', 'session_title' => 'Batch B - Afternoon Session', 'room_name' => 'Engineering Lab 202', 'exam_date' => '2026-09-15', 'time_slot' => '1:00 PM – 4:30 PM', 'capacity' => 45, 'registered_count' => 42, 'present_count' => 39, 'absent_count' => 3, 'attendance_rate' => '92.9%', 'status' => 'Completed'],
            ],
            'prepared_by' => 'Guidance Staff',
            'verified_by' => 'Chief Test Administrator',
            'approved_by' => 'Head of Guidance & Testing',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'sessions';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['sessions'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
