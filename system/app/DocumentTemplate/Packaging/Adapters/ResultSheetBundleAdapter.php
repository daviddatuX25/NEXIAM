<?php

namespace App\DocumentTemplate\Packaging\Adapters;

use App\DocumentTemplate\Packaging\Contracts\DocumentTemplateBundleAdapterInterface;
use App\Models\ResultSheet\ResultSheetTemplate;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;

class ResultSheetBundleAdapter implements DocumentTemplateBundleAdapterInterface
{
    public function surfaceKey(): string
    {
        return 'result_sheet';
    }

    public function export(int|string $templateId): array
    {
        $template = ResultSheetTemplate::query()->findOrFail($templateId);
        $files = [];
        $templateData = [
            'name' => $template->name,
            'mode' => $template->mode,
            'paper_size' => $template->paper_size,
            'orientation' => $template->orientation,
            'logical_unit' => $template->logical_unit,
            'source_kind' => $template->source_kind,
        ];

        if ($template->mode === ResultSheetTemplate::MODE_DOCX) {
            $path = storage_path('app/private/'.(string) $template->document_path);
            if (! is_file($path)) {
                throw new RuntimeException('The result-sheet template document is unavailable.');
            }

            $document = file_get_contents($path);
            if (! is_string($document)) {
                throw new RuntimeException('The result-sheet template document cannot be read.');
            }
            $files['template.docx'] = $document;
            $templateData['source_kind'] = ResultSheetTemplate::SOURCE_DOCX;
        } elseif ($template->mode === ResultSheetTemplate::MODE_HTML) {
            if (is_array($template->builder_json)) {
                $files['blueprint.json'] = json_encode($template->builder_json, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
                $templateData['source_kind'] = ResultSheetTemplate::SOURCE_BUILDER;
            } elseif (is_string($template->content)) {
                $files['content.html'] = $template->content;
            } else {
                throw new RuntimeException('The result-sheet HTML template has no source content.');
            }
        } else {
            throw new InvalidArgumentException('Unsupported result-sheet template mode.');
        }

        return [
            'manifest' => [
                'surface_key' => $this->surfaceKey(),
                'template' => $templateData,
            ],
            'files' => $files,
        ];
    }

    public function import(array $bundle, array $options = []): array
    {
        $templateData = $bundle['manifest']['template'] ?? null;
        if (! is_array($templateData) || ! isset($templateData['mode'], $templateData['name'])) {
            throw new InvalidArgumentException('The result-sheet bundle template metadata is invalid.');
        }

        $mode = $templateData['mode'];
        $template = new ResultSheetTemplate;
        $template->name = ($options['name'] ?? $templateData['name']).' (Imported)';
        $template->mode = $mode;
        $template->paper_size = $templateData['paper_size'] ?? 'a4';
        $template->orientation = $templateData['orientation'] ?? 'portrait';
        $template->logical_unit = $templateData['logical_unit'] ?? 'full';
        $template->is_active = false;

        if ($mode === ResultSheetTemplate::MODE_DOCX) {
            $document = $bundle['files']['template.docx'] ?? null;
            if (! is_string($document)) {
                throw new InvalidArgumentException('The result-sheet DOCX bundle is missing template.docx.');
            }

            $template->source_kind = ResultSheetTemplate::SOURCE_DOCX;
            $template->document_extension = 'docx';
            $template->document_checksum = hash('sha256', $document);
            $template->save();

            $relativePath = 'result-sheet-templates/'.$template->id.'.docx';
            $absolutePath = storage_path('app/private/'.$relativePath);
            File::ensureDirectoryExists(dirname($absolutePath));
            if (file_put_contents($absolutePath, $document, LOCK_EX) === false) {
                $template->delete();
                throw new RuntimeException('Unable to write the imported result-sheet document.');
            }
            $template->document_path = $relativePath;
            $template->save();
        } elseif ($mode === ResultSheetTemplate::MODE_HTML) {
            $template->source_kind = $templateData['source_kind'] ?? ResultSheetTemplate::SOURCE_LEGACY_HTML;
            if (isset($bundle['files']['blueprint.json'])) {
                $decoded = json_decode($bundle['files']['blueprint.json'], true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    throw new InvalidArgumentException('The result-sheet blueprint must contain a JSON object.');
                }
                $template->builder_json = $decoded;
                $template->builder_schema_version = $templateData['builder_schema_version'] ?? ResultSheetTemplate::BUILDER_SCHEMA_VERSION;
            } elseif (isset($bundle['files']['content.html'])) {
                $template->content = $bundle['files']['content.html'];
            } else {
                throw new InvalidArgumentException('The result-sheet HTML bundle is missing its source file.');
            }
            $template->save();
        } else {
            throw new InvalidArgumentException('Unsupported result-sheet template mode.');
        }

        return ['template_id' => $template->id, 'is_active' => false];
    }
}
