<?php

namespace App\Ingestion\Services;

use App\Models\Ingestion\ImportBatch;
use App\Models\Ingestion\ImportBatchRow;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ErrorReportGenerator
{
    public function generate(ImportBatch $batch): StreamedResponse
    {
        $filename = "import_errors_batch_{$batch->id}_".date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($batch) {
            $handle = fopen('php://output', 'w');

            // Headers
            fputcsv($handle, ['Sheet', 'Row', 'Reference Code', 'Status', 'Errors', 'Raw Data (JSON)']);

            $batch->rows()
                ->where(function ($query): void {
                    $query->whereIn('status', [ImportBatchRow::STATUS_INVALID, ImportBatchRow::STATUS_FAILED])
                        ->orWhere(function ($query): void {
                            $query->where('status', ImportBatchRow::STATUS_APPLICANT_IMPORTED)
                                ->where('guidance_status', 'failed');
                        });
                })
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, array_map(fn (string $value): string => $this->sanitizeCell($value), [
                            $row->sheet_name,
                            (string) $row->row_index,
                            $row->reference_code ?? '—',
                            $row->status,
                            implode('; ', $row->errors ?? []),
                            json_encode($row->raw_payload, JSON_UNESCAPED_UNICODE) ?: '{}',
                        ]));
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function sanitizeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
