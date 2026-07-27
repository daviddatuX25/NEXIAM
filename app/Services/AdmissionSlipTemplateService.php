<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AdmissionSlipTemplate;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;

class AdmissionSlipTemplateService
{
    public function __construct(
        protected PrintTemplateCssService $cssService,
    ) {}

    public const PLACEHOLDERS = [
        'reference_number', 'applicant_number', 'applicant_no', 'full_name', 'first_name', 'last_name', 'middle_name', 'suffix',
        'birthdate', 'sex',
        'course_1', 'course_2', 'course_3',
        'photo_placeholder', 'qr_code',
        'institution_name', 'institution_address', 'institution_logo', 'exam_title', 'academic_year', 'registrar_name',
        'reference_number_2', 'applicant_number_2', 'applicant_no_2', 'full_name_2', 'first_name_2', 'last_name_2', 'middle_name_2', 'suffix_2',
        'birthdate_2', 'sex_2',
        'course_1_2', 'course_2_2', 'course_3_2',
        'photo_placeholder_2', 'qr_code_2',
        'institution_name_2', 'institution_address_2', 'institution_logo_2', 'exam_title_2', 'academic_year_2', 'registrar_name_2',
    ];

    /**
     * Render template HTML for one or two applications (admission slip data).
     *
     * @param  array<int, array{reference_number: string, full_name: string, birthdate: string, sex: string, course_1: string, course_2: string, course_3: string}>  $applications
     */
    public function render(AdmissionSlipTemplate $template, array $applications, bool $useSampleData = false): string
    {
        $replacements = $this->buildReplacements($applications, $useSampleData);

        if ($template->mode === AdmissionSlipTemplate::MODE_HTML) {
            return $this->cssService->wrap(
                $this->renderHtml($template->content ?: '', $replacements)
            );
        }

        return $this->renderDocx($template->docx_path, $replacements);
    }

    public function renderHtmlContent(string $content, array $applications = [], bool $useSampleData = true): string
    {
        $replacements = $this->buildReplacements($applications, $useSampleData);

        return $this->cssService->wrap(
            $this->renderHtml($content, $replacements)
        );
    }

    private function buildReplacements(array $applications, bool $useSampleData = true): array
    {
        $applications = array_values($applications);
        $sample = $this->sampleApplicantData();
        $institution = $this->institutionData();

        $replacements = [];
        foreach ([1 => 0, 2 => 1] as $slot => $idx) {
            $app = $applications[$idx] ?? null;
            $data = $app ?? ($useSampleData ? $sample : null);
            $suffix = $slot === 1 ? '' : '_2';
            if ($data) {
                $replacements["reference_number{$suffix}"] = $data['reference_number'] ?? '—';
                $replacements["applicant_number{$suffix}"] = $data['reference_number'] ?? '—';
                $replacements["applicant_no{$suffix}"] = $data['reference_number'] ?? '—';
                $replacements["full_name{$suffix}"] = $data['full_name'] ?? '—';
                $replacements["first_name{$suffix}"] = $data['first_name'] ?? '—';
                $replacements["last_name{$suffix}"] = $data['last_name'] ?? '—';
                $replacements["middle_name{$suffix}"] = $data['middle_name'] ?? '';
                $replacements["suffix{$suffix}"] = $data['suffix'] ?? '';
                $replacements["birthdate{$suffix}"] = $data['birthdate'] ?? '—';
                $replacements["sex{$suffix}"] = $data['sex'] ?? '—';
                $replacements["course_1{$suffix}"] = $data['course_1'] ?? '—';
                $replacements["course_2{$suffix}"] = $data['course_2'] ?? '—';
                $replacements["course_3{$suffix}"] = $data['course_3'] ?? '—';
                $replacements["photo_placeholder{$suffix}"] = $this->photoPlaceholder();
                $replacements["qr_code{$suffix}"] = $data['qr_code'] ?? $this->qrPlaceholder($data['reference_number'] ?? '');
                $replacements["qr_placeholder{$suffix}"] = $data['qr_code'] ?? $this->qrPlaceholder($data['reference_number'] ?? '');
            } else {
                $replacements["reference_number{$suffix}"] = '—';
                $replacements["applicant_number{$suffix}"] = '—';
                $replacements["applicant_no{$suffix}"] = '—';
                $replacements["full_name{$suffix}"] = '—';
                $replacements["first_name{$suffix}"] = '—';
                $replacements["last_name{$suffix}"] = '—';
                $replacements["middle_name{$suffix}"] = '';
                $replacements["suffix{$suffix}"] = '';
                $replacements["birthdate{$suffix}"] = '—';
                $replacements["sex{$suffix}"] = '—';
                $replacements["course_1{$suffix}"] = '—';
                $replacements["course_2{$suffix}"] = '—';
                $replacements["course_3{$suffix}"] = '—';
                $replacements["photo_placeholder{$suffix}"] = '';
                $replacements["qr_code{$suffix}"] = '';
                $replacements["qr_placeholder{$suffix}"] = '';
            }

            $iSuffix = $suffix;
            $replacements["institution_name{$iSuffix}"] = $institution['institution_name'] ?? '';
            $replacements["institution_address{$iSuffix}"] = $institution['institution_address'] ?? '';
            $replacements["institution_logo{$iSuffix}"] = $institution['institution_logo'] ?? '';
            $replacements["exam_title{$iSuffix}"] = $institution['exam_title'] ?? '';
            $replacements["academic_year{$iSuffix}"] = $institution['academic_year'] ?? '';
            $replacements["registrar_name{$iSuffix}"] = $institution['registrar_name'] ?? '';
        }

        return $replacements;
    }

    private function institutionData(): array
    {
        $logoUrl = SystemSetting::institution('logo_url');
        $logoTag = $logoUrl ? sprintf('<img src="%s" alt="Institution Logo" style="max-height:80px;">', e($logoUrl)) : '';

        $activeYear = AcademicYear::active();
        $academicYearLabel = $activeYear ? trim("{$activeYear->academic_year} {$activeYear->semesterLabel()}") : '';

        return [
            'institution_name' => SystemSetting::institution('name') ?? '',
            'institution_address' => SystemSetting::institution('address') ?? '',
            'institution_logo' => $logoTag,
            'exam_title' => SystemSetting::institution('exam_title') ?? '',
            'academic_year' => $academicYearLabel,
            'registrar_name' => SystemSetting::institution('personnel.registrar.name') ?? '',
        ];
    }

    /**
     * Render DOCX file to HTML (for preview).
     *
     * @param  array<string, string>  $replacements
     */
    public function renderDocxFile(string $path, array $replacements = [], bool $useSampleIfEmpty = true): string
    {
        if (empty($replacements) && $useSampleIfEmpty) {
            $replacements = $this->buildReplacements([], true);
        }

        return $this->renderDocx($path, $replacements);
    }

    protected function renderHtml(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        return $content;
    }

    protected function renderDocx(?string $docxPath, array $replacements): string
    {
        if (! $docxPath) {
            return '<p class="text-muted-foreground">No DOCX template.</p>';
        }

        $fullPath = Storage::path($docxPath);
        if (! is_file($fullPath)) {
            return '<p class="text-destructive">DOCX file not found.</p>';
        }

        $processor = new TemplateProcessor($fullPath);
        $processor->setMacroChars('{{', '}}');
        foreach ($replacements as $key => $value) {
            $processor->setValue($key, $value);
        }

        $tempDocx = tempnam(sys_get_temp_dir(), 'ast_').'.docx';
        $processor->saveAs($tempDocx);

        try {
            $phpWord = IOFactory::load($tempDocx);
            $tempHtml = tempnam(sys_get_temp_dir(), 'ast_').'.html';
            $writer = IOFactory::createWriter($phpWord, 'HTML');
            $writer->save($tempHtml);
            $html = file_get_contents($tempHtml);
        } finally {
            @unlink($tempDocx);
            if (isset($tempHtml) && is_file($tempHtml)) {
                @unlink($tempHtml);
            }
        }

        return $html ?: '<p class="text-muted-foreground">Failed to convert DOCX to HTML.</p>';
    }

    protected function photoPlaceholder(): string
    {
        return '<div class="photo-placeholder" style="width:120px;height:150px;border:2px dashed #9ca3af;display:inline-block;text-align:center;line-height:150px;color:#9ca3af;font-size:10px;">Photo</div>';
    }

    protected function qrPlaceholder(string $reference): string
    {
        return sprintf(
            '<div class="qr-placeholder" style="width:80px;height:80px;border:2px dashed #9ca3af;display:inline-block;text-align:center;line-height:80px;color:#9ca3af;font-size:10px;">QR Code</div>'
        );
    }

    protected function sampleApplicantData(): array
    {
        return [
            'reference_number' => 'APP-2026-00001',
            'full_name' => 'Juan Dela Cruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'middle_name' => '',
            'suffix' => '',
            'birthdate' => 'January 15, 2005',
            'sex' => 'Male',
            'course_1' => 'Bachelor of Science in Computer Science',
            'course_2' => 'Bachelor of Science in Information Technology',
            'course_3' => 'Bachelor of Science in Data Science',
            'qr_code' => $this->qrPlaceholder('APP-2026-00001'),
        ];
    }

    /**
     * Get CSS dimensions for preview container.
     */
    public function previewDimensions(string $paperSize, string $orientation, string $logicalUnit): array
    {
        $sizes = [
            'a4' => ['portrait' => [210, 297], 'landscape' => [297, 210]],
            'legal' => ['portrait' => [216, 356], 'landscape' => [356, 216]],
            'letter' => ['portrait' => [216, 279], 'landscape' => [279, 216]],
        ];
        [$w, $h] = $sizes[$paperSize][$orientation] ?? [210, 297];

        if (str_starts_with($logicalUnit, 'half_')) {
            $h = (int) ($h / 2);
        }

        return ['width' => "{$w}mm", 'height' => "{$h}mm"];
    }
}
