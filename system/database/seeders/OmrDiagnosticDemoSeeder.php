<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Assessment\Actions\Omr\ActivateOmrConfiguration;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\PrintOmrBlankInstances;
use App\Assessment\Actions\Omr\CreateOmrCaptureBatch;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredTemplateDraft;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Actions\Omr\Verification\StartOmrTemplateVerification;
use App\Assessment\Omr\Canonical\CanonicalArtifactMaterializer;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator;
use App\Assessment\Omr\Canonical\OmrCalibrationQrPayload;
use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Assessment\Omr\Canonical\OmrNoGoMatrixRunner;
use App\Assessment\Omr\Canonical\OmrPdfPageRasterizer;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\Canonical\OmrTemplateAssetStore;
use App\Assessment\Omr\Diagnostics\CandidateArtifactDiagnosticTarget;
use App\Assessment\Omr\Diagnostics\OmrProofPlanner;
use App\Assessment\Omr\Diagnostics\ProofPurpose;
use App\Assessment\Omr\NativeBundle;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrLayoutVerificationPort;
use App\Assessment\Omr\OmrLayoutVerificationResult;
use App\Assessment\Omr\OmrOptionObservation;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Assessment\Omr\Queries\OmrReviewGeometryQuery;
use App\Assessment\Omr\Review\OmrReviewDecision;
use App\Assessment\Omr\Verification\OmrPhysicalAssuranceCoordinator;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Jobs\Assessment\RunOmrTemplateVerificationJob;
use App\Jobs\Assessment\RunOmrTemplateVerificationVariantJob;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetIssue;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrPaperReproductionAuthorization;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Assessment\OmrPhysicalChallenge;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationMethod;
use App\Models\Assessment\OmrTemplateVerificationMethodPage;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\Assessment\ScoreRecord;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Printing\PrintJob;
use App\Models\User;
use App\Printing\BundleRenderer;
use App\Printing\PrintRenderer;
use App\Support\SensitiveAssetStore;
use FPDF;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OmrDiagnosticDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        if (! $institution->usesSeatingArrangements() || ! $institution->usesMultiSetBooklets()) {
            $this->command?->info('Advanced OMR diagnostics skipped; enable assigned seating and alternate Booklet Sets explicitly.');

            return;
        }
        ini_set('memory_limit', '512M');
        $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        app(OmrMarkerProfileService::class)->seedCandidates($actor);
        $definitions = [
            ['code' => 'CANONICAL-HTML', 'name' => 'Canonical HTML Exam Template', 'kind' => OmrTemplateAuthoringDraft::SOURCE_HTML, 'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED],
            ['code' => 'CANONICAL-PRESET', 'name' => 'Standard 150-Item Preset (6 Subjects x 25 Qs)', 'kind' => OmrTemplateAuthoringDraft::SOURCE_PRESET, 'stage' => OmrTemplateAuthoringDraft::STAGE_DRAFT],
        ];
        foreach ($definitions as $definition) {
            $draft = OmrTemplateAuthoringDraft::query()->firstOrNew([
                'code' => $definition['code'],
                'owner_user_id' => $actor->id,
            ]);
            if (! $draft->exists) {
                $draft->forceFill([
                    'name' => $definition['name'],
                    'source_kind' => $definition['kind'],
                    'stage' => $definition['stage'],
                    'authoring_json' => ['demo' => true, 'pages' => [], 'mapping' => null, 'verification' => null],
                    'lock_version' => 0,
                ])->save();
            }
            $draft = $draft->fresh();
            if (! $this->draftHasImmutableVerificationEvidence($draft)) {
                $this->seedStage($draft, $definition['stage']);
            }
            if ($definition['code'] === 'CANONICAL-HTML') {
                $this->seedVerificationChallenge($draft->fresh(), $actor);
            }
        }
        $this->seedScannerPrerequisites($actor);

        $this->seedOperationalWorkbenchStates($actor);

    }

    private function draftHasImmutableVerificationEvidence(OmrTemplateAuthoringDraft $draft): bool
    {
        $runIds = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->pluck('id');

        return $runIds->isNotEmpty()
            && DB::table('omr_template_verification_captures')->whereIn('run_id', $runIds)->exists();
    }

    private function hasCanonicalStudioGeometry(OmrTemplateAuthoringDraft $draft): bool
    {
        $authoring = (array) $draft->authoring_json;
        $studio = (array) ($authoring['studio'] ?? []);
        $sections = (array) ($studio['sections'] ?? []);
        $actual = [];
        foreach ($sections as $section) {
            if (is_array($section) && isset($section['id'])) {
                $actual[(string) $section['id']] = $section;
            }
        }

        $expected = [
            'Examinee_ID' => ['type' => 'roll', 'posX' => 110, 'posY' => 24, 'w_mm' => 58, 'h_mm' => 52, 'questionsCount' => 7, 'optionsCount' => 10, 'bubblePitchMm' => 5.5, 'rowPitchMm' => 4.8],
            'Part_1' => ['type' => 'mcq', 'posX' => 15, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'optionsCount' => 4, 'startQ' => 1, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8],
            'Part_2' => ['type' => 'mcq', 'posX' => 45, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'optionsCount' => 4, 'startQ' => 26, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8],
            'Part_3' => ['type' => 'mcq', 'posX' => 76, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'optionsCount' => 4, 'startQ' => 51, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8],
            'Part_4' => ['type' => 'mcq', 'posX' => 106, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'optionsCount' => 4, 'startQ' => 76, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8],
            'Part_5' => ['type' => 'mcq', 'posX' => 137, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'optionsCount' => 4, 'startQ' => 101, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8],
            'Part_6' => ['type' => 'mcq', 'posX' => 167, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'optionsCount' => 4, 'startQ' => 126, 'bubblePitchMm' => 6, 'rowPitchMm' => 6.8],
        ];
        if (count($actual) !== count($expected)) {
            return false;
        }

        foreach ($expected as $id => $geometry) {
            $section = $actual[$id] ?? null;
            if (! is_array($section)) {
                return false;
            }
            foreach ($geometry as $key => $value) {
                if (! array_key_exists($key, $section)
                    || (is_numeric($value) && (float) $section[$key] !== (float) $value)
                    || (! is_numeric($value) && (string) $section[$key] !== (string) $value)) {
                    return false;
                }
            }
        }

        return true;
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
            'mapping' => [
                // Keep the imported source mapping on a validated physical
                // bubble contract; the editable sections below still provide
                // the full A4 preset canvas for the studio.
                'input' => ['page_width' => 1200, 'page_height' => 1700, 'page_count' => 1],
                'field_blocks' => ['Part_1' => [
                    'fieldType' => 'QTYPE_MCQ4', 'direction' => 'horizontal',
                    'fieldLabels' => ['LOGIC_Q_1'], 'bubblesGap' => 72, 'labelsGap' => 81,
                    'origin' => [180, 420], 'bubbleDimensions' => [53, 53],
                ]],
            ],
            'verification' => $draft->authoring_json['verification'] ?? null,
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
                ['id' => 'Examinee_ID', 'name' => 'Examinee Number (7 Digits)', 'type' => 'roll', 'posX' => 110, 'posY' => 24, 'w_mm' => 58, 'h_mm' => 52, 'questionsCount' => 7, 'cols' => 7, 'optionsCount' => 10, 'startQ' => 1, 'customValuesStr' => '0, 1, 2, 3, 4, 5, 6, 7, 8, 9', 'bubblePitchMm' => 5.5, 'rowPitchMm' => 4.8, 'isLocked' => false],
                ['id' => 'Part_1', 'name' => 'Part 1: English (Q1–25)', 'type' => 'mcq', 'posX' => 15, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 1, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 6.0, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_2', 'name' => 'Part 2: Math (Q26–50)', 'type' => 'mcq', 'posX' => 45, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 26, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 6.0, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_3', 'name' => 'Part 3: Science (Q51–75)', 'type' => 'mcq', 'posX' => 76, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 51, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 6.0, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_4', 'name' => 'Part 4: Abstract (Q76–100)', 'type' => 'mcq', 'posX' => 106, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 76, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 6.0, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_5', 'name' => 'Part 5: Gen Info (Q101–125)', 'type' => 'mcq', 'posX' => 137, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 101, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 6.0, 'rowPitchMm' => 6.8, 'isLocked' => false],
                ['id' => 'Part_6', 'name' => 'Part 6: Logic (Q126–150)', 'type' => 'mcq', 'posX' => 167, 'posY' => 88, 'w_mm' => 28, 'h_mm' => 175, 'questionsCount' => 25, 'cols' => 1, 'optionsCount' => 4, 'startQ' => 126, 'customValuesStr' => 'A, B, C, D', 'bubblePitchMm' => 6.0, 'rowPitchMm' => 6.8, 'isLocked' => false],
            ],
        ];
        $authoring['studio'] = $studioPayload;

        if (in_array($stage, [OmrTemplateAuthoringDraft::STAGE_PAGE_PREPARED, OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE, OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED], true)) {
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
            $canonical = (array) ($authoring['canonical'] ?? []);
            $canonical['settings'] = ['paper_width_mm' => 210, 'paper_height_mm' => 297];
            $canonical['raster_assets'] = [['page_no' => 1, 'asset_id' => $raster->id, 'sha256' => $raster->sha256]];
            $canonical['pdf_asset_id'] = $pdf->id;
            $canonical['composition_hash'] = hash('sha256', $raster->sha256.$pdf->sha256);
            $canonical['actual_size_instruction'] = 'Print at 100% / actual size — do not fit to page.';
            $authoring['canonical'] = $canonical;
        }
        $draft->forceFill(['stage' => $stage, 'authoring_json' => $authoring])->save();
    }

    private function seedVerificationChallenge(OmrTemplateAuthoringDraft $draft, User $actor): void
    {
        $profile = OmrMarkerProfile::query()
            ->where('code', 'omrchecker.sample')
            ->where('version', 1)
            ->firstOrFail();
        if ((int) $draft->marker_profile_id !== (int) $profile->id) {
            $draft->forceFill(['marker_profile_id' => $profile->id])->save();
        }
        $draft = $draft->fresh(['assets', 'markerProfile', 'template']);
        $draft = $this->resolveRehearsalTarget($draft, $actor);
        $draft = $draft->fresh(['assets', 'markerProfile', 'template']);
        if ((int) $draft->marker_profile_id !== (int) $profile->id) {
            $draft->forceFill(['marker_profile_id' => $profile->id])->save();
            $draft = $draft->fresh(['assets', 'markerProfile', 'template']);
        }

        $digitalRun = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
            ->where('attempt', 1)
            ->latest('id')
            ->first();
        if (! $this->digitalRunIsProduction($draft, $digitalRun)) {
            $this->resetVerificationRuns($draft);
            $draft = $draft->fresh(['assets', 'markerProfile', 'template']);
            $authoring = (array) $draft->authoring_json;
            $canonical = (array) ($authoring['canonical'] ?? []);
            $artifactSet = (array) ($canonical['artifact_set'] ?? []);
            // Rebuild candidate artifacts from repaired visual sections instead
            // of pinning the next run to rejected geometry.
            $authoring['canonical']['artifact_set'] = null;
            $authoring['canonical']['artifact_hash'] = null;
            $authoring['canonical']['raster_assets'] = [];
            $authoring['canonical']['pdf_asset_id'] = null;
            $authoring['verification'] = null;
            $draft->forceFill([
                'stage' => OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE,
                'authoring_json' => $authoring,
            ])->save();
            $draft = app(MaterializeOmrCanonicalDraft::class)->handle(
                $draft->fresh(['markerProfile', 'template', 'assets']),
                $actor,
                (int) $draft->lock_version,
            );
            $this->runDigitalVerification($draft->fresh(['markerProfile', 'template', 'assets']), $actor);
        }
        $digitalRun = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
            ->where('attempt', 1)
            ->latest('id')
            ->first();

        $draft = $draft->fresh(['assets', 'markerProfile', 'template']);
        $draft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED])->save();
        $draft = $draft->fresh(['assets', 'markerProfile', 'template']);
        $quickRun = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
            ->where('attempt', 1)
            ->latest('id')
            ->first();
        $challenge = $quickRun === null
            ? null
            : OmrPhysicalChallenge::query()->where('run_id', $quickRun->id)->where('attempt', 1)->first();

        if (! $this->quickChallengeIsProduction($quickRun, $challenge)) {
            $this->resetVerificationRuns($draft, [
                OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
                OmrTemplateVerificationRun::MODE_FULL_QUALIFICATION,
            ]);
            $created = app(CreateOmrPhysicalChallenge::class)->handle(
                $draft->fresh(['assets', 'markerProfile', 'template']),
                $actor,
                OmrPhysicalChallenge::MODE_QUICK,
            );
            $quickRun = $created['run'];
            $challenge = $created['challenge'];
        }

        // Keep both method cards clickable in the rehearsal baseline. The
        // second method reuses the same durable rehearsal run but has its own
        // immutable plan, sheet, and shading guide.
        $handChallenge = OmrPhysicalChallenge::query()
            ->where('run_id', $quickRun?->id)
            ->where('method', OmrPhysicalChallenge::METHOD_HAND_SHADED)
            ->where('status', '!=', OmrPhysicalChallenge::STATUS_SUPERSEDED)
            ->latest('id')
            ->first();
        if ($handChallenge === null && $quickRun !== null) {
            $createdHand = app(CreateOmrPhysicalChallenge::class)->handle(
                $draft->fresh(['assets', 'markerProfile', 'template']),
                $actor,
                OmrPhysicalChallenge::MODE_QUICK,
                OmrPhysicalChallenge::METHOD_HAND_SHADED,
            );
            $handChallenge = $createdHand['challenge'];
            $quickRun = $createdHand['run'];
        }

        // The browser-ready rehearsal baseline has both method cards in a
        // resumable capture state, with immutable Run 31-style history.
        $quickRun = $quickRun?->fresh();
        if ($quickRun !== null && $challenge !== null) {
            $quickRun = app(OmrPhysicalAssuranceCoordinator::class)->markPrinted(
                $quickRun,
                $actor,
                (string) ($challenge->method ?: OmrPhysicalChallenge::METHOD_PRE_SHADED),
                (int) $challenge->id,
            );
        }
        if ($quickRun !== null && $handChallenge !== null) {
            $quickRun = app(OmrPhysicalAssuranceCoordinator::class)->markPrinted(
                $quickRun,
                $actor,
                (string) ($handChallenge->method ?: OmrPhysicalChallenge::METHOD_HAND_SHADED),
                (int) $handChallenge->id,
            );
        }
        if ($quickRun !== null) {
            $this->seedPhysicalAttemptHistory($draft->fresh(['assets', 'markerProfile', 'template']), $actor, $quickRun->fresh());
            $draft->forceFill(['stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED])->save();
        }
        $this->command?->info(sprintf(
            'OMR canonical quick rehearsal ready: draft #%d; digital run #%d; quick run #%d; challenge %s; plan %s; sheet asset #%d/%s; guide asset #%d/%s.',
            $draft->id,
            $digitalRun?->id ?? (int) ($digitalRun?->id ?? 0),
            $quickRun?->id ?? 0,
            $challenge?->challenge_code ?? '—',
            $quickRun?->plan_hash ?? '—',
            $challenge?->sheet_asset_id ?? 0,
            $challenge?->sheetAsset?->sha256 ?? '—',
            $challenge?->guide_asset_id ?? 0,
            $challenge?->guideAsset?->sha256 ?? '—',
        ));
    }

    private function resolveRehearsalTarget(OmrTemplateAuthoringDraft $draft, User $actor): OmrTemplateAuthoringDraft
    {
        $digitalRun = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
            ->where('attempt', 1)
            ->latest('id')
            ->first();
        $quickRun = OmrTemplateVerificationRun::query()
            ->where('draft_id', $draft->id)
            ->where('mode', OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
            ->where('attempt', 1)
            ->latest('id')
            ->first();
        $challenge = $quickRun === null ? null : OmrPhysicalChallenge::query()
            ->where('run_id', $quickRun->id)
            ->where('method', OmrTemplateVerificationMethod::METHOD_PRE_SHADED)
            ->latest('id')
            ->first();
        if ($quickRun === null || ($this->digitalRunIsProduction($draft, $digitalRun)
            && $this->physicalRehearsalIsProduction($quickRun, $challenge))) {
            if (($digitalRun === null || ! $digitalRun->isActive())
                && $this->hasCanonicalStudioGeometry($draft)) {
                return $draft;
            }
        }

        foreach (OmrTemplateAuthoringDraft::query()
            ->where('owner_user_id', $actor->id)
            ->where('code', 'like', 'CANONICAL-HTML-REHEARSAL%')
            ->orderByDesc('id')
            ->get() as $candidate) {
            $candidate = $candidate->fresh(['assets', 'markerProfile', 'template']);
            $candidateDigital = OmrTemplateVerificationRun::query()
                ->where('draft_id', $candidate->id)->where('mode', OmrTemplateVerificationRun::MODE_DIGITAL)
                ->where('attempt', 1)->latest('id')->first();
            $candidateQuick = OmrTemplateVerificationRun::query()
                ->where('draft_id', $candidate->id)->where('mode', OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL)
                ->where('attempt', 1)->latest('id')->first();
            $candidateChallenge = $candidateQuick === null ? null : OmrPhysicalChallenge::query()
                ->where('run_id', $candidateQuick->id)->where('method', OmrTemplateVerificationMethod::METHOD_PRE_SHADED)
                ->latest('id')->first();
            if ($this->hasCanonicalStudioGeometry($candidate)
                && $this->digitalRunIsProduction($candidate, $candidateDigital)
                && $this->physicalRehearsalIsProduction($candidateQuick, $candidateChallenge)) {
                return $candidate;
            }
        }

        $baseCode = 'CANONICAL-HTML-REHEARSAL';
        $suffix = 0;
        do {
            $code = $suffix === 0 ? $baseCode : $baseCode.'-'.$suffix;
            $suffix++;
        } while (OmrTemplateAuthoringDraft::query()
            ->where('owner_user_id', $actor->id)->where('code', $code)->exists());
        $clean = OmrTemplateAuthoringDraft::query()->create([
            'code' => $code,
            'name' => 'Canonical HTML Exam Template (Clean Rehearsal)',
            'source_kind' => OmrTemplateAuthoringDraft::SOURCE_HTML,
            'registration_mode' => OmrRegistrationMode::FiducialAnchored,
            'qr_mode' => OmrQrMode::Signed,
            'requested_page_count' => 1,
            'stage' => OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED,
            'authoring_json' => ['demo' => true, 'pages' => [], 'mapping' => null, 'verification' => null],
            'lock_version' => 0,
            'owner_user_id' => $actor->id,
        ]);
        $this->seedStage($clean, OmrTemplateAuthoringDraft::STAGE_DIGITAL_VERIFIED);

        return $clean->fresh(['assets', 'markerProfile', 'template']);
    }

    private function physicalRehearsalIsProduction(
        ?OmrTemplateVerificationRun $run,
        ?OmrPhysicalChallenge $challenge,
    ): bool {
        if (! $this->quickChallengeIsProduction($run, $challenge)) {
            return false;
        }
        $methods = OmrTemplateVerificationMethod::query()->where('run_id', $run->id)->with('pages')->get()->keyBy('method');
        if ($methods->count() !== 2) {
            return false;
        }
        foreach ([OmrTemplateVerificationMethod::METHOD_PRE_SHADED, OmrTemplateVerificationMethod::METHOD_HAND_SHADED] as $methodName) {
            $method = $methods->get($methodName);
            if (! $method instanceof OmrTemplateVerificationMethod) {
                return false;
            }
            $pages = $method->pages->where('required', true);
            if ($pages->isEmpty()) {
                return false;
            }
            foreach ($pages as $page) {
                $captures = OmrTemplateVerificationCapture::query()->where('method_id', $method->id)->where('page_no', $page->page_no)->get();
                if (! $captures->contains(fn (OmrTemplateVerificationCapture $capture): bool => $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED)
                    || ! $captures->contains(fn (OmrTemplateVerificationCapture $capture): bool => $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_PASSED)) {
                    return false;
                }
                $selectedId = (int) ($page->selected_attempt_id ?? 0);
                $selected = $selectedId > 0 ? $captures->firstWhere('id', $selectedId) : null;
                if (! $selected instanceof OmrTemplateVerificationCapture
                    || $selected->attempt_state !== OmrTemplateVerificationCapture::ATTEMPT_PASSED
                    || DB::table('omr_template_verification_item_evidence')->where('capture_id', $selected->id)->count() !== 150) {
                    return false;
                }
            }
        }

        return true;
    }

    private function digitalRunIsProduction(OmrTemplateAuthoringDraft $draft, ?OmrTemplateVerificationRun $run): bool
    {
        if ($run === null
            || $run->mode !== OmrTemplateVerificationRun::MODE_DIGITAL
            || $run->status !== OmrTemplateVerificationRun::STATUS_PASSED) {
            return false;
        }
        $verification = (array) (((array) $draft->authoring_json)['verification'] ?? []);
        if (($verification['seeded_demo'] ?? false) === true) {
            return false;
        }

        $captures = OmrTemplateVerificationCapture::query()->where('run_id', $run->id)->get();

        return $captures->count() === count(OmrNoGoMatrixRunner::VARIANTS)
            && $captures->every(
                static fn (OmrTemplateVerificationCapture $capture): bool => $capture->status === OmrTemplateVerificationCapture::STATUS_PASS,
            );
    }

    private function quickChallengeIsProduction(
        ?OmrTemplateVerificationRun $run,
        ?OmrPhysicalChallenge $challenge,
    ): bool {
        if ($run === null || $challenge === null
            || $run->mode !== OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL
            || ! in_array($run->status, [
                OmrTemplateVerificationRun::STATUS_AWAITING_PRINT,
                OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
                OmrTemplateVerificationRun::STATUS_PASSED,
            ], true)
            || $challenge->mode !== OmrPhysicalChallenge::MODE_QUICK
            || ! in_array($challenge->status, [
                OmrPhysicalChallenge::STATUS_CREATED,
                OmrPhysicalChallenge::STATUS_PRINTED,
                OmrPhysicalChallenge::STATUS_AWAITING_CAPTURE,
                OmrPhysicalChallenge::STATUS_PASSED,
            ], true)
            || (int) $challenge->run_id !== (int) $run->id
            || (string) $challenge->expected_vector_hash !== (string) $run->expected_vector_hash) {
            return false;
        }
        $sheet = $challenge->sheetAsset;
        $guide = $challenge->guideAsset;
        $sheetMetadata = (array) ($sheet?->metadata ?? []);
        $guideMetadata = (array) ($guide?->metadata ?? []);
        if ($sheet === null
            || $guide === null
            || ($sheetMetadata['role'] ?? null) !== 'challenge_sheet'
            || ! is_string($sheetMetadata['sheet_hash'] ?? null)
            || ($guideMetadata['role'] ?? null) !== 'challenge_guide_pdf'
            || ($guideMetadata['guide_mode'] ?? null) !== 'quick') {
            return false;
        }

        $method = OmrTemplateVerificationMethod::query()
            ->where('run_id', $run->id)
            ->where('method', (string) $challenge->method)
            ->first();
        if ($method === null || (int) $method->required_page_count < 1) {
            return false;
        }
        $pageCount = (int) $method->required_page_count;
        try {
            $payload = app(OmrCalibrationQrPayload::class)->sign(
                (string) $challenge->challenge_code,
                1,
                $pageCount,
            );
        } catch (\Throwable) {
            return false;
        }

        return hash_equals((string) $challenge->qr_payload_hash, hash('sha256', $payload));
    }

    private function runDigitalVerification(OmrTemplateAuthoringDraft $draft, User $actor): void
    {
        // Feature/demo seeds must be reproducible without depending on the
        // currently configured external worker; the same fixture fakes are used
        // by the focused seeder test and still traverse the full verification
        // jobs, gates, and evidence persistence.
        $useDeterministicFixture = ! app()->environment('production');
        $originalDetector = null;
        $originalLayout = null;
        if ($useDeterministicFixture) {
            if (config('omr.qr-keyring.keys', []) === []) {
                config([
                    'omr.qr-keyring.current' => 'dev-v1',
                    'omr.qr-keyring.keys' => ['dev-v1' => 'canonical-seeder-test-key'],
                ]);
            }
            $profile = $draft->markerProfile ?? $draft->markerProfile()->firstOrFail();
            $materialized = app(CanonicalArtifactMaterializer::class)->materialize($draft, $profile);
            $target = new CandidateArtifactDiagnosticTarget($draft, $materialized, $profile);
            $plan = app(OmrProofPlanner::class)->plan($target, ProofPurpose::Digital);
            $expectedVector = $plan->expectedVectorForPage(1);
            $originalDetector = app(OmrDetectionPort::class);
            $originalLayout = app(OmrLayoutVerificationPort::class);
            app()->instance(OmrDetectionPort::class, $this->testingDetector($expectedVector));
            app()->instance(OmrLayoutVerificationPort::class, $this->testingLayout());
        }
        try {
            $run = app(StartOmrTemplateVerification::class)->handle($draft->fresh(), $actor);
            app()->call([new RunOmrTemplateVerificationJob($run->id), 'handle']);
            $run = $run->fresh();
            while ($run !== null && ! $run->isTerminal()) {
                $phase = (array) $run->phase_detail;
                $variant = $phase['current_variant'] ?? null;
                if (! is_string($variant) || $variant === '') {
                    break;
                }
                app()->call([new RunOmrTemplateVerificationVariantJob($run->id, (int) $run->attempt, $variant, (int) $run->optimistic_version), 'handle']);
                $run = $run->fresh();
            }
            $finished = $run?->fresh();
            if ($finished === null || $finished->status !== OmrTemplateVerificationRun::STATUS_PASSED) {
                throw new RuntimeException('canonical_digital_verification_failed: status='.($finished?->status ?? 'null').', code='.($finished?->error_code ?? 'none').', phase='.json_encode($finished?->phase_detail).', summary='.json_encode($finished?->result_summary));
            }
        } finally {
            if ($useDeterministicFixture) {
                app()->instance(OmrDetectionPort::class, $originalDetector);
                app()->instance(OmrLayoutVerificationPort::class, $originalLayout);
            }
        }
    }

    private function testingDetector(array $expectedVector = []): OmrDetectionPort
    {
        return new class($expectedVector) implements OmrDetectionPort
        {
            public function __construct(private readonly array $expectedVector) {}

            public function detect(OmrDetectionRequest $request): OmrDetectionResult
            {
                $items = [];
                foreach ($request->items as $geometry) {
                    $selected = $this->expectedVector[$geometry->nativeLabel] ?? ($geometry->options[0]->code ?? null);
                    $items[] = new OmrDetectedItem(
                        position: $geometry->position,
                        areaCode: $geometry->areaCode,
                        questionNo: $geometry->questionNo,
                        selectedOption: $selected,
                        state: OmrDetectedItem::STATE_MARKED,
                        confidence: null,
                        region: $geometry->region,
                        observedOptions: $selected === null ? [] : [$selected],
                        optionObservations: array_map(
                            static fn ($option): OmrOptionObservation => new OmrOptionObservation(
                                code: $option->code,
                                mean: null,
                                classification: $option->code === $selected ? 'observed' : 'not_observed',
                            ),
                            $geometry->options,
                        ),
                        issueCodes: [],
                    );
                }

                return OmrDetectionResult::succeeded(new OmrDetectionSuccess(
                    engineName: $request->engineName,
                    engineVersion: 'canonical-seeder-test',
                    engineCommit: $request->engineCommit,
                    inputHash: $request->inputHash,
                    configHash: $request->configHash,
                    applicantNumberProposal: null,
                    applicantNumberConfidence: null,
                    identityFields: [],
                    items: $items,
                    issues: [],
                    sheetConfidence: null,
                    responseEnvelopeVersion: $request->envelopeVersion,
                    runId: $request->runId,
                    correlationId: $request->correlationId,
                    bookletHash: $request->bookletHash,
                    nativeBundleHash: $request->nativeBundleHash,
                    manifestHash: $request->manifestHash,
                    patchIdentity: $request->patchIdentity,
                    calibrationStatus: 'unqualified',
                ));
            }
        };
    }

    private function testingLayout(): OmrLayoutVerificationPort
    {
        return new class implements OmrLayoutVerificationPort
        {
            private int $calls = 0;

            public function verifyLayout(OmrDetectionRequest $request): OmrLayoutVerificationResult
            {
                $this->calls++;
                if ($this->calls > 7) {
                    return OmrLayoutVerificationResult::failed('canonical_seeder_fixture_rejection', 'Synthetic transport rejection for the declared no-go variant.');
                }
                $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
                $hash = hash('sha256', $bytes);

                return new OmrLayoutVerificationResult(
                    outcome: OmrLayoutVerificationResult::OUTCOME_SUCCEEDED,
                    alignedBytes: $bytes,
                    alignedImage: ['mime' => 'image/png', 'width' => 1, 'height' => 1, 'hash' => $hash],
                    overlayBytes: $bytes,
                    overlayImage: ['mime' => 'image/png', 'width' => 1, 'height' => 1, 'hash' => $hash],
                    markerMatches: ['quarter_1' => 0.9, 'quarter_2' => 0.9, 'quarter_3' => 0.9, 'quarter_4' => 0.9],
                    fieldBlockShifts: [],
                    bubbleMeans: [],
                    gates: ['marker_count' => 'pass', 'registration' => 'pass', 'dimensions' => 'pass', 'shifts' => 'pass'],
                    preprocessMs: 1.0,
                    verificationMs: 1.0,
                );
            }
        };
    }

    private function seedPhysicalAttemptHistory(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        OmrTemplateVerificationRun $run,
    ): void {
        $run = $run->fresh(['methods.challenge', 'methods.pages']);
        if ($run === null) {
            return;
        }

        $store = app(OmrTemplateAssetStore::class);
        foreach ([OmrTemplateVerificationMethod::METHOD_PRE_SHADED, OmrTemplateVerificationMethod::METHOD_HAND_SHADED] as $methodName) {
            $method = $run->methods->firstWhere('method', $methodName);
            if (! $method instanceof OmrTemplateVerificationMethod || $method->challenge === null) {
                continue;
            }

            $captures = OmrTemplateVerificationCapture::query()
                ->where('method_id', $method->id)
                ->orderBy('attempt_no')
                ->get();
            $hasFailed = $captures->contains(fn (OmrTemplateVerificationCapture $capture): bool => $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_FAILED);
            $hasPassed = $captures->contains(fn (OmrTemplateVerificationCapture $capture): bool => $capture->attempt_state === OmrTemplateVerificationCapture::ATTEMPT_PASSED);
            $hasSelected = $method->pages()->whereNotNull('selected_attempt_id')->exists();
            if ($hasFailed && $hasPassed && $hasSelected) {
                continue;
            }
            // Never append synthetic rows to an operator's existing physical
            // history; the fixture only fills an untouched demo method.
            if ($captures->isNotEmpty()) {
                continue;
            }

            foreach ($method->pages()->where('required', true)->orderBy('page_no')->get() as $page) {
                $this->seedPhysicalPageHistory($draft, $actor, $run, $method, $method->challenge, $page, $store);
            }
        }
    }

    private function seedPhysicalPageHistory(
        OmrTemplateAuthoringDraft $draft,
        User $actor,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        OmrPhysicalChallenge $challenge,
        OmrTemplateVerificationMethodPage $page,
        OmrTemplateAssetStore $store,
    ): void {
        $pageNo = (int) $page->page_no;
        $medium = $method->method === OmrTemplateVerificationMethod::METHOD_HAND_SHADED
            ? OmrTemplateVerificationCapture::MEDIUM_HB_2_PENCIL
            : null;
        $challengeHash = hash('sha256', json_encode([
            'challenge_id' => $challenge->id,
            'challenge_code' => $challenge->challenge_code,
            'plan_hash' => $challenge->plan_hash,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $failedBytes = 'nexiam-seeded-run31-landscape|'.$method->method.'|'.$pageNo;
        $failedAsset = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_EVIDENCE, $failedBytes, 'image/png', $pageNo, [
            'run_id' => $run->id,
            'method' => $method->method,
            'attempt_no' => 1,
            'role' => 'capture_input',
            'orientation' => 'landscape',
            'seeded_demo' => true,
        ]);

        $this->transitionSeedMethod($method, OmrTemplateVerificationMethod::STATE_ANALYZING);
        $this->transitionSeedPage($page, OmrTemplateVerificationMethodPage::STATE_ANALYZING);
        $failed = $this->createSeededPhysicalCapture(
            $actor,
            $run,
            $method,
            $challenge,
            $pageNo,
            1,
            false,
            $failedAsset,
            null,
            null,
            $medium,
            $challengeHash,
        );
        $this->transitionSeedMethod($method, OmrTemplateVerificationMethod::STATE_FAILED, true);
        $this->transitionSeedPage($page, OmrTemplateVerificationMethodPage::STATE_FAILED);

        // A retry is represented by the next immutable attempt, not by
        // mutating the failed landscape row.
        $this->transitionSeedMethod($method, OmrTemplateVerificationMethod::STATE_AWAITING_CAPTURE);
        $this->transitionSeedPage($page, OmrTemplateVerificationMethodPage::STATE_AWAITING_CAPTURE);
        $this->transitionSeedMethod($method, OmrTemplateVerificationMethod::STATE_ANALYZING);
        $this->transitionSeedPage($page, OmrTemplateVerificationMethodPage::STATE_ANALYZING);

        $portraitBytes = 'nexiam-seeded-run31-portrait|'.$method->method.'|'.$pageNo;
        $portraitAsset = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_EVIDENCE, $portraitBytes, 'image/png', $pageNo, [
            'run_id' => $run->id,
            'method' => $method->method,
            'attempt_no' => 2,
            'role' => 'capture_input',
            'orientation' => 'portrait',
            'seeded_demo' => true,
        ]);
        $alignedAsset = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_EVIDENCE, 'nexiam-seeded-run31-aligned|'.$method->method.'|'.$pageNo, 'image/png', $pageNo, [
            'run_id' => $run->id,
            'method' => $method->method,
            'attempt_no' => 2,
            'role' => 'aligned_evidence',
            'width' => 1200,
            'height' => 1700,
            'seeded_demo' => true,
        ]);
        $overlayAsset = $store->putDraftAsset($draft, OmrTemplateAsset::KIND_EVIDENCE, 'nexiam-seeded-run31-overlay|'.$method->method.'|'.$pageNo, 'image/png', $pageNo, [
            'run_id' => $run->id,
            'method' => $method->method,
            'attempt_no' => 2,
            'role' => 'overlay_evidence',
            'width' => 1200,
            'height' => 1700,
            'seeded_demo' => true,
        ]);
        $passed = $this->createSeededPhysicalCapture(
            $actor,
            $run,
            $method,
            $challenge,
            $pageNo,
            2,
            true,
            $portraitAsset,
            $alignedAsset,
            $overlayAsset,
            $medium,
            $challengeHash,
        );
        $this->seedPhysicalEvidenceItems($passed);
        $this->transitionSeedPage($page, OmrTemplateVerificationMethodPage::STATE_PASSED, (int) $passed->id);
        $this->transitionSeedMethod($method, OmrTemplateVerificationMethod::STATE_PASSED);
        $challenge->forceFill(['status' => OmrPhysicalChallenge::STATUS_PASSED])->save();

        // Keep the variable referenced so static analysis documents that the
        // first attempt is intentionally retained immutable history.
        unset($failed);
    }

    private function transitionSeedMethod(
        OmrTemplateVerificationMethod $method,
        string $state,
        bool $failed = false,
    ): void {
        if ($method->method_state === $state) {
            return;
        }
        $method->forceFill([
            'method_state' => $state,
            'failed_at' => $failed ? now() : $method->failed_at,
            'completed_at' => $state === OmrTemplateVerificationMethod::STATE_PASSED ? now() : $method->completed_at,
            'last_attempt_at' => now(),
            'lock_version' => (int) $method->lock_version + 1,
        ])->save();
    }

    private function transitionSeedPage(
        OmrTemplateVerificationMethodPage $page,
        string $state,
        ?int $selectedAttemptId = null,
    ): void {
        $attributes = ['page_state' => $state];
        if ($page->page_state !== $state) {
            $attributes['page_version'] = (int) $page->page_version + 1;
        }
        if ($selectedAttemptId !== null) {
            $attributes['selected_attempt_id'] = $selectedAttemptId;
        }
        $page->forceFill($attributes)->save();
    }

    private function createSeededPhysicalCapture(
        User $actor,
        OmrTemplateVerificationRun $run,
        OmrTemplateVerificationMethod $method,
        OmrPhysicalChallenge $challenge,
        int $pageNo,
        int $attemptNo,
        bool $passed,
        OmrTemplateAsset $inputAsset,
        ?OmrTemplateAsset $alignedAsset,
        ?OmrTemplateAsset $overlayAsset,
        ?string $medium,
        string $challengeHash,
    ): OmrTemplateVerificationCapture {
        $inputHash = (string) $inputAsset->sha256;
        $expectedVectorHash = (string) $method->expected_vector_hash;
        $orientationDiagnostics = [
            'candidates' => array_map(
                static fn (int $orientation): array => ['orientation' => $orientation, 'outcome' => $passed ? 'succeeded' : 'failed'],
                [0, 90, 180, 270],
            ),
            'results' => array_map(
                static fn (int $orientation): array => ['orientation' => $orientation, 'marker_count' => $passed ? 4 : 0],
                [0, 90, 180, 270],
            ),
        ];
        $markerMatches = $passed
            ? ['quarter_1' => 0.9, 'quarter_2' => 0.9, 'quarter_3' => 0.9, 'quarter_4' => 0.9]
            : ['quarter_1' => 0.35, 'quarter_2' => 0.35, 'quarter_3' => 0.35, 'quarter_4' => 0.35];
        $gates = [
            'marker_count' => $passed ? 'pass' : 'fail',
            'registration' => $passed ? 'pass' : 'fail',
            'dimensions' => 'pass',
            'shifts' => 'pass',
            'unsafe_blocks' => [],
        ];
        $code = $passed ? null : 'marker_mismatch';
        $detail = $passed
            ? null
            : 'Seeded Run 31 landscape frame retained: marker registration failed before recognition.';
        $prepared = [
            'outcome' => $passed ? 'succeeded' : 'failed',
            'code' => $code,
            'detail' => $detail,
            'accepted_orientation' => $passed ? 270 : null,
            'orientation_diagnostics' => $orientationDiagnostics,
            'gates' => $gates,
        ];
        $evidence = [
            'outcome' => $passed ? 'succeeded' : 'failed',
            'page_no' => $pageNo,
            'items' => [],
            'identity' => [],
            'calibration' => [],
            'gates' => $gates,
            'marker_matches' => $markerMatches,
            'field_block_shifts' => [],
            'hashes' => [
                'input' => $inputHash,
                'aligned' => $alignedAsset?->sha256,
                'overlay' => $overlayAsset?->sha256,
                'plan' => $method->plan_hash,
            ],
            'timings' => ['preprocess_ms' => 1.0, 'verification_ms' => 1.0],
            'seeded_demo' => true,
        ];
        $now = now();

        return OmrTemplateVerificationCapture::query()->create([
            'run_id' => $run->id,
            'method_id' => $method->id,
            'challenge_id' => $challenge->id,
            'attempt' => (int) $run->attempt,
            'attempt_no' => $attemptNo,
            'page_no' => $pageNo,
            'method' => $method->method,
            'mode' => (string) $run->mode,
            'variant' => 'physical_capture',
            'attempt_state' => $passed ? OmrTemplateVerificationCapture::ATTEMPT_PASSED : OmrTemplateVerificationCapture::ATTEMPT_FAILED,
            'target_kind' => $method->target_kind,
            'target_id' => (int) $method->target_id,
            'target_version' => $method->target_version,
            'target_hash' => $method->target_hash,
            'plan_version' => $method->plan_version,
            'plan_seed' => $method->plan_seed,
            'plan_hash' => $method->plan_hash,
            'challenge_hash' => $challengeHash,
            'renderer_version' => $challenge->renderer_version,
            'renderer_hash' => $challenge->renderer_hash,
            'marker_profile_id' => $challenge->marker_profile_id,
            'marker_profile_version' => $challenge->marker_profile_version,
            'marker_profile_hash' => $challenge->marker_profile_hash,
            'page_manifest_hash' => $challenge->page_manifest_hash,
            'expected' => 'pass',
            'actual' => $passed ? OmrTemplateVerificationCapture::ACTUAL_PASS : OmrTemplateVerificationCapture::ACTUAL_FAILED,
            'status' => $passed ? OmrTemplateVerificationCapture::STATUS_PASS : OmrTemplateVerificationCapture::STATUS_FAIL,
            'error_code' => $code,
            'failure_code' => $code,
            'failure_detail' => $detail,
            'input_hash' => $inputHash,
            'input_asset_hash' => $inputAsset->sha256,
            'input_asset_id' => $inputAsset->id,
            'aligned_asset_hash' => $alignedAsset?->sha256,
            'aligned_asset_id' => $alignedAsset?->id,
            'overlay_asset_hash' => $overlayAsset?->sha256,
            'overlay_asset_id' => $overlayAsset?->id,
            'evidence_version' => 'v1',
            'evidence_hash' => hash('sha256', sprintf('seeded-run31|%s|%d|%s', $method->method, $attemptNo, $passed ? 'portrait-pass' : 'landscape-fail')),
            'expected_vector_hash' => $expectedVectorHash,
            'detected_vector_hash' => $passed ? $expectedVectorHash : null,
            'vector_match' => $passed,
            'orientation_tried' => [0, 90, 180, 270],
            'accepted_orientation' => $passed ? 270 : null,
            'orientation_diagnostics' => $orientationDiagnostics,
            'marker_result' => $markerMatches,
            'layout_result' => ['outcome' => $passed ? 'succeeded' : 'failed', 'gates' => $gates, 'marker_matches' => $markerMatches],
            'gate_result' => $gates,
            'medium' => $medium,
            'summary' => [
                'execution' => 'omr_capture_analysis_pipeline',
                'capture_source' => 'upload',
                'seeded_demo' => true,
                'gates' => $gates,
                'marker_matches' => $markerMatches,
                'field_block_shifts' => [],
                'timings' => ['preprocess_ms' => 1.0, 'verification_ms' => 1.0],
                'issues' => $passed ? [] : [['code' => 'marker_mismatch', 'detail' => $detail]],
                'calibration_status' => 'unqualified',
                'evidence' => $evidence,
                'prepared_capture' => $prepared,
            ],
            'selected_by_user_id' => $passed ? $actor->id : null,
            'selected_at' => $passed ? $now : null,
            'selection_reason' => $passed ? 'Seeded portrait pass retained as the selected rehearsal evidence.' : null,
            'captured_by_user_id' => $actor->id,
            'captured_at' => $now,
            'lock_version' => $passed ? 1 : 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function seedPhysicalEvidenceItems(OmrTemplateVerificationCapture $capture): array
    {
        $manifest = $this->canonicalDemoEvidenceManifest();
        $existing = DB::table('omr_template_verification_item_evidence')
            ->where('capture_id', $capture->id)
            ->exists();
        if ($existing) {
            $staleItem = OmrTemplateVerificationItemEvidence::query()
                ->where('capture_id', $capture->id)
                ->where('item_id', $manifest['items']['stale_reviewed']['verification_item_id'])
                ->first();
            if ($staleItem !== null) {
                $this->ensureStaleVerificationReview($capture, $staleItem);
            }

            return $manifest;
        }

        $areaCodes = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
        $optionCodes = ['A', 'B', 'C', 'D'];
        $rows = [];
        $now = now();
        $position = 1;
        foreach ($areaCodes as $areaPosition => $areaCode) {
            for ($questionNo = 1; $questionNo <= 25; $questionNo++) {
                $expected = $optionCodes[($position - 1) % count($optionCodes)];
                $demoState = $this->canonicalDemoState($position);
                if ($demoState['key'] === 'ambiguous_without_expected') {
                    $expected = null;
                }
                $observed = $this->canonicalObservedOptions($demoState['key'], $expected ?? $optionCodes[0], $optionCodes);
                $detected = $demoState['machine_state'] === 'marked'
                    ? ($demoState['key'] === 'mismatch'
                        ? $this->canonicalOtherOption($expected, $optionCodes)
                        : $expected)
                    : null;
                $options = [];
                $means = [];
                $rowY = round(0.08 + (($questionNo - 1) * 0.03), 4);
                foreach ($optionCodes as $optionIndex => $optionCode) {
                    $means[$optionCode] = in_array($optionCode, $observed, true) ? 198.0 : 36.0 + ($optionIndex * 54.0);
                    $options[] = [
                        'code' => $optionCode,
                        'region' => [
                            'x' => round(0.12 + ($optionIndex * 0.18), 4),
                            'y' => $rowY,
                            'w' => 0.1,
                            'h' => 0.05,
                        ],
                        'mean' => $means[$optionCode],
                        'classification' => in_array($optionCode, $observed, true) ? 'observed' : 'not_observed',
                        'observed' => in_array($optionCode, $observed, true),
                        'threshold_status' => 'Unqualified',
                    ];
                }
                $storedState = match ($demoState['key']) {
                    'mismatch' => OmrTemplateVerificationItemEvidence::STATE_MISMATCH,
                    'blank' => OmrTemplateVerificationItemEvidence::STATE_MISSING,
                    'ambiguous_with_expected', 'ambiguous_without_expected' => OmrTemplateVerificationItemEvidence::STATE_AMBIGUOUS,
                    'unreadable' => 'unreadable',
                    default => OmrTemplateVerificationItemEvidence::STATE_MATCH,
                };
                $item = [
                    'capture_id' => $capture->id,
                    'group_type' => OmrTemplateVerificationItemEvidence::GROUP_APTITUDE_AREA,
                    'area_key' => $areaCode,
                    'area_position' => $areaPosition + 1,
                    'question_no' => $questionNo,
                    'item_id' => sprintf('%s_Q_%03d', $areaCode, $questionNo),
                    'options' => $options,
                    'expected' => $expected,
                    'detected' => $detected,
                    'state' => $storedState,
                    'means' => $means,
                    'delta' => null,
                    'status' => $storedState,
                    'crop_asset_id' => null,
                    'crop_asset_hash' => null,
                    'crop_rect' => ['x' => 0.1, 'y' => $rowY, 'w' => 0.7, 'h' => 0.08],
                    'evidence_version' => 'v1',
                    'row_version' => 0,
                ];
                $item['evidence_hash'] = hash('sha256', json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $item['created_at'] = $now;
                $item['updated_at'] = $now;
                foreach (['options', 'means', 'crop_rect'] as $jsonField) {
                    if (is_array($item[$jsonField] ?? null)) {
                        $item[$jsonField] = json_encode($item[$jsonField], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    }
                }
                $rows[] = $item;
                $position++;
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('omr_template_verification_item_evidence')->insert($chunk);
        }
        $staleItem = OmrTemplateVerificationItemEvidence::query()
            ->where('capture_id', $capture->id)
            ->where('item_id', $manifest['items']['stale_reviewed']['verification_item_id'])
            ->first();
        if ($staleItem !== null) {
            $this->ensureStaleVerificationReview($capture, $staleItem);
        }

        return $manifest;
    }

    /** @return array{key:string,label:string,machine_state:string} */
    private function canonicalDemoState(int $position): array
    {
        return $this->canonicalDemoStates()[$position] ?? [
            'key' => 'ordinary_match',
            'label' => 'Ordinary match',
            'machine_state' => 'marked',
        ];
    }

    /** @return array<int, array{key:string,label:string,machine_state:string}> */
    private function canonicalDemoStates(): array
    {
        return [
            1 => ['key' => 'clean_match', 'label' => 'Clean match', 'machine_state' => 'marked'],
            2 => ['key' => 'mismatch', 'label' => 'Mismatch', 'machine_state' => 'marked'],
            3 => ['key' => 'blank', 'label' => 'Blank', 'machine_state' => 'blank'],
            4 => ['key' => 'ambiguous_with_expected', 'label' => 'Ambiguous with expected', 'machine_state' => 'ambiguous'],
            5 => ['key' => 'ambiguous_without_expected', 'label' => 'Ambiguous without expected', 'machine_state' => 'ambiguous'],
            6 => ['key' => 'unreadable', 'label' => 'Unreadable', 'machine_state' => 'unreadable'],
            7 => ['key' => 'stale_reviewed', 'label' => 'Stale reviewed', 'machine_state' => 'marked'],
        ];
    }

    /** @return array<string, mixed> */
    private function canonicalDemoEvidenceManifest(): array
    {
        $areaCodes = ['PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
        $items = [];
        foreach ($this->canonicalDemoStates() as $position => $state) {
            $areaCode = $areaCodes[(int) floor(($position - 1) / 25)];
            $questionNo = (($position - 1) % 25) + 1;
            $items[$state['key']] = [
                'label' => $state['label'],
                'position' => $position,
                'state' => $state['machine_state'],
                'verification_item_id' => sprintf('%s_Q_%03d', $areaCode, $questionNo),
                'grading_item_key' => sprintf('%s_Q_%d', $areaCode, $questionNo),
            ];
        }

        return [
            'contract' => 'omr-evidence-visual-review',
            'version' => 1,
            'items' => $items,
        ];
    }

    /** @param list<string> $optionCodes @return list<string> */
    private function canonicalObservedOptions(string $state, string $expected, array $optionCodes): array
    {
        return match ($state) {
            'blank' => [],
            'ambiguous_with_expected' => [$expected, $this->canonicalOtherOption($expected, $optionCodes)],
            'ambiguous_without_expected' => [
                $this->canonicalOtherOption($expected, $optionCodes),
                $this->canonicalOtherOption($expected, $optionCodes, 2),
            ],
            'mismatch', 'unreadable' => [$this->canonicalOtherOption($expected, $optionCodes)],
            default => [$expected],
        };
    }

    /** @param list<string> $optionCodes */
    private function canonicalOtherOption(string $expected, array $optionCodes, int $offset = 1): string
    {
        $index = array_search($expected, $optionCodes, true);
        $index = $index === false ? 0 : $index;

        return $optionCodes[($index + $offset) % count($optionCodes)];
    }

    private function ensureStaleVerificationReview(
        OmrTemplateVerificationCapture $capture,
        OmrTemplateVerificationItemEvidence $item,
    ): void {
        $itemKey = (string) $item->item_id;
        if (OmrItemReviewRevision::query()
            ->forVerificationItem((int) $item->id)
            ->where('item_key', $itemKey)
            ->latestRevision()
            ->exists()) {
            return;
        }
        $reviewerId = (int) ($capture->captured_by_user_id ?? 0);
        $expected = is_scalar($item->expected) ? trim((string) $item->expected) : '';
        $currentHash = (string) $item->evidence_hash;
        if ($reviewerId < 1 || $expected === '' || $currentHash === '') {
            return;
        }

        OmrItemReviewRevision::query()->create([
            'verification_item_evidence_id' => (int) $item->id,
            'capture_sheet_id' => null,
            'item_key' => $itemKey,
            'revision' => 1,
            'supersedes_revision_id' => null,
            'decision' => OmrReviewDecision::ConfirmMachine,
            'selected_option' => $expected,
            'reason_code' => null,
            'note' => null,
            'reviewed_machine_evidence_hash' => hash('sha256', json_encode([
                'item_key' => $itemKey,
                'machine_version' => 0,
                'selected_option' => $expected,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'parent_version' => 0,
            'reviewed_by_user_id' => $reviewerId,
            'command_name' => 'omr.demo.seed.stale_review',
            'idempotency_key' => 'omr-canonical-demo-verification-stale-'.$capture->id,
            'correlation_id' => $this->canonicalSeedUuid('verification-stale|'.$capture->id.'|'.$itemKey),
        ]);
    }

    private function canonicalSeedUuid(string $seed): string
    {
        $hex = hash('sha256', $seed);

        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 3),
            substr($hex, 15, 3),
            substr($hex, 18, 12),
        );
    }

    /** @param  list<string>|null  $modes */
    private function resetVerificationRuns(OmrTemplateAuthoringDraft $draft, ?array $modes = null): void
    {
        $query = OmrTemplateVerificationRun::query()->where('draft_id', $draft->id);
        if ($modes !== null) {
            $query->whereIn('mode', $modes);
        }
        // Physical and digital capture rows are immutable once persisted.
        // Only an unmaterialized run (no captures) can be removed while
        // repairing a stale demo target; retained evidence stays queryable.
        $runs = $query->get()->filter(static fn (OmrTemplateVerificationRun $run): bool => ! DB::table('omr_template_verification_captures')->where('run_id', $run->id)->exists());
        if ($runs->isEmpty()) {
            return;
        }
        $runIds = $runs->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $assetIds = [];
        foreach (OmrTemplateVerificationCapture::query()->whereIn('run_id', $runIds)->get() as $capture) {
            foreach (['input_asset_id', 'aligned_asset_id', 'overlay_asset_id'] as $field) {
                if ($capture->{$field} !== null) {
                    $assetIds[] = (int) $capture->{$field};
                }
            }
        }
        foreach (OmrPhysicalChallenge::query()->whereIn('run_id', $runIds)->get() as $challenge) {
            foreach (['sheet_asset_id', 'guide_asset_id'] as $field) {
                if ($challenge->{$field} !== null) {
                    $assetIds[] = (int) $challenge->{$field};
                }
            }
        }
        $methodIds = DB::table('omr_template_verification_methods')
            ->whereIn('run_id', $runIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        if ($methodIds !== []) {
            // Selected-attempt FKs point from method pages to captures; clear
            // and remove those projections before deleting immutable history.
            DB::table('omr_template_verification_method_pages')->whereIn('method_id', $methodIds)->update(['selected_attempt_id' => null]);
            DB::table('omr_template_verification_method_pages')->whereIn('method_id', $methodIds)->delete();
        }
        DB::table('omr_template_verification_captures')->whereIn('run_id', $runIds)->delete();
        if ($methodIds !== []) {
            DB::table('omr_template_verification_methods')->whereIn('id', $methodIds)->delete();
        }
        DB::table('omr_physical_challenges')->whereIn('run_id', $runIds)->delete();
        DB::table('omr_template_verification_runs')->whereIn('id', $runIds)->delete();

        $assets = $draft->assets()->get()->filter(function (OmrTemplateAsset $asset) use ($runIds, $assetIds): bool {
            return in_array((int) $asset->id, $assetIds, true)
                || in_array((int) (($asset->metadata['run_id'] ?? 0)), $runIds, true);
        });
        foreach ($assets as $asset) {
            Storage::disk($asset->disk)->delete($asset->path);
            $asset->delete();
        }
    }

    private function seedScannerPrerequisites(User $actor): void
    {
        $pair = $this->ensureCanonicalPair($actor);
        $grading = $this->ensureScannerGradingSession($actor, $pair);
        $snapshot = $grading->examSession?->currentMaterialSnapshot;
        if ($snapshot !== null) {
            $pair = OmrTemplateBookletPair::query()->firstOrCreate(
                [
                    'template_version_id' => $snapshot->template_version_id,
                    'booklet_version_id' => $snapshot->booklet_version_id,
                ],
                [
                    'status' => OmrTemplateBookletPair::STATUS_ACTIVE,
                    'approved_by_user_id' => $actor->id,
                    'approved_at' => now(),
                ],
            );
        }
        $this->resetDemoScannerBatches($grading, $pair, $actor);
        $version = $pair->templateVersion->fresh(['template']);
        $this->ensureCanonicalPrintAsset($version, $actor);
        $this->ensureSystemDesignedPreviewRaster($version);
        $instance = $this->ensurePrintedBlank($pair->templateVersion->fresh(['template']), $actor);
        $batch = $this->ensureScannerBatch($grading, $pair, $actor);
        $demoSheet = $this->ensureScannerEvidenceDemo($grading, $batch, $pair, $actor);
        $this->ensurePaperLifecycleDemoStates($pair->templateVersion, $grading, $actor, $instance, $demoSheet);
        $recoveryFixtures = $this->ensureRecoveryFixtures($grading, $batch, $demoSheet, $actor);
        $pageHash = (string) ($instance->pages()->orderBy('page_no')->value('qr_payload_hash') ?? '');

        $this->command?->info(sprintf(
            'OMR scanner prerequisites ready: grading session #%d (%s); pair template #%d/booklet #%d; blank instance #%d %s; QR manifest %s; page QR %s; evidence sheet #%d; duplicate sheet #%d; QR invalid pending #%d; key missing pending #%d; ambiguous pending #%d.',
            $grading->id,
            (string) $grading->examSession?->code,
            (int) $pair->template_version_id,
            (int) $pair->booklet_version_id,
            $instance->id,
            $instance->instance_code,
            $instance->qr_manifest_hash,
            $pageHash,
            $demoSheet->id,
            $recoveryFixtures['duplicate_sheet_id'],
            $recoveryFixtures['qr_invalid_pending_id'],
            $recoveryFixtures['key_missing_pending_id'],
            $recoveryFixtures['ambiguous_pending_id'],
        ));
    }

    private function ensureCanonicalPair(User $actor): OmrTemplateBookletPair
    {
        $pair = $this->findCanonicalPair();
        if ($pair !== null) {
            return $pair;
        }

        $source = $this->findCanonicalPairCandidate();
        if ($source === null) {
            (new OmrFocusedDemoSeeder)->run();
            $pair = $this->findCanonicalPair();
            if ($pair !== null) {
                return $pair;
            }
            $source = $this->findCanonicalPairCandidate();
        }

        if ($source === null) {
            throw new RuntimeException('canonical_scanner_pair_missing');
        }

        return $this->activateFreshCanonicalPair($source, $actor);
    }

    private function findCanonicalPair(): ?OmrTemplateBookletPair
    {
        foreach ($this->canonicalPairCandidates() as $pair) {
            if (! $pair->isUsable()) {
                continue;
            }

            try {
                app(OmrReviewGeometryQuery::class)->itemsFor(
                    $pair->templateVersion,
                    $pair->bookletVersion,
                );
            } catch (OmrTemplateProjectionException) {
                continue;
            }

            return $pair;
        }

        return null;
    }

    private function findCanonicalPairCandidate(): ?OmrTemplateBookletPair
    {
        foreach ($this->canonicalPairCandidates() as $pair) {
            if ($pair->isUsable()) {
                return $pair;
            }
        }

        return null;
    }

    /** @return list<OmrTemplateBookletPair> */
    private function canonicalPairCandidates(): array
    {
        return OmrTemplateBookletPair::query()
            ->with([
                'templateVersion.template',
                'bookletVersion.booklet',
                'bookletVersion.questions.aptitudeArea',
            ])
            ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
            ->whereHas('templateVersion.template', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-150'))
            ->whereHas('bookletVersion.booklet', fn ($query) => $query->where('code', 'SYN-OMR-PROFILE-KEY'))
            ->orderByDesc('id')
            ->get()
            ->all();
    }

    private function activateFreshCanonicalPair(
        OmrTemplateBookletPair $sourcePair,
        User $actor,
    ): OmrTemplateBookletPair {
        $sourceTemplateVersion = $sourcePair->templateVersion?->fresh(['template']);
        $template = $sourceTemplateVersion?->template;
        if ($sourceTemplateVersion === null || $template === null) {
            throw new RuntimeException('canonical_scanner_template_missing');
        }

        $templateCode = (string) $template->code;
        $this->cleanCorruptCanonicalBundleArtifacts($template, $templateCode);

        $bundle = NativeBundle::load($templateCode, (int) $sourceTemplateVersion->version);
        $templateDraft = $this->findCompatibleTemplateDraft($templateCode, $bundle->template);
        if ($templateDraft === null) {
            $templateDraft = app(SaveAuthoredTemplateDraft::class)->handle(
                $templateCode,
                (string) $template->name,
                $bundle->template,
                $actor,
                is_array($sourceTemplateVersion->print_overlay_json)
                    ? $sourceTemplateVersion->print_overlay_json
                    : null,
                true,
            );
        }

        $sourceBookletVersion = $sourcePair->bookletVersion?->fresh(['booklet', 'questions.aptitudeArea']);
        $booklet = $sourceBookletVersion?->booklet;
        if ($sourceBookletVersion === null || $booklet === null) {
            throw new RuntimeException('canonical_scanner_booklet_missing');
        }

        $questions = $this->bookletQuestions($sourceBookletVersion);
        $bookletDraft = $this->findCompatibleBookletDraft($booklet, $questions);
        if ($bookletDraft === null) {
            $bookletDraft = app(SaveAuthoredBookletVersion::class)->handle(
                (string) $booklet->code,
                (string) $booklet->name,
                $questions,
                $actor,
            );
        }

        return app(ActivateOmrConfiguration::class)->handle(
            $templateDraft->fresh(['template']),
            $bookletDraft->fresh(),
            $actor,
        );
    }

    /**
     * Remove only invalid durable bundle directories that have no immutable
     * template-version row. Committed source bundles live outside this disk.
     */
    private function cleanCorruptCanonicalBundleArtifacts(
        OmrAnswerSheetTemplate $template,
        string $code,
    ): void {
        $disk = Storage::disk('local');
        $knownVersions = $template->versions()
            ->pluck('version')
            ->map(static fn ($version): int => (int) $version)
            ->all();

        foreach ($disk->directories('omr-bundles/'.$code) as $directory) {
            $name = basename($directory);
            if (preg_match('/^v([1-9][0-9]*)$/', $name, $matches) !== 1) {
                continue;
            }

            $version = (int) $matches[1];
            if (in_array($version, $knownVersions, true)) {
                continue;
            }

            $path = $disk->path($directory);
            if (! is_dir($path) || is_link($path)) {
                continue;
            }

            try {
                NativeBundle::loadFromDirectory($path, $code, $version);
            } catch (\Throwable) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    /** @return list<array{area_id:int, question_no:int, position:int, correct_option_code:string}> */
    private function bookletQuestions(OmrQuestionBookletVersion $version): array
    {
        $rows = $version->questions()
            ->with('aptitudeArea')
            ->orderBy('question_position')
            ->get();
        if ($rows->isEmpty()) {
            throw new RuntimeException('canonical_scanner_booklet_empty');
        }

        $questionNos = [];
        $questions = [];
        foreach ($rows as $row) {
            $areaCode = (string) ($row->aptitudeArea?->code ?? '');
            if ($areaCode === '') {
                throw new RuntimeException('canonical_scanner_booklet_area_missing');
            }
            $questionNos[$areaCode] = (int) ($questionNos[$areaCode] ?? 0) + 1;
            $questions[] = [
                'area_id' => (int) $row->aptitude_area_id,
                'question_no' => $questionNos[$areaCode],
                'position' => (int) $row->question_position,
                'correct_option_code' => (string) $row->correct_option_code,
            ];
        }

        return $questions;
    }

    private function findCompatibleTemplateDraft(string $code, array $template): ?OmrAnswerSheetTemplateVersion
    {
        $model = OmrAnswerSheetTemplate::query()->where('code', $code)->first();
        if ($model === null) {
            return null;
        }

        foreach ($model->versions()->where('status', OmrAnswerSheetTemplateVersion::STATUS_DRAFT)->orderByDesc('version')->get() as $draft) {
            if (! is_array($draft->review_manifest_json)) {
                continue;
            }

            try {
                if (NativeBundle::load($code, (int) $draft->version)->template === $template) {
                    return $draft;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  list<array{area_id:int, question_no:int, position:int, correct_option_code:string}>  $questions
     */
    private function findCompatibleBookletDraft(
        OmrQuestionBooklet $booklet,
        array $questions,
    ): ?OmrQuestionBookletVersion {
        foreach ($booklet->versions()->where('status', OmrQuestionBookletVersion::STATUS_DRAFT)->orderByDesc('version')->get() as $draft) {
            $rows = $draft->questions()
                ->orderBy('question_position')
                ->get(['aptitude_area_id', 'question_position', 'correct_option_code']);
            if ($rows->count() !== count($questions)) {
                continue;
            }

            $matches = true;
            foreach ($rows as $index => $row) {
                $expected = $questions[$index];
                if ((int) $row->aptitude_area_id !== $expected['area_id']
                    || (int) $row->question_position !== $expected['position']
                    || (string) $row->correct_option_code !== $expected['correct_option_code']) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return $draft;
            }
        }

        return null;
    }

    private function resetDemoScannerBatches(
        GradingSession $grading,
        OmrTemplateBookletPair $pair,
        User $actor,
    ): void {
        $batches = OmrCaptureBatch::query()
            ->where('grading_session_id', $grading->id)
            ->orderBy('id')
            ->get();

        foreach ($batches as $batch) {
            if ($batch->isImmutable()) {
                continue;
            }
            if ($batch->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('canonical_scanner_batch_reset_unsafe');
            }
            if ((int) $batch->template_version_id === (int) $pair->template_version_id
                && (int) $batch->booklet_version_id === (int) $pair->booklet_version_id) {
                continue;
            }

            $sheetIds = $batch->sheets()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $hasReviewHistory = $sheetIds !== []
                && OmrItemReviewRevision::query()->whereIn('capture_sheet_id', $sheetIds)->exists();
            if ($hasReviewHistory) {
                $this->retainDemoScannerBatch($batch, $actor);
            } else {
                $this->deleteDemoScannerBatch($batch);
            }
        }
    }

    private function retainDemoScannerBatch(OmrCaptureBatch $batch, User $actor): void
    {
        DB::transaction(function () use ($batch, $actor): void {
            $locked = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('canonical_scanner_batch_reset_unsafe');
            }
            $locked->forceFill([
                'status' => OmrCaptureBatch::STATUS_REJECTED,
                'version' => (int) $locked->version + 1,
                'rejecting_by_user_id' => $actor->id,
                'rejecting_at' => now(),
                'confirmed_at' => now(),
            ])->save();
            app(RecordAuditEvent::class)->handle('grading.omr_demo_batch_retained', [
                'batch_id' => $locked->id,
                'grading_session_id' => $locked->grading_session_id,
                'reason' => 'pair_replaced_with_review_history',
            ], (string) $actor->id);
        });
    }

    private function deleteDemoScannerBatch(OmrCaptureBatch $batch): void
    {
        $assetPaths = [];

        DB::transaction(function () use ($batch, &$assetPaths): void {
            $locked = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== OmrCaptureBatch::STATUS_OPEN) {
                throw new RuntimeException('canonical_scanner_batch_reset_unsafe');
            }

            $sheets = $locked->sheets()->with('pages')->orderBy('id')->get();
            $sheetIds = $sheets->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            if ($sheets->contains(static fn (OmrCaptureSheet $sheet): bool => $sheet->status === OmrCaptureSheet::STATUS_COMMITTED)) {
                throw new RuntimeException('canonical_scanner_batch_has_committed_sheet');
            }
            if ($sheetIds !== [] && ScoreRecord::query()->whereIn('capture_sheet_id', $sheetIds)->exists()) {
                throw new RuntimeException('canonical_scanner_batch_has_scores');
            }

            foreach ($sheets as $sheet) {
                foreach ([$sheet->asset_path, $sheet->aligned_asset_path] as $path) {
                    if (is_string($path) && $path !== '') {
                        $assetPaths[] = $path;
                    }
                }
                foreach ($sheet->pages as $page) {
                    foreach ([$page->asset_path, $page->aligned_asset_path] as $path) {
                        if (is_string($path) && $path !== '') {
                            $assetPaths[] = $path;
                        }
                    }
                }
            }

            if ($sheetIds !== []) {
                OmrCaptureSheet::query()->whereIn('id', $sheetIds)->update(['current_processing_run_id' => null]);
                OmrCaptureSheetPage::query()->whereIn('sheet_id', $sheetIds)->update(['current_processing_run_id' => null]);
                OmrCaptureSheetIssue::query()->whereIn('sheet_id', $sheetIds)->delete();
                OmrProcessingRun::query()->whereIn('sheet_id', $sheetIds)->delete();
                OmrCaptureSheetPage::query()->whereIn('sheet_id', $sheetIds)->delete();
                OmrCaptureSheet::query()->whereIn('id', $sheetIds)->delete();
            }

            $locked->delete();
        });

        $assetStore = app(SensitiveAssetStore::class);
        foreach (array_values(array_unique($assetPaths)) as $path) {
            $assetStore->delete($path);
        }
    }

    private function ensureScannerEvidenceDemo(
        GradingSession $grading,
        OmrCaptureBatch $batch,
        OmrTemplateBookletPair $pair,
        User $actor,
    ): OmrCaptureSheet {
        $template = $pair->templateVersion?->fresh(['template']);
        $booklet = $pair->bookletVersion?->fresh(['booklet', 'questions.aptitudeArea']);
        if ($template === null || $booklet === null) {
            throw new RuntimeException('canonical_scanner_geometry_missing');
        }
        $geometryItems = app(OmrReviewGeometryQuery::class)->itemsFor($template, $booklet);
        $pageCount = max(1, $template->pageCount());
        $manifest = $this->canonicalDemoEvidenceManifest();
        $clientCaptureId = '00000000-0000-4000-8000-000000000131';
        $existing = OmrCaptureSheet::query()
            ->where('batch_id', $batch->id)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        $items = $this->canonicalGradingItems(
            $geometryItems,
            1,
            (string) ($template->review_manifest_hash ?: $template->native_bundle_hash),
        );
        if ($existing !== null) {
            $this->ensureStaleGradingReview($existing, $items, $actor, $manifest);

            return $existing;
        }

        $proposal = $this->canonicalGradingProposal($template, $items, $pageCount, $manifest);
        $bytes = $this->pagePng(1200, 1700, 'NEXIAM OMR canonical evidence demo');
        $assetStore = app(SensitiveAssetStore::class);
        $assetHash = hash('sha256', $bytes);
        $assetHmac = $assetStore->hmac($bytes);
        $sheetAssetPath = 'omr/canonical-demo/'.$clientCaptureId.'.png';
        $alignedAssetPath = 'omr/canonical-demo/'.$clientCaptureId.'-aligned.png';
        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $grading->exam_session_id)
            ->active()
            ->orderBy('id')
            ->first();
        $sheet = OmrCaptureSheet::query()->create([
            'batch_id' => $batch->id,
            'admission_attempt_id' => $assignment?->admission_attempt_id,
            'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
            'capture_mode' => OmrCaptureSheet::MODE_AUTO,
            'version' => 1,
            'expected_page_count' => $pageCount,
            'client_capture_id' => $clientCaptureId,
            'detected_applicant_number' => '1234567890',
            'detected_applicant_confidence' => null,
            'detected_birthdate' => null,
            'sheet_confidence' => null,
            'proposal_json' => $proposal,
            'original_mime' => 'image/png',
            'original_bytes' => strlen($bytes),
            'aligned_mime' => 'image/png',
            'aligned_bytes' => strlen($bytes),
            'aligned_width' => 1200,
            'aligned_height' => 1700,
            'aligned_hash' => $assetHash,
            'asset_path' => $sheetAssetPath,
            'aligned_asset_path' => $alignedAssetPath,
            'asset_hmac' => $assetHmac,
            'aligned_asset_hmac' => $assetHmac,
            'asset_hmac_key_id' => 'omr-demo-v1',
            'aligned_asset_hmac_key_id' => 'omr-demo-v1',
            'asset_format_version' => 'v1',
            'asset_key_id' => 'omr-demo-v1',
            'aligned_asset_key_id' => 'omr-demo-v1',
            'asset_state' => OmrCaptureSheet::ASSET_LIVE,
            'captured_by_user_id' => $actor->id,
            'captured_at' => now()->subMinutes(15),
        ]);
        if (! Storage::disk('sensitive')->exists($sheetAssetPath)) {
            $assetStore->store($bytes, $sheetAssetPath);
        }
        if (! Storage::disk('sensitive')->exists($alignedAssetPath)) {
            $assetStore->store($bytes, $alignedAssetPath);
        }
        foreach (range(1, $pageCount) as $pageNo) {
            $pageAssetPath = 'omr/canonical-demo/'.$clientCaptureId.'-p'.$pageNo.'.png';
            $pageAlignedAssetPath = 'omr/canonical-demo/'.$clientCaptureId.'-p'.$pageNo.'-aligned.png';
            if (! Storage::disk('sensitive')->exists($pageAssetPath)) {
                $assetStore->store($bytes, $pageAssetPath);
            }
            if (! Storage::disk('sensitive')->exists($pageAlignedAssetPath)) {
                $assetStore->store($bytes, $pageAlignedAssetPath);
            }
            OmrCaptureSheetPage::query()->create([
                'sheet_id' => $sheet->id,
                'page_no' => $pageNo,
                'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
                'asset_path' => $pageAssetPath,
                'asset_hmac' => $assetHmac,
                'asset_hmac_key_id' => 'omr-demo-v1',
                'asset_key_id' => 'omr-demo-v1',
                'asset_format_version' => 'v1',
                'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($bytes),
                'aligned_mime' => 'image/png',
                'aligned_bytes' => strlen($bytes),
                'aligned_width' => 1200,
                'aligned_height' => 1700,
                'aligned_hash' => $assetHash,
                'aligned_asset_path' => $pageAlignedAssetPath,
                'aligned_asset_hmac' => $assetHmac,
                'aligned_asset_hmac_key_id' => 'omr-demo-v1',
                'aligned_asset_key_id' => 'omr-demo-v1',
                'proposal_json' => [
                    ...$proposal,
                    'page_no' => $pageNo,
                    'items' => array_values(array_filter(
                        $items,
                        static fn (array $item): bool => (int) ($item['page_no'] ?? 1) === $pageNo,
                    )),
                ],
            ]);
        }
        $this->ensureStaleGradingReview($sheet, $items, $actor, $manifest);

        return $sheet;
    }

    /**
     * @param  list<array<string, mixed>>  $geometryItems
     * @return list<array<string, mixed>>
     */
    private function canonicalGradingItems(
        array $geometryItems,
        int $sheetVersion,
        string $manifestHash,
    ): array {
        $optionCodes = ['A', 'B', 'C', 'D'];
        $items = [];
        foreach ($geometryItems as $geometry) {
            $position = (int) ($geometry['position'] ?? 0);
            $areaCode = strtoupper((string) ($geometry['area_code'] ?? ''));
            $questionNo = (int) ($geometry['question_no'] ?? 0);
            if ($position < 1 || $areaCode === '' || $questionNo < 1) {
                continue;
            }
            $itemKey = sprintf('%s_Q_%d', $areaCode, $questionNo);
            $expected = (string) ($geometry['correct_option_code'] ?? '');
            $demoState = $this->canonicalDemoState($position);
            $observed = $this->canonicalObservedOptions($demoState['key'], $expected, $optionCodes);
            $selected = $demoState['machine_state'] === 'marked'
                ? ($demoState['key'] === 'mismatch'
                    ? $this->canonicalOtherOption($expected, $optionCodes)
                    : $expected)
                : null;
            $confidence = in_array($demoState['key'], ['clean_match', 'mismatch', 'stale_reviewed'], true)
                ? 0.98
                : null;
            $issueCodes = $demoState['key'] === 'unreadable' ? ['unreadable_capture'] : [];
            $observations = [];
            foreach ($optionCodes as $optionCode) {
                $observations[] = [
                    'code' => $optionCode,
                    'mean' => in_array($optionCode, $observed, true) ? 198.0 : 36.0,
                    'classification' => in_array($optionCode, $observed, true) ? 'observed' : 'not_observed',
                ];
            }
            $machine = [
                'state' => $demoState['machine_state'],
                'selected_option' => $selected,
                'observed_options' => $observed,
                'option_observations' => $observations,
                'confidence' => $confidence,
                'issue_codes' => $issueCodes,
                'expected_available' => $demoState['key'] !== 'ambiguous_without_expected',
            ];
            $evidenceHash = hash('sha256', json_encode([
                'item_key' => $itemKey,
                'machine' => $machine,
                'option_set' => $optionCodes,
                'manifest_hash' => $manifestHash,
                'sheet_version' => $sheetVersion,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $machine['evidence_hash'] = $evidenceHash;
            $items[] = [
                'item_id' => $itemKey,
                'position' => $position,
                'question_no' => $questionNo,
                'area_id' => (int) ($geometry['area_id'] ?? 0),
                'area_code' => $areaCode,
                'page_no' => (int) ($geometry['page_no'] ?? 1),
                'region' => $geometry['region'] ?? null,
                'machine' => $machine,
                'demo_state' => $demoState['key'],
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function canonicalGradingProposal(
        OmrAnswerSheetTemplateVersion $template,
        array $items,
        int $pageCount,
        array $manifest,
    ): array {
        $hash = static fn (mixed $value, string $fallback): string => is_string($value)
            && preg_match('/^[a-fA-F0-9]{64}$/', $value) === 1
            ? strtolower($value)
            : hash('sha256', $fallback);

        return [
            'envelope' => OmrDetectionRequest::ENVELOPE_VERSION,
            'provider' => 'nexiam.fake',
            'input_hash' => hash('sha256', 'omr-canonical-demo-grading-input-v1'),
            'native_bundle_hash' => $hash($template->native_bundle_hash, 'omr-canonical-demo-native-bundle'),
            'manifest_hash' => $hash($template->review_manifest_hash, 'omr-canonical-demo-manifest'),
            'config_hash' => $hash($template->config_hash, 'omr-canonical-demo-config'),
            'patch_identity' => (string) ($template->patch_identity ?: 'nexiam-headless-overlay-v1'),
            'image' => null,
            'field_block_shifts' => [],
            'aligned_image' => null,
            'applicant_number_proposal' => '1234567890',
            'applicant_number_confidence' => null,
            'birthdate_proposal' => null,
            'sheet_confidence' => null,
            'per_area_scores' => [],
            'items' => $items,
            'pages' => $pageCount,
            'demo_evidence' => $manifest,
            'effective_answers_source' => 'server_pinned_booklet',
            'seeded_demo' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $manifest
     */
    private function ensureStaleGradingReview(
        OmrCaptureSheet $sheet,
        array $items,
        User $actor,
        array $manifest,
    ): void {
        $staleKey = (string) ($manifest['items']['stale_reviewed']['grading_item_key'] ?? '');
        foreach ($items as $item) {
            if ((string) ($item['item_id'] ?? '') !== $staleKey) {
                continue;
            }
            if (OmrItemReviewRevision::query()
                ->forGradingItem((int) $sheet->id, $staleKey)
                ->latestRevision()
                ->exists()) {
                return;
            }
            $machine = is_array($item['machine'] ?? null) ? $item['machine'] : [];
            $selected = is_scalar($machine['selected_option'] ?? null)
                ? trim((string) $machine['selected_option'])
                : '';
            $currentHash = is_scalar($machine['evidence_hash'] ?? null)
                ? trim((string) $machine['evidence_hash'])
                : '';
            if ($selected === '' || $currentHash === '') {
                return;
            }
            OmrItemReviewRevision::query()->create([
                'verification_item_evidence_id' => null,
                'capture_sheet_id' => (int) $sheet->id,
                'item_key' => $staleKey,
                'revision' => 1,
                'supersedes_revision_id' => null,
                'decision' => OmrReviewDecision::ConfirmMachine,
                'selected_option' => $selected,
                'reason_code' => null,
                'note' => null,
                'reviewed_machine_evidence_hash' => hash('sha256', json_encode([
                    'item_key' => $staleKey,
                    'machine_version' => 0,
                    'selected_option' => $selected,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'parent_version' => 0,
                'reviewed_by_user_id' => $actor->id,
                'command_name' => 'omr.demo.seed.stale_review',
                'idempotency_key' => 'omr-canonical-demo-grading-stale-'.$sheet->id,
                'correlation_id' => $this->canonicalSeedUuid('grading-stale|'.$sheet->id.'|'.$staleKey),
            ]);

            return;
        }
    }

    private function ensureScannerBatch(
        GradingSession $grading,
        OmrTemplateBookletPair $pair,
        User $actor,
    ): OmrCaptureBatch {
        $active = OmrCaptureBatch::query()
            ->where('grading_session_id', $grading->id)
            ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
            ->first();
        if ($active !== null) {
            if ((int) $active->template_version_id !== (int) $pair->template_version_id
                || (int) $active->booklet_version_id !== (int) $pair->booklet_version_id) {
                throw new RuntimeException('canonical_scanner_batch_pair_mismatch');
            }

            return $active;
        }

        return app(CreateOmrCaptureBatch::class)->handle($grading, $actor, 'omr-canonical-demo-batch');
    }

    private function ensureScannerGradingSession(User $actor, OmrTemplateBookletPair $pair): GradingSession
    {
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $room = Room::query()->whereIn('code', ['ITBR-104', 'RM-LabA'])->firstOrFail();
        $attempt = $this->ensureScannerApplicant($cycle, $actor);
        $missingSubAttempt = $this->ensureMissingSubmissionApplicant($cycle, $actor);
        $existingGrading = GradingSession::query()
            ->with('examSession.currentMaterialSnapshot')
            ->whereHas('examSession', fn ($query) => $query->where('code', 'EX-OMR-CANONICAL'))
            ->whereHas('examSession.currentMaterialSnapshot', fn ($query) => $query
                ->where('template_version_id', (int) $pair->template_version_id)
                ->where('booklet_version_id', (int) $pair->booklet_version_id)
            )
            ->first();
        if ($existingGrading !== null) {
            if ($existingGrading->status === GradingSession::STATUS_FINALIZED) {
                throw new RuntimeException('canonical_scanner_grading_session_finalized');
            }

            $this->ensureExamAssignmentsAndAttendance($existingGrading->examSession, $attempt, $missingSubAttempt, $actor);

            return $existingGrading->fresh('examSession.currentMaterialSnapshot');
        }

        $booklet = OmrQuestionBookletVersion::query()
            ->whereKey((int) $pair->booklet_version_id)
            ->firstOrFail();
        if (! $booklet->isFinalized()) {
            $existingFinalized = OmrQuestionBookletVersion::query()
                ->where('template_version_id', (int) $pair->template_version_id)
                ->whereNotNull('finalized_at')
                ->orderByDesc('id')
                ->first();
            if ($existingFinalized !== null) {
                $booklet = $existingFinalized;
            } else {
                $pair->templateVersion->loadMissing('areas');
                if ($booklet->template_version_id === null) {
                    $booklet->update([
                        'template_version_id' => $pair->template_version_id,
                        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
                    ]);
                }
                $keys = [];
                $globalPosition = 1;
                foreach ($pair->templateVersion->areas as $area) {
                    for ($areaPosition = 1; $areaPosition <= (int) $area->max_items; $areaPosition++) {
                        $keys[] = [
                            'aptitude_area_id' => (int) $area->aptitude_area_id,
                            'area_position' => $areaPosition,
                            'global_position' => $globalPosition,
                            'correct_option_code' => ['A', 'B', 'C', 'D'][($globalPosition - 1) % 4],
                        ];
                        $globalPosition++;
                    }
                }
                if ($booklet->isDraft() && $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY) {
                    $booklet = app(SetBookletSetsMode::class)->handle($booklet, $actor, true, $booklet->lock_version, false);
                    foreach (OmrBookletSet::SET_CODES as $setCode) {
                        app(SaveBookletSetKeys::class)->handle($booklet, $setCode, $keys, $actor);
                    }
                    $booklet = app(FinalizeBookletVersion::class)->handle($booklet, $actor);
                } else {
                    $previousStatus = $booklet->status;
                    $booklet->forceFill([
                        'template_version_id' => $pair->template_version_id,
                        'authoring_mode' => OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
                        'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                        'finalized_at' => null,
                    ])->save();
                    $booklet = $booklet->fresh();
                    $booklet = app(SetBookletSetsMode::class)->handle($booklet, $actor, true, $booklet->lock_version, false);

                    foreach (OmrBookletSet::SET_CODES as $setCode) {
                        app(SaveBookletSetKeys::class)->handle($booklet->fresh(), $setCode, $keys, $actor);
                    }
                    $booklet = app(FinalizeBookletVersion::class)->handle($booklet->fresh(), $actor);
                    if ($previousStatus !== OmrQuestionBookletVersion::STATUS_DRAFT) {
                        $booklet->forceFill(['status' => $previousStatus])->save();
                    }
                }
            }
        }

        $arrangement = SeatingArrangement::query()
            ->active()
            ->where(function ($query) use ($room): void {
                if ($room->rows !== null && $room->columns !== null) {
                    $query->where('rows', '<=', (int) $room->rows)
                        ->where('columns', '<=', (int) $room->columns);
                } else {
                    $query->whereRaw('(rows * columns) <= ?', [(int) $room->capacity]);
                }
            })
            ->where(function ($query) use ($room): void {
                $query
                    ->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
                    ->orWhereHas('rooms', fn ($roomQuery) => $roomQuery->whereKey($room->id));
            })
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$room->default_seating_arrangement_id ?? 0])
            ->orderBy('id')
            ->first();
        if ($arrangement === null || $arrangement->desk_count < 2) {
            $arrangement = SeatingArrangement::query()->firstOrNew([
                'normalized_name' => 'guidance lab a omr',
            ]);
            $arrangement->forceFill([
                'name' => 'Guidance Lab A OMR',
                'rows' => 4,
                'columns' => 6,
                'booklet_pattern' => SeatingArrangement::PATTERN_UNIFORM,
                'availability_mode' => SeatingArrangement::AVAILABILITY_ALL_ROOMS,
                'is_active' => true,
                'lock_version' => max(1, (int) $arrangement->lock_version),
            ])->save();
        }
        $session = ExamSession::query()->where('code', 'EX-OMR-CANONICAL')->first();
        if ($session === null) {
            $session = ExamSession::query()->create([
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'booklet_version_id' => $booklet->id,
                'seating_arrangement_id' => $arrangement->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'code' => 'EX-OMR-CANONICAL',
                'starts_at' => now()->subHour(),
                'effective_end_at' => now()->addHours(2),
                'version' => 1,
            ]);
        } elseif ($session->status === ExamSession::STATUS_CANCELLED) {
            throw new RuntimeException('canonical_scanner_session_cancelled');
        } else {
            $currentSnapshot = $session->currentMaterialSnapshot;
            if ($currentSnapshot === null
                || (int) $currentSnapshot->template_version_id !== (int) $pair->template_version_id
                || (int) $currentSnapshot->booklet_version_id !== (int) $pair->booklet_version_id
            ) {
                $session->forceFill([
                    'status' => ExamSession::STATUS_DRAFT,
                    'room_id' => $room->id,
                    'booklet_version_id' => $booklet->id,
                    'seating_arrangement_id' => $arrangement->id,
                ])->save();
            }
        }

        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $attempt->id)
            ->active()
            ->first();
        if ($assignment === null) {
            $assignment = app(AssignApplicantToExamSession::class)
                ->handle($session, $attempt, $actor);
        }
        $missingAssignment = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $missingSubAttempt->id)
            ->active()
            ->first();
        if ($missingAssignment === null) {
            $missingAssignment = app(AssignApplicantToExamSession::class)
                ->handle($session, $missingSubAttempt, $actor);
        }
        if ($session->status === ExamSession::STATUS_DRAFT) {
            $session->forceFill([
                'booklet_version_id' => $booklet->id,
                'seating_arrangement_id' => $arrangement->id,
            ])->save();
        }

        $session = $session->fresh();
        if ($session->status === ExamSession::STATUS_DRAFT) {
            $session = app(PublishExamSession::class)
                ->handle($session, $actor, (int) $session->version);
        }
        $session = $session->fresh();
        if (in_array($session->status, [ExamSession::STATUS_PUBLISHED, ExamSession::STATUS_IN_PROGRESS], true)) {
            if ($session->starts_at === null || $session->effective_end_at === null
                || now()->lt($session->starts_at) || now()->gt($session->effective_end_at)) {
                $session->forceFill([
                    'starts_at' => now()->subHour(),
                    'effective_end_at' => now()->addHours(2),
                ])->save();
            }
            if (! $assignment->attendanceRecord()->exists()) {
                app(RecordExamAttendance::class)->handle(
                    $assignment,
                    $actor,
                    ExamAttendanceRecord::OUTCOME_PRESENT,
                    'omr-canonical-attendance-'.$assignment->id,
                );
            }
            if (! $assignment->submissionRecord()->exists()) {
                app(RecordExamSubmission::class)->handle(
                    $assignment,
                    $actor,
                    'omr-canonical-submission-'.$assignment->id,
                );
            }
            if (! $missingAssignment->attendanceRecord()->exists()) {
                app(RecordExamAttendance::class)->handle(
                    $missingAssignment,
                    $actor,
                    ExamAttendanceRecord::OUTCOME_PRESENT,
                    'omr-canonical-attendance-'.$missingAssignment->id,
                );
            }
            // Deliberately omit submissionRecord for $missingAssignment (APP-2026-00002) to prove missing-submission generic recovery
            app(CloseExamSession::class)->handle(
                $session->fresh(),
                $actor,
                (int) $session->version,
            );
        }

        $grading = GradingSession::query()->where('exam_session_id', $session->id)->first();
        if ($grading === null) {
            throw new RuntimeException('canonical_scanner_grading_session_missing');
        }
        if ($grading->status === GradingSession::STATUS_FINALIZED) {
            throw new RuntimeException('canonical_scanner_grading_session_finalized');
        }

        return $grading->fresh('examSession');
    }

    private function ensureScannerApplicant(AcademicCycle $cycle, User $actor): AdmissionAttempt
    {
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $accepted = (array) config('seed.applicants.accepted', []);
        $email = (string) ($accepted['email'] ?? 'omr.canonical.accepted@nexiam.test');
        $attempt = AdmissionAttempt::query()
            ->where('academic_cycle_id', $cycle->id)
            ->whereHas('identity', fn ($query) => $query->where('email', $email))
            ->latest('id')
            ->first();
        if ($attempt === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new RuntimeException('A designated published PublicPolicy revision is required for OMR demo intake.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The designated intake policy has no published revision.');
            $result = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: (string) ($accepted['first_name'] ?? 'OMR'),
                    lastName: (string) ($accepted['last_name'] ?? 'Canonical'),
                    birthdate: (string) ($accepted['birthdate'] ?? '2001-01-01'),
                    sex: (string) ($accepted['sex'] ?? 'female'),
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSIT', 'BA-Psych'],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234567',
                    lastSchoolEnrolled: 'NEXIAM OMR Demo School',
                    strand: 'STEM',
                    addressLine: '1 Canonical Way',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->getKey(),
                    publicPolicyRevisionId: (int) $revision->getKey(),
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                'omr-canonical-seeder',
            );
            $attempt = $result['attempt'];
        }
        if (! $attempt->isExamEligible()) {
            $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
            $attempt = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }

        $this->ensureApplicantReferenceCode($attempt, 'APP-2026-00001', $cycle);

        return $attempt->fresh();
    }

    private function ensureCanonicalPrintAsset(OmrAnswerSheetTemplateVersion $version, User $actor): void
    {
        if ($version->canonicalAssets()->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)->exists()) {
            return;
        }
        $template = $version->template;
        if ($template === null) {
            throw new RuntimeException('canonical_scanner_template_missing');
        }
        $bundle = NativeBundle::load((string) $template->code, (int) $version->version);
        $renderer = app(BundleRenderer::class);
        $html = $renderer->renderHtml($bundle->template, ['overlay' => (array) ($version->print_overlay_json ?? [])]);
        $paper = $renderer->paper();
        $pdf = app(PrintRenderer::class)->render($html, [
            'format' => 'custom',
            'orientation' => 'portrait',
            'width_mm' => $paper['width'],
            'height_mm' => $paper['height'],
        ]);
        app(OmrTemplateAssetStore::class)->putVersionAsset(
            $version,
            OmrTemplateAsset::KIND_PRINT_PDF,
            $pdf,
            'application/pdf',
            null,
            [
                'source' => 'canonical_seed_renderer',
                'template_version_id' => $version->id,
                'bundle_hash' => $bundle->bundleHash,
                'actual_size_instruction' => 'Print at 100% / actual size — do not fit to page.',
                'created_by_user_id' => $actor->id,
            ],
        );
    }

    private function ensureSystemDesignedPreviewRaster(OmrAnswerSheetTemplateVersion $version): void
    {
        if (! OmrSourcePolicy::isSystemDesignedVersion($version)) {
            return;
        }

        $existingPages = $version->canonicalAssets()
            ->where('kind', OmrTemplateAsset::KIND_RASTER_PAGE)
            ->get()
            ->keyBy('page_no');
        $allRasterPagesExist = true;
        for ($pageNo = 1; $pageNo <= $version->pageCount(); $pageNo++) {
            if (! $existingPages->has($pageNo)) {
                $allRasterPagesExist = false;
                break;
            }
        }
        if ($allRasterPagesExist) {
            return;
        }

        $disk = (string) config('omr.canonical.storage.disk', 'local');
        $printAsset = $version->canonicalAssets()
            ->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)
            ->first();
        if ($printAsset === null || $printAsset->disk !== $disk || $printAsset->mime !== 'application/pdf') {
            throw new RuntimeException('canonical_preview_print_asset_unavailable');
        }

        $nativeBundleHash = (string) $version->native_bundle_hash;
        if ($nativeBundleHash === '' || ($printAsset->metadata['bundle_hash'] ?? null) !== $nativeBundleHash) {
            throw new RuntimeException('canonical_preview_bundle_hash_mismatch');
        }

        $pdfPath = Storage::disk($disk)->path($printAsset->path);
        if (! is_file($pdfPath) || hash_file('sha256', $pdfPath) !== $printAsset->sha256) {
            throw new RuntimeException('canonical_preview_print_hash_mismatch');
        }

        $paper = app(BundleRenderer::class)->paper();
        $paperWidthMm = (float) $paper['width'];
        $paperHeightMm = (float) $paper['height'];
        $ppi = 300;
        $rasterPages = [];
        try {
            $rasterPages = app(OmrPdfPageRasterizer::class)->rasterize($pdfPath, [
                'width_mm' => $paperWidthMm,
                'height_mm' => $paperHeightMm,
                'ppi' => $ppi,
            ]);
            if (count($rasterPages) !== $version->pageCount()) {
                throw new RuntimeException('canonical_preview_page_count_mismatch');
            }
            $expectedWidth = (int) round($paperWidthMm / 25.4 * $ppi);
            $expectedHeight = (int) round($paperHeightMm / 25.4 * $ppi);
            foreach ($rasterPages as $index => $rasterPage) {
                if ((int) ($rasterPage['page_no'] ?? 0) !== $index + 1) {
                    throw new RuntimeException('canonical_preview_page_order_mismatch');
                }

                $width = (int) ($rasterPage['width'] ?? 0);
                $height = (int) ($rasterPage['height'] ?? 0);
                if ($width < 1 || $height < 1) {
                    throw new RuntimeException('canonical_preview_raster_dimensions_invalid');
                }
                if (abs($width - $expectedWidth) > max(2, (int) round($expectedWidth * 0.1))
                    || abs($height - $expectedHeight) > max(2, (int) round($expectedHeight * 0.1))) {
                    throw new RuntimeException('canonical_preview_paper_dimensions_mismatch');
                }
            }

            $store = app(OmrTemplateAssetStore::class);
            foreach ($rasterPages as $index => $rasterPage) {
                $pageNo = $index + 1;
                if ($existingPages->has($pageNo)) {
                    continue;
                }

                $bytes = file_get_contents((string) $rasterPage['path']);
                if (! is_string($bytes)
                    || $bytes === ''
                    || hash('sha256', $bytes) !== (string) $rasterPage['sha256']
                    || strlen($bytes) !== (int) $rasterPage['bytes']) {
                    throw new RuntimeException('canonical_preview_raster_file_corrupt');
                }

                $width = (int) $rasterPage['width'];
                $height = (int) $rasterPage['height'];
                $asset = $store->putVersionAsset(
                    $version,
                    OmrTemplateAsset::KIND_RASTER_PAGE,
                    $bytes,
                    'image/png',
                    $pageNo,
                    [
                        'page_no' => $pageNo,
                        'width_px' => $width,
                        'height_px' => $height,
                        'paper_width_mm' => $paperWidthMm,
                        'paper_height_mm' => $paperHeightMm,
                        'ppi' => $ppi,
                        'source_print_pdf_sha256' => $printAsset->sha256,
                        'source_native_bundle_sha256' => $nativeBundleHash,
                    ],
                );
                $storedBytes = Storage::disk($asset->disk)->get($asset->path);
                if ((int) $asset->template_version_id !== (int) $version->id
                    || $asset->kind !== OmrTemplateAsset::KIND_RASTER_PAGE
                    || (int) $asset->page_no !== $pageNo
                    || $asset->sha256 !== hash('sha256', $bytes)
                    || $asset->disk !== $disk
                    || ! is_string($storedBytes)
                    || hash('sha256', $storedBytes) !== $asset->sha256) {
                    throw new RuntimeException('canonical_preview_raster_storage_mismatch');
                }
            }
        } finally {
            $directories = [];
            foreach ($rasterPages as $rasterPage) {
                $path = (string) ($rasterPage['path'] ?? '');
                if ($path !== '') {
                    @unlink($path);
                    $directories[dirname($path)] = true;
                }
            }
            foreach (array_keys($directories) as $directory) {
                @rmdir($directory);
            }
        }
    }

    private function ensurePrintedBlank(OmrAnswerSheetTemplateVersion $version, User $actor): OmrBlankInstance
    {
        $instance = OmrBlankInstance::query()
            ->where('template_version_id', $version->id)
            ->where('status', OmrBlankInstance::STATUS_PRINTED)
            ->orderBy('page_set_no')
            ->first();
        if ($instance !== null) {
            return $instance->fresh('pages');
        }

        app(PrintOmrBlankInstances::class)->handle(
            $version,
            $actor,
            1,
            'omr-canonical-demo-blank-v1',
        );
        $job = PrintJob::query()
            ->where('artifact_type', PrintJob::ARTIFACT_BLANK_SHEET)
            ->where('scope_type', 'template_version')
            ->where('scope_id', $version->id)
            ->latest('id')
            ->firstOrFail();
        if (! OmrBlankInstance::query()->where('template_version_id', $version->id)->where('status', OmrBlankInstance::STATUS_PRINTED)->exists()
            && $job->status !== PrintJob::STATUS_READY) {
            (new RenderOmrBlankInstanceBatchJob($job->id, $actor->id))->handle(
                app(OmrBlankInstanceService::class),
                app(OmrBlankPrintOrchestrator::class),
                app(RecordAuditEvent::class),
            );
        }

        $instance = OmrBlankInstance::query()
            ->where('template_version_id', $version->id)
            ->where('status', OmrBlankInstance::STATUS_PRINTED)
            ->orderBy('page_set_no')
            ->first();
        if ($instance === null) {
            throw new RuntimeException('canonical_scanner_blank_instance_missing');
        }

        return $instance->fresh('pages');
    }

    private function ensurePaperLifecycleDemoStates(
        OmrAnswerSheetTemplateVersion $version,
        GradingSession $grading,
        User $actor,
        OmrBlankInstance $authorizedSource,
        OmrCaptureSheet $demoSheet,
    ): void {
        $pageCount = max(1, $version->pageCount());
        $artifactHashes = array_map(
            static fn (int $pageNo): string => hash('sha256', 'omr-paper-demo-page-'.$pageNo),
            range(1, $pageCount),
        );
        $service = app(OmrBlankInstanceService::class);

        $master = OmrBlankInstance::query()
            ->where('template_version_id', $version->id)
            ->where('copy_policy', OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER)
            ->first();
        if ($master === null) {
            $master = $service->createPrintedSet(
                $version,
                $actor,
                $pageCount,
                $artifactHashes,
                copyPolicy: OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
                intendedCopyCount: 30,
            );
        }

        $unauthorizedSource = OmrBlankInstance::query()
            ->where('template_version_id', $version->id)
            ->where('copy_policy', OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE)
            ->whereKeyNot($authorizedSource->id)
            ->first();
        if ($unauthorizedSource === null) {
            $unauthorizedSource = $service->createPrintedSet($version, $actor, $pageCount, $artifactHashes);
        }

        $demoPage = $demoSheet->pages()->orderBy('page_no')->firstOrFail();
        $demoPage->loadMissing('blankInstanceClaim.blankInstancePage.instance');
        $claimedInstance = $demoPage->blankInstanceClaim?->blankInstancePage?->instance;
        if ($claimedInstance !== null && (int) $claimedInstance->template_version_id === (int) $version->id) {
            $authorizedSource = $claimedInstance;
        }
        if ($unauthorizedSource->is($authorizedSource)) {
            $unauthorizedSource = OmrBlankInstance::query()
                ->where('template_version_id', $version->id)
                ->where('copy_policy', OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE)
                ->whereKeyNot($authorizedSource->id)
                ->first() ?? $service->createPrintedSet($version, $actor, $pageCount, $artifactHashes);
        }
        $service->registerCapture($authorizedSource, 1, $demoPage, $actor, $grading->exam_session_id);
        $unauthorizedPage = $this->replicateLifecycleCapturePage($demoSheet, $demoPage, '00000000-0000-4000-8000-000000000141');
        $unauthorizedPage->loadMissing('blankInstanceClaim.blankInstancePage.instance');
        $claimedUnauthorized = $unauthorizedPage->blankInstanceClaim?->blankInstancePage?->instance;
        if ($claimedUnauthorized !== null && (int) $claimedUnauthorized->template_version_id === (int) $version->id) {
            $unauthorizedSource = $claimedUnauthorized;
        }
        $service->registerCapture($unauthorizedSource, 1, $unauthorizedPage, $actor, $grading->exam_session_id);
        foreach ([
            '00000000-0000-4000-8000-000000000142',
            '00000000-0000-4000-8000-000000000143',
        ] as $clientCaptureId) {
            $copyPage = $this->replicateLifecycleCapturePage($demoSheet, $demoPage, $clientCaptureId);
            $service->registerCapture($master, 1, $copyPage, $actor, $grading->exam_session_id);
            $copyPage->loadMissing('blankInstanceClaim.blankInstancePage.instance');
            $claimedMaster = $copyPage->blankInstanceClaim?->blankInstancePage?->instance;
            if ($claimedMaster !== null && (int) $claimedMaster->template_version_id === (int) $version->id) {
                $master = $claimedMaster;
            }
        }

        $sourcePage = $authorizedSource->pages()->orderBy('page_no')->firstOrFail();
        OmrPaperReproductionAuthorization::query()
            ->where('exam_session_id', $grading->exam_session_id)
            ->where('status', OmrPaperReproductionAuthorization::STATUS_OPEN)
            ->update([
                'status' => OmrPaperReproductionAuthorization::STATUS_VOID,
                'updated_at' => now(),
            ]);
        $authorization = OmrPaperReproductionAuthorization::query()
            ->where('exam_session_id', $grading->exam_session_id)
            ->where('blank_instance_page_id', $sourcePage->id)
            ->latest('id')
            ->first() ?? new OmrPaperReproductionAuthorization;
        $usedClaims = $authorization->exists ? $authorization->claims()->count() : 0;
        $authorization->forceFill([
            'exam_session_id' => $grading->exam_session_id,
            'blank_instance_page_id' => $sourcePage->id,
            'maximum_claims' => 2,
            'used_claims' => $usedClaims,
            'status' => OmrPaperReproductionAuthorization::STATUS_OPEN,
            'reason' => 'Seeded supervised emergency-copy scenario.',
            'expires_at' => now()->addHours(4),
            'lock_version' => 0,
            'authorized_by_user_id' => $actor->id,
            'reconciled_by_user_id' => null,
            'reconciled_at' => null,
        ])->save();
        $authorizedCopyPage = $this->replicateLifecycleCapturePage(
            $demoSheet,
            $demoPage,
            '00000000-0000-4000-8000-000000000144',
        );
        $service->registerCapture($authorizedSource, 1, $authorizedCopyPage, $actor, $grading->exam_session_id);
    }

    private function replicateLifecycleCapturePage(
        OmrCaptureSheet $sourceSheet,
        OmrCaptureSheetPage $sourcePage,
        string $clientCaptureId,
    ): OmrCaptureSheetPage {
        $sheet = OmrCaptureSheet::query()
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        if ($sheet !== null && (int) $sheet->batch_id !== (int) $sourceSheet->batch_id) {
            foreach ($sheet->pages as $p) {
                $p->blankInstanceClaim()?->delete();
                $p->delete();
            }
            $sheet->delete();
            $sheet = null;
        }

        if ($sheet === null) {
            $sheet = OmrCaptureSheet::query()->create([
                ...$sourceSheet->replicate(['client_capture_id'])->getAttributes(),
                'batch_id' => $sourceSheet->batch_id,
                'client_capture_id' => $clientCaptureId,
                'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                'version' => 1,
                'admission_attempt_id' => null,
            ]);
        }

        return $sheet->pages()->firstOrCreate(
            ['page_no' => 1],
            $sourcePage->replicate(['sheet_id', 'page_no'])->getAttributes(),
        );
    }

    /**
     * @return array{
     *     duplicate_sheet_id: int,
     *     qr_invalid_pending_id: int,
     *     key_missing_pending_id: int,
     *     ambiguous_pending_id: int
     * }
     */
    private function ensureRecoveryFixtures(
        GradingSession $grading,
        OmrCaptureBatch $batch,
        OmrCaptureSheet $demoSheet,
        User $actor,
    ): array {
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $room = Room::query()->whereIn('code', ['ITBR-104', 'RM-LabA'])->firstOrFail();

        // 1. Duplicate sheet fixture
        $dupCaptureId = '00000000-0000-4000-8000-000000000132';
        $dupSheet = OmrCaptureSheet::query()
            ->where('batch_id', $batch->id)
            ->where('client_capture_id', $dupCaptureId)
            ->first();
        if ($dupSheet === null) {
            $dupSheet = OmrCaptureSheet::query()->create([
                ...$demoSheet->replicate(['client_capture_id', 'id'])->getAttributes(),
                'batch_id' => $batch->id,
                'client_capture_id' => $dupCaptureId,
                'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                'admission_attempt_id' => null,
                'detected_applicant_number' => $demoSheet->detected_applicant_number,
                'version' => 1,
                'captured_at' => now()->subMinutes(10),
            ]);
            $demoPage = $demoSheet->pages()->orderBy('page_no')->first();
            if ($demoPage !== null) {
                $dupSheet->pages()->firstOrCreate(
                    ['page_no' => 1],
                    $demoPage->replicate(['sheet_id', 'page_no', 'id'])->getAttributes(),
                );
            }
        }

        // 2. Alternative session for candidate ambiguity
        $altSession = ExamSession::query()->firstOrCreate(
            ['code' => 'EX-OMR-ALT'],
            [
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'status' => ExamSession::STATUS_COMPLETED,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => now()->subHours(4),
                'effective_end_at' => now()->subHour(),
                'version' => 1,
            ],
        );
        $altGrading = GradingSession::query()->firstOrCreate(
            ['exam_session_id' => $altSession->id],
            [
                'status' => GradingSession::STATUS_OPEN,
                'opened_at' => now(),
            ],
        );

        $assetStore = app(SensitiveAssetStore::class);
        $dummyBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        $dummyHash = hash('sha256', $dummyBytes);
        $dummyHmac = $assetStore->hmac($dummyBytes);

        // 3. QR invalid pending capture
        $qrInvalidCaptureId = '00000000-0000-4000-8000-000000000201';
        $qrInvalidAssetPath = 'omr/pending/demo/'.$qrInvalidCaptureId.'.png';
        if (! Storage::disk('sensitive')->exists($qrInvalidAssetPath)) {
            $assetStore->store($dummyBytes, $qrInvalidAssetPath);
        }
        $qrInvalidPending = OmrPendingCapture::query()->updateOrCreate(
            ['client_capture_id' => $qrInvalidCaptureId],
            [
                'status' => OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                'capture_mode' => 'auto',
                'page_no' => 1,
                'page_count' => 1,
                'qr_state' => 'present_invalid',
                'analysis_code' => 'qr_invalid',
                'detected_applicant_number' => null,
                'resolution_json' => [
                    'outcome' => 'selection_required',
                    'code' => 'qr_invalid',
                    'resolution_code' => 'qr_invalid',
                    'candidates' => [],
                ],
                'institution_id' => $cycle->institution_id,
                'academic_cycle_id' => $cycle->id,
                'asset_path' => $qrInvalidAssetPath,
                'asset_hmac' => $dummyHmac,
                'asset_format_version' => 'v1',
                'asset_state' => OmrPendingCapture::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($dummyBytes),
                'input_hash' => $dummyHash,
                'uploaded_by_user_id' => $actor->id,
                'version' => 0,
                'expires_at' => now()->addDays(7),
            ],
        );

        // 4. Key missing pending capture
        $keyMissingCaptureId = '00000000-0000-4000-8000-000000000202';
        $keyMissingAssetPath = 'omr/pending/demo/'.$keyMissingCaptureId.'.png';
        if (! Storage::disk('sensitive')->exists($keyMissingAssetPath)) {
            $assetStore->store($dummyBytes, $keyMissingAssetPath);
        }
        $keyMissingPending = OmrPendingCapture::query()->updateOrCreate(
            ['client_capture_id' => $keyMissingCaptureId],
            [
                'status' => OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                'capture_mode' => 'auto',
                'page_no' => 1,
                'page_count' => 1,
                'qr_state' => 'verified',
                'analysis_code' => 'key_missing',
                'detected_applicant_number' => null,
                'resolution_json' => [
                    'outcome' => 'selection_required',
                    'code' => 'key_missing',
                    'resolution_code' => 'key_missing',
                    'candidates' => [],
                ],
                'institution_id' => $cycle->institution_id,
                'academic_cycle_id' => $cycle->id,
                'asset_path' => $keyMissingAssetPath,
                'asset_hmac' => $dummyHmac,
                'asset_format_version' => 'v1',
                'asset_state' => OmrPendingCapture::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($dummyBytes),
                'input_hash' => $dummyHash,
                'uploaded_by_user_id' => $actor->id,
                'version' => 0,
                'expires_at' => now()->addDays(7),
            ],
        );

        // 5. Session ambiguous pending capture (bounded candidate selection)
        $ambiguousCaptureId = '00000000-0000-4000-8000-000000000203';
        $ambiguousAssetPath = 'omr/pending/demo/'.$ambiguousCaptureId.'.png';
        if (! Storage::disk('sensitive')->exists($ambiguousAssetPath)) {
            $assetStore->store($dummyBytes, $ambiguousAssetPath);
        }
        $ambiguousPending = OmrPendingCapture::query()->updateOrCreate(
            ['client_capture_id' => $ambiguousCaptureId],
            [
                'status' => OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                'capture_mode' => 'auto',
                'page_no' => 1,
                'page_count' => 1,
                'qr_state' => 'verified',
                'analysis_code' => 'session_ambiguous',
                'detected_applicant_number' => null,
                'resolution_json' => [
                    'outcome' => 'selection_required',
                    'code' => 'session_ambiguous',
                    'resolution_code' => 'session_ambiguous',
                    'candidates' => [
                        [
                            'id' => (int) $grading->id,
                            'exam_code' => (string) ($grading->examSession?->code ?? 'EX-OMR-CANONICAL'),
                            'room_name' => (string) ($grading->examSession?->room?->name ?? 'Laboratory layout'),
                            'monitoring_url' => route('exams.sessions.monitoring', $grading->examSession),
                        ],
                        [
                            'id' => (int) $altGrading->id,
                            'exam_code' => (string) $altSession->code,
                            'room_name' => (string) ($altSession->room?->name ?? 'Laboratory layout'),
                            'monitoring_url' => route('exams.sessions.monitoring', $altSession),
                        ],
                    ],
                ],
                'institution_id' => $cycle->institution_id,
                'academic_cycle_id' => $cycle->id,
                'asset_path' => $ambiguousAssetPath,
                'asset_hmac' => $dummyHmac,
                'asset_format_version' => 'v1',
                'asset_state' => OmrPendingCapture::ASSET_LIVE,
                'original_mime' => 'image/png',
                'original_bytes' => strlen($dummyBytes),
                'input_hash' => $dummyHash,
                'uploaded_by_user_id' => $actor->id,
                'version' => 0,
                'expires_at' => now()->addDays(7),
            ],
        );

        return [
            'duplicate_sheet_id' => (int) $dupSheet->id,
            'qr_invalid_pending_id' => (int) $qrInvalidPending->id,
            'key_missing_pending_id' => (int) $keyMissingPending->id,
            'ambiguous_pending_id' => (int) $ambiguousPending->id,
        ];
    }

    private function ensureApplicantReferenceCode(AdmissionAttempt $attempt, string $targetCode, AcademicCycle $cycle): void
    {
        if ($attempt->reference_code === $targetCode) {
            return;
        }

        $existing = AdmissionAttempt::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('reference_code', $targetCode)
            ->first();

        if ($existing !== null && $existing->id !== $attempt->id) {
            $tempCode = 'APP-TEMP-'.uniqid();
            $currentCode = $attempt->reference_code;
            $existing->forceFill(['reference_code' => $tempCode])->save();
            $attempt->forceFill(['reference_code' => $targetCode])->save();
            $existing->forceFill(['reference_code' => $currentCode])->save();
        } else {
            $attempt->forceFill(['reference_code' => $targetCode])->save();
        }
    }

    private function ensureMissingSubmissionApplicant(AcademicCycle $cycle, User $actor): AdmissionAttempt
    {
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $email = 'omr.recovery.missing.sub@nexiam.test';
        $attempt = AdmissionAttempt::query()
            ->where('academic_cycle_id', $cycle->id)
            ->whereHas('identity', fn ($query) => $query->where('email', $email))
            ->latest('id')
            ->first();
        if ($attempt === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new RuntimeException('A designated published PublicPolicy revision is required for OMR demo intake.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The designated intake policy has no published revision.');
            $result = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: 'Missing',
                    lastName: 'Submission',
                    birthdate: '2001-01-02',
                    sex: 'male',
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSIT', 'BA-Psych'],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234568',
                    lastSchoolEnrolled: 'NEXIAM OMR Demo School',
                    strand: 'STEM',
                    addressLine: '2 Recovery Way',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->getKey(),
                    publicPolicyRevisionId: (int) $revision->getKey(),
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                'omr-canonical-seeder',
            );
            $attempt = $result['attempt'];
        }
        if (! $attempt->isExamEligible()) {
            $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
            $attempt = app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }

        $this->ensureApplicantReferenceCode($attempt, 'APP-2026-00002', $cycle);

        return $attempt->fresh();
    }

    private function ensureExamAssignmentsAndAttendance(
        ExamSession $session,
        AdmissionAttempt $attempt,
        AdmissionAttempt $missingSubAttempt,
        User $actor,
    ): void {
        $assignment = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $attempt->id)
            ->first();
        if ($assignment === null) {
            $assignment = ExamAssignment::query()->create([
                'exam_session_id' => $session->id,
                'admission_attempt_id' => $attempt->id,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            ]);
        }
        $assignment->attendanceRecord()->firstOrCreate(
            [],
            [
                'recorded_by_user_id' => $actor->id,
                'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
                'idempotency_key' => 'omr-canonical-attendance-'.$assignment->id,
                'recorded_at' => now(),
            ],
        );
        if (! $assignment->submissionRecord()->exists()) {
            app(RecordExamSubmission::class)->handle(
                $assignment,
                $actor,
                'omr-canonical-submission-'.$assignment->id,
            );
        }

        $missingAssignment = ExamAssignment::query()
            ->where('exam_session_id', $session->id)
            ->where('admission_attempt_id', $missingSubAttempt->id)
            ->first();
        if ($missingAssignment === null) {
            $missingAssignment = ExamAssignment::query()->create([
                'exam_session_id' => $session->id,
                'admission_attempt_id' => $missingSubAttempt->id,
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            ]);
        }
        $missingAssignment->attendanceRecord()->firstOrCreate(
            [],
            [
                'recorded_by_user_id' => $actor->id,
                'outcome' => ExamAttendanceRecord::OUTCOME_PRESENT,
                'idempotency_key' => 'omr-canonical-attendance-'.$missingAssignment->id,
                'recorded_at' => now(),
            ],
        );
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

    private function seedOperationalWorkbenchStates(User $actor): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $template = OmrAnswerSheetTemplateVersion::query()
            ->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->firstOrFail();
        $booklet = OmrQuestionBookletVersion::query()
            ->where('template_version_id', (int) $template->id)
            ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
            ->whereNotNull('finalized_at')
            ->orderByDesc('id')
            ->first();

        if ($booklet === null) {
            $this->command?->warn('No finalized routed OMR booklet found; skipping operational diagnostic fixtures.');

            return;
        }

        $reviewRun = $this->ensureDiagnosticRun(
            $actor,
            $institution,
            $cycle,
            $template,
            $booklet,
            $this->canonicalSeedUuid('operational-workbench-review-run|'.$template->id.'|'.$booklet->id),
            OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
            OmrDiagnosticRun::PHASE_REVIEW,
            4,
        );
        $this->ensureDiagnosticReviewCapture($reviewRun, $actor);

        $recoveryRun = $this->ensureDiagnosticRun(
            $actor,
            $institution,
            $cycle,
            $template,
            $booklet,
            $this->canonicalSeedUuid('operational-workbench-recovery-run|'.$template->id.'|'.$booklet->id),
            OmrDiagnosticRun::STATUS_NEEDS_REVIEW,
            OmrDiagnosticRun::PHASE_RECOVERY,
            1,
        );
        $this->ensureDiagnosticRecoveryCapture($recoveryRun, $actor);

        $this->command?->info(sprintf(
            'OMR diagnostic workbench fixtures ready: review run %s and recovery run %s.',
            (string) $reviewRun->public_id,
            (string) $recoveryRun->public_id,
        ));
    }

    private function ensureDiagnosticRun(
        User $actor,
        Institution $institution,
        AcademicCycle $cycle,
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        string $publicId,
        string $status,
        string $phase,
        int $revision,
    ): OmrDiagnosticRun {
        $run = OmrDiagnosticRun::query()->where('public_id', $publicId)->first();
        if ($run !== null) {
            if ((int) $run->institution_id !== (int) $institution->id
                || (int) $run->academic_cycle_id !== (int) $cycle->id
                || (int) $run->template_version_id !== (int) $template->id
                || (int) $run->booklet_version_id !== (int) $booklet->id
                || (string) $run->created_by_user_id !== (string) $actor->id) {
                throw new RuntimeException('omr_diagnostic_demo_run_context_mismatch');
            }

            return $run;
        }

        return OmrDiagnosticRun::query()->create([
            'public_id' => $publicId,
            'execution_kind' => OmrDiagnosticRun::EXECUTION_KIND_DIAGNOSTIC,
            'institution_id' => $institution->id,
            'academic_cycle_id' => $cycle->id,
            'template_version_id' => $template->id,
            'booklet_version_id' => $booklet->id,
            'declared_set_code' => 'A',
            'status' => $status,
            'phase' => $phase,
            'revision' => $revision,
            'created_by_user_id' => $actor->id,
        ]);
    }

    private function ensureDiagnosticReviewCapture(
        OmrDiagnosticRun $run,
        User $actor,
    ): void {
        $clientCaptureId = $this->canonicalSeedUuid('operational-workbench-review-capture|'.$run->public_id);
        $bytes = $this->diagnosticFixtureBytes();
        $assetStore = app(SensitiveAssetStore::class);
        $inputAsset = $this->ensureDiagnosticAsset($run, $assetStore, $clientCaptureId, 'capture_input', $bytes);
        $alignedAsset = $this->ensureDiagnosticAsset($run, $assetStore, $clientCaptureId, 'capture_aligned', $bytes);
        $overlayAsset = $this->ensureDiagnosticAsset($run, $assetStore, $clientCaptureId, 'capture_overlay', $bytes);
        $items = $this->diagnosticReviewItems();
        $evidence = [
            'outcome' => 'succeeded',
            'code' => null,
            'detail' => null,
            'page_no' => 1,
            'target' => ['kind' => 'compiled_version', 'id' => (int) $run->template_version_id],
            'hashes' => [
                'input' => (string) $inputAsset->sha256,
                'aligned' => (string) $alignedAsset->sha256,
                'overlay' => (string) $overlayAsset->sha256,
            ],
            'assets' => [
                'captured' => ['mime' => 'image/png', 'sha256' => (string) $inputAsset->sha256],
                'aligned' => ['mime' => 'image/png', 'sha256' => (string) $alignedAsset->sha256],
                'overlay' => ['mime' => 'image/png', 'sha256' => (string) $overlayAsset->sha256],
            ],
            'gates' => ['layout' => 'passed', 'identity' => 'advisory'],
            'marker_matches' => null,
            'field_block_shifts' => [],
            'items' => $items,
            'identity' => [],
            'summary' => [
                'total' => count($items),
                'ambiguous' => 1,
                'missing' => 0,
            ],
            'timings' => ['fixture_ms' => 0.0],
            'issues' => [['code' => 'ambiguous_mark', 'severity' => 'review']],
            'calibration_status' => 'unqualified',
            'provider_item_evidence' => null,
        ];
        $evidence['evidence_hash'] = hash(
            'sha256',
            json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
        $detectedAnswers = [];
        foreach ($items as $item) {
            $detectedAnswers[(string) $item['native_label']] = [
                'state' => $item['state'],
                'selected_option' => $item['selected_option'],
                'confidence' => $item['confidence'],
            ];
        }

        $capture = OmrDiagnosticCapture::query()
            ->where('diagnostic_run_id', (int) $run->id)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        if ($capture === null) {
            $capture = OmrDiagnosticCapture::query()->create([
                'diagnostic_run_id' => $run->id,
                'client_capture_id' => $clientCaptureId,
                'status' => OmrDiagnosticCapture::STATUS_NEEDS_REVIEW,
                'version' => 2,
                'input_asset_id' => $inputAsset->id,
                'input_asset_hash' => $inputAsset->sha256,
                'aligned_asset_id' => $alignedAsset->id,
                'aligned_asset_hash' => $alignedAsset->sha256,
                'overlay_asset_id' => $overlayAsset->id,
                'overlay_asset_hash' => $overlayAsset->sha256,
                'evidence_json' => $evidence,
                'evidence_hash' => $evidence['evidence_hash'],
                'detected_answers_json' => $detectedAnswers,
                'effective_answers_json' => null,
                'projected_score_json' => null,
                'created_by_user_id' => $actor->id,
                'captured_at' => now()->subMinutes(8),
            ]);
        }

        foreach ($items as $item) {
            OmrDiagnosticItemEvidence::query()->firstOrCreate(
                [
                    'diagnostic_capture_id' => (int) $capture->id,
                    'item_key' => (string) $item['native_label'],
                ],
                [
                    'page_no' => 1,
                    'area_code' => $item['area_code'],
                    'question_no' => $item['question_no'],
                    'option_code' => $item['selected_option'],
                    'option_evidence_json' => $item['option_observations'],
                    'machine_proposal_json' => $item,
                    'machine_confidence' => $item['confidence'],
                    'evidence_hash' => $item['evidence_hash'],
                    'evidence_version' => 'v1',
                ],
            );
        }

        $reviewItem = OmrDiagnosticItemEvidence::query()
            ->where('diagnostic_capture_id', (int) $capture->id)
            ->where('item_key', (string) $items[0]['native_label'])
            ->firstOrFail();
        OmrDiagnosticReviewRevision::query()->firstOrCreate(
            [
                'diagnostic_item_evidence_id' => (int) $reviewItem->id,
                'idempotency_key' => 'omr-diagnostic-demo-review-'.$run->public_id,
            ],
            [
                'revision' => 1,
                'parent_revision' => 2,
                'decision' => OmrReviewDecision::ConfirmMachine,
                'final_option' => (string) $reviewItem->option_code,
                'reason' => null,
                'note' => 'Seeded review fixture; confirm the observed machine mark.',
                'reviewed_evidence_hash' => (string) $reviewItem->evidence_hash,
                'actor_id' => $actor->id,
            ],
        );
    }

    private function ensureDiagnosticRecoveryCapture(
        OmrDiagnosticRun $run,
        User $actor,
    ): void {
        $clientCaptureId = $this->canonicalSeedUuid('operational-workbench-recovery-capture|'.$run->public_id);
        $bytes = $this->diagnosticFixtureBytes();
        $assetStore = app(SensitiveAssetStore::class);
        $inputAsset = $this->ensureDiagnosticAsset($run, $assetStore, $clientCaptureId, 'capture_input', $bytes);
        $existing = OmrDiagnosticCapture::query()
            ->where('diagnostic_run_id', (int) $run->id)
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        if ($existing !== null) {
            return;
        }

        OmrDiagnosticCapture::query()->create([
            'diagnostic_run_id' => $run->id,
            'client_capture_id' => $clientCaptureId,
            'status' => OmrDiagnosticCapture::STATUS_FAILED,
            'version' => 1,
            'input_asset_id' => $inputAsset->id,
            'input_asset_hash' => $inputAsset->sha256,
            'evidence_json' => null,
            'evidence_hash' => null,
            'detected_answers_json' => [],
            'effective_answers_json' => null,
            'projected_score_json' => null,
            'failure_code' => 'analysis_failed',
            'failure_detail' => 'Seeded recovery fixture: retry the capture after reviewing the input.',
            'created_by_user_id' => $actor->id,
            'captured_at' => now()->subMinutes(14),
            'completed_at' => now()->subMinutes(13),
        ]);
    }

    private function ensureDiagnosticAsset(
        OmrDiagnosticRun $run,
        SensitiveAssetStore $assetStore,
        string $clientCaptureId,
        string $role,
        string $bytes,
    ): OmrTemplateAsset {
        $path = 'omr/diagnostic/'.(string) $run->public_id.'/'.hash('sha256', $clientCaptureId).'_'.$role.'.bin';
        $sha256 = hash('sha256', $bytes);
        $asset = OmrTemplateAsset::query()
            ->where('disk', 'sensitive')
            ->where('path', $path)
            ->first();
        if ($asset !== null) {
            if ((int) $asset->template_version_id !== (int) $run->template_version_id
                || (string) $asset->sha256 !== $sha256) {
                throw new RuntimeException('omr_diagnostic_demo_asset_context_mismatch');
            }
            $assetStore->store($bytes, $path);

            return $asset;
        }

        $assetStore->store($bytes, $path);

        return OmrTemplateAsset::query()->create([
            'template_version_id' => $run->template_version_id,
            'kind' => OmrTemplateAsset::KIND_EVIDENCE,
            'page_no' => 1,
            'disk' => 'sensitive',
            'path' => $path,
            'mime' => 'image/png',
            'bytes' => strlen($bytes),
            'sha256' => $sha256,
            'metadata' => [
                'source' => 'omr_diagnostic',
                'diagnostic_run_id' => (int) $run->id,
                'role' => $role,
                'width' => 1,
                'height' => 1,
                'hmac' => $assetStore->hmac($bytes),
                'seed' => 'omr-operational-workbench-v1',
            ],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function diagnosticReviewItems(): array
    {
        $items = [
            [
                'native_label' => 'PROFILE_VERBAL_Q_001',
                'position' => 1,
                'area_code' => 'PROFILE_VERBAL',
                'question_no' => 1,
                'selected_option' => 'A',
                'state' => OmrDetectedItem::STATE_MARKED,
                'confidence' => 0.94,
                'region' => ['x' => 0.12, 'y' => 0.18, 'w' => 0.02, 'h' => 0.02],
                'observed_options' => ['A'],
                'option_observations' => [
                    ['code' => 'A', 'mean' => 0.94, 'classification' => 'observed'],
                    ['code' => 'B', 'mean' => 0.08, 'classification' => 'not_observed'],
                    ['code' => 'C', 'mean' => 0.06, 'classification' => 'not_observed'],
                    ['code' => 'D', 'mean' => 0.05, 'classification' => 'not_observed'],
                ],
                'issue_codes' => [],
                'legacy_evidence_limited' => false,
            ],
            [
                'native_label' => 'PROFILE_VERBAL_Q_002',
                'position' => 2,
                'area_code' => 'PROFILE_VERBAL',
                'question_no' => 2,
                'selected_option' => null,
                'state' => OmrDetectedItem::STATE_AMBIGUOUS,
                'confidence' => 0.48,
                'region' => ['x' => 0.15, 'y' => 0.18, 'w' => 0.02, 'h' => 0.02],
                'observed_options' => ['B', 'C'],
                'option_observations' => [
                    ['code' => 'A', 'mean' => 0.12, 'classification' => 'not_observed'],
                    ['code' => 'B', 'mean' => 0.48, 'classification' => 'candidate'],
                    ['code' => 'C', 'mean' => 0.46, 'classification' => 'candidate'],
                    ['code' => 'D', 'mean' => 0.11, 'classification' => 'not_observed'],
                ],
                'issue_codes' => ['ambiguous_mark'],
                'legacy_evidence_limited' => false,
            ],
            [
                'native_label' => 'PROFILE_QUANT_Q_001',
                'position' => 3,
                'area_code' => 'PROFILE_QUANT',
                'question_no' => 1,
                'selected_option' => null,
                'state' => OmrDetectedItem::STATE_BLANK,
                'confidence' => 0.12,
                'region' => ['x' => 0.12, 'y' => 0.22, 'w' => 0.02, 'h' => 0.02],
                'observed_options' => [],
                'option_observations' => [
                    ['code' => 'A', 'mean' => 0.12, 'classification' => 'not_observed'],
                    ['code' => 'B', 'mean' => 0.10, 'classification' => 'not_observed'],
                    ['code' => 'C', 'mean' => 0.11, 'classification' => 'not_observed'],
                    ['code' => 'D', 'mean' => 0.09, 'classification' => 'not_observed'],
                ],
                'issue_codes' => ['blank'],
                'legacy_evidence_limited' => false,
            ],
        ];

        foreach ($items as &$item) {
            $item['evidence_hash'] = hash(
                'sha256',
                json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
        }
        unset($item);

        return $items;
    }

    private function diagnosticFixtureBytes(): string
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        );
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('omr_diagnostic_demo_fixture_invalid');
        }

        return $bytes;
    }
}
