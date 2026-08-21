<?php

namespace Database\Seeders;

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use FPDF;
use Illuminate\Database\Seeder;

class OmrCanonicalDemoSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        app(OmrMarkerProfileService::class)->seedCandidates($actor);
        $definitions = [
            ['code' => 'CANONICAL-PRESET', 'name' => 'Standard 150-Item Preset (6 Subjects x 25 Qs)', 'kind' => OmrTemplateAuthoringDraft::SOURCE_PRESET, 'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT],
            ['code' => 'CANONICAL-HTML', 'name' => 'Canonical HTML Exam Template', 'kind' => OmrTemplateAuthoringDraft::SOURCE_HTML, 'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT],
        ];
        foreach ($definitions as $definition) {
            $draft = OmrTemplateAuthoringDraft::query()->updateOrCreate(
                ['code' => $definition['code'], 'owner_user_id' => $actor->id],
                [
                    'name' => $definition['name'],
                    'source_kind' => $definition['kind'],
                    'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT,
                    'authoring_json' => ['demo' => true, 'pages' => [], 'mapping' => null, 'verification' => null],
                    'lock_version' => 0,
                ],
            );
            $this->seedStage($draft, $definition['stage']);
        }
    }

    private function seedStage(OmrTemplateAuthoringDraft $draft, string $stage): void
    {
        if ($stage === OmrTemplateAuthoringDraft::STAGE_DRAFT) {
            return;
        }
        $store = app(OmrTemplateAssetStore::class);
        $intermediate = $draft->assets()->where('kind', OmrTemplateAsset::KIND_INTERMEDIATE_PAGE)->first();
        if ($intermediate === null) {
            $intermediate = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_INTERMEDIATE_PAGE, $this->pagePng(600, 900, $draft->name), 'image/png', 1, [
                'width' => 600, 'height' => 900, 'width_mm' => 216, 'height_mm' => 330, 'source_dpi' => 300,
            ]);
        }
        $authoring = [
            'demo' => true,
            'source' => ['sha256' => $intermediate->sha256, 'converter_version' => 'canonical-demo-v1', 'settings_hash' => hash('sha256', 'canonical-demo')],
            'pages' => [[
                'page_no' => 1, 'asset_id' => $intermediate->id, 'sha256' => $intermediate->sha256,
                'width' => 600, 'height' => 900, 'width_mm' => 216, 'height_mm' => 330,
                'orientation' => 'portrait', 'confirmed' => true, 'rejection_reason' => null,
            ]],
            'mapping' => null,
            'verification' => null,
        ];

        $studioPayload = [
            'current_stage' => 1,
            'marginMm' => 15,
            'markerPattern' => 'square',
            'bubbleRadiusMm' => 4.5,
            'showHeaderOverlay' => false,
            'headerTitle' => 'University General Aptitude Test',
            'headerSubtitle' => 'Standard 150-Item Answer Sheet',
            'headerFontSize' => 14.0,
            'headerFontFamily' => 'Inter, sans-serif',
            'headerColor' => '#0f172a',
            'headerAlign' => 'left',
            'headerX' => 25, 'headerY' => 20, 'headerW' => 160, 'headerH' => 15, 'headerLocked' => false,
            'showQrOverlay' => true,
            'qrX' => 162, 'qrY' => 18, 'qrW' => 26, 'qrH' => 26, 'qrLocked' => false,
            'showInstructionsOverlay' => false,
            'instructionsContent' => 'Use 2B pencil only. Shade bubbles completely. Erase cleanly to change.',
            'instructionsFontSize' => 9.0,
            'instructionsFontFamily' => 'Inter, sans-serif',
            'instructionsColor' => '#334155',
            'instX' => 25, 'instY' => 250, 'instW' => 90, 'instH' => 28, 'instLocked' => false,
            'showSignatureOverlay' => false,
            'signatureContent' => 'EXAMINEE SIGNATURE',
            'signatureFontSize' => 8.5,
            'signatureFontFamily' => 'Inter, sans-serif',
            'signatureColor' => '#475569',
            'sigX' => 125, 'sigY' => 250, 'sigW' => 60, 'sigH' => 28, 'sigLocked' => false,
            'showMetadataOverlay' => false,
            'metadataContent' => 'ROOM / SEAT NO.',
            'metadataFontSize' => 9.0,
            'showRollOverlay' => false,
            'rollDigits' => 6,
            'customTextItems' => [],
            'stage1Mode' => 'html',
            'baseType' => 'html',
            'sections' => [
                ['id' => 'Examinee_ID', 'name' => 'Examinee Number (7 Digits)', 'type' => 'roll', 'posX' => 125, 'posY' => 22, 'w_mm' => 58, 'h_mm' => 54, 'questionsCount' => 7, 'cols' => 7, 'optionsCount' => 10, 'startQ' => 1, 'customValuesStr' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9', 'bubblePitchMm' => 5.5, 'rowPitchMm' => 4.8, 'isLocked' => false],
                ['id' => 'Part_1', 'name' => 'Part 1: English (Q1–25)', 'type' => 'mcq', 'posX' => 10, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 1, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 5.2, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_2', 'name' => 'Part 2: Math (Q26–50)', 'type' => 'mcq', 'posX' => 42, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 26, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 5.2, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_3', 'name' => 'Part 3: Science (Q51–75)', 'type' => 'mcq', 'posX' => 74, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 51, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 5.2, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_4', 'name' => 'Part 4: Abstract (Q76–100)', 'type' => 'mcq', 'posX' => 106, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 76, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 5.2, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_5', 'name' => 'Part 5: Gen Info (Q101–125)', 'type' => 'mcq', 'posX' => 138, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 101, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 5.2, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_6', 'name' => 'Part 6: Logic (Q126–150)', 'type' => 'mcq', 'posX' => 170, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 126, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 5.2, 'rowPitchMm' => 6.8, 'isLocked' => false],
            ],
        ];
        $authoring['studio'] = $studioPayload;

        if (in_array($stage, [OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED, OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE], true)) {
            $raster = $draft->assets()->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)->first();
            if ($raster === null) {
                $raster = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_RASTER_PAGE, $this->pagePng(1200, 1700, $draft->name), 'image/png', 1, [
                    'paper_width_mm' => 210, 'paper_height_mm' => 297, 'ppi' => 300,
                ]);
            }
            $pdf = $draft->assets()->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)->first();
            if ($pdf === null) {
                $pdf = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_PRINT_PDF, $this->pagePdf($draft->name), 'application/pdf', null, [
                    'actual_size_instruction' => 'Print at 100% / actual size — do not fit to page.',
                ]);
            }
            $authoring['canonical'] = [
                'settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297],
                'raster_assets' => [['page_no' => 1, 'asset_id' => $raster->id, 'sha256' => $raster->sha256]],
                'pdf_asset_id' => $pdf->id,
                'composition_hash' => hash('sha256', $raster->sha256.$pdf->sha256),
                'actual_size_instruction' => 'Print at 100% / actual size — do not fit to page.',
            ];
        }
        $draft->forceFill(['stage' => $stage, 'authoring_json' => $authoring])->save();
    }

    private function pagePng(int $width, int $height, string $title): string
    {
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $ink = imagecolorallocate($image, 15, 23, 42);
        imagefill($image, 0, 0, $white);
        imagestring($image, 5, 30, 30, $title, $ink);
        imagerectangle($image, 25, 70, $width - 25, $height - 25, $ink);
        ob_start();
        imagepng($image, null, 6);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function pagePdf(string $title): string
    {
        $pdf = new FPDF('P', 'mm', [216, 330]);
        $pdf->AddPage('P', [216, 330]);
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(15, 20, $title);

        return (string) $pdf->Output('S');
    }
}
