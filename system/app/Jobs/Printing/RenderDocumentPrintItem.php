<?php

namespace App\Jobs\Printing;

use App\Admission\Support\ApplicantQrCode;
use App\Admission\Support\ApplicantReference;
use App\Assessment\Omr\Canonical\OmrQrCodeRenderer;
use App\DocumentTemplate\DocumentSurfaceRegistry;
use App\DocumentTemplate\MountSlotRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Rendering\ReportHtmlContextFiller;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Examination\ExamSession;
use App\Models\Printing\PrintJob;
use App\Models\Printing\PrintJobItem;
use App\Models\Reports\ReportTemplate;
use App\Printing\PrintRenderer;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\TemplateShortValue;
use App\Support\SensitiveAssetStore;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class RenderDocumentPrintItem implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 240;

    public array $backoff = [1, 5, 10];

    /** @param array<string, mixed> $itemPayload */
    public function __construct(
        public readonly int $printJobId,
        public readonly int $itemId,
        public readonly int $templateId,
        public readonly array $itemPayload,
    ) {}

    public function handle(
        ReportHtmlContextFiller $htmlFiller,
        DocxTemplateFiller $docxFiller,
        LibreOfficeConverter $converter,
        PrintRenderer $renderer,
        DocumentBlueprintCompiler $compiler,
        SensitiveAssetStore $assetStore,
    ): void {
        $claim = (string) Str::uuid();
        $claimed = DB::transaction(function () use ($claim): bool {
            $item = PrintJobItem::query()->where('print_job_id', $this->printJobId)->lockForUpdate()->find($this->itemId);
            if ($item === null || $item->status === PrintJobItem::STATUS_READY || $item->status === PrintJobItem::STATUS_FAILED) {
                return false;
            }
            if ($item->status !== PrintJobItem::STATUS_QUEUED
                && ! ($item->status === PrintJobItem::STATUS_FILLING && $item->claim_expires_at?->isPast())) {
                return false;
            }

            $item->update([
                'status' => PrintJobItem::STATUS_FILLING,
                'claim_token' => $claim,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addMinutes(10),
                'attempt_count' => (int) $item->attempt_count + 1,
            ]);

            return true;
        });
        if (! $claimed) {
            return;
        }

        $temporaryPath = null;
        try {
            $template = ReportTemplate::query()->findOrFail($this->templateId);
            $context = $this->resolveContext($template);
            $paperSize = (string) ($template->paper_size ?? 'a4');
            $orientation = (string) ($template->orientation ?? 'portrait');
            if ($template->mode === ReportTemplate::MODE_HTML) {
                $html = $this->templateHtml($template, $compiler);
                $filledHtml = $htmlFiller->fill($html, $context);
                $page = (new ResultSheetCssService)->wrapForPdf(
                    $filledHtml,
                    $paperSize,
                    $orientation,
                    (float) data_get($template->builder_json, 'page.margin_mm', 12),
                );
                $bytes = $renderer->render($page, ['format' => $paperSize, 'orientation' => $orientation]);
            } else {
                $sourcePath = Storage::disk('local')->path((string) $template->document_path);
                if (! is_file($sourcePath) || ! $converter->isAvailable()) {
                    throw new RuntimeException('document_source_or_converter_unavailable');
                }
                $filledPath = $docxFiller->fill($sourcePath, $context, $this->repeatingRegions($template));
                try {
                    $bytes = $converter->convertToPdf($filledPath);
                } finally {
                    if (is_file($filledPath)) {
                        unlink($filledPath);
                    }
                }
            }

            if ($bytes === '') {
                throw new RuntimeException('document_render_empty');
            }
            $sha256 = hash('sha256', $bytes);
            $checksum = $assetStore->hmac($bytes);
            $path = 'print-artifacts/tmp/'.$this->printJobId.'/'.$this->itemId.'_'.$sha256.'.pdf';
            $temporaryPath = $assetStore->store($bytes, $path);

            DB::transaction(function () use ($claim, $path, $checksum): void {
                $item = PrintJobItem::query()->where('print_job_id', $this->printJobId)->lockForUpdate()->findOrFail($this->itemId);
                if (! hash_equals((string) $item->claim_token, $claim) || $item->status !== PrintJobItem::STATUS_FILLING) {
                    throw new RuntimeException('document_item_claim_lost');
                }
                $item->update([
                    'status' => PrintJobItem::STATUS_READY,
                    'file_path' => $path,
                    'file_checksum' => $checksum,
                    'claim_token' => null,
                    'claimed_at' => null,
                    'claim_expires_at' => null,
                    'safe_error' => null,
                ]);
                PrintJob::query()->whereKey($this->printJobId)->increment('progress_done');
            });
        } catch (Throwable $exception) {
            if ($temporaryPath !== null) {
                $assetStore->delete($temporaryPath);
            }
            $this->releaseItemForRetry($claim);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        PrintJobItem::query()->where('print_job_id', $this->printJobId)->whereKey($this->itemId)
            ->where('status', PrintJobItem::STATUS_QUEUED)
            ->update(['status' => PrintJobItem::STATUS_FAILED, 'safe_error' => 'document_item_render_failed']);
    }

    /** @return array<string, mixed> */
    private function resolveContext(ReportTemplate $template): array
    {
        $grain = (string) ($this->itemPayload['grain'] ?? $template->grain ?? MountSlotRegistry::GRAIN_CYCLE_PROGRAM);
        if ($grain === MountSlotRegistry::GRAIN_STUDENT) {
            $attempt = AdmissionAttempt::query()
                ->forWorkspace()
                ->with(['identity', 'academicCycle.institution', 'examAssignments.examSession.room', 'latestClearanceReceipt.confirmedBy', 'latestClearanceReceipt.program'])
                ->findOrFail((int) $this->itemPayload['attempt_id']);
            $preparedBy = PrintJob::query()->with('requestedBy')->findOrFail($this->printJobId)->requestedBy?->name;

            return $this->studentContext($attempt, 1, $template, $preparedBy);
        }

        if ($grain === MountSlotRegistry::GRAIN_SESSION) {
            $attemptIds = array_values(array_unique(array_map('intval', (array) ($this->itemPayload['attempt_ids'] ?? []))));
            $attempts = $attemptIds !== []
                ? AdmissionAttempt::query()
                    ->forWorkspace()
                    ->with(['identity', 'academicCycle.institution', 'examAssignments.examSession.room'])
                    ->whereIn('id', $attemptIds)
                    ->orderBy('id')
                    ->get()
                : collect();
            if ($attempts->count() !== count($attemptIds)) {
                throw new RuntimeException('document_session_scope_unavailable');
            }
            $preparedBy = PrintJob::query()->with('requestedBy')->findOrFail($this->printJobId)->requestedBy?->name;
            $examinees = $attempts->map(fn (AdmissionAttempt $attempt, int $index): array => $this->studentContext($attempt, $index + 1, $template, $preparedBy))->all();

            $sessionId = (int) ($this->itemPayload['session_id'] ?? 0);
            $session = ExamSession::query()->with('room')->find($sessionId);

            return [
                'session_id' => $sessionId,
                'session_code' => (string) ($session?->code ?? ''),
                'session_label' => (string) ($session?->label ?? $session?->code ?? ''),
                'room_name' => (string) ($session?->room?->name ?? $session?->room?->code ?? ''),
                'session_examinees' => $examinees,
                'examinees' => $examinees,
                'row_count' => count($examinees),
                'institution_name' => 'Ilocos Sur Polytechnic State College',
                'academic_cycle_name' => 'AY 2026-2027, 1st Semester',
                'generated_at' => now()->format('Y-m-d H:i'),
                'prepared_by_name' => $preparedBy ?? 'Authorized Staff',
            ];
        }
        $surface = DocumentSurfaceRegistry::resolve((string) $template->surface_key);
        if ($surface === null) {
            throw new RuntimeException('document_surface_unavailable');
        }
        $filters = [
            'academic_cycle_id' => $this->itemPayload['cycle_id'] ?? null,
            'cycle_id' => $this->itemPayload['cycle_id'] ?? null,
            'program_code' => $this->itemPayload['program_code'] ?? null,
            'department_code' => $this->itemPayload['department_code'] ?? null,
        ];
        $context = $surface->resolveData($filters);
        if ($context === []) {
            $context = $surface->sampleContext();
        }

        return $context;
    }

    /** @return array<string, mixed> */
    private function studentContext(AdmissionAttempt $attempt, int $rowNo, ReportTemplate $template, ?string $preparedBy): array
    {
        $identity = $attempt->identity;
        $referenceCode = (string) $attempt->reference_code;
        $firstName = (string) ($identity?->first_name ?? '');
        $familyName = (string) ($identity?->last_name ?? '');
        $applicantName = trim($firstName.' '.$familyName) ?: $referenceCode;
        $programCode = (string) ($attempt->preferred_program_code ?: $attempt->program_preference_1_code ?: '');
        $receipt = $attempt->latestClearanceReceipt;
        $assignment = $attempt->examAssignments->first(static fn ($item): bool => $item->isActive()) ?? $attempt->examAssignments->first();
        $session = $assignment?->examSession;
        $institution = $attempt->academicCycle?->institution;
        $gwa = $attempt->gwa === null ? '' : number_format((float) $attempt->gwa, 2);

        $locatorQrToken = ApplicantQrCode::issue($attempt);
        $locatorQrUrl = ApplicantQrCode::entryUrl($attempt);
        $qrDataUri = 'data:image/png;base64,'.base64_encode(
            (new DynamicAssetResolutionService)->renderVerificationQr($locatorQrUrl, 400, 4)
        );
        $qrSvg = (new OmrQrCodeRenderer)->svg($locatorQrUrl, 240);

        return [
            'applicant_locator_qr' => $qrDataUri,
            'verification_qr' => $qrDataUri,
            'qr_code_svg' => $qrSvg,
            'qr' => $locatorQrToken,
            'verification_code' => $locatorQrToken,
            'applicant_reference' => $referenceCode,
            'reference_code' => $referenceCode,
            'applicant_no' => $referenceCode,
            'applicant_number' => $referenceCode,
            'examinee_number' => ApplicantReference::paddedDigits($referenceCode, $attempt->academicCycle) ?? $referenceCode,
            'sequence_number' => ApplicantReference::compactNumber($referenceCode, $attempt->academicCycle) ?? $referenceCode,
            'applicant_name' => $applicantName,
            'first_name' => $firstName,
            'family_name' => $familyName,
            ...TemplateShortValue::fields(
                $attempt->last_school_enrolled,
                $attempt->strand,
            ),
            'strand' => (string) ($attempt->strand ?? ''),
            'strand_or_prev_course' => (string) ($attempt->strand ?? ''),
            'gwa' => $gwa,
            'program_code' => (string) ($receipt?->program?->code ?? $programCode),
            'course_applied_code' => $programCode,
            'admission_status' => ucfirst(str_replace('_', ' ', (string) $attempt->status)),
            'academic_cycle' => (string) ($attempt->academicCycle?->staffLabel() ?? ''),
            'institution_name' => $institution?->name ?? 'Institution',
            'institution_code' => $institution?->code ?? 'INST',
            'campus_name' => $institution?->campus_name ?? $institution?->name ?? 'Main Campus',
            'campus_address' => $institution?->address ?? '',
            'issuing_office_name' => match ($template->office) {
                'admin' => 'Admin Office',
                'registrar' => 'Registrar Office',
                default => 'Guidance Office',
            },
            'document_title' => $template->name,
            'custom_heading' => $template->name,
            'custom_note' => '',
            'academic_cycle_name' => $attempt->academicCycle?->name ?? 'Current Cycle',
            'generated_at' => now()->format('Y-m-d H:i'),
            'prepared_by_name' => $preparedBy ?? 'Authorized Staff',
            'shs_strand' => $attempt->strand ?? 'N/A',
            'gpa_or_gwa' => $attempt->gwa !== null ? number_format((float) $attempt->gwa, 2) : 'N/A',
            'application_status' => (string) ($attempt->status ?? 'pending'),
            'clearance_reference_no' => $receipt?->receipt_number ?? $attempt->reference_code,
            'first_choice_program' => $attempt->preferred_program_code ?? 'N/A',
            'recommended_program' => $attempt->preferred_program_code ?? 'N/A',
            'counselor_decision' => (string) ($attempt->status ?? 'Advised'),
            'report_date' => now()->format('F j, Y'),
            'exam_date' => $session?->starts_at?->format('F j, Y') ?? '',
            'exam_time' => $session?->starts_at?->format('g:i A') ?? '',
            'exam_time_window' => $session?->starts_at?->format('g:i A') ?? '',
            'room_name' => (string) ($session?->room?->name ?? $session?->room?->code ?? ''),
            'clearance_status' => $receipt?->status === AdmissionClearanceReceipt::STATUS_CONFIRMED ? 'Confirmed' : '',
            'receipt_number' => (string) ($receipt?->receipt_number ?? ''),
            'confirmed_at' => $receipt?->confirmed_at?->format('F j, Y g:i A') ?? '',
            'verified_by' => (string) ($receipt?->confirmedBy?->name ?? ''),
            'row_no' => $rowNo,
            'qualifiers' => [[
                'row_no' => $rowNo,
                'reference_code' => $referenceCode,
                'applicant_name' => $applicantName,
                'strand' => (string) ($attempt->strand ?? ''),
                'gwa' => $gwa,
                'program_code' => $programCode,
                'admission_status' => ucfirst(str_replace('_', ' ', (string) $attempt->status)),
                'accepted_at' => $attempt->accepted_at?->format('Y-m-d') ?? '',
            ]],
        ];
    }

    private function templateHtml(ReportTemplate $template, DocumentBlueprintCompiler $compiler): string
    {
        if (is_array($template->builder_json) && ! empty($template->builder_json['components'])) {
            $compiled = $compiler->compile(ResultSheetBuilderSchema::normalize($template->builder_json));
            $html = is_array($compiled) ? (string) ($compiled['html'] ?? '') : (string) $compiled;
            if (trim($html) !== '') {
                return $html;
            }
        }
        foreach ([$template->compiled_html, $template->content] as $html) {
            if (is_string($html) && trim($html) !== '') {
                return $html;
            }
        }

        throw new RuntimeException('document_template_html_unavailable');
    }

    /** @return list<string> */
    private function repeatingRegions(ReportTemplate $template): array
    {
        $surface = DocumentSurfaceRegistry::resolve((string) $template->surface_key);

        return $surface?->repeatingRegions() ?? [];
    }

    private function releaseItemForRetry(string $claim): void
    {
        PrintJobItem::query()->where('print_job_id', $this->printJobId)->whereKey($this->itemId)
            ->where('claim_token', $claim)
            ->where('status', PrintJobItem::STATUS_FILLING)
            ->update([
                'status' => PrintJobItem::STATUS_QUEUED,
                'claim_token' => null,
                'claimed_at' => null,
                'claim_expires_at' => null,
                'safe_error' => 'document_item_render_failed',
            ]);
    }
}
