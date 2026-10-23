<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Reports\Queries\RoomUtilizationDataQuery;

final class RoomUtilizationSurface implements DocumentSurfaceInterface
{
    public function __construct(private readonly RoomUtilizationDataQuery $dataQuery) {}

    public function surfaceKey(): string
    {
        return 'room_utilization';
    }

    public function title(): string
    {
        return 'Room Capacity & Venue Utilization Report';
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
                'description' => 'Institution identity and room utilization title.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle}}'],
                'inspector_fields' => ['title', 'subtitle', 'align'],
            ],
            'kpi_grid' => [
                'label' => 'Capacity KPIs',
                'description' => 'Total rooms, total capacity, total examinees seated, and utilization rate.',
                'default_w' => 186,
                'default_h' => 24,
                'default_props' => ['metrics' => $this->kpiMetrics()],
                'inspector_fields' => ['metrics'],
            ],
            'data_table' => [
                'label' => 'Rooms table',
                'description' => 'Repeating rows of rooms, buildings, capacity, and utilization percentage.',
                'default_w' => 186,
                'default_h' => 96,
                'default_props' => ['repeat_key' => 'rooms', 'columns' => $this->columns()],
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
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 56, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'rooms', 'columns' => $this->columns()]],
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
            ['key' => 'room_code', 'label' => 'Code'],
            ['key' => 'room_name', 'label' => 'Venue / Room Name'],
            ['key' => 'building', 'label' => 'Building'],
            ['key' => 'capacity', 'label' => 'Capacity'],
            ['key' => 'sessions_hosted', 'label' => 'Sessions'],
            ['key' => 'seats_utilized', 'label' => 'Examinees Seated'],
            ['key' => 'utilization_rate', 'label' => 'Utilization %'],
        ];
    }

    /** @return list<array{label: string, binding: string}> */
    private function kpiMetrics(): array
    {
        return [
            ['label' => 'Configured Testing Rooms', 'binding' => 'total_rooms'],
            ['label' => 'Aggregate Room Capacity', 'binding' => 'total_capacity'],
            ['label' => 'Total Examinees Seated', 'binding' => 'total_examinees_seated'],
            ['label' => 'Average Utilization', 'binding' => 'average_utilization_rate'],
        ];
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle', 'report_date',
            'total_rooms', 'total_capacity', 'total_examinees_seated', 'average_utilization_rate',
            'rooms.row_no', 'rooms.room_code', 'rooms.room_name', 'rooms.building', 'rooms.floor',
            'rooms.capacity', 'rooms.sessions_hosted', 'rooms.seats_utilized', 'rooms.utilization_rate', 'rooms.status',
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
            'total_rooms' => 8,
            'total_capacity' => 380,
            'total_examinees_seated' => 345,
            'average_utilization_rate' => '90.8%',
            'rooms' => [
                ['row_no' => 1, 'room_code' => 'RM-101', 'room_name' => 'Main Auditorium', 'building' => 'Admin Wing', 'floor' => '1', 'capacity' => 60, 'sessions_hosted' => 3, 'seats_utilized' => 172, 'utilization_rate' => '95.6%', 'status' => 'Active'],
                ['row_no' => 2, 'room_code' => 'RM-102', 'room_name' => 'Computer Laboratory 1', 'building' => 'ICT Building', 'floor' => '2', 'capacity' => 45, 'sessions_hosted' => 3, 'seats_utilized' => 125, 'utilization_rate' => '92.6%', 'status' => 'Active'],
            ],
            'prepared_by' => 'Logistics Officer',
            'verified_by' => 'Facility Supervisor',
            'approved_by' => 'Head of Guidance & Testing',
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'rooms';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['rooms'];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        return $this->dataQuery->handle($filters);
    }
}
