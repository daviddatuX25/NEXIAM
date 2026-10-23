<?php

declare(strict_types=1);

namespace App\Release\Support;

use App\Models\Printing\PrintJobItem;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Support\SensitiveAssetStore;
use RuntimeException;
use Throwable;

final class ResultDocumentPdf
{
    public function __construct(private readonly SensitiveAssetStore $assetStore) {}

    public function isReady(ResultDocument $document): bool
    {
        try {
            $this->read($document);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function canPrepare(ResultDocument $document): bool
    {
        $snapshot = is_array($document->snapshot_payload_json) ? $document->snapshot_payload_json : [];
        $template = $snapshot['_template'] ?? null;
        $source = $snapshot['_template_source'] ?? null;
        if (
            ! is_array($template)
            || ! in_array($template['mode'] ?? null, [ResultSheetTemplate::MODE_HTML, ResultSheetTemplate::MODE_DOCX], true)
            || ! is_array($source)
            || ! is_string($source['bytes_base64'] ?? null)
            || ! is_string($source['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $source['sha256']) !== 1
        ) {
            return false;
        }

        $bytes = base64_decode($source['bytes_base64'], true);

        return is_string($bytes)
            && $bytes !== ''
            && hash_equals($source['sha256'], hash('sha256', $bytes));
    }

    public function read(ResultDocument $document): string
    {
        $artifact = $this->manifest($document);
        if ($artifact === null) {
            throw new RuntimeException('A valid ready PDF artifact is required.');
        }

        try {
            $bytes = $this->assetStore->read($artifact['path']);
        } catch (Throwable $exception) {
            throw new RuntimeException('The stored result PDF could not be read.', previous: $exception);
        }
        if (
            strlen($bytes) !== $artifact['size']
            || ! hash_equals($artifact['sha256'], hash('sha256', $bytes))
            || ! str_starts_with($bytes, '%PDF-')
        ) {
            throw new RuntimeException('The stored result PDF failed integrity validation.');
        }

        return $bytes;
    }

    /**
     * @return array{path: string, size: int, sha256: string}|null
     */
    private function manifest(ResultDocument $document): ?array
    {
        $manifest = is_array($document->artifact_manifest) ? $document->artifact_manifest : [];
        $pdf = $manifest['pdf'] ?? null;
        if (
            ! is_array($pdf)
            || ($pdf['status'] ?? null) !== 'ready'
            || ! is_string($pdf['path'] ?? null)
            || ! is_int($pdf['size'] ?? null)
            || $pdf['size'] < 1
            || ! is_string($pdf['sha256'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/', $pdf['sha256']) !== 1
            || (! str_starts_with($pdf['path'], 'results/'.$document->admission_attempt_id.'/')
                && ! PrintJobItem::query()
                    ->where('result_document_id', $document->id)
                    ->where('admission_attempt_id', $document->admission_attempt_id)
                    ->where('status', PrintJobItem::STATUS_READY)
                    ->where('file_path', $pdf['path'])
                    ->where('file_checksum', $pdf['sha256'])
                    ->exists())
            || str_contains($pdf['path'], '..')
            || str_starts_with($pdf['path'], '/')
            || str_contains($pdf['path'], '\\')
        ) {
            return null;
        }

        return [
            'path' => $pdf['path'],
            'size' => $pdf['size'],
            'sha256' => $pdf['sha256'],
        ];
    }
}
