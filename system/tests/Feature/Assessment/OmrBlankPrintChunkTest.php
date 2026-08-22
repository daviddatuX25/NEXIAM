<?php

use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('blank printing produces ordered downloadable ZIP without duplicate retry rows', function (): void {
    Storage::fake('local');
    Bus::fake();
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $actor = User::factory()->create();
    $template = OmrAnswerSheetTemplate::query()->create(['code' => 'BLANK-PRINT', 'name' => 'Blank print']);
    $version = OmrAnswerSheetTemplateVersion::query()->create([
        'template_id' => $template->id, 'version' => 1, 'status' => 'active', 'page_width' => 2551, 'page_height' => 3898,
        'identity_regions_json' => [], 'provider_config_json' => [], 'config_hash' => hash('sha256', 'config'), 'template_json_hash' => hash('sha256', 'template'),
        'native_bundle_version' => 'nexiam.omr.native-bundle.v1', 'native_bundle_json' => ['template' => ['pages' => [['page_no' => 1, 'pageDimensions' => [2551, 3898], 'fieldBlocks' => ['Questions' => []]]]]],
        'native_bundle_hash' => hash('sha256', 'bundle'), 'engine_name' => 'OMRChecker', 'engine_commit' => 'test', 'patch_identity' => 'test',
        'review_manifest_json' => [], 'review_manifest_hash' => hash('sha256', 'review'),
    ]);
    $draft = OmrTemplateAuthoringDraft::query()->create(['code' => 'BLANK-PRINT-DRAFT', 'name' => 'Draft', 'source_kind' => 'image', 'stage' => 'compiled', 'authoring_json' => [], 'lock_version' => 0, 'owner_user_id' => $actor->id, 'compiled_version_id' => $version->id]);
    $pdf = new FPDF('P', 'mm', [216, 330]);
    $pdf->AddPage('P', [216, 330]);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Canonical blank sheet');
    app(OmrTemplateAssetStore::class)->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, (string) $pdf->Output('S'), 'application/pdf');
    $draftAsset = $draft->assets()->firstOrFail();
    app(OmrTemplateAssetStore::class)->pinToVersion($draftAsset, $version);

    $operation = app(PrintOmrBlankInstances::class)->handle($version, $actor, 2, 'print-once');
    $job = PrintJob::query()->latest('id')->firstOrFail();
    (new RenderOmrBlankInstanceBatchJob($job->id, $actor->id))->handle(
        app(\App\Assessment\Omr\Canonical\OmrBlankInstanceService::class),
        app(\App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator::class),
        app(\App\Foundation\Actions\RecordAuditEvent::class),
    );

    $ready = $job->fresh();
    expect($ready->status)->toBe(PrintJob::STATUS_READY)
        ->and($ready->file_path)->toEndWith('.zip')
        ->and(Storage::disk('local')->exists($ready->file_path))->toBeTrue()
        ->and(OmrBlankInstance::query()->count())->toBe(2);

    app(PrintOmrBlankInstances::class)->handle($version, $actor, 2, 'print-once');
    expect(PrintJob::query()->count())->toBe(1);
});
