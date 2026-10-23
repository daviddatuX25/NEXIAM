<?php

namespace App\ResultSheet\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ResultSheet\Builder\ResultSheetBuilderCompiler;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Create or update a versioned result-sheet template. Single-active is
 * enforced inside the same transaction; Word/ODT documents are stored on the
 * private disk under result-sheet-templates/{id}.{ext} with a sha256 checksum.
 */
final class SaveResultSheetTemplate
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly ResultSheetBuilderCompiler $builderCompiler,
        private readonly ActivateResultSheetTemplate $activateResultSheetTemplate,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ResultSheetTemplate
    {
        $data = $this->prepareBuilderData($data);
        $isDocument = ($data['mode'] ?? null) === ResultSheetTemplate::MODE_DOCX;
        $isBuilder = ($data['source_kind'] ?? null) === ResultSheetTemplate::SOURCE_BUILDER;
        $shouldActivate = (bool) ($data['is_active'] ?? false);
        $requiresVerification = $shouldActivate && ($isDocument || $isBuilder);

        return DB::transaction(function () use ($data, $actor, $isDocument, $shouldActivate, $requiresVerification): ResultSheetTemplate {
            if ($shouldActivate && ! $requiresVerification) {
                ResultSheetTemplate::query()->where('is_active', true)->update(['is_active' => false]);
            }

            $template = ResultSheetTemplate::query()->create([
                'name' => $data['name'],
                'mode' => $data['mode'],
                'source_kind' => $data['source_kind'] ?? ($isDocument ? ResultSheetTemplate::SOURCE_DOCX : ResultSheetTemplate::SOURCE_LEGACY_HTML),
                'builder_schema_version' => $data['builder_schema_version'] ?? null,
                'builder_json' => $data['builder_json'] ?? null,
                'compiled_html' => $data['compiled_html'] ?? null,
                'compiler_version' => $data['compiler_version'] ?? null,
                'lock_version' => 1,
                'paper_size' => $data['paper_size'] ?? ResultSheetTemplate::PAPER_A4,
                'orientation' => $data['orientation'] ?? ResultSheetTemplate::ORIENTATION_PORTRAIT,
                'logical_unit' => $data['logical_unit'] ?? ResultSheetTemplate::LOGICAL_FULL,
                'content' => ! $isDocument ? ($data['content'] ?? $data['compiled_html'] ?? '') : null,
                'watermark_text' => $data['watermark_text'] ?? null,
                'is_active' => $shouldActivate && ! $requiresVerification,
                'created_by_user_id' => $actor->id,
            ]);

            $pendingDocumentPath = null;

            try {
                if ($isDocument) {
                    $document = $data['document'];

                    if ($requiresVerification) {
                        $pendingDocumentPath = $this->pendingDocumentPath($template, $document);
                        $this->storeDocument($template, $document, $pendingDocumentPath);
                    } else {
                        $this->storeDocument($template, $document);
                    }
                }

                if ($requiresVerification) {
                    $template = $this->activateResultSheetTemplate->handle($template, $actor);

                    if ($pendingDocumentPath !== null) {
                        $this->promoteDocument($template, $pendingDocumentPath);
                        $pendingDocumentPath = null;
                    }
                }
            } catch (Throwable $exception) {
                if ($pendingDocumentPath !== null) {
                    Storage::disk('local')->delete($pendingDocumentPath);
                }

                throw $exception;
            }

            $this->recordAuditEvent->handle('result_template.created', [
                'template_id' => $template->id,
                'name' => $template->name,
                'mode' => $template->mode,
                'is_active' => $template->is_active,
                'document_checksum' => $template->document_checksum,
            ], (string) $actor->id);

            return $template;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ResultSheetTemplate $template, array $data, User $actor): ResultSheetTemplate
    {
        $data = $this->prepareBuilderData($data, $template);

        return DB::transaction(function () use ($template, $data, $actor): ResultSheetTemplate {
            $template = ResultSheetTemplate::query()->lockForUpdate()->findOrFail($template->id);

            if (array_key_exists('lock_version', $data)
                && (int) $data['lock_version'] !== (int) $template->lock_version) {
                throw new RuntimeException('template_conflict');
            }

            $mode = $data['mode'] ?? $template->mode;
            $isDocument = $mode === ResultSheetTemplate::MODE_DOCX;
            $sourceKind = $data['source_kind']
                ?? ($isDocument
                    ? ResultSheetTemplate::SOURCE_DOCX
                    : (($template->source_kind ?? null) === ResultSheetTemplate::SOURCE_BUILDER
                        ? ResultSheetTemplate::SOURCE_BUILDER
                        : ResultSheetTemplate::SOURCE_LEGACY_HTML));

            if (! $isDocument && $sourceKind !== ResultSheetTemplate::SOURCE_BUILDER) {
                $sourceKind = ResultSheetTemplate::SOURCE_LEGACY_HTML;
            }

            $requestedActive = array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : (bool) $template->is_active;
            $activateDocument = $isDocument && $requestedActive;
            $requiresVerification = $requestedActive
                && ($activateDocument || $sourceKind === ResultSheetTemplate::SOURCE_BUILDER);

            $updates = [
                'name' => $data['name'] ?? $template->name,
                'mode' => $mode,
                'source_kind' => $sourceKind,
                'builder_schema_version' => $data['builder_schema_version']
                    ?? ($sourceKind === ResultSheetTemplate::SOURCE_BUILDER ? $template->builder_schema_version : null),
                'builder_json' => array_key_exists('builder_json', $data)
                    ? $data['builder_json']
                    : ($sourceKind === ResultSheetTemplate::SOURCE_BUILDER ? $template->builder_json : null),
                'compiled_html' => array_key_exists('compiled_html', $data)
                    ? $data['compiled_html']
                    : ($sourceKind === ResultSheetTemplate::SOURCE_BUILDER ? $template->compiled_html : null),
                'compiler_version' => array_key_exists('compiler_version', $data)
                    ? $data['compiler_version']
                    : ($sourceKind === ResultSheetTemplate::SOURCE_BUILDER ? $template->compiler_version : null),
                'paper_size' => $data['paper_size'] ?? $template->paper_size,
                'orientation' => $data['orientation'] ?? $template->orientation,
                'logical_unit' => $data['logical_unit'] ?? $template->logical_unit,
                'watermark_text' => array_key_exists('watermark_text', $data) ? $data['watermark_text'] : $template->watermark_text,
                'lock_version' => ((int) $template->lock_version) + 1,
            ];

            if ($mode === ResultSheetTemplate::MODE_HTML) {
                $updates['content'] = $data['content'] ?? $data['compiled_html'] ?? $template->content;
                $updates['document_path'] = null;
                $updates['document_checksum'] = null;
                $updates['document_extension'] = null;
            } else {
                $updates['source_kind'] = ResultSheetTemplate::SOURCE_DOCX;
                $updates['builder_schema_version'] = null;
                $updates['builder_json'] = null;
                $updates['compiled_html'] = null;
                $updates['compiler_version'] = null;
            }

            if ($requiresVerification) {
                // Activation is committed only by the verifier action.
                $updates['is_active'] = false;
            } elseif (array_key_exists('is_active', $data)) {
                $updates['is_active'] = (bool) $data['is_active'];
            }

            if (! $requiresVerification && (($updates['is_active'] ?? $template->is_active) === true)) {
                ResultSheetTemplate::query()
                    ->where('is_active', true)
                    ->where('id', '!=', $template->id)
                    ->update(['is_active' => false]);
            }

            $template->update($updates);
            $pendingDocumentPath = null;

            try {
                if ($isDocument && isset($data['document'])) {
                    if ($activateDocument) {
                        $pendingDocumentPath = $this->pendingDocumentPath($template, $data['document']);
                        $this->storeDocument($template, $data['document'], $pendingDocumentPath);
                    } else {
                        $this->storeDocument($template, $data['document']);
                    }
                }

                if ($requiresVerification) {
                    $template = $this->activateResultSheetTemplate->handle($template, $actor);

                    if ($pendingDocumentPath !== null) {
                        $this->promoteDocument($template, $pendingDocumentPath);
                        $pendingDocumentPath = null;
                    }
                }
            } catch (Throwable $exception) {
                if ($pendingDocumentPath !== null) {
                    Storage::disk('local')->delete($pendingDocumentPath);
                }

                throw $exception;
            }

            $this->recordAuditEvent->handle('result_template.updated', [
                'template_id' => $template->id,
                'name' => $template->name,
                'mode' => $template->mode,
                'source_kind' => $template->source_kind,
                'is_active' => $template->is_active,
                'document_checksum' => $template->document_checksum,
            ], (string) $actor->id);

            return $template->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepareBuilderData(array $data, ?ResultSheetTemplate $existing = null): array
    {
        $mode = $data['mode'] ?? $existing?->mode;
        if ($mode !== ResultSheetTemplate::MODE_HTML) {
            $data['source_kind'] = ResultSheetTemplate::SOURCE_DOCX;

            return $data;
        }

        $sourceKind = $data['source_kind'] ?? $existing?->source_kind;
        $builder = $data['builder_json'] ?? null;
        if (is_string($builder)) {
            $builder = json_decode($builder, true);
        }

        if (! is_array($builder)) {
            if ($sourceKind === ResultSheetTemplate::SOURCE_BUILDER
                && $existing?->source_kind === ResultSheetTemplate::SOURCE_BUILDER
                && $existing->builder_json !== null
                && ! array_key_exists('builder_json', $data)) {
                $builder = $existing->builder_json;
            } elseif ($sourceKind === ResultSheetTemplate::SOURCE_BUILDER || isset($data['builder_json'])) {
                throw new RuntimeException('builder_invalid:Builder document missing.');
            } else {
                $data['source_kind'] = ResultSheetTemplate::SOURCE_LEGACY_HTML;

                return $data;
            }
        }

        $compiled = $this->builderCompiler->compile($builder);
        if ($compiled['diagnostics'] !== []) {
            throw new RuntimeException('builder_invalid:'.json_encode($compiled['diagnostics'], JSON_THROW_ON_ERROR));
        }

        $data['source_kind'] = ResultSheetTemplate::SOURCE_BUILDER;
        $data['builder_schema_version'] = ResultSheetBuilderSchema::VERSION;
        $data['builder_json'] = $compiled['document'];
        $data['compiled_html'] = $compiled['html'];
        $data['compiler_version'] = ResultSheetBuilderSchema::COMPILER_VERSION;
        $data['content'] = $compiled['html'];

        return $data;
    }

    private function storeDocument(ResultSheetTemplate $template, UploadedFile $document, ?string $destination = null): void
    {
        $extension = $this->documentExtension($document);
        $destination ??= 'result-sheet-templates/'.$template->id.'.'.$extension;
        $bytes = (string) $document->get();

        Storage::disk('local')->put($destination, $bytes);

        $template->update([
            'document_path' => $destination,
            'document_checksum' => hash('sha256', $bytes),
            'document_extension' => $extension,
        ]);
    }

    private function documentExtension(UploadedFile $document): string
    {
        $extension = strtolower($document->getClientOriginalExtension());

        if (! in_array($extension, ['docx', 'odt'], true)) {
            throw new RuntimeException('Only docx and odt templates are supported.');
        }

        return $extension;
    }

    private function pendingDocumentPath(ResultSheetTemplate $template, UploadedFile $document): string
    {
        return 'result-sheet-templates/.pending/'.$template->id.'-'.Str::uuid().'.'.$this->documentExtension($document);
    }

    private function promoteDocument(ResultSheetTemplate $template, string $pendingDocumentPath): void
    {
        $destination = 'result-sheet-templates/'.$template->id.'.'.$template->document_extension;

        if (! Storage::disk('local')->move($pendingDocumentPath, $destination)) {
            throw new RuntimeException('Unable to finalize template document.');
        }

        $template->update(['document_path' => $destination]);
    }
}
