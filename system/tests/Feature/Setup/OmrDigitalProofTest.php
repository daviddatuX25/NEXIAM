<?php

use App\Assessment\Actions\Omr\Canonical\RunOmrDigitalVerification;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('digital proof is draft-scoped, deterministic, and does not fabricate identity', function (): void {
    config(['omr.qr-keyring.current' => 'current-v1', 'omr.qr-keyring.keys' => ['current-v1' => 'test-secret']]);
    $actor = User::factory()->create();
    $draft = app(OmrAuthoringDraftService::class)->create($actor, 'DIGITAL-PROOF', 'Digital proof', 'image');
    $pdf = new FPDF('P', 'mm', [216, 330]);
    $pdf->AddPage('P', [216, 330]);
    $pdf->SetFont('Helvetica', '', 12);
    $pdf->Text(20, 20, 'Digital proof source');
    $pdfAsset = app(OmrTemplateAssetStore::class)->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, (string) $pdf->Output('S'), 'application/pdf');
    $draft->forceFill([
        'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
        'authoring_json' => [
            'pages' => [['page_no' => 1, 'confirmed' => true]],
            'canonical' => ['pdf_asset_id' => $pdfAsset->id, 'settings' => ['paper_width_mm' => 216, 'paper_height_mm' => 330]],
            'mapping' => ['field_blocks' => ['Questions' => ['fieldType' => 'QTYPE_MCQ4', 'fieldLabels' => ['Q_001'], 'bubblesGap' => 80, 'labelsGap' => 80, 'origin' => [400, 900], 'bubbleDimensions' => [50, 50]]]],
        ],
    ])->save();

    $saved = app(RunOmrDigitalVerification::class)->handle($draft->fresh(), $actor);

    expect($saved->stage)->toBe(OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED)
        ->and($saved->authoring_json['verification']['status'])->toBe('passed')
        ->and($saved->authoring_json['verification'])->not->toHaveKey('identity');
});
