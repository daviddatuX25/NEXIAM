<?php

namespace App\ResultSheet\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Create or update a versioned result-sheet template. Single-active is
 * enforced inside the same transaction; Word/ODT documents are stored on the
 * private disk under result-sheet-templates/{id}.{ext} with a sha256 checksum.
 */
final class SaveResultSheetTemplate
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ResultSheetTemplate
    {
        return DB::transaction(function () use ($data, $actor): ResultSheetTemplate {
            if (($data['is_active'] ?? false) === true) {
                ResultSheetTemplate::query()->where('is_active', true)->update(['is_active' => false]);
            }

            $template = ResultSheetTemplate::query()->create([
                'name' => $data['name'],
                'mode' => $data['mode'],
                'paper_size' => $data['paper_size'] ?? ResultSheetTemplate::PAPER_A4,
                'orientation' => $data['orientation'] ?? ResultSheetTemplate::ORIENTATION_PORTRAIT,
                'logical_unit' => $data['logical_unit'] ?? ResultSheetTemplate::LOGICAL_FULL,
                'content' => $data['mode'] === ResultSheetTemplate::MODE_HTML ? ($data['content'] ?? '') : null,
                'watermark_text' => $data['watermark_text'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'created_by_user_id' => $actor->id,
            ]);

            if ($data['mode'] === ResultSheetTemplate::MODE_DOCX) {
                $this->storeDocument($template, $data['document']);
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
        return DB::transaction(function () use ($template, $data, $actor): ResultSheetTemplate {
            $template = ResultSheetTemplate::query()->lockForUpdate()->findOrFail($template->id);

            $mode = $data['mode'] ?? $template->mode;

            $updates = [
                'name' => $data['name'] ?? $template->name,
                'mode' => $mode,
                'paper_size' => $data['paper_size'] ?? $template->paper_size,
                'orientation' => $data['orientation'] ?? $template->orientation,
                'logical_unit' => $data['logical_unit'] ?? $template->logical_unit,
                'watermark_text' => array_key_exists('watermark_text', $data) ? $data['watermark_text'] : $template->watermark_text,
            ];

            if ($mode === ResultSheetTemplate::MODE_HTML) {
                $updates['content'] = $data['content'] ?? $template->content;
                $updates['document_path'] = null;
                $updates['document_checksum'] = null;
                $updates['document_extension'] = null;
            }

            if (($data['is_active'] ?? null) !== null) {
                $updates['is_active'] = (bool) $data['is_active'];
            }

            if ($updates['is_active'] === true) {
                ResultSheetTemplate::query()
                    ->where('is_active', true)
                    ->where('id', '!=', $template->id)
                    ->update(['is_active' => false]);
            }

            $template->update($updates);

            if ($mode === ResultSheetTemplate::MODE_DOCX && isset($data['document'])) {
                $this->storeDocument($template, $data['document']);
            }

            $this->recordAuditEvent->handle('result_template.updated', [
                'template_id' => $template->id,
                'name' => $template->name,
                'mode' => $template->mode,
                'is_active' => $template->is_active,
                'document_checksum' => $template->document_checksum,
            ], (string) $actor->id);

            return $template->fresh();
        });
    }

    private function storeDocument(ResultSheetTemplate $template, UploadedFile $document): void
    {
        $extension = strtolower($document->getClientOriginalExtension());

        if (! in_array($extension, ['docx', 'odt'], true)) {
            throw new RuntimeException('Only docx and odt templates are supported.');
        }

        $destination = 'result-sheet-templates/'.$template->id.'.'.$extension;
        $bytes = (string) $document->get();

        Storage::disk('local')->put($destination, $bytes);

        $template->update([
            'document_path' => $destination,
            'document_checksum' => hash('sha256', $bytes),
            'document_extension' => $extension,
        ]);
    }
}
