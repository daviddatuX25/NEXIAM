<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\SystemSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\View;

class AdmissionSlipService
{
    public function __construct(
        protected AdmissionSlipTemplateService $templateService,
        protected PrintTemplateCssService $cssService,
    ) {}

    public static function isEnabled(): bool
    {
        return SystemSetting::admissionSlipEnabled();
    }

    public function generatePdf(Application $application): \Barryvdh\DomPDF\PDF
    {
        $customTemplate = SystemSetting::admissionSlipTemplate();

        if ($customTemplate) {
            $data = $this->buildPlaceholderData($application);
            $html = $this->templateService->renderHtmlContent($customTemplate, [$data], false);
            $pdfHtml = $this->cssService->wrapForPdf($html);

            return Pdf::loadHTML($pdfHtml);
        }

        return Pdf::loadView('pdf.admission-slip', $this->bladeViewData($application));
    }

    public function renderHtml(Application $application): string
    {
        $customTemplate = SystemSetting::admissionSlipTemplate();

        if ($customTemplate) {
            $data = $this->buildPlaceholderData($application);
            $html = $this->templateService->renderHtmlContent($customTemplate, [$data], false);

            return $this->cssService->wrap($html);
        }

        return View::make('pdf.admission-slip', $this->bladeViewData($application))->render();
    }

    public function buildPlaceholderData(Application $application): array
    {
        $application->load(['coursePreference1', 'coursePreference2', 'coursePreference3']);

        $fullName = trim(implode(' ', array_filter([
            $application->first_name,
            $application->middle_name,
            $application->last_name,
            $application->suffix,
        ])));

        $qrCode = $this->generateQrCodeTag($application->reference_number);

        $logoUrl = SystemSetting::institution('logo_url');
        $logoTag = $logoUrl ? sprintf('<img src="%s" alt="Institution Logo" style="max-height:80px;">', e($logoUrl)) : '';

        $activeYear = AcademicYear::active();
        $academicYearLabel = $activeYear ? trim("{$activeYear->academic_year} {$activeYear->semesterLabel()}") : '';

        return [
            'reference_number' => $application->reference_number ?? '—',
            'full_name' => $fullName ?: '—',
            'first_name' => $application->first_name ?? '—',
            'last_name' => $application->last_name ?? '—',
            'middle_name' => $application->middle_name ?? '',
            'suffix' => $application->suffix ?? '',
            'birthdate' => $application->birthdate?->format('F j, Y') ?? '—',
            'sex' => ucfirst($application->sex ?? ''),
            'course_1' => $application->coursePreference1?->name ?? '—',
            'course_2' => $application->coursePreference2?->name ?? '—',
            'course_3' => $application->coursePreference3?->name ?? '—',
            'qr_code' => $qrCode,
            'institution_name' => SystemSetting::institution('name') ?? '',
            'institution_address' => SystemSetting::institution('address') ?? '',
            'institution_logo' => $logoTag,
            'exam_title' => SystemSetting::institution('exam_title') ?? '',
            'academic_year' => $academicYearLabel,
            'registrar_name' => SystemSetting::institution('personnel.registrar.name') ?? '',
        ];
    }

    private function generateQrCodeTag(string $referenceNumber): string
    {
        try {
            $result = Builder::create()
                ->writer(new PngWriter)
                ->writerOptions([])
                ->data($referenceNumber)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(ErrorCorrectionLevel::High)
                ->size(200)
                ->margin(0)
                ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
                ->build();

            $base64 = base64_encode($result->getString());

            return sprintf('<img src="data:image/png;base64,%s" alt="QR Code" style="width:80px;height:80px;">', $base64);
        } catch (\Throwable $e) {
            return sprintf(
                '<div style="width:80px;height:80px;border:2px dashed #9ca3af;display:inline-block;text-align:center;line-height:80px;color:#9ca3af;font-size:10px;">QR</div>'
            );
        }
    }

    private function bladeViewData(Application $application): array
    {
        $application->load(['coursePreference1', 'coursePreference2', 'coursePreference3']);

        $fullName = trim(implode(' ', array_filter([
            $application->first_name,
            $application->middle_name,
            $application->last_name,
            $application->suffix,
        ])));

        $courseLabels = [
            $application->coursePreference1?->name ?? '—',
            $application->coursePreference2?->name ?? '—',
            $application->coursePreference3?->name ?? '—',
        ];

        return [
            'referenceNumber' => $application->reference_number,
            'fullName' => $fullName,
            'birthdate' => $application->birthdate->format('F j, Y'),
            'sex' => ucfirst($application->sex),
            'courseLabels' => $courseLabels,
        ];
    }
}
