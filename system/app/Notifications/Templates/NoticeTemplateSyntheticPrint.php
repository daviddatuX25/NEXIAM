<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Notifications\Services\NoticeDocumentAttachmentCompiler;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\ResultSheet\Data\TemplateFieldAliasCatalog;
use App\ResultSheet\Support\ResultSheetPlaceholderService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class NoticeTemplateSyntheticPrint
{
    public function __construct(private readonly StoreCommunicationTemplateDocument $store,
        private readonly NoticeDocumentAttachmentCompiler $compiler) {}

    /** @return array{bytes:string,sha256:string} */
    public function render(CommunicationTemplate $template, CommunicationTemplateDraft $draft, array $context): array
    {
        if ($draft->document_mode === 'none' || ! CommunicationTemplateRegistry::supportsPdf($template->domain, $template->slug, $template->event_key)) {
            throw new RuntimeException('print_not_enabled');
        }
        $official = NoticePrintContext::isOfficialScorecard($template);
        $tokens = NoticePrintContext::catalog($template)['all'];
        if ($official) {
            $placeholders = app(ResultSheetPlaceholderService::class);
            $sample = $placeholders->sampleData();
            $sample['name'] = 'Synthetic scorecard applicant';
            $sample['reference'] = 'SYNTHETIC-PRINT';
            $context = $placeholders->buildReplacements([$sample]);
            $context['institution_name'] = 'Synthetic institution';
        }
        $path = null;
        try {
            if ($draft->document_mode === 'docx') {
                $source = $this->store->verified($template, $draft->document_asset_id, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
                $sourcePath = Storage::disk('local')->path($source->storage_path);
                $analysis = app(NoticePrintContext::class)->analyzeDocx($template, $sourcePath);
                if (! $analysis['has_printable_content']) {
                    throw new RuntimeException('print_source_empty');
                }
                if ($analysis['missing_required'] !== []) {
                    throw new RuntimeException('print_required_token_missing');
                }
                if ($analysis['unknown'] !== []) {
                    throw new RuntimeException('print_token_unknown');
                }
                if ($analysis['unresolved'] !== [] || ! $analysis['valid']) {
                    throw new RuntimeException('print_source_tokens_invalid');
                }
                foreach ($analysis['found'] as $token) {
                    $this->assertToken($token, $context, $tokens);
                }
                if ($official) {
                    foreach ($context as $key => $value) {
                        if (TemplateFieldAliasCatalog::canonical($key) === 'scores_rows') {
                            $context[$key] = implode("\n", array_map(static fn (array $score): string => (string) $score['domain'].': '.(string) $score['raw'].' — '.(string) $score['converted'], $sample['scores']));
                        }
                    }
                }
                $path = $this->compiler->compileNoticePdf($sourcePath, $context, 'synthetic-notice.pdf', $source->checksum);
            } elseif ($draft->document_mode === 'builder') {
                $analysis = NoticePrintContext::analyzeBuilder($draft->builder_json);
                $bindings = $analysis['bindings'];
                if (! $analysis['has_printable_content']) {
                    throw new RuntimeException('print_source_empty');
                }
                if ($analysis['unresolved'] !== []) {
                    throw new RuntimeException('print_source_tokens_invalid');
                }
                if (NoticePrintContext::missingRequiredBindings($template, $bindings) !== []) {
                    throw new RuntimeException('print_required_token_missing');
                }
                foreach ($bindings as $token) {
                    $this->assertToken($token, $context, $tokens);
                }
                $path = $this->compiler->compileBuilderPdf($draft->builder_json, $draft->paper_size, $draft->orientation,
                    'synthetic-notice.pdf', null, $context, $official);
            } else {
                throw new RuntimeException('print_not_enabled');
            }
            $bytes = file_get_contents($path);
            $checksum = hash_file('sha256', $path);
            if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-') || ! is_string($checksum)
                || ! hash_equals($checksum, hash('sha256', $bytes))) {
                throw new RuntimeException('print_artifact_invalid');
            }

            return ['bytes' => $bytes, 'sha256' => $checksum];
        } finally {
            if ($path !== null) {
                @unlink($path);
                @rmdir(dirname($path));
            }
        }
    }

    private function assertToken(string $token, array $context, array $allowed): void
    {
        if (! in_array($token, $allowed, true)) {
            throw new RuntimeException('print_token_unknown');
        }
        if (! array_key_exists($token, $context) || ! is_scalar($context[$token])) {
            throw new RuntimeException('print_context_missing');
        }
    }
}
