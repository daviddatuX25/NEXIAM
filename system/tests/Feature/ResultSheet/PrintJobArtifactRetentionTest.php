<?php

use App\Models\Printing\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('retention removes QR-disabled per-page PDFs nested in the print manifest', function (): void {
    Storage::fake('local');
    config(['result-sheet.retention_days' => 30]);

    $job = PrintJob::query()->create([
        'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
        'scope_type' => 'template_version',
        'scope_id' => 1,
        'status' => PrintJob::STATUS_READY,
        'requested_by_user_id' => null,
        'pinned_input_json' => ['qr_disabled_issuance_id' => 1],
        'completed_at' => now()->subDays(31),
    ]);
    $zipPath = 'omr-canonical/print-jobs/'.$job->id.'/qr-disabled-issuance.zip';
    $pagePath = 'omr-canonical/print-jobs/'.$job->id.'/qr-disabled/QDI-'.strtoupper((string) Str::uuid()).'/copy-00001-page-001.pdf';
    Storage::disk('local')->put($zipPath, 'zip-bytes');
    Storage::disk('local')->put($pagePath, 'per-page-pdf-bytes');
    $job->forceFill([
        'file_path' => $zipPath,
        'file_checksum' => hash('sha256', 'zip-bytes'),
        'output_manifest' => [
            'schema' => 'nexiam.omr.qr-disabled-print.v1',
            'copies' => [[
                'copy_no' => 1,
                'pages' => [['path' => $pagePath, 'archive_name' => 'copy-00001/page-001.pdf']],
            ]],
        ],
    ])->save();

    $this->artisan('result-sheet:prune --dry-run')->assertSuccessful();
    expect(Storage::disk('local')->exists($zipPath))->toBeTrue()
        ->and(Storage::disk('local')->exists($pagePath))->toBeTrue();

    $this->artisan('result-sheet:prune')->assertSuccessful();

    expect(Storage::disk('local')->exists($zipPath))->toBeFalse()
        ->and(Storage::disk('local')->exists($pagePath))->toBeFalse()
        ->and($job->fresh()->file_path)->toBeNull()
        ->and($job->fresh()->output_manifest)->toBeNull();
});
