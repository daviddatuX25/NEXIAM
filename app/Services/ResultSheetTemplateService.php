<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\ApplicantScore;
use App\Models\AptitudeArea;
use App\Models\Course;
use App\Models\GradingSession;
use App\Models\RatingScale;
use App\Models\ResultSheetTemplate;
use App\Models\SystemSetting;
use App\ValueObjects\DocxValidationResult;
use App\ValueObjects\RenderResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;

class ResultSheetTemplateService
{
    public function __construct(
        protected PrintTemplateCssService $cssService,
        protected ResultSheetDocxService $docxService,
        protected ResultSheetOdtService $odtService,
    ) {}

    protected function documentService(ResultSheetTemplate $template): ResultSheetDocxService|ResultSheetOdtService
    {
        $ext = strtolower(pathinfo($template->document_path ?? '', PATHINFO_EXTENSION));

        return $ext === 'odt' ? $this->odtService : $this->docxService;
    }

    public const PLACEHOLDERS = [
        'applicant_name', 'applicant_reference', 'applicant_number', 'applicant_no',
        'family_name', 'first_name', 'middle_name', 'suffix',
        'sex', 'gwa', 'course_applied', 'course_applied_code', 'strand', 'strand_or_prev_course', 'applicant_type',
        'exam_date', 'exam_time', 'room_name',
        'exam_date_start', 'exam_date_end', 'exam_time_start', 'exam_time_end',
        'scores_rows', 'overall_pct',
        'recommended_course', 'counselor_comments', 'counselor_name',
        'applicant_name_2', 'applicant_reference_2', 'applicant_number_2', 'applicant_no_2',
        'family_name_2', 'first_name_2', 'middle_name_2', 'suffix_2',
        'sex_2', 'gwa_2', 'course_applied_2', 'course_applied_code_2', 'strand_2', 'strand_or_prev_course_2', 'applicant_type_2',
        'exam_date_2', 'exam_time_2', 'room_name_2',
        'exam_date_start_2', 'exam_date_end_2', 'exam_time_start_2', 'exam_time_end_2',
        'scores_rows_2', 'overall_pct_2',
        'recommended_course_2', 'counselor_comments_2', 'counselor_name_2',
        'institution_name', 'institution_campus', 'institution_address',
        'institution_contact', 'institution_email', 'institution_website',
        'institution_exam_name', 'institution_exam_acronym',
        'institution_contact_number', 'examination_name', 'examination_acronym',
    ];

    /**
     * Render template HTML for one or two applicants.
     *
     * @param  array<int, array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}>  $applicants
     */
    public function render(ResultSheetTemplate $template, array $applicants, bool $useSampleData = false, bool $forPdf = false): RenderResult
    {
        $applicants = array_values($applicants);
        $replacements = $this->buildReplacements($applicants, $useSampleData);
        $paperSize = $template->paper_size ?? ResultSheetTemplate::PAPER_A4;
        $orientation = $template->orientation ?? ResultSheetTemplate::ORIENTATION_PORTRAIT;

        if ($template->mode === ResultSheetTemplate::MODE_HTML) {
            $rawHtml = $this->renderRaw($template->content ?: '', $replacements);
            $html = $forPdf
                ? $this->cssService->wrapForPdf($rawHtml, $paperSize, $orientation)
                : $this->cssService->wrap($rawHtml);
        } else {
            $docxReplacements = $this->buildDocxReplacements($applicants, $useSampleData);
            $html = $this->documentService($template)->renderFromStoragePath($template->document_path, $docxReplacements);
        }

        return new RenderResult(
            html: $html,
            mode: $template->mode,
            paperSize: $paperSize,
            orientation: $orientation,
            logicalUnit: $template->logical_unit ?? ResultSheetTemplate::LOGICAL_FULL,
            watermarkText: $template->watermark_text,
        );
    }

    /**
     * Render two applicants in a dual (crosswise/half-page) layout on one sheet.
     *
     * @param  array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}  $applicant1
     * @param  array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}  $applicant2
     */
    public function renderDual(ResultSheetTemplate $template, array $applicant1, array $applicant2, bool $useSampleData = false, bool $forPdf = false): RenderResult
    {
        $replacements1 = $this->buildReplacements([$applicant1], $useSampleData);
        $replacements2 = $this->buildReplacements([$applicant2], $useSampleData);
        $paperSize = $template->paper_size ?? ResultSheetTemplate::PAPER_A4;
        $orientation = $template->orientation ?? ResultSheetTemplate::ORIENTATION_PORTRAIT;

        if ($template->mode === ResultSheetTemplate::MODE_HTML) {
            $html1 = $this->renderRaw($template->content ?: '', $replacements1);
            $html2 = $this->renderRaw($template->content ?: '', $replacements2);
            $html = $forPdf
                ? $this->cssService->wrapDualForPdf($html1, $html2, $paperSize, $orientation)
                : $this->cssService->wrapDual($html1, $html2);
        } else {
            $docxReplacements1 = $this->buildDocxReplacements([$applicant1], $useSampleData);
            $docxReplacements2 = $this->buildDocxReplacements([$applicant2], $useSampleData);
            $html1 = $this->documentService($template)->renderFromStoragePath($template->document_path, $docxReplacements1);
            $html2 = $this->documentService($template)->renderFromStoragePath($template->document_path, $docxReplacements2);
            $html = $forPdf
                ? $this->cssService->wrapDualForPdf($html1, $html2, $paperSize, $orientation)
                : $this->cssService->wrapDual($html1, $html2);
        }

        return new RenderResult(
            html: $html,
            mode: $template->mode,
            paperSize: $paperSize,
            orientation: $orientation,
            logicalUnit: $template->logical_unit ?? ResultSheetTemplate::LOGICAL_FULL,
            watermarkText: $template->watermark_text,
        );
    }

    /**
     * Fetch applicants + scores for given IDs, render into sheet HTML blobs.
     *
     * @param  int[]  $applicantIds
     * @return string[]
     */
    public function buildSheetsForApplicantIds(
        array $applicantIds,
        ResultSheetTemplate $template,
        ?int $gradingSessionId = null,
    ): array {
        $applicantsWithScores = $this->fetchApplicantsWithScores($applicantIds, $gradingSessionId);

        app(AuditService::class)->log('result_sheet.rendered', ResultSheetTemplate::class, $template->id, [], [
            'applicant_ids' => $applicantIds,
            'mode' => $template->mode,
            'count' => count($applicantIds),
        ]);

        return $this->buildSheetsFromApplicantData($applicantsWithScores, $template);
    }

    /**
     * Build sheet HTML from pre-built applicant data arrays.
     *
     * @param  array<int, array<string, mixed>>  $applicantsWithScores
     * @return string[]
     */
    public function buildSheetsFromApplicantData(array $applicantsWithScores, ResultSheetTemplate $template, bool $forPdf = false): array
    {
        $logicalUnit = $template->logical_unit ?? ResultSheetTemplate::LOGICAL_FULL;
        $chunkSize = in_array($logicalUnit, [ResultSheetTemplate::LOGICAL_HALF_A4, ResultSheetTemplate::LOGICAL_HALF_LEGAL, ResultSheetTemplate::LOGICAL_HALF_LETTER], true) ? 2 : 1;

        $sheetsHtml = [];
        foreach (array_chunk($applicantsWithScores, $chunkSize) as $chunk) {
            if (count($chunk) === 2) {
                $result = $this->renderDual($template, $chunk[0], $chunk[1], false, $forPdf);
                $sheetsHtml[] = $result->html;
            } else {
                $result = $this->render($template, $chunk, false, $forPdf);
                $sheetsHtml[] = $result->html;
            }
        }

        return $sheetsHtml;
    }

    /**
     * Build raw (unwrapped) HTML fragments for each sheet — for use in bulk PDF assembly.
     *
     * Unlike buildSheetsFromApplicantData(), these fragments have NO CSS wrapping and no
     * full HTML document shell. The caller (ResultSheetPdfService) is responsible for
     * combining them and producing a single valid HTML document.
     *
     * @param  array<int, array<string, mixed>>  $applicantsWithScores
     * @return string[]
     */
    public function buildRawSheetsFromApplicantData(array $applicantsWithScores, ResultSheetTemplate $template): array
    {
        $logicalUnit = $template->logical_unit ?? ResultSheetTemplate::LOGICAL_FULL;
        $chunkSize = in_array($logicalUnit, [ResultSheetTemplate::LOGICAL_HALF_A4, ResultSheetTemplate::LOGICAL_HALF_LEGAL, ResultSheetTemplate::LOGICAL_HALF_LETTER], true) ? 2 : 1;

        $sheetsHtml = [];
        foreach (array_chunk($applicantsWithScores, $chunkSize) as $chunk) {
            if (count($chunk) === 2) {
                $sheetsHtml[] = $this->buildRawDualFragment($template, $chunk[0], $chunk[1]);
            } else {
                $sheetsHtml[] = $this->buildRawFragment($template, $chunk);
            }
        }

        return $sheetsHtml;
    }

    /**
     * Build filled DOCX temp files for each applicant (for LibreOffice PDF conversion).
     * Caller MUST clean up temp files after use.
     *
     * @param  array<int, array<string, mixed>>  $applicantsWithScores
     * @return string[] Array of temp file paths to filled DOCX files
     */
    public function buildFilledDocumentFiles(array $applicantsWithScores, ResultSheetTemplate $template): array
    {
        if (! $template->document_path) {
            throw new \RuntimeException('Template has no document file.');
        }

        $fullPath = Storage::path($template->document_path);

        if (! is_file($fullPath)) {
            throw new \RuntimeException('Document template file not found on disk.');
        }

        $logicalUnit = $template->logical_unit ?? ResultSheetTemplate::LOGICAL_FULL;
        $chunkSize = in_array($logicalUnit, [ResultSheetTemplate::LOGICAL_HALF_A4, ResultSheetTemplate::LOGICAL_HALF_LEGAL, ResultSheetTemplate::LOGICAL_HALF_LETTER], true) ? 2 : 1;

        $tempDir = storage_path('app/temp/phpword');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $ext = strtolower(pathinfo($template->document_path, PATHINFO_EXTENSION));

        if ($ext === 'odt') {
            return $this->buildFilledOdtFiles($applicantsWithScores, $template, $fullPath, $chunkSize, $tempDir);
        }

        $repairedPath = $this->docxService->getRepairedTemplate($fullPath) ?: $fullPath;

        $tempFiles = [];

        try {
            foreach (array_chunk($applicantsWithScores, $chunkSize) as $chunk) {
                $chunk = array_values($chunk);
                $replacements = $this->buildDocxReplacements($chunk);

                $processor = new TemplateProcessor($repairedPath);
                $processor->setMacroChars('{{', '}}');

                foreach ($replacements as $key => $value) {
                    $processor->setValue($key, $value);
                }

                $tempFile = tempnam($tempDir, 'docx_filled_').'.docx';
                $processor->saveAs($tempFile);
                $tempFiles[] = $tempFile;
            }
        } finally {
            if ($repairedPath !== $fullPath && is_file($repairedPath)) {
                @unlink($repairedPath);
            }
        }

        return $tempFiles;
    }

    protected function buildFilledOdtFiles(array $applicantsWithScores, ResultSheetTemplate $template, string $fullPath, int $chunkSize, string $tempDir): array
    {
        $repairedPath = $this->odtService->getRepairedTemplate($fullPath) ?: $fullPath;
        $tempFiles = [];

        try {
            foreach (array_chunk($applicantsWithScores, $chunkSize) as $chunk) {
                $chunk = array_values($chunk);
                $replacements = $this->buildDocxReplacements($chunk);

                $zip = new \ZipArchive;
                $tmpCopy = tempnam($tempDir, 'odt_filled_').'.odt';
                copy($repairedPath, $tmpCopy);

                if ($zip->open($tmpCopy) === true) {
                    $contentXml = $zip->getFromName('content.xml');
                    if ($contentXml !== false) {
                        foreach ($replacements as $key => $value) {
                            $contentXml = str_replace('{{'.$key.'}}', $value, $contentXml);
                        }
                        $zip->addFromString('content.xml', $contentXml);
                    }
                    $zip->close();
                }

                $tempFiles[] = $tmpCopy;
            }
        } finally {
            if ($repairedPath !== $fullPath && is_file($repairedPath)) {
                @unlink($repairedPath);
            }
        }

        return $tempFiles;
    }

    /**
     * Fetch applicants + scores for given IDs and return raw HTML fragments for bulk PDF.
     *
     * @param  int[]  $applicantIds
     * @return string[]
     */
    public function buildRawSheetsForApplicantIds(
        array $applicantIds,
        ResultSheetTemplate $template,
        ?int $gradingSessionId = null,
    ): array {
        $applicantsWithScores = $this->fetchApplicantsWithScores($applicantIds, $gradingSessionId);

        app(AuditService::class)->log('result_sheet.rendered', ResultSheetTemplate::class, $template->id, [], [
            'applicant_ids' => $applicantIds,
            'mode' => $template->mode,
            'count' => count($applicantIds),
        ]);

        return $this->buildRawSheetsFromApplicantData($applicantsWithScores, $template);
    }

    /**
     * @param  array<int, array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}>  $applicants
     */
    public function buildRawFragment(ResultSheetTemplate $template, array $applicants, bool $useSampleData = false): string
    {
        $applicants = array_values($applicants);

        if ($template->mode === ResultSheetTemplate::MODE_HTML) {
            $replacements = $this->buildReplacements($applicants, $useSampleData);
            $html = $this->renderRaw($template->content ?: '', $replacements);
        } else {
            $docxReplacements = $this->buildDocxReplacements($applicants, $useSampleData);
            $html = $this->documentService($template)->renderFromStoragePath($template->document_path, $docxReplacements);
        }

        return "<div class=\"print-template\">{$html}</div>";
    }

    /**
     * @param  array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}  $applicant1
     * @param  array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}  $applicant2
     */
    public function buildRawDualFragment(ResultSheetTemplate $template, array $applicant1, array $applicant2, bool $useSampleData = false): string
    {
        if ($template->mode === ResultSheetTemplate::MODE_HTML) {
            $replacements1 = $this->buildReplacements([$applicant1], $useSampleData);
            $replacements2 = $this->buildReplacements([$applicant2], $useSampleData);
            $html1 = $this->renderRaw($template->content ?: '', $replacements1);
            $html2 = $this->renderRaw($template->content ?: '', $replacements2);
        } else {
            $docxReplacements1 = $this->buildDocxReplacements([$applicant1], $useSampleData);
            $docxReplacements2 = $this->buildDocxReplacements([$applicant2], $useSampleData);
            $html1 = $this->documentService($template)->renderFromStoragePath($template->document_path, $docxReplacements1);
            $html2 = $this->documentService($template)->renderFromStoragePath($template->document_path, $docxReplacements2);
        }

        return "<div class=\"print-template print-template--dual\">\n"
            ."  <div class=\"print-template--half\">{$html1}</div>\n"
            ."  <div class=\"print-template--half\">{$html2}</div>\n"
            .'</div>';
    }

    /**
     * Render from raw HTML content (for preview before template is saved).
     */
    public function renderHtmlContent(
        string $content,
        array $applicants = [],
        bool $useSampleData = true,
        string $paperSize = ResultSheetTemplate::PAPER_A4,
        string $orientation = ResultSheetTemplate::ORIENTATION_PORTRAIT,
        string $logicalUnit = ResultSheetTemplate::LOGICAL_FULL
    ): RenderResult {
        $applicants = array_values($applicants);
        $replacements = $this->buildReplacements($applicants, $useSampleData);

        return new RenderResult(
            html: $this->cssService->wrap($this->renderRaw($content, $replacements)),
            mode: ResultSheetTemplate::MODE_HTML,
            paperSize: $paperSize,
            orientation: $orientation,
            logicalUnit: $logicalUnit,
            watermarkText: null,
        );
    }

    /**
     * Render DOCX file to HTML (for preview).
     *
     * @param  array<string, string>  $replacements
     */
    public function renderDocumentFile(
        string $path,
        array $replacements = [],
        bool $useSampleIfEmpty = true,
        string $paperSize = ResultSheetTemplate::PAPER_A4,
        string $orientation = ResultSheetTemplate::ORIENTATION_PORTRAIT,
        string $logicalUnit = ResultSheetTemplate::LOGICAL_FULL,
        ?string $originalName = null
    ): RenderResult {
        if (empty($replacements) && $useSampleIfEmpty) {
            $replacements = $this->buildSampleReplacements();
        }

        $ext = strtolower(pathinfo($originalName ?? $path, PATHINFO_EXTENSION));
        $service = $ext === 'odt' ? $this->odtService : $this->docxService;
        $html = $service->renderFromFullPath($path, $replacements);

        return new RenderResult(
            html: $html,
            mode: ResultSheetTemplate::MODE_DOCX,
            paperSize: $paperSize,
            orientation: $orientation,
            logicalUnit: $logicalUnit,
            watermarkText: null,
        );
    }

    /**
     * Slugify domain name for placeholder key (lowercase, underscores).
     */
    public function aptitudeAreaSlug(string $name): string
    {
        return str_replace('-', '_', Str::slug($name, '_'));
    }

    public function getPlaceholderGroups(): array
    {
        $descriptions = [
            'applicant_name' => 'Full name of applicant',
            'applicant_reference' => 'Reference number (Deprecated: use applicant_number or applicant_no)',
            'applicant_number' => 'Applicant number',
            'applicant_no' => 'Applicant number (alias)',
            'family_name' => 'Family/last name',
            'first_name' => 'First name',
            'middle_name' => 'Middle name',
            'suffix' => 'Name suffix (Jr., Sr., etc.)',
            'sex' => 'Sex',
            'gwa' => 'General Weighted Average',
            'course_applied' => 'Course applied for',
            'course_applied_code' => 'Course code of course applied for',
            'strand' => 'SHS Strand (for new students)',
            'strand_or_prev_course' => 'SHS Strand or Previous Course (auto-adapts: SHS strand for new students, previous college course for transferees)',
            'applicant_type' => 'Applicant type (New Student, Transferee)',

            'exam_date' => 'Examination date',
            'exam_time' => 'Examination time',
            'exam_date_start' => 'Examination start date',
            'exam_date_end' => 'Examination end date',
            'exam_time_start' => 'Examination start time',
            'exam_time_end' => 'Examination end time',
            'room_name' => 'Examination room',
            'scores_rows' => 'HTML scores table rows (HTML mode)',
            'overall_pct' => 'Overall percentage score',
            'recommended_course' => 'Recommended course from counseling',
            'counselor_comments' => 'Counselor comments',
            'counselor_name' => 'Counselor name',
            'institution_name' => 'Institution name',
            'institution_campus' => 'Institution campus',
            'institution_address' => 'Institution address',
            'institution_contact' => 'Institution contact number',
            'institution_email' => 'Institution email',
            'institution_website' => 'Institution website',
            'institution_exam_name' => 'Examination name',
            'institution_exam_acronym' => 'Examination acronym',
        ];

        $slot1Fields = [
            'applicant_name', 'applicant_reference', 'applicant_number', 'applicant_no',
            'family_name', 'first_name', 'middle_name', 'suffix',
            'sex', 'gwa', 'course_applied', 'course_applied_code', 'strand', 'strand_or_prev_course', 'applicant_type',
            'exam_date', 'exam_time', 'exam_date_start', 'exam_date_end', 'exam_time_start', 'exam_time_end', 'room_name',
            'scores_rows', 'overall_pct',
            'recommended_course', 'counselor_comments', 'counselor_name',
        ];

        $activeCourses = Course::where('is_active', true)->orderBy('code')->get(['code', 'name']);

        $applicant1 = [];
        foreach ($slot1Fields as $key) {
            $applicant1[] = [
                'placeholder' => '{{'.$key.'}}',
                'description' => $descriptions[$key] ?? str_replace('_', ' ', Str::title($key)),
            ];
        }
        $courseChecks = [];
        foreach ($activeCourses as $course) {
            $ph = [
                'placeholder' => '{{'.$course->code.'_check}}',
                'description' => 'Checkmark if recommended course is '.$course->name.', empty otherwise',
            ];
            $applicant1[] = $ph;
            $courseChecks[] = $ph;
        }

        $applicant2 = [];
        foreach ($slot1Fields as $key) {
            $key2 = $key.'_2';
            $applicant2[] = [
                'placeholder' => '{{'.$key2.'}}',
                'description' => $descriptions[$key] ?? str_replace('_', ' ', Str::title($key)),
            ];
        }
        $courseChecks2 = [];
        foreach ($activeCourses as $course) {
            $ph = [
                'placeholder' => '{{'.$course->code.'_check_2}}',
                'description' => 'Checkmark if recommended course is '.$course->name.' (applicant 2), empty otherwise',
            ];
            $applicant2[] = $ph;
            $courseChecks2[] = $ph;
        }

        $institutionKeys = [
            'institution_name', 'institution_campus', 'institution_address',
            'institution_contact', 'institution_email', 'institution_website',
            'institution_exam_name', 'institution_exam_acronym',
        ];
        $institution = [];
        foreach ($institutionKeys as $key) {
            $institution[] = [
                'placeholder' => '{{'.$key.'}}',
                'description' => $descriptions[$key] ?? str_replace('_', ' ', Str::title($key)),
            ];
        }
        $institution[] = [
            'placeholder' => '{{institution_contact_number}}',
            'description' => 'Alias: institution_contact',
        ];
        $institution[] = [
            'placeholder' => '{{examination_name}}',
            'description' => 'Alias: institution_exam_name',
        ];
        $institution[] = [
            'placeholder' => '{{examination_acronym}}',
            'description' => 'Alias: institution_exam_acronym',
        ];

        $personnel = [];
        $personnelConfig = config('institution.personnel', []);
        foreach ($personnelConfig as $role => $defaults) {
            $roleTitle = Str::title(str_replace('_', ' ', $role));
            foreach (['name', 'title', 'credentials'] as $field) {
                $key = "personnel_{$role}_{$field}";
                $personnel[] = [
                    'placeholder' => '{{'.$key.'}}',
                    'description' => "{$roleTitle} {$field}",
                ];
                $personnel[] = [
                    'placeholder' => '{{'."{$role}_{$field}".'}}',
                    'description' => "Alias: {$key}",
                ];
            }
        }

        $domains = AptitudeArea::where('is_active', true)->orderBy('display_order')->get(['name']);
        $domainsGroup = [];
        foreach ($domains as $domain) {
            $slug = $this->aptitudeAreaSlug($domain->name);
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'}}',
                'description' => $domain->name.' percentage',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_raw}}',
                'description' => $domain->name.' raw score',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_wunit}}',
                'description' => $domain->name.' percentage with ordinal',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_rating'.'}}',
                'description' => $domain->name.' rating',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_2}}',
                'description' => $domain->name.' percentage (applicant 2)',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_raw_2}}',
                'description' => $domain->name.' raw score (applicant 2)',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_wunit_2}}',
                'description' => $domain->name.' percentage with ordinal (applicant 2)',
            ];
            $domainsGroup[] = [
                'placeholder' => '{{'.$slug.'_rating_2'.'}}',
                'description' => $domain->name.' rating (applicant 2)',
            ];
        }

        return [
            'applicant1' => $applicant1,
            'applicant2' => $applicant2,
            'institution' => $institution,
            'personnel' => $personnel,
            'domains' => $domainsGroup,
            'course_checks' => $courseChecks,
            'course_checks_2' => $courseChecks2,
        ];
    }

    protected function buildSampleReplacements(): array
    {
        return $this->buildReplacements([], true);
    }

    /**
     * Build replacement map for applicant data (slot 1 and slot 2).
     *
     * @param  array<int, array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}>  $applicant  * @return array<string, string>
     */
    public function buildReplacements(array $applicants, bool $useSampleData): array
    {
        $sample = $this->sampleApplicantData();
        $replacements = [];
        $activeCourses = Course::where('is_active', true)->get(['code']);

        foreach ([1 => 0, 2 => 1] as $slot => $idx) {
            $app = $applicants[$idx] ?? null;
            $data = null;
            if ($app) {
                $data = $app;
            } elseif ($useSampleData) {
                if ($slot === 1) {
                    $data = $sample;
                } else {
                    $data = [
                        'name' => $sample['name_2'] ?? '',
                        'family_name' => $sample['family_name_2'] ?? '',
                        'first_name' => $sample['first_name_2'] ?? '',
                        'middle_name' => $sample['middle_name_2'] ?? '',
                        'suffix' => $sample['suffix_2'] ?? '',
                        'sex' => $sample['sex_2'] ?? '',
                        'gwa' => $sample['gwa_2'] ?? '',
                        'course_applied' => $sample['course_applied_2'] ?? '',
                        'course_applied_code' => $sample['course_applied_code_2'] ?? '',
                        'strand' => $sample['strand_2'] ?? '',
                        'strand_or_prev_course' => $sample['strand_2'] ?? '',
                        'applicant_type' => $sample['applicant_type_2'] ?? '',
                        'reference' => $sample['reference_2'] ?? '',
                        'exam_date' => $sample['exam_date_2'] ?? $sample['exam_date'] ?? '',
                        'exam_time' => $sample['exam_time_2'] ?? '',
                        'room_name' => $sample['room_name_2'] ?? '',
                        'scores' => $sample['scores_2'] ?? [],
                        'overall_pct' => $sample['overall_pct_2'] ?? 0,
                        'recommended_course' => $sample['recommended_course_2'] ?? '',
                        'recommended_course_code' => $sample['recommended_course_code_2'] ?? '',
                        'counselor_comments' => $sample['counselor_comments_2'] ?? '',
                        'counselor_name' => $sample['counselor_name_2'] ?? '',
                    ];
                }
            }

            $suffix = $slot === 1 ? '' : '_2';
            if ($data) {
                $replacements["applicant_name{$suffix}"] = $data['name'] ?? '';
                $replacements["applicant_reference{$suffix}"] = $data['reference'] ?? '';
                $replacements["applicant_number{$suffix}"] = $data['reference'] ?? '';
                $replacements["applicant_no{$suffix}"] = $data['reference'] ?? '';
                $replacements["exam_date{$suffix}"] = $data['exam_date'] ?? '';
                $replacements["room_name{$suffix}"] = $data['room_name'] ?? '';
                $replacements["scores_rows{$suffix}"] = $this->buildScoresRows($data['scores'] ?? []);
                $replacements["overall_pct{$suffix}"] = (string) ($data['overall_pct'] ?? 0);

                $newFields = [
                    'family_name', 'first_name', 'middle_name', 'suffix',
                    'sex', 'gwa', 'course_applied', 'course_applied_code', 'strand', 'strand_or_prev_course', 'applicant_type',
                    'exam_time', 'exam_date_start', 'exam_date_end', 'exam_time_start', 'exam_time_end',
                    'recommended_course', 'recommended_course_code', 'counselor_comments', 'counselor_name',
                ];
                foreach ($newFields as $field) {
                    $val = (string) ($data[$field] ?? '');
                    if ($field === 'sex') {
                        $lowerVal = strtolower($val);
                        if ($lowerVal === 'male' || $lowerVal === 'm') {
                            $val = 'M';
                        } elseif ($lowerVal === 'female' || $lowerVal === 'f') {
                            $val = 'F';
                        }
                    }
                    $replacements["{$field}{$suffix}"] = $val;
                }
            } else {
                $replacements["applicant_name{$suffix}"] = '';
                $replacements["applicant_reference{$suffix}"] = '';
                $replacements["applicant_number{$suffix}"] = '';
                $replacements["applicant_no{$suffix}"] = '';
                $replacements["exam_date{$suffix}"] = '';
                $replacements["room_name{$suffix}"] = '';
                $replacements["scores_rows{$suffix}"] = '';
                $replacements["overall_pct{$suffix}"] = '';

                foreach (['family_name', 'first_name', 'middle_name', 'suffix', 'sex', 'gwa', 'course_applied', 'course_applied_code', 'strand', 'strand_or_prev_course', 'applicant_type', 'exam_time', 'exam_date_start', 'exam_date_end', 'exam_time_start', 'exam_time_end', 'recommended_course', 'recommended_course_code', 'counselor_comments', 'counselor_name'] as $field) {
                    $replacements["{$field}{$suffix}"] = '';
                }
            }

            // Fill course recommendation checkmarks for this slot
            $recommendedCode = $data ? ($data['recommended_course_code'] ?? '') : '';
            foreach ($activeCourses as $course) {
                $courseCode = $course->code;
                $replacements["{$courseCode}_check{$suffix}"] = ($recommendedCode === $courseCode) ? '✔' : '';
            }
        }

        $replacements['exam_date'] = $replacements['exam_date'] ?? $replacements['exam_date_2'] ?? '';
        $replacements['room_name'] = $replacements['room_name'] ?? $replacements['room_name_2'] ?? '';

        $replacements['institution_name'] = SystemSetting::institution('name', '');
        $replacements['institution_campus'] = SystemSetting::institution('campus', '');
        $replacements['institution_address'] = SystemSetting::institution('address', '');
        $replacements['institution_contact'] = SystemSetting::institution('contact_number', '');
        $replacements['institution_email'] = SystemSetting::institution('email', '');
        $replacements['institution_website'] = SystemSetting::institution('website', '');
        $replacements['institution_exam_name'] = SystemSetting::institution('exam_name', '');
        $replacements['institution_exam_acronym'] = SystemSetting::institution('exam_acronym', '');

        $replacements['institution_contact_number'] = $replacements['institution_contact'];
        $replacements['examination_name'] = $replacements['institution_exam_name'];
        $replacements['examination_acronym'] = $replacements['institution_exam_acronym'];

        $personnel = config('institution.personnel', []);
        foreach ($personnel as $role => $defaults) {
            foreach (['name', 'title', 'credentials'] as $field) {
                $dotKey = "personnel.{$role}.{$field}";
                $val = SystemSetting::institution($dotKey) ?? ($defaults[$field] ?? '');
                $replacements["personnel_{$role}_{$field}"] = $val ?: '';
                $replacements["{$role}_{$field}"] = $val ?: '';
            }
        }

        $this->addPerDomainReplacements($replacements, $applicants, $sample, $useSampleData, RatingScale::default());

        return $replacements;
    }

    /**
     * Build replacements suitable for DOCX TemplateProcessor (setValue).
     *
     * Unlike buildReplacements() which produces HTML for scores_rows,
     * this method produces plain-text values safe for DOCX injection:
     * - scores_rows / scores_rows_2 are set to empty string (use per-domain
     *   placeholders like {{spatial_awareness}} instead for DOCX tables)
     * - All values have {{ and }} escaped to prevent TemplateProcessor conflicts
     * - HTML tags are stripped from any value that might contain them
     *
     * @param  array<int, array{name: string, reference: string, exam_date: string, room_name: string, scores: array<array{domain: string, raw: int, max: int, pct: int}>, overall_pct: int}>  $applicants
     * @return array<string, string>
     */
    public function buildDocxReplacements(array $applicants, bool $useSampleData = false): array
    {
        $html = $this->buildReplacements($applicants, $useSampleData);

        $docx = [];
        foreach ($html as $key => $value) {
            if (Str::startsWith($key, 'scores_rows')) {
                $docx[$key] = '';

                continue;
            }

            $clean = strip_tags($value);
            $clean = str_replace(['{{', '}}'], ['{ {', '} }'], $clean);
            $docx[$key] = $clean;
        }

        return $docx;
    }

    /**
     * Extract numeric value from a percentile string (e.g., "85th" → 85, "99+" → 99).
     */
    private function extractNumeric(string $percentileStr): int
    {
        preg_match('/(\d+)/', $percentileStr, $matches);

        return (int) ($matches[1] ?? 0);
    }

    /**
     * Format a number as an ordinal string (e.g., 1 → "1st", 11 → "11th", 22 → "22nd").
     */
    private function formatOrdinal(int $n): string
    {
        $suffix = match ($n % 10) {
            1 => $n % 100 === 11 ? 'th' : 'st',
            2 => $n % 100 === 12 ? 'th' : 'nd',
            3 => $n % 100 === 13 ? 'th' : 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }

    /**
     * Add per-domain replacements (e.g. {{spatial_awareness}}, {{spatial_awareness_raw}}) for DOCX strict binding.
     *
     * @param  array<string, string>  $replacements
     * @param  array<int, array{name?: string, reference?: string, scores?: array<array{domain: string, raw: int|float|null, max: int|float|null, pct: int, pct_string: string|null, pct_numeric: int}>>>  $applicants
     * @param  array{name?: string, reference?: string, scores?: array<array{domain: string, raw: int|float|null, max: int|float|null, pct: int, pct_string: string|null, pct_numeric: int}>}  $sample
     */
    protected function addPerDomainReplacements(array &$replacements, array $applicants, array $sample, bool $useSampleData, ?RatingScale $ratingScale = null): void
    {
        $domains = AptitudeArea::where('is_active', true)->orderBy('display_order')->get(['id', 'name']);

        foreach ([1 => 0, 2 => 1] as $slot => $idx) {
            $suffix = $slot === 1 ? '' : '_2';
            $data = $applicants[$idx] ?? null;
            $data = $data ?? ($useSampleData ? $sample : null);
            $scores = $data['scores'] ?? [];
            $scoresByDomain = collect($scores)->keyBy(fn (array $s) => $s['domain'] ?? '');

            foreach ($domains as $domain) {
                $slug = $this->aptitudeAreaSlug($domain->name);
                $score = $scoresByDomain->get($domain->name);
                if ($score !== null) {
                    $pct = (string) ((int) ($score['pct'] ?? 0));
                    $raw = $score['max'] > 0
                        ? sprintf('%d / %d', (int) ($score['raw'] ?? 0), (int) ($score['max']))
                        : (string) ($score['raw'] ?? '');
                    $pctWithUnit = $score['pct_string'] ?? $this->formatOrdinal((int) ($score['pct_numeric'] ?? 0));
                    $rating = $this->percentileToRating((int) ($score['pct_numeric'] ?? 0), $ratingScale);
                } else {
                    $pct = '';
                    $raw = '';
                    $pctWithUnit = '';
                    $rating = '';
                }

                $replacements[$slug.$suffix] = $pct;
                $replacements[$slug.'_raw'.$suffix] = $raw;
                $replacements[$slug.'_wunit'.$suffix] = $pctWithUnit;
                $replacements[$slug.'_rating'.$suffix] = $rating;
            }
        }
    }

    /**
     * @param  int[]  $applicantIds
     * @return array<int, array<string, mixed>>
     */
    public function fetchApplicantsWithScores(array $applicantIds, ?int $gradingSessionId = null): array
    {
        if ($gradingSessionId !== null) {
            return $this->fetchApplicantsForSession($applicantIds, $gradingSessionId);
        }

        return $this->fetchApplicantsAgnostic($applicantIds);
    }

    /**
     * @param  int[]  $applicantIds
     * @return array<int, array<string, mixed>>
     */
    protected function fetchApplicantsForSession(array $applicantIds, int $gradingSessionId): array
    {
        $session = GradingSession::with('examSession.room')->findOrFail($gradingSessionId);
        $applicants = $session->applicants()
            ->whereIn('applicants.id', $applicantIds)
            ->with([
                'application.coursePreference1',
                'consultationSummary.recommendedCourse',
                'consultationSummary.counselor',
                'examSessions.room',
            ])
            ->get();

        $scoresByApplicant = $session->applicantScores()
            ->whereIn('applicant_id', $applicantIds)
            ->with('aptitudeArea')
            ->get()
            ->groupBy('applicant_id');

        return $applicants->map(function ($a) use ($session, $scoresByApplicant) {
            $scores = $this->mapScoresFromCollection($scoresByApplicant->get($a->id, collect()));

            return $this->buildApplicantDataArray($a, $session, $scores);
        })->values()->all();
    }

    /**
     * @param  int[]  $applicantIds
     * @return array<int, array<string, mixed>>
     */
    protected function fetchApplicantsAgnostic(array $applicantIds): array
    {
        $applicants = Applicant::whereIn('id', $applicantIds)
            ->with([
                'application.coursePreference1',
                'consultationSummary.recommendedCourse',
                'consultationSummary.counselor',
                'gradingSessions.examSession.room',
                'examSessions.room',
            ])
            ->get();

        $applicantSessionMap = [];
        foreach ($applicants as $applicant) {
            $gs = $applicant->gradingSessions->where('status', GradingSession::STATUS_FINALIZED)->first()
                ?? $applicant->gradingSessions->first();
            if ($gs) {
                $applicantSessionMap[$applicant->id] = $gs->id;
            }
        }

        $allScores = ApplicantScore::whereIn('applicant_id', array_keys($applicantSessionMap))
            ->whereIn('grading_session_id', array_unique(array_values($applicantSessionMap)))
            ->with('aptitudeArea')
            ->get()
            ->groupBy('applicant_id');

        return $applicants->map(function ($a) use ($allScores) {
            $gs = $a->gradingSessions->where('status', GradingSession::STATUS_FINALIZED)->first()
                ?? $a->gradingSessions->first();
            $scores = $this->mapScoresFromCollection(
                $allScores->get($a->id, collect())
                    ->filter(fn ($s) => $gs && $s->grading_session_id === $gs->id)
            );

            return $this->buildApplicantDataArray($a, $gs, $scores);
        })->values()->all();
    }

    /**
     * @param  Collection<int, ApplicantScore>  $scores
     * @return array<int, array{domain: string, raw: int|float|null, max: int|float|null, pct: int, pct_string: string|null, pct_numeric: int}>
     */
    protected function mapScoresFromCollection(Collection $scores): array
    {
        return $scores->map(function ($s) {
            if ($s->percentile_string !== null) {
                $numeric = $this->extractNumeric($s->percentile_string);

                return [
                    'domain' => $s->aptitudeArea?->name ?? '',
                    'raw' => $s->raw_score,
                    'max' => $s->max_score,
                    'pct' => $numeric,
                    'pct_string' => $s->percentile_string,
                    'pct_numeric' => $numeric,
                ];
            }

            $pctVal = $s->normalized_score
                ?? ($s->max_score > 0 ? (int) round(($s->raw_score / $s->max_score) * 100) : 0);

            return [
                'domain' => $s->aptitudeArea?->name ?? '',
                'raw' => $s->normalized_score ?? $s->raw_score,
                'max' => $s->max_score,
                'pct' => (int) $pctVal,
                'pct_string' => null,
                'pct_numeric' => (int) $pctVal,
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array{domain: string, raw: int|float|null, max: int|float|null, pct: int}>  $scores
     * @return array<string, mixed>
     */
    protected function buildApplicantDataArray(Applicant $applicant, ?GradingSession $session, array $scores): array
    {
        $overallPct = count($scores) > 0
            ? (int) round(collect($scores)->avg('pct_numeric'))
            : 0;

        $name = '';
        if ($applicant->application) {
            $name = trim(implode(' ', array_filter([
                $applicant->application->first_name,
                $applicant->application->middle_name,
                $applicant->application->last_name,
                $applicant->application->suffix,
            ])));
        }

        $app = $applicant->application;
        $summary = $applicant->consultationSummary;

        $examSession = $session?->examSession
            ?? $applicant->gradingSessions->where('status', GradingSession::STATUS_FINALIZED)->first()?->examSession
            ?? $applicant->gradingSessions->first()?->examSession
            ?? $applicant->examSessions->first();

        $examDate = $examSession?->date?->format('F d, Y') ?? '';
        $examTime = $examSession?->start_time
            ? Carbon::parse($examSession->start_time)->format('g:i A')
            : '';

        $examDateStart = $examDate;
        $examDateEnd = $examDate;
        $examTimeStart = $examTime;
        $examTimeEnd = $examSession?->end_time
            ? Carbon::parse($examSession->end_time)->format('g:i A')
            : '';

        return [
            'id' => $applicant->id,
            'name' => $name,
            'family_name' => $app?->last_name ?? '',
            'first_name' => $app?->first_name ?? '',
            'middle_name' => $app?->middle_name ?? '',
            'suffix' => $app?->suffix ?? '',
            'sex' => $app?->sex ?? '',
            'gwa' => $app?->gwa ?? '',
            'course_applied' => $app?->coursePreference1?->name ?? '',
            'course_applied_code' => $app?->coursePreference1?->code ?? '',
            'strand' => $app?->strand ?? $app?->last_school_enrolled ?? '',
            'strand_or_prev_course' => $app?->strand ?? $app?->last_school_enrolled ?? '',
            'applicant_type' => $app?->applicant_type ?? '',
            'reference' => $app?->reference_number ?? '',
            'exam_date' => $examDate,
            'exam_time' => $examTime,
            'exam_date_start' => $examDateStart,
            'exam_date_end' => $examDateEnd,
            'exam_time_start' => $examTimeStart,
            'exam_time_end' => $examTimeEnd,
            'room_name' => $examSession?->room?->name ?? '',
            'scores' => $scores,
            'overall_pct' => $overallPct,
            'recommended_course' => $summary?->recommendedCourse?->name ?? '',
            'recommended_course_code' => $summary?->recommendedCourse?->code ?? '',
            'counselor_comments' => $summary?->counselor_comments ?? '',
            'counselor_name' => $summary?->counselor?->name ?? '',
        ];
    }

    /**
     * Render raw HTML content with placeholder replacements (no CSS wrapping).
     */
    private function renderRaw(string $content, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        foreach (['scores_rows_2' => 'scores-rows-placeholder-2', 'scores_rows' => 'scores-rows-placeholder'] as $key => $class) {
            $rows = $replacements[$key] ?? '';

            // Try matching TR first
            $content = preg_replace_callback(
                '/<\s*tr\s+class\s*=\s*["\']'.preg_quote($class, '/').'["\'][^>]*>.*?<\s*\/\s*tr\s*>/s',
                fn () => $rows ?: '<tr class="'.$class.'"><td colspan="3"></td></tr>',
                $content,
                1,
                $count
            );

            // If no TR matched, try matching TBODY
            if ($count === 0) {
                $content = preg_replace_callback(
                    '/(<\s*tbody\s+class\s*=\s*["\']'.preg_quote($class, '/').'["\'][^>]*>)(.*?)(<\s*\/\s*tbody\s*>)/s',
                    fn ($matches) => $matches[1]."\n".($rows ?: '<tr><td colspan="3"></td></tr>')."\n".$matches[3],
                    $content,
                    1
                );
            }
        }

        return $content;
    }

    public function getDocumentValidation(string $fullPath, bool $isCrosswise, ?string $originalName = null): DocxValidationResult
    {
        $ext = strtolower(pathinfo($originalName ?? $fullPath, PATHINFO_EXTENSION));
        $service = $ext === 'odt' ? $this->odtService : $this->docxService;

        return $service->validateTemplate(
            $fullPath,
            $this->buildCategorizedPlaceholders(),
            $isCrosswise,
        );
    }

    /**
     * @return array{required: string[], recommended: string[], optional: string[], html_only: string[], domain: string[], personnel: string[], institution: string[], applicant2: string[]}
     */
    protected function buildCategorizedPlaceholders(): array
    {
        $categorized = [
            'required' => ['applicant_reference'],
            'recommended' => [
                'family_name', 'first_name', 'middle_name',
                'sex', 'course_applied', 'course_applied_code', 'applicant_type',
            ],
            'optional' => [
                'applicant_name', 'applicant_number', 'applicant_no', 'suffix', 'exam_date', 'exam_time',
                'exam_date_start', 'exam_date_end', 'exam_time_start', 'exam_time_end', 'room_name', 'overall_pct',
                'gwa', 'strand',
                'recommended_course', 'counselor_comments', 'counselor_name',
            ],
            'html_only' => [
                'scores_rows',
                'scores_rows_2',
            ],
            'applicant2' => [
                'applicant_name_2', 'applicant_reference_2', 'applicant_number_2', 'applicant_no_2',
                'family_name_2', 'first_name_2', 'middle_name_2', 'suffix_2',
                'sex_2', 'gwa_2', 'course_applied_2', 'course_applied_code_2', 'strand_2', 'applicant_type_2',
                'exam_date_2', 'exam_time_2', 'exam_date_start_2', 'exam_date_end_2', 'exam_time_start_2', 'exam_time_end_2', 'room_name_2',
                'overall_pct_2',
                'recommended_course_2', 'counselor_comments_2', 'counselor_name_2',
            ],
            'institution' => [
                'institution_name', 'institution_campus', 'institution_address',
                'institution_contact', 'institution_email', 'institution_website',
                'institution_exam_name', 'institution_exam_acronym',
                'institution_contact_number', 'examination_name', 'examination_acronym',
            ],
            'personnel' => [],
            'domain' => [],
        ];

        $domains = AptitudeArea::where('is_active', true)->orderBy('display_order')->get(['name']);
        foreach ($domains as $domain) {
            $slug = $this->aptitudeAreaSlug($domain->name);
            $categorized['domain'][] = $slug;
            $categorized['domain'][] = $slug.'_raw';
            $categorized['domain'][] = $slug.'_wunit';
            $categorized['domain'][] = $slug.'_rating';
            $categorized['applicant2'][] = $slug.'_2';
            $categorized['applicant2'][] = $slug.'_raw_2';
            $categorized['applicant2'][] = $slug.'_wunit_2';
            $categorized['applicant2'][] = $slug.'_rating_2';
        }

        $personnelRoles = array_keys(config('institution.personnel', []));
        foreach ($personnelRoles as $role) {
            foreach (['name', 'title', 'credentials'] as $field) {
                $categorized['personnel'][] = "personnel_{$role}_{$field}";
                $categorized['personnel'][] = "{$role}_{$field}";
            }
        }

        $activeCourses = Course::where('is_active', true)->get(['code']);
        foreach ($activeCourses as $course) {
            $categorized['optional'][] = "{$course->code}_check";
            $categorized['applicant2'][] = "{$course->code}_check_2";
        }

        $categorized['optional'][] = 'recommended_course_code';
        $categorized['applicant2'][] = 'recommended_course_code_2';

        return $categorized;
    }

    protected function buildScoresRows(array $scores): string
    {
        $rows = [];
        foreach ($scores as $s) {
            $rawFormatted = $s['max'] > 0
                ? sprintf('%d / %d', (int) ($s['raw'] ?? 0), (int) ($s['max']))
                : (string) ($s['raw'] ?? '');

            $rows[] = sprintf(
                '<tr class="border-b border-border/50"><td class="py-1.5">%s</td><td class="text-right py-1.5">%s</td><td class="text-right py-1.5 font-medium">%d%%</td></tr>',
                htmlspecialchars($s['domain'] ?? ''),
                $rawFormatted,
                (int) ($s['pct'] ?? 0)
            );
        }

        return implode("\n", $rows);
    }

    protected function sampleApplicantData(): array
    {
        return [
            'name' => 'Juan M. Dela Cruz',
            'family_name' => 'Dela Cruz',
            'first_name' => 'Juan',
            'middle_name' => 'M.',
            'suffix' => '',
            'sex' => 'Male',
            'gwa' => '1.50',
            'course_applied' => 'BS Information Technology',
            'course_applied_code' => 'BSIT',
            'strand' => 'STEM',
            'applicant_type' => 'Freshman',
            'reference' => 'ICAT-2026-00042',
            'exam_date' => now()->format('F d, Y'),
            'exam_time' => '8:00 AM',
            'exam_date_start' => now()->format('F d, Y'),
            'exam_date_end' => now()->format('F d, Y'),
            'exam_time_start' => '8:00 AM',
            'exam_time_end' => '10:00 AM',
            'room_name' => 'Conference Hall A - Seat 12',
            'recommended_course' => 'BS Information Technology',
            'recommended_course_code' => 'BSIT',
            'counselor_comments' => 'Strong aptitude in numerical and logical reasoning. Recommended for IT/CS programs.',
            'counselor_name' => 'Maria Santos',
            'scores' => [
                ['domain' => 'Spatial Awareness', 'raw' => 20, 'max' => 25, 'pct' => 80, 'pct_string' => null, 'pct_numeric' => 80],
                ['domain' => 'Numerical Ability', 'raw' => 22, 'max' => 25, 'pct' => 88, 'pct_string' => null, 'pct_numeric' => 88],
                ['domain' => 'Verbal Reasoning', 'raw' => 19, 'max' => 25, 'pct' => 76, 'pct_string' => null, 'pct_numeric' => 76],
                ['domain' => 'Abstract Reasoning', 'raw' => 16, 'max' => 20, 'pct' => 80, 'pct_string' => null, 'pct_numeric' => 80],
                ['domain' => 'Logical Reasoning', 'raw' => 21, 'max' => 25, 'pct' => 84, 'pct_string' => null, 'pct_numeric' => 84],
                ['domain' => 'Perceptual Speed & Accuracy', 'raw' => 17, 'max' => 20, 'pct' => 85, 'pct_string' => null, 'pct_numeric' => 85],
            ],
            'overall_pct' => 82,
            'name_2' => 'Maria L. Santos',
            'family_name_2' => 'Santos',
            'first_name_2' => 'Maria',
            'middle_name_2' => 'L.',
            'suffix_2' => '',
            'sex_2' => 'Female',
            'gwa_2' => '1.75',
            'course_applied_2' => 'BS Accountancy',
            'course_applied_code_2' => 'BSA',
            'strand_2' => 'ABM',
            'applicant_type_2' => 'Freshman',
            'reference_2' => 'ICAT-2026-00043',
            'exam_time_2' => '8:00 AM',
            'exam_date_start_2' => now()->format('F d, Y'),
            'exam_date_end_2' => now()->format('F d, Y'),
            'exam_time_start_2' => '8:00 AM',
            'exam_time_end_2' => '10:00 AM',
            'room_name_2' => 'Conference Hall A - Seat 13',
            'recommended_course_2' => 'BS Computer Science',
            'recommended_course_code_2' => 'BSCS',
            'counselor_comments_2' => 'Excellent numerical aptitude. Well-suited for business programs.',
            'counselor_name_2' => 'Maria Santos',
            'scores_2' => [
                ['domain' => 'Spatial Awareness', 'raw' => 18, 'max' => 25, 'pct' => 72, 'pct_string' => null, 'pct_numeric' => 72],
                ['domain' => 'Numerical Ability', 'raw' => 24, 'max' => 25, 'pct' => 96, 'pct_string' => null, 'pct_numeric' => 96],
                ['domain' => 'Verbal Reasoning', 'raw' => 21, 'max' => 25, 'pct' => 84, 'pct_string' => null, 'pct_numeric' => 84],
                ['domain' => 'Abstract Reasoning', 'raw' => 14, 'max' => 20, 'pct' => 70, 'pct_string' => null, 'pct_numeric' => 70],
                ['domain' => 'Logical Reasoning', 'raw' => 19, 'max' => 25, 'pct' => 76, 'pct_string' => null, 'pct_numeric' => 76],
                ['domain' => 'Perceptual Speed & Accuracy', 'raw' => 15, 'max' => 20, 'pct' => 75, 'pct_string' => null, 'pct_numeric' => 75],
            ],
            'overall_pct_2' => 79,
        ];
    }

    private function percentileToRating(int $pct, ?RatingScale $ratingScale = null): string
    {
        if ($ratingScale) {
            return $ratingScale->ratingFor($pct);
        }

        return match (true) {
            $pct >= 90 => 'Outstanding',
            $pct >= 75 => 'Above Average',
            $pct >= 50 => 'Average',
            $pct >= 25 => 'Below Average',
            default => 'Needs Improvement',
        };
    }
}
