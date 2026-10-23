<?php

namespace App\Reports\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\ProgramQuota;

final class InstitutionalSummarySurface implements DocumentSurfaceInterface
{
    public function surfaceKey(): string
    {
        return 'institutional_executive_summary';
    }

    public function title(): string
    {
        return 'Institutional Executive & Quota Summary';
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Flow;
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $placeholders = [
            'institution_name', 'institution_code', 'academic_cycle_name', 'generated_at',
            'quota_capacity', 'qualified_count', 'remaining_quota_slots',
            'program_quota_rows.program_code', 'program_quota_rows.program_name',
            'program_quota_rows.department_code', 'program_quota_rows.quota_capacity',
            'program_quota_rows.qualified_count', 'program_quota_rows.remaining_slots', 'prepared_by_name',
            'verification_qr',
        ];

        return ['required' => $placeholders, 'recommended' => [], 'all' => $placeholders];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        return [
            'institution_name' => 'NEXIAM Institute of Technology',
            'institution_code' => 'SYN-INST-001',
            'academic_cycle_name' => '2026–2027 1st Semester',
            'generated_at' => '2026-10-13 09:00',
            'quota_capacity' => 360,
            'qualified_count' => 245,
            'remaining_quota_slots' => 115,
            'program_quota_rows' => [
                ['program_code' => 'BSIT', 'program_name' => 'Information Technology', 'department_code' => 'CCS', 'quota_capacity' => 120, 'qualified_count' => 84, 'remaining_slots' => 36],
                ['program_code' => 'BSBA', 'program_name' => 'Business Administration', 'department_code' => 'CBA', 'quota_capacity' => 120, 'qualified_count' => 79, 'remaining_slots' => 41],
                ['program_code' => 'BSED', 'program_name' => 'Secondary Education', 'department_code' => 'COE', 'quota_capacity' => 120, 'qualified_count' => 82, 'remaining_slots' => 38],
            ],
            'prepared_by_name' => 'Admin Office',
            'verification_qr' => 'data:image/png;base64,'.base64_encode(
                (new DynamicAssetResolutionService)->renderVerificationQr(
                    'https://nexiam.test/admin?verified_report=institutional_executive_summary',
                    240,
                    2,
                )
            ),
        ];
    }

    public function repeatingRowKey(): ?string
    {
        return 'program_quota_rows';
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return ['program_quota_rows'];
    }

    /** @return array<string, array<string, mixed>> */
    public function componentCatalog(): array
    {
        return [
            'header' => ['label' => 'Report header', 'description' => 'Institution and cycle summary heading.', 'default_w' => 186, 'default_h' => 24, 'default_props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle_name}}'], 'inspector_fields' => ['title', 'subtitle', 'align']],
            'data_table' => ['label' => 'Program quota summary', 'description' => 'Aggregate quota and qualified counts by program.', 'default_w' => 186, 'default_h' => 96, 'default_props' => ['repeat_key' => 'program_quota_rows', 'columns' => $this->columns()], 'inspector_fields' => ['repeat_key', 'columns']],
            'signature' => ['label' => 'Signature', 'description' => 'Named report preparer.', 'default_w' => 56, 'default_h' => 22, 'default_props' => ['label' => 'Prepared by', 'binding' => 'prepared_by_name'], 'inspector_fields' => ['label', 'binding']],
            'dynamic_qr' => ['label' => 'Verification QR', 'description' => 'Document authenticity and verification QR code.', 'default_w' => 28, 'default_h' => 28, 'default_props' => ['binding' => 'verification_qr', 'alt' => 'Verification QR', 'error_correction' => 'M'], 'inspector_fields' => ['binding', 'error_correction']],
            'footer' => ['label' => 'Footer', 'description' => 'Generated date and pagination.', 'default_w' => 186, 'default_h' => 8, 'default_props' => ['text' => 'Generated on {{generated_at}} · Page {{page_i}} of {{page_all}}'], 'inspector_fields' => ['text', 'align']],
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
            'page' => ['paper_size' => $paperSize, 'orientation' => $orientation, ...$dimensions, 'margin_mm' => 12],
            'components' => [
                ['id' => 'header-1', 'type' => 'header', 'x' => 0, 'y' => 0, 'w' => $fullWidth, 'h' => 24, 'props' => ['title' => $this->title(), 'subtitle' => '{{academic_cycle_name}}']],
                ['id' => 'data_table-1', 'type' => 'data_table', 'x' => 0, 'y' => 30, 'w' => $fullWidth, 'h' => 96, 'props' => ['repeat_key' => 'program_quota_rows', 'columns' => $this->columns()]],
                ['id' => 'signature-1', 'type' => 'signature', 'x' => 0, 'y' => 138, 'w' => 56, 'h' => 22, 'props' => ['label' => 'Prepared by', 'binding' => 'prepared_by_name']],
                ['id' => 'qr-1', 'type' => 'dynamic_qr', 'x' => max(0, $fullWidth - 30), 'y' => 134, 'w' => 28, 'h' => 28, 'props' => ['binding' => 'verification_qr', 'alt' => 'Verification QR', 'error_correction' => 'M']],
                ['id' => 'footer-1', 'type' => 'footer', 'x' => 0, 'y' => 166, 'w' => $fullWidth, 'h' => 8, 'props' => ['text' => 'Generated on {{generated_at}} · Page {{page_i}} of {{page_all}}']],
            ],
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        $cycleId = isset($filters['academic_cycle_id']) ? (int) $filters['academic_cycle_id'] : AcademicCycle::active()?->id;
        $cycle = $cycleId === null ? null : AcademicCycle::query()->with('institution')->find($cycleId);
        $quotaRows = $cycleId === null
            ? collect()
            : ProgramQuota::query()->with(['program.department'])->where('academic_cycle_id', $cycleId)->orderBy('program_id')->get();

        $qualifiedCounts = $quotaRows->isEmpty() ? [] : AdmissionAttempt::query()
            ->forPracticeSet(null)
            ->where('academic_cycle_id', $cycleId)
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
        $institution = $cycle?->institution ?? Institution::query()->first();

        return [
            'institution_name' => (string) ($institution?->name ?? 'Institution'),
            'institution_code' => (string) ($institution?->code ?? ''),
            'academic_cycle_name' => (string) ($cycle?->staffLabel() ?? ''),
            'generated_at' => now()->format('Y-m-d H:i'),
            'quota_capacity' => $capacity,
            'qualified_count' => $qualified,
            'remaining_quota_slots' => max(0, $capacity - $qualified),
            'program_quota_rows' => $rows,
            'prepared_by_name' => 'Admin Office',
            'verification_qr' => 'data:image/png;base64,'.base64_encode(
                (new DynamicAssetResolutionService)->renderVerificationQr(
                    url('/admin?report=institutional_executive_summary&cycle='.($cycleId ?? '')),
                    240,
                    2,
                )
            ),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function columns(): array
    {
        return [
            ['key' => 'program_code', 'label' => 'Program Code'],
            ['key' => 'program_name', 'label' => 'Program Name'],
            ['key' => 'department_code', 'label' => 'Department'],
            ['key' => 'quota_capacity', 'label' => 'Quota Capacity'],
            ['key' => 'qualified_count', 'label' => 'Qualified Count'],
            ['key' => 'remaining_slots', 'label' => 'Remaining Slots'],
        ];
    }
}
