<?php

declare(strict_types=1);

namespace App\Notifications\Services;

use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Rendering\DocxTemplateFiller;
use App\DocumentTemplate\Rendering\LibreOfficeConverter;
use App\DocumentTemplate\Services\DynamicAssetResolutionService;
use App\DocumentTemplate\Support\MacroScanner;
use App\DocumentTemplate\Validation\DocumentBlueprintValidator;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDocument;
use App\Models\Notifications\NoticeDocumentVersion;
use App\Models\Notifications\NotificationDeliveryAttemptArtifact;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\CanonicalReleasedResultBinding;
use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticeDocumentPin;
use App\Notifications\Templates\NoticePrintContext;
use App\Printing\DomPdfPrintRenderer;
use App\Printing\PrintRenderer;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Rendering\ResultSheetCssService;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class NoticeDocumentAttachmentCompiler
{
    public function __construct(
        private readonly DocxTemplateFiller $filler,
        private readonly LibreOfficeConverter $converter,
        private readonly ?DynamicAssetResolutionService $assetService = null,
        private readonly ?PrintRenderer $pdfRenderer = null,
        private readonly ?ResultSheetCssService $css = null,
    ) {}

    /** Compiles only the selected, recipient-bound postcommit print snapshot. */
    public function compileFrozenPdf(array $print, NotificationDelivery $delivery): string
    {
        $payload = $delivery->payload;
        if (! in_array($payload['snapshot_schema_version'] ?? null, ['notice-delivery-2', 'notice-delivery-3'], true)
            || ($payload['recipient']['type'] ?? null) !== $delivery->recipient_type
            || (int) ($payload['recipient']['id'] ?? 0) !== (int) $delivery->recipient_id
            || $print !== ($payload['print'] ?? null)) {
            throw new RuntimeException('notice_print_snapshot_invalid');
        }
        $tuple = [$payload['domain'] ?? null, $payload['canonical_slug'] ?? null, $delivery->event_type, $delivery->template_key];
        if (! in_array($tuple, [
            ['admissions', 'accepted', 'admission.accepted.v1', 'admission.accepted'],
            ['examination', 'session-published', 'exam.session_published.v1', 'exam.session_published'],
            ['results', 'released-online', 'result.released.v1', 'result.released.online'],
        ], true)) {
            throw new RuntimeException('notice_print_event_ineligible');
        }
        $mode = $print['mode'] ?? null;
        $values = $payload['context']['values'] ?? [];
        $binding = $print['canonical_released_result'] ?? null;
        if ($tuple[0] === 'results') {
            if (! is_array($binding)) {
                throw new RuntimeException('released_result_binding_missing');
            }
            app(CanonicalReleasedResultBinding::class)->verify($binding, $delivery, $mode !== 'result_document');
            $values = $mode === 'docx' ? $binding['docx_replacements'] : $binding['replacements'];
        } elseif ($binding !== null) {
            throw new RuntimeException('released_result_binding_invalid');
        }
        if ($mode === 'notice_document') {
            if (($payload['snapshot_schema_version'] ?? null) !== 'notice-delivery-3') {
                throw new RuntimeException('notice_print_snapshot_invalid');
            }

            return $this->compileFrozenNoticeDocument($print, $delivery, $values, is_array($binding) ? $binding : null);
        }
        if ($mode === 'docx') {
            $source = CommunicationTemplateDocument::query()->whereKey($print['source_asset_id'] ?? null)
                ->where('communication_template_id', $payload['template']['id'] ?? null)
                ->where('institution_id', $payload['institution_id'] ?? null)->first();
            if ($source === null || $source->mime_type !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                || ! is_string($print['sha256'] ?? null) || $source->checksum !== $print['sha256']
                || ! str_starts_with($source->storage_path, 'communication-templates/'.$source->communication_template_id.'/')
                || (int) $source->file_size_bytes > 10485760) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            $path = $this->privateStoragePath($source->storage_path);
            if (filesize($path) !== (int) $source->file_size_bytes) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            if (hash_file('sha256', $path) !== $print['sha256']) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            $family = new CommunicationTemplate(['domain' => $tuple[0], 'slug' => $tuple[1],
                'event_key' => $tuple[0] === 'results' ? 'result.released.online.v1' : $delivery->event_type]);
            $analysis = app(NoticePrintContext::class)->analyzeDocx($family, $path, [
                'all' => array_keys($values), 'required' => is_array($binding) ? ['applicant_reference'] : [],
                'recommended' => [], 'aliases' => [],
            ]);
            if (! $analysis['valid']) {
                throw new RuntimeException('notice_print_context_missing');
            }

            return $this->compileNoticePdf($path, $values, 'notice.pdf', $print['sha256'], $delivery);
        }
        if ($mode === 'builder' && is_array($print['blueprint'] ?? null) && is_array($print['settings'] ?? null)) {
            if (! in_array($print['settings']['paper_size'] ?? null, ['a4', 'letter', 'legal'], true)
                || ! in_array($print['settings']['orientation'] ?? null, ['portrait', 'landscape'], true)
                || app(DocumentBlueprintValidator::class)->validate($print['blueprint'], array_keys($values)) !== []) {
                throw new RuntimeException('notice_print_snapshot_invalid');
            }
            if (is_array($binding)) {
                $rows = app(ResultSheetPlaceholderService::class)->buildScoresRows($binding['score_rows'] ?? []);
                foreach (array_keys($values) as $key) {
                    if (TemplateFieldAliasCatalog::canonical($key) === 'scores_rows') {
                        $values[$key] = $rows;
                    }
                }
                $values['scores_rows'] = $rows;
            }

            return $this->compileBuilderPdf($print['blueprint'], (string) ($print['settings']['paper_size'] ?? 'a4'),
                (string) ($print['settings']['orientation'] ?? 'portrait'), 'notice.pdf', $delivery, $values, is_array($binding));
        }
        if ($mode === 'result_document' && is_array($binding)) {
            return $this->compileResultDocumentPdf($binding, 'notice.pdf', $delivery);
        }

        throw new RuntimeException('notice_print_snapshot_invalid');
    }

    /**
     * @param  array<string, mixed>  $print
     * @param  array<string, string>  $values
     */
    private function compileFrozenNoticeDocument(array $print, NotificationDelivery $delivery, array $values, ?array $binding): string
    {
        $payload = $delivery->payload;
        $family = CommunicationTemplate::query()->whereKey($payload['template']['id'] ?? null)
            ->where('institution_id', $payload['institution_id'] ?? null)
            ->where('domain', $payload['domain'] ?? null)
            ->where('slug', $payload['canonical_slug'] ?? null)->first();
        if ($family === null || ! CommunicationTemplateRegistry::supportsPdf($family->domain, $family->slug, $family->event_key)
            || ($print['mode'] ?? null) !== 'notice_document'
            || ! is_array($print['state'] ?? null) || ! is_int($print['document_revision_id'] ?? null)
            || ! is_string($print['definition_sha256'] ?? null) || ! is_string($print['render_sha256'] ?? null)) {
            throw new RuntimeException('notice_print_snapshot_invalid');
        }
        $version = NoticeDocumentVersion::query()
            ->whereKey($print['document_revision_id'])->where('communication_template_id', $family->id)->first();
        $definition = $print['state'];
        if ($version === null || (int) $version->version !== (int) ($print['version'] ?? 0)
            || ! hash_equals($version->definition_sha256, $print['definition_sha256'])
            || ! hash_equals($version->render_sha256, $print['render_sha256'])
            || ! hash_equals(CommunicationTemplatePayload::hash($version->state), CommunicationTemplatePayload::hash($definition))
            || ! hash_equals($version->definition_sha256, CommunicationTemplatePayload::hash($definition))
            || ! hash_equals($version->render_sha256, NoticeDocumentPin::renderDigest($definition))) {
            throw new RuntimeException('notice_print_snapshot_invalid');
        }
        $pins = $definition['pins'] ?? [];
        $references = app(NoticeDocumentPin::class)->references($definition);
        $assets = [];
        foreach ($references as $assetId => $checksum) {
            $asset = CommunicationTemplateDocument::query()->whereKey($assetId)
                ->where('communication_template_id', $family->id)->where('institution_id', $family->institution_id)->first();
            if ($asset === null || ! hash_equals($checksum, $asset->checksum)
                || ! str_starts_with($asset->storage_path, 'communication-templates/'.$family->id.'/')) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            $path = $this->privateStoragePath($asset->storage_path);
            if (! hash_equals($checksum, hash_file('sha256', $path))) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            $assets[$assetId] = ['path' => $path, 'bytes' => file_get_contents($path), 'mime' => $asset->mime_type];
        }
        $sourceKind = $definition['source_kind'] ?? 'builder';
        if ($sourceKind === 'docx') {
            $sourceId = $definition['document_asset_id'] ?? null;
            $source = $assets[$sourceId] ?? null;
            $docxValues = is_array($binding) ? ($binding['docx_replacements'] ?? null) : $values;
            if (! is_array($source) || $source['mime'] !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                || ! is_string($pins['source_checksum'] ?? null)
                || ! hash_equals($pins['source_checksum'], (string) ($references[$sourceId] ?? ''))
                || ! is_array($docxValues)) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            $catalog = is_array($pins['catalog'] ?? null) ? $pins['catalog'] : [];
            $analysis = app(NoticePrintContext::class)->analyzeDocx($family, $source['path'], [
                'all' => array_keys($docxValues), 'required' => $catalog['required'] ?? [],
                'recommended' => $catalog['recommended'] ?? [], 'aliases' => $catalog['aliases'] ?? [],
            ]);
            if (! $analysis['valid']) {
                throw new RuntimeException('notice_print_context_missing');
            }

            return $this->compileNoticePdf($source['path'], $docxValues, 'notice.pdf', $pins['source_checksum'], $delivery);
        }
        if ($sourceKind !== 'builder' || ! is_array($definition['builder_json'] ?? null)) {
            throw new RuntimeException('notice_print_snapshot_invalid');
        }
        if (is_array($binding)) {
            $rows = app(ResultSheetPlaceholderService::class)->buildScoresRows($binding['score_rows'] ?? []);
            foreach (array_keys($values) as $key) {
                if (TemplateFieldAliasCatalog::canonical($key) === 'scores_rows') {
                    $values[$key] = $rows;
                }
            }
        }

        $blueprint = $definition['builder_json'];
        foreach ($blueprint['components'] ?? [] as $index => $component) {
            if (! is_array($component) || ($component['type'] ?? null) !== 'image') {
                continue;
            }
            $assetId = $component['props']['asset_id'] ?? null;
            $asset = is_string($assetId) ? ($assets[$assetId] ?? null) : null;
            if (! is_array($asset) || ! in_array($asset['mime'], ['image/png', 'image/jpeg'], true) || ! is_string($asset['bytes'])) {
                throw new RuntimeException('notice_print_source_invalid');
            }
            $blueprint['components'][$index]['props']['src'] = 'data:'.$asset['mime'].';base64,'.base64_encode($asset['bytes']);
        }

        return $this->compileBuilderPdf($blueprint, (string) ($definition['paper_size'] ?? 'a4'),
            (string) ($definition['orientation'] ?? 'portrait'), 'notice.pdf', $delivery, $values, is_array($binding));
    }

    /**
     * @param  array<string, mixed>  $replacements
     */
    public function compileNoticePdf(
        string $docxTemplatePath,
        array $replacements,
        ?string $outputFilename = null,
        ?string $expectedSourceChecksum = null,
        ?NotificationDelivery $delivery = null,
    ): string {
        if (! is_file($docxTemplatePath)) {
            throw new RuntimeException('Notice DOCX source file is unavailable.');
        }

        $sourceChecksum = hash_file('sha256', $docxTemplatePath);
        if ($sourceChecksum === false || ($expectedSourceChecksum !== null && ! hash_equals($expectedSourceChecksum, $sourceChecksum))) {
            throw new RuntimeException('Notice DOCX source checksum verification failed.');
        }

        $filledDocxPath = $this->filler->fill($docxTemplatePath, $replacements);
        try {
            if (! $this->converter->isAvailable()) {
                throw new RuntimeException('libreoffice_unavailable');
            }
            $this->normalizeGeneratedSettings($docxTemplatePath, $filledDocxPath);
            $analysis = app(NoticePrintContext::class)->analyzeDocx(new CommunicationTemplate, $filledDocxPath, [
                'all' => [], 'required' => [], 'recommended' => [], 'aliases' => [],
            ]);
            if (! $analysis['valid']) {
                throw new RuntimeException('notice_print_unresolved_tokens');
            }

            $pdfContents = $this->converter->convertToPdf($filledDocxPath);

            return $this->writeVerifiedPdf($pdfContents, $outputFilename, $delivery, $sourceChecksum, 'docx');
        } finally {
            if (is_file($filledDocxPath)) {
                @unlink($filledDocxPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $builderJson
     */
    public function compileBuilderPdf(
        array $builderJson,
        string $paperSize = 'a4',
        string $orientation = 'portrait',
        ?string $outputFilename = null,
        ?NotificationDelivery $delivery = null,
        array $replacements = [],
        bool $officialScoreRows = false,
    ): string {
        if ($replacements === [] && $delivery !== null && is_array($delivery->payload['event_context'] ?? null)) {
            $replacements = $delivery->payload['event_context'];
        }

        $html = (new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults()))->compile($builderJson);
        $rawRows = $officialScoreRows;
        foreach ($replacements as $key => $value) {
            if ($rawRows && TemplateFieldAliasCatalog::canonical($key) === 'scores_rows' && is_string($value)) {
                $html = $this->replaceScoreRows($html, $key, $value);
            } elseif (is_scalar($value) || $value === null) {
                $html = str_replace('{{'.$key.'}}', e((string) ($value ?? '')), $html);
            }
        }
        if ($rawRows) {
            $rows = (string) ($replacements['scores_rows'] ?? '');
            $html = preg_replace_callback('/(<tbody\\b[^>]*class=["\\\']scores-rows-placeholder["\\\'][^>]*>).*?(<\\/tbody>)/s',
                static fn (array $match): string => $match[1].$rows.$match[2], $html) ?? $html;
        }
        if ($delivery !== null && in_array($delivery->payload['snapshot_schema_version'] ?? null, ['notice-delivery-2', 'notice-delivery-3'], true)) {
            $scan = MacroScanner::scan($html);
            if ($scan['found'] !== [] || $scan['unresolved'] !== []) {
                throw new RuntimeException('notice_print_context_missing');
            }
        }
        $page = is_array($builderJson['page'] ?? null) ? $builderJson['page'] : [];
        $dimensions = config('result-sheet.paper_sizes.'.$paperSize, ['width_mm' => 210, 'height_mm' => 297]);
        $html = ($this->css ?? new ResultSheetCssService)->wrapForPdf(
            $html,
            $paperSize,
            $orientation,
            (float) ($page['margin_mm'] ?? 0),
        );
        $pdfContents = ($this->pdfRenderer ?? new DomPdfPrintRenderer)->render($html, [
            'format' => $paperSize,
            'orientation' => $orientation,
            'width_mm' => $dimensions['width_mm'],
            'height_mm' => $dimensions['height_mm'],
        ]);

        return $this->writeVerifiedPdf($pdfContents, $outputFilename, $delivery, CommunicationTemplatePayload::hash($builderJson), 'builder');
    }

    /**
     * Converts a prepared result document's frozen, checksummed HTML to an attached PDF.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function compileResultDocumentPdf(
        array $snapshot,
        ?string $outputFilename = null,
        ?NotificationDelivery $delivery = null,
    ): string {
        $relativePath = $snapshot['document_path'] ?? null;
        $expectedChecksum = $snapshot['document_checksum'] ?? null;
        if (! is_string($relativePath) || $relativePath === '' || ! is_string($expectedChecksum) || $expectedChecksum === '') {
            throw new RuntimeException('Result document snapshot is incomplete.');
        }
        if ($delivery !== null && ($delivery->payload['snapshot_schema_version'] ?? null) !== 'notice-delivery-2') {
            $this->verifyHistoricalResultSource($snapshot, $delivery);
        }

        $sourcePath = $this->privateStoragePath($relativePath);
        $html = file_get_contents($sourcePath);
        if (! is_string($html) || ! hash_equals($expectedChecksum, hash('sha256', $html))) {
            throw new RuntimeException('Result document source checksum verification failed.');
        }

        $paperSize = (string) ($snapshot['paper_size'] ?? 'a4');
        $orientation = (string) ($snapshot['orientation'] ?? 'portrait');
        $dimensions = config('result-sheet.paper_sizes.'.$paperSize, ['width_mm' => 210, 'height_mm' => 297]);
        $pdfContents = ($this->pdfRenderer ?? new DomPdfPrintRenderer)->render($html, [
            'format' => $paperSize,
            'orientation' => $orientation,
            'width_mm' => $dimensions['width_mm'],
            'height_mm' => $dimensions['height_mm'],
        ]);

        return $this->writeVerifiedPdf($pdfContents, $outputFilename, $delivery, $expectedChecksum, 'result_document');
    }

    public function prepareApplicantAssetReplacements(
        ApplicantIdentity $identity,
        ?string $photoPath = null,
        ?string $verificationQrUrl = null,
    ): array {
        $verificationUrl = $verificationQrUrl ?? url('/applicant/verification/'.$identity->id);

        return [
            'applicant_photo_2x2' => $photoPath,
            'verification_qr' => $verificationUrl,
            'applicant.photo_2x2' => $photoPath,
            'applicant.qr_code' => $verificationUrl,
        ];
    }

    private function writeVerifiedPdf(
        string $pdfContents,
        ?string $outputFilename,
        ?NotificationDelivery $delivery,
        string $sourceChecksum,
        string $sourceKind,
    ): string {
        if (! str_starts_with($pdfContents, '%PDF-')) {
            throw new RuntimeException('The PDF renderer produced no output.');
        }

        $outputDirectory = null;
        $pdfPath = null;
        $completed = false;
        try {
            $outputDirectory = $this->createOutputDirectory();
            $filename = $outputFilename === null || trim($outputFilename) === ''
                ? 'notice.pdf'
                : basename($outputFilename);
            $pdfPath = $outputDirectory.DIRECTORY_SEPARATOR.$filename;
            $expectedChecksum = hash('sha256', $pdfContents);
            if ($this->writePdfBytes($pdfPath, $pdfContents) === false) {
                throw new RuntimeException('Unable to write compiled notice PDF.');
            }

            $writtenChecksum = hash_file('sha256', $pdfPath);
            if ($writtenChecksum === false || ! hash_equals($expectedChecksum, $writtenChecksum)) {
                throw new RuntimeException('Compiled notice PDF checksum verification failed.');
            }
            if ($delivery !== null) {
                $artifactAttributes = [
                    'checksum' => $writtenChecksum,
                    'mime_type' => 'application/pdf',
                    'file_size_bytes' => filesize($pdfPath) ?: 0,
                    'source_sha256' => $sourceChecksum,
                    'source_kind' => $sourceKind,
                    'canonical_binding' => $this->canonicalArtifactMetadata($delivery),
                ];
                $artifact = NotificationDeliveryAttemptArtifact::query()->firstOrCreate(
                    [
                        'delivery_id' => $delivery->getKey(),
                        'attempt' => max(1, (int) $delivery->attempts),
                    ],
                    $artifactAttributes,
                );
                foreach ($artifactAttributes as $key => $value) {
                    if ($artifact->getAttribute($key) !== $value) {
                        throw new RuntimeException('notice_attempt_artifact_conflict');
                    }
                }
            }
            $completed = true;

            return $pdfPath;
        } finally {
            if (! $completed) {
                if ($pdfPath !== null) {
                    @unlink($pdfPath);
                }
                if ($outputDirectory !== null) {
                    @rmdir($outputDirectory);
                }
            }
        }
    }

    protected function writePdfBytes(string $path, string $contents): int|false
    {
        return file_put_contents($path, $contents, LOCK_EX);
    }

    private function privateStoragePath(string $relativePath): string
    {
        $storageRoot = realpath(Storage::disk('local')->path(''));
        $resolvedPath = realpath(Storage::disk('local')->path($relativePath));
        if ($storageRoot === false
            || $resolvedPath === false
            || ! str_starts_with($resolvedPath, $storageRoot.DIRECTORY_SEPARATOR)
            || ! is_file($resolvedPath)) {
            throw new RuntimeException('Frozen result document is unavailable in private storage.');
        }

        return $resolvedPath;
    }

    private function verifyHistoricalResultSource(array $snapshot, NotificationDelivery $delivery): void
    {
        $payload = $delivery->payload;
        $document = ResultDocument::query()->whereKey($snapshot['document_id'] ?? null)
            ->where('admission_attempt_id', $payload['attempt_id'] ?? null)->where('status', ResultDocument::STATUS_READY)
            ->where('checksum', $snapshot['document_checksum'])->where('storage_path', $snapshot['document_path'])
            ->whereHas('admissionAttempt', fn ($query) => $query->where('applicant_identity_id', $delivery->recipient_id))
            ->first();
        if ($delivery->recipient_type !== ApplicantIdentity::class
            || (isset($payload['identity_id']) && (int) $payload['identity_id'] !== (int) $delivery->recipient_id)
            || ($document !== null && isset($payload['institution_id'])
                && (int) $document->admissionAttempt?->institution_id !== (int) $payload['institution_id'])) {
            throw new RuntimeException('released_result_binding_invalid');
        }
        if ($document === null || $delivery->event_type !== 'result.released.v1' || $delivery->template_key !== 'result.released.online'
            || ! ResultRelease::query()->whereKey($payload['release_id'] ?? null)
                ->where('admission_attempt_id', $document->admission_attempt_id)->where('result_document_id', $document->id)
                ->where('release_version', $payload['release_version'] ?? null)->where('mode', 'online')
                ->where('is_current', true)->whereNull('unreleased_at')->exists()) {
            throw new RuntimeException('released_result_binding_invalid');
        }
    }

    private function canonicalArtifactMetadata(NotificationDelivery $delivery): ?array
    {
        $binding = $delivery->payload['print']['canonical_released_result'] ?? null;
        if (is_array($binding)) {
            return CanonicalReleasedResultBinding::metadata($binding);
        }
        if ($delivery->event_type !== 'result.released.v1' || $delivery->template_key !== 'result.released.online') {
            return null;
        }

        return ['recipient' => ['type' => $delivery->recipient_type, 'id' => (int) $delivery->recipient_id],
            ...array_intersect_key($delivery->payload, array_fill_keys(['institution_id', 'attempt_id', 'release_id', 'release_version'], true)),
            ...array_intersect_key($delivery->payload['document_snapshot'] ?? [], array_fill_keys(['document_id', 'document_version', 'document_checksum'], true))];
    }

    private function createOutputDirectory(): string
    {
        $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'notice_pdf_'.bin2hex(random_bytes(8));
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create notice PDF output directory.');
        }

        return $directory;
    }

    /** Restore PHPWord's optional settings part when its writer emits it empty. */
    private function normalizeGeneratedSettings(string $sourcePath, string $filledPath): void
    {
        $source = new ZipArchive;
        if ($source->open($sourcePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('notice_print_source_unavailable');
        }
        try {
            $originalSettings = $source->getFromName('word/settings.xml');
        } finally {
            $source->close();
        }
        if (is_string($originalSettings) && $originalSettings === '') {
            throw new RuntimeException('notice_print_source_invalid');
        }

        $filled = new ZipArchive;
        if ($filled->open($filledPath) !== true) {
            throw new RuntimeException('notice_print_filled_package_invalid');
        }
        try {
            $index = $filled->locateName('word/settings.xml');
            if ($index !== false) {
                $stat = $filled->statIndex($index);
                if (! is_array($stat)) {
                    throw new RuntimeException('notice_print_filled_package_invalid');
                }
                if ($stat['size'] === 0) {
                    $normalized = is_string($originalSettings)
                        ? $filled->addFromString('word/settings.xml', $originalSettings)
                        : $filled->deleteIndex($index);
                    if (! $normalized) {
                        throw new RuntimeException('notice_print_generated_settings_invalid');
                    }
                }
            }
        } finally {
            if (! $filled->close()) {
                throw new RuntimeException('notice_print_filled_package_invalid');
            }
        }
    }

    private function replaceScoreRows(string $html, string $key, string $rows): string
    {
        $token = '\{\{\s*'.preg_quote($key, '~').'\s*\}\}';
        $replace = static fn (string $text, string $replacement): string => preg_replace_callback(
            '~'.$token.'~', static fn (): string => $replacement, $text,
        ) ?? $text;
        $table = '<table class="notice-score-rows" style="width:100%;border-collapse:collapse;"><tbody>'.$rows.'</tbody></table>';
        $html = preg_replace_callback(
            '~<(p|span|h[1-6]|strong|em|b|i|u|a)\b([^>]*)>([^<]*'.$token.'[^<]*)</\1>~i',
            static fn (array $match): string => '<div'.$match[2].'>'.$replace($match[3], $table).'</div>',
            $html,
        ) ?? $html;
        $html = preg_replace_callback(
            '~<(table|thead|tbody|tfoot)\b([^>]*)>([^<]*'.$token.'[^<]*)</\1>~i',
            static fn (array $match): string => '<'.$match[1].$match[2].'>'.$replace($match[3], $rows).'</'.$match[1].'>',
            $html,
        ) ?? $html;

        return $replace($html, $table);
    }
}
