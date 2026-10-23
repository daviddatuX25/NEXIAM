<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Authorization\CapabilityCatalog;
use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\DocumentTemplate\Validation\DocumentTemplateAnalyzer;
use App\Jobs\Reports\RenderReportTemplateThumbnailJob;
use App\Jobs\ResultSheet\RenderResultSheetThumbnailJob;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\Reports\ReportTemplate;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\NoticeMessageState;
use App\Notifications\Templates\NoticePrintContext;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use App\Notifications\Templates\Surfaces\NoticeTemplateSurface;
use App\Reports\Surfaces\CourseQuotaQualifiersSurface;
use App\Reports\Surfaces\PassingReportSurface;
use App\ResultSheet\Builder\ResultSheetBuilderCompiler;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Surfaces\ResultSheetSurface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Throwable;
use ZipArchive;

/**
 * Minimal, explicitly scoped fixtures for the isolated document-recovery runtime.
 * This helper never runs application seeders or activation workflows.
 */
final class DocumentRecoveryFixtures
{
    /**
     * @return list<array{
     *     id: string,
     *     surface: string,
     *     source_kind: string,
     *     marker: string,
     *     source_sha256: string,
     *     builder_sha256?: string,
     *     actor: array{id: int, email: string, password: string},
     *     template: array{id: int, name: string, is_active: false, list_url: string},
     *     libreoffice_available: bool
     * }>
     */
    public function prepare(string $runId, bool $libreOfficeAvailable): array
    {
        $this->assertIsolatedEnvironment();
        $this->assertTargetTablesAreEmpty();

        if ($libreOfficeAvailable !== true) {
            throw new RuntimeException('Document recovery fixtures require the isolated image LibreOffice converter.');
        }

        $suffix = strtoupper(substr(hash('sha256', $runId), 0, 10));
        $resultSurface = app(ResultSheetSurface::class);
        $resultCatalog = $resultSurface->placeholderCatalog();
        $resultKeys = $this->catalogKeys($resultCatalog);
        $resultWordPath = ResultSheetDocxFixture::createValid($resultKeys);
        $reportWordPaths = [];
        $storedPaths = [];

        try {
            $this->appendVisibleMarker($resultWordPath, 'RECOVERY_RESULT_WORD_'.$suffix);
            $resultAnalysis = app(DocumentTemplateAnalyzer::class)->analyze(
                app(DocxTextExtractor::class)->text($resultWordPath),
                $resultSurface,
            );
            if ($resultAnalysis['valid'] !== true) {
                throw new RuntimeException('The result Word fixture failed the real placeholder catalog analysis.');
            }
            $passingSurface = app(PassingReportSurface::class);
            $quotaSurface = app(CourseQuotaQualifiersSurface::class);
            $reportWordPaths['passing_report'] = $this->createReportWordDocument(
                $passingSurface->placeholderCatalog(),
                'RECOVERY_PASSING_REPORT_WORD_'.$suffix,
            );

            $resultBuilder = ResultSheetBuilderSchema::defaultDocument();
            $resultBuilder['components'][0]['props']['title'] = 'RECOVERY_RESULT_BUILDER_'.$suffix;
            $resultBuilderCompile = app(ResultSheetBuilderCompiler::class)->compile($resultBuilder);
            $this->assertNoDiagnostics($resultBuilderCompile['diagnostics'], 'result builder');

            $quotaBuilder = $quotaSurface->defaultBlueprint('a4', 'portrait');
            $quotaBuilder['components'][0]['props']['title'] = 'RECOVERY_COURSE_QUOTA_BUILDER_'.$suffix;
            $quotaBuilder = $this->compileReportBlueprint($quotaBuilder, $quotaSurface->placeholderCatalog());

            $resultWordBytes = $this->readFixtureFile($resultWordPath);
            $passingWordBytes = $this->readFixtureFile($reportWordPaths['passing_report']);
            $guidancePassword = bin2hex(random_bytes(24));
            $registrarPassword = bin2hex(random_bytes(24));
            $resultWordMarker = 'RECOVERY_RESULT_WORD_'.$suffix;
            $resultBuilderMarker = 'RECOVERY_RESULT_BUILDER_'.$suffix;
            $passingWordMarker = 'RECOVERY_PASSING_REPORT_WORD_'.$suffix;
            $quotaBuilderMarker = 'RECOVERY_COURSE_QUOTA_BUILDER_'.$suffix;

            $prepared = DB::transaction(function () use (
                $suffix,
                $passingSurface,
                $resultWordBytes,
                $passingWordBytes,
                $resultBuilderCompile,
                $quotaBuilder,
                $guidancePassword,
                $registrarPassword,
                &$storedPaths
            ): array {
                $institution = Institution::query()->create([
                    'code' => 'SYN-INST-001',
                    'name' => 'Document Recovery Synthetic Institution',
                ]);
                $cycle = AcademicCycle::query()->create([
                    'institution_id' => $institution->getKey(),
                    'code' => 'SYN-CYCLE-2026',
                    'name' => '2025-2026 1st Semester',
                    'school_year' => '2025-2026',
                    'semester' => '1',
                    'opens_on' => '2026-01-01',
                    'closes_on' => '2026-12-31',
                    'is_active' => true,
                ]);

                $guidance = $this->createStaff(
                    'guidance',
                    $suffix,
                    'guidance',
                    $institution,
                    $cycle,
                    $guidancePassword,
                    [
                        CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                        CapabilityCatalog::GUIDANCE_REPORTS_VIEW,
                    ],
                );
                $registrar = $this->createStaff(
                    'registrar',
                    $suffix,
                    'registrar',
                    $institution,
                    $cycle,
                    $registrarPassword,
                    [
                        CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
                        CapabilityCatalog::REGISTRAR_REPORTS_VIEW,
                    ],
                );

                $resultWord = ResultSheetTemplate::query()->create([
                    'name' => 'Recovery Result Word '.$suffix,
                    'mode' => ResultSheetTemplate::MODE_DOCX,
                    'source_kind' => ResultSheetTemplate::SOURCE_DOCX,
                    'paper_size' => ResultSheetTemplate::PAPER_A4,
                    'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
                    'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                    'content' => null,
                    'document_path' => null,
                    'document_checksum' => hash('sha256', $resultWordBytes),
                    'document_extension' => 'docx',
                    'watermark_text' => null,
                    'builder_schema_version' => null,
                    'builder_json' => null,
                    'compiled_html' => null,
                    'compiler_version' => null,
                    'lock_version' => 1,
                    'is_active' => false,
                    'created_by_user_id' => $guidance->getKey(),
                ]);
                $resultWordStoredPath = 'result-sheet-templates/'.$resultWord->getKey().'.docx';
                $this->storePrivateFile($resultWordStoredPath, $resultWordBytes, $storedPaths);
                $resultWord->forceFill(['document_path' => $resultWordStoredPath])->save();

                $resultBuilder = ResultSheetTemplate::query()->create([
                    'name' => 'Recovery Result Builder '.$suffix,
                    'mode' => ResultSheetTemplate::MODE_HTML,
                    'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
                    'paper_size' => ResultSheetTemplate::PAPER_A4,
                    'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
                    'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                    'content' => $resultBuilderCompile['html'],
                    'document_path' => null,
                    'document_checksum' => null,
                    'document_extension' => null,
                    'watermark_text' => null,
                    'builder_schema_version' => ResultSheetBuilderSchema::VERSION,
                    'builder_json' => $resultBuilderCompile['document'],
                    'compiled_html' => $resultBuilderCompile['html'],
                    'compiler_version' => ResultSheetBuilderSchema::COMPILER_VERSION,
                    'lock_version' => 1,
                    'is_active' => false,
                    'created_by_user_id' => $guidance->getKey(),
                ]);

                $passingWord = $this->createReportWordTemplate(
                    'passing_report',
                    $passingSurface,
                    'Recovery Passing Report Word '.$suffix,
                    $passingWordBytes,
                    $guidance,
                    $storedPaths,
                );
                $quotaBuilderTemplate = $this->createReportBuilderTemplate(
                    'course_quota_qualifiers',
                    'Recovery Course Quota Builder '.$suffix,
                    $quotaBuilder,
                    $registrar,
                );

                return [
                    'result_word' => $resultWord,
                    'result_builder' => $resultBuilder,
                    'passing_word' => $passingWord,
                    'quota_builder' => $quotaBuilderTemplate,
                    'guidance' => $guidance,
                    'registrar' => $registrar,
                ];
            });

            $cases = [
                $this->resultCase(
                    'result-word',
                    'docx',
                    $resultWordMarker,
                    $prepared['result_word'],
                    $prepared['guidance'],
                    $guidancePassword,
                    hash('sha256', $resultWordBytes),
                    false,
                ),
                $this->resultCase(
                    'result-builder',
                    'builder',
                    $resultBuilderMarker,
                    $prepared['result_builder'],
                    $prepared['guidance'],
                    $guidancePassword,
                    $prepared['result_builder']->printChecksum(),
                    true,
                ),
                $this->reportCase(
                    'passing-report-word',
                    'passing_report',
                    'docx',
                    $passingWordMarker,
                    $prepared['passing_word'],
                    $prepared['guidance'],
                    $guidancePassword,
                    hash('sha256', $passingWordBytes),
                    false,
                ),
                $this->reportCase(
                    'course-quota-builder',
                    'course_quota_qualifiers',
                    'builder',
                    $quotaBuilderMarker,
                    $prepared['quota_builder'],
                    $prepared['registrar'],
                    $registrarPassword,
                    $prepared['quota_builder']->sourceChecksum(),
                    true,
                ),
            ];

            $this->dispatchThumbnailJobs($cases, $prepared);

            return $cases;
        } catch (Throwable $exception) {
            foreach ($storedPaths ?? [] as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        } finally {
            ResultSheetDocxFixture::delete($resultWordPath);
            foreach ($reportWordPaths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Prepare scoped message fixtures after the four historical document cases.
     *
     * @return array{
     *     supplemental_notice_actors: array<string, array{id:int,email:string,password:string}>,
     *     notice_case_urls: array<string, string>,
     *     notice_state_hashes: array<string, mixed>
     * }
     */
    public function prepareNoticeSupplement(string $runId): array
    {
        $this->assertIsolatedEnvironment();

        foreach (['communication_templates', 'communication_template_versions', 'communication_template_drafts',
            'communication_template_documents', 'notice_document_versions', 'notice_document_drafts',
            'communication_template_receipts', 'notice_document_receipts',
            'notification_deliveries', 'audit_events'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing to add notice fixtures to a non-empty private notice state.');
            }
        }

        $suffix = strtoupper(substr(hash('sha256', $runId), 0, 10));
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('institution_id', $institution->getKey())
            ->where('is_active', true)->firstOrFail();

        $guidancePassword = bin2hex(random_bytes(24));
        $assessmentPassword = bin2hex(random_bytes(24));
        $registrarPassword = bin2hex(random_bytes(24));
        $wrongOfficePassword = bin2hex(random_bytes(24));
        $guidance = $this->createStaff('guidance', $suffix, 'notice-author', $institution, $cycle, $guidancePassword,
            [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE]);
        $assessmentReader = $this->createStaff('guidance', $suffix, 'notice-assessment-reader', $institution, $cycle, $assessmentPassword,
            [CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG]);
        $registrar = $this->createStaff('registrar', $suffix, 'notice-registrar', $institution, $cycle, $registrarPassword,
            [CapabilityCatalog::SETUP_INSTITUTION_MANAGE]);
        $wrongOffice = $this->createStaff('registrar', $suffix, 'notice-wrong-office', $institution, $cycle, $wrongOfficePassword,
            [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE]);

        app(ProvisionCanonicalCommunicationTemplates::class)->provision($institution);
        $families = CommunicationTemplate::query()->where('institution_id', $institution->getKey())->get();
        $expectedFamilies = array_merge(
            CommunicationTemplateRegistry::slotsFor('admissions'),
            CommunicationTemplateRegistry::slotsFor('examination'),
            CommunicationTemplateRegistry::slotsFor('results'),
        );
        if ($families->count() !== 19 || count($expectedFamilies) !== 19) {
            throw new RuntimeException('Canonical notice provisioning did not produce the exact nineteen-family inventory.');
        }

        $cancelled = $families->firstWhere('slug', 'session-cancelled');
        $permit = $families->firstWhere('slug', 'session-published');
        $accepted = $families->firstWhere('slug', 'accepted');
        $scorecard = $families->firstWhere('slug', 'released-online');
        if ($cancelled === null || $cancelled->domain !== 'examination'
            || $permit === null || $permit->domain !== 'examination'
            || $accepted === null || $accepted->domain !== 'admissions'
            || $scorecard === null || $scorecard->domain !== 'results') {
            throw new RuntimeException('The required canonical cancellation, permit, offer, or scorecard family is unavailable.');
        }

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/XioAAAAASUVORK5CYII=', true);
        $jpegBytes = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=', true);
        if (! is_string($pngBytes) || ! is_string($jpegBytes)) {
            throw new RuntimeException('Genuine private notice image fixture bytes are unavailable.');
        }
        $png = app(StoreCommunicationTemplateDocument::class)->storeImageBytes($cancelled, $pngBytes, 'image/png', $guidance);
        $jpeg = app(StoreCommunicationTemplateDocument::class)->storeImageBytes($permit, $jpegBytes, 'image/jpeg', $guidance);

        $permitDocument = $this->createNoticePermitVersion($permit, $guidance);
        $builder = (new NoticeTemplateSurface('examination', 'session-published'))->defaultBlueprint();
        $builder['components'] = [
            ['type' => 'header', 'props' => ['title' => 'Synthetic examination permit', 'subtitle' => 'Fixture document; not a live permit']],
            ['type' => 'field', 'props' => ['label' => 'Application number', 'binding' => 'application_no']],
        ];
        $builderAnalysis = NoticePrintContext::analyzeBuilder($builder);
        if (! $builderAnalysis['has_printable_content'] || NoticePrintContext::missingRequiredBindings($permit, $builderAnalysis['bindings']) !== []) {
            throw new RuntimeException('The synthetic permit builder input does not satisfy the canonical application_no requirement.');
        }

        $initialStates = [
            'cancellation' => $this->noticeMessageState(
                'Examination session cancellation — {{session_code}}',
                'Your examination session {{session_code}} is cancelled: {{cancellation_reason}}.',
                $cancelled,
                $png->getKey(),
                null,
            ),
            'permit' => $this->noticeMessageState(
                'Examination permit — {{application_no}}',
                'Your examination session {{session_code}} is scheduled. Keep your official permit ready.',
                $permit,
                $jpeg->getKey(),
                (int) $permitDocument['id'],
            ),
        ];

        $stateHashes = [];
        foreach (['cancellation' => [$cancelled, $guidance], 'permit' => [$permit, $guidance]] as $key => [$family, $actor]) {
            $state = $initialStates[$key];
            $versionNumber = (int) $family->versions()->max('version') + 1;
            $digest = app(NoticeMessageState::class)->contentDigest($family, $state, NoticeMessageState::NEW_EMAIL_RENDERER);
            $version = CommunicationTemplateVersion::query()->create([
                'communication_template_id' => $family->getKey(),
                'version' => $versionNumber,
                'content_schema_version' => 'notice-content-3',
                'subject' => $state['subject'],
                'body' => $state['body'],
                'email_subject' => $state['subject'],
                'email_body' => $state['body'],
                'in_app_title' => $state['subject'],
                'in_app_body' => $state['body'],
                'action_label' => $state['action_label'],
                'action_route' => $state['action_route'],
                'document_mode' => 'none',
                'document_asset_id' => null,
                'document_revision_id' => $state['document_revision_id'],
                'builder_json' => null,
                'paper_size' => null,
                'orientation' => null,
                'email_blueprint' => $state['email_blueprint'],
                'email_renderer_version' => NoticeMessageState::NEW_EMAIL_RENDERER,
                'content_sha256' => $digest,
                'created_by_user_id' => $actor->getKey(),
            ]);
            $family->forceFill(['active_version' => $versionNumber])->save();
            $stateHashes[$key] = [
                'family_id' => (string) $family->getKey(),
                'version_id' => (string) $version->getKey(),
                'version' => $versionNumber,
                'content_sha256' => $digest,
                'email_sha256' => app(NoticeMessageState::class)->emailDigest($family, $state),
                'portal_sha256' => app(NoticeMessageState::class)->portalDigest($state),
                'state_sha256' => hash('sha256', json_encode($state, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'document_version_id' => $state['document_revision_id'],
                'document_definition_sha256' => $state['document_revision_id'] === null
                    ? null : DB::table('notice_document_versions')->where('id', $state['document_revision_id'])->value('definition_sha256'),
                'email_asset_id' => $key === 'cancellation' ? (string) $png->getKey() : (string) $jpeg->getKey(),
                'email_asset_sha256' => $key === 'cancellation' ? $png->checksum : $jpeg->checksum,
                'schema' => 'notice-content-3',
                'renderer' => NoticeMessageState::NEW_EMAIL_RENDERER,
            ];
        }

        $actors = [
            'guidance_author' => $this->noticeActor($guidance, $guidancePassword),
            'assessment_only_reader' => $this->noticeActor($assessmentReader, $assessmentPassword),
            'registrar' => $this->noticeActor($registrar, $registrarPassword),
            'wrong_office_guidance_capability' => $this->noticeActor($wrongOffice, $wrongOfficePassword),
        ];
        $caseUrls = [
            'cancellation' => route('setup.notifications.examination.templates.studio', ['slug' => 'session-cancelled'], false),
            'permit' => route('setup.notifications.examination.templates.studio', ['slug' => 'session-published'], false),
            'accepted_offer' => route('setup.notifications.admissions.templates.studio', ['slug' => 'accepted'], false),
            'scorecard_metadata_only' => route('setup.notifications.results.templates.studio', ['slug' => 'released-online'], false),
            'examination_list' => route('setup.notifications.examination'),
            'admissions_list' => route('setup.notifications.admissions'),
            'results_list' => route('setup.notifications.results'),
            'cancellation_edit' => route('setup.notifications.templates.edit', ['domain' => 'examination', 'slug' => 'session-cancelled'], false),
            'permit_edit' => route('setup.notifications.templates.edit', ['domain' => 'examination', 'slug' => 'session-published'], false),
            'accepted_offer_edit' => route('setup.notifications.templates.edit', ['domain' => 'admissions', 'slug' => 'accepted'], false),
        ];
        $noticeIds = $families->mapWithKeys(static fn (CommunicationTemplate $family): array => [
            $family->domain.'.'.$family->slug => (string) $family->getKey(),
        ])->all();
        $stateHashes['starting_state'] = [
            'institution_id' => (int) $institution->getKey(),
            'cycle_id' => (int) $cycle->getKey(),
            'cycle_code' => $cycle->code,
            'family_ids' => $noticeIds,
            'canonical_family_count' => count($noticeIds),
            'notice_document_version_id' => (int) $permitDocument['id'],
            'notice_document_definition_sha256' => $permitDocument['definition_sha256'],
            'notice_document_render_sha256' => $permitDocument['render_sha256'],
            'notice_document_pdf_sha256' => $permitDocument['pdf_sha256'],
            'notice_document_thumbnail' => $permitDocument['thumbnail'],
            'word_asset_id' => $permitDocument['word_asset_id'],
            'word_asset_sha256' => $permitDocument['word_asset_sha256'],
            'permit_builder_input' => $builder,
            'image_asset_ids' => [(string) $png->getKey(), (string) $jpeg->getKey()],
            'initial_messages' => $stateHashes,
        ];

        return [
            'supplemental_notice_actors' => $actors,
            'notice_case_urls' => $caseUrls,
            'notice_state_hashes' => $stateHashes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createNoticePermitVersion(CommunicationTemplate $family, User $actor): array
    {
        $documentState = [
            'source_kind' => 'builder',
            'document_asset_id' => null,
            'builder_json' => (new NoticeTemplateSurface('examination', 'session-published'))->defaultBlueprint(),
            'paper_size' => 'a4',
            'orientation' => 'portrait',
        ];
        $workflow = app(NoticeDocumentWorkflow::class);
        $saved = $workflow->save($family, [
            'base_version' => 0,
            'document_revision' => (int) $family->document_revision,
            'lock_version' => null,
            'idempotency_key' => 'document-recovery-notice-permit-create-'.$family->getKey(),
            'state' => $documentState,
            'stage' => 1,
        ], $actor);
        $draftId = (int) $saved['document_draft']['id'];

        $wordPath = tempnam(sys_get_temp_dir(), 'nexiam-notice-permit-');
        if (! is_string($wordPath)) {
            throw new RuntimeException('Unable to create a temporary genuine Word permit source.');
        }
        try {
            $word = new PhpWord;
            $word->addSection()->addText('Official synthetic examination permit — {{application_no}}');
            IOFactory::createWriter($word, 'Word2007')->save($wordPath);
            $uploaded = new UploadedFile(
                $wordPath,
                'synthetic-examination-permit.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true,
            );
            $uploadedState = $workflow->upload($family, $uploaded, [
                'base_version' => 0,
                'document_revision' => $saved['document_revision'],
                'lock_version' => $saved['document_draft']['lock_version'],
                'idempotency_key' => 'document-recovery-notice-permit-upload-'.$family->getKey(),
                'document_draft_id' => $draftId,
            ], $actor);
        } finally {
            @unlink($wordPath);
        }

        $preview = $workflow->preview($family, [
            'base_version' => 0,
            'document_revision' => $uploadedState['document_revision'],
            'lock_version' => $uploadedState['document_draft']['lock_version'],
            'render_sha256' => $uploadedState['render_sha256'],
        ], $actor);
        $version = $workflow->createVersion($family, [
            'base_version' => 0,
            'document_revision' => $uploadedState['document_revision'],
            'lock_version' => $uploadedState['document_draft']['lock_version'],
            'idempotency_key' => 'document-recovery-notice-permit-version-'.$family->getKey(),
            'render_sha256' => $preview['render_sha256'],
            'preview_receipt' => $preview['preview_receipt'],
        ], $actor);
        $documentVersion = $family->documentVersions()->whereKey($version['document_version']['id'])->firstOrFail();
        $thumbnailService = app(DocumentThumbnailService::class);
        $thumbnail = $thumbnailService->read('notice-documents', (int) $documentVersion->getKey(),
            $thumbnailService->fingerprint($documentVersion->render_sha256));

        return [
            'id' => (int) $documentVersion->getKey(),
            'definition_sha256' => $documentVersion->definition_sha256,
            'render_sha256' => $documentVersion->render_sha256,
            'pdf_sha256' => $preview['pdf']['sha256'],
            'thumbnail' => is_string($thumbnail) && str_starts_with($thumbnail, "\x89PNG") ? 'ready' : 'queued',
            'word_asset_id' => $documentVersion->state['document_asset_id'],
            'word_asset_sha256' => $documentVersion->state['pins']['source_checksum'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noticeMessageState(
        string $subject,
        string $body,
        CommunicationTemplate $family,
        string $imageAssetId,
        ?int $documentVersionId,
    ): array {
        $slot = CommunicationTemplateRegistry::findBySlug($family->domain, $family->slug)
            ?? throw new RuntimeException('The notice message fixture family is missing its canonical slot.');

        return [
            'subject' => $subject,
            'body' => $body,
            'action_label' => $slot['default_action_label'],
            'action_route' => $slot['default_action_route'],
            'email_blueprint' => [
                'schema_version' => 'notice-email-1',
                'layout' => 'flow',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => $body],
                    ['type' => 'image', 'asset_id' => $imageAssetId, 'alt' => 'Synthetic notice attachment illustration', 'width' => 1],
                ],
            ],
            'document_revision_id' => $documentVersionId,
        ];
    }

    /**
     * @return array{id: int, email: string, password: string}
     */
    private function noticeActor(User $actor, string $password): array
    {
        return ['id' => (int) $actor->getKey(), 'email' => (string) $actor->email, 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $catalog
     * @return list<string>
     */
    private function catalogKeys(array $catalog): array
    {
        $keys = $catalog['all'] ?? [];
        if (! is_array($keys)) {
            throw new RuntimeException('The source surface has no valid placeholder catalog.');
        }

        if (! array_is_list($keys)) {
            $keys = array_keys($keys);
        }

        $keys = array_values(array_unique(array_filter($keys, 'is_string')));
        if ($keys === []) {
            throw new RuntimeException('The source surface placeholder catalog is empty.');
        }

        return $keys;
    }

    private function createReportWordDocument(array $catalog, string $marker): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nexiam-document-recovery-');
        if (! is_string($path)) {
            throw new RuntimeException('Unable to create a temporary Word fixture.');
        }

        try {
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText($marker);
            $scalarKeys = [];
            $repeatingKeys = [];
            foreach ($this->catalogKeys($catalog) as $key) {
                if (str_contains($key, '.')) {
                    $repeatingKeys[] = $key;
                } else {
                    $scalarKeys[] = $key;
                }
            }
            foreach ($scalarKeys as $key) {
                $section->addText('Catalog: {{'.$key.'}}');
            }
            if ($repeatingKeys !== []) {
                $table = $section->addTable();
                $row = $table->addRow();
                foreach ($repeatingKeys as $key) {
                    $row->addCell(2000)->addText('{{'.$key.'}}');
                }
            }

            IOFactory::createWriter($word, 'Word2007')->save($path);

            $surface = $catalog;
            $documentText = app(DocxTextExtractor::class)->text($path);
            $analysis = app(DocumentTemplateAnalyzer::class)->analyze($documentText, $surface);
            if ($analysis['valid'] !== true) {
                throw new RuntimeException('The report Word fixture does not satisfy its real placeholder catalog.');
            }

            return $path;
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }

            throw $exception;
        }
    }

    private function appendVisibleMarker(string $docxPath, string $marker): void
    {
        $archive = new ZipArchive;
        if ($archive->open($docxPath) !== true) {
            throw new RuntimeException('The result Word fixture is not a readable DOCX archive.');
        }

        try {
            $xml = $archive->getFromName('word/document.xml');
            if (! is_string($xml)) {
                throw new RuntimeException('The result Word fixture is missing its document XML.');
            }

            $bodyClose = strrpos($xml, '</w:body>');
            if ($bodyClose === false) {
                throw new RuntimeException('The result Word fixture document XML has no body close tag.');
            }

            $escapedMarker = htmlspecialchars($marker, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $paragraph = '<w:p><w:r><w:t xml:space="preserve">'.$escapedMarker.'</w:t></w:r></w:p>';
            $updatedXml = substr($xml, 0, $bodyClose).$paragraph.substr($xml, $bodyClose);

            if (! $archive->addFromString('word/document.xml', $updatedXml)) {
                throw new RuntimeException('Unable to add the result Word fixture marker.');
            }
        } finally {
            if (! $archive->close()) {
                throw new RuntimeException('Unable to close the result Word fixture archive.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  array<string, mixed>  $catalog
     * @return array{document: array<string, mixed>, html: string}
     */
    private function compileReportBlueprint(array $blueprint, array $catalog): array
    {
        $document = ResultSheetBuilderSchema::normalize($blueprint);
        $allowedBindings = $this->catalogKeys($catalog);
        $diagnostics = ResultSheetBuilderSchema::diagnostics($document, null, $allowedBindings);
        $this->assertNoDiagnostics($diagnostics, 'report builder');

        $compiler = new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults());

        return [
            'document' => $document,
            'html' => $compiler->compile($document),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $diagnostics
     */
    private function assertNoDiagnostics(array $diagnostics, string $label): void
    {
        if ($diagnostics !== []) {
            throw new RuntimeException('The default '.$label.' fixture failed its current compiler validation.');
        }
    }

    /**
     * @param  list<string>  $capabilities
     */
    private function createStaff(
        string $office,
        string $suffix,
        string $localName,
        Institution $institution,
        AcademicCycle $cycle,
        string $password,
        array $capabilities,
    ): User {
        $email = 'document-recovery.'.$localName.'.'.strtolower($suffix).'@nexiam.test';
        $user = User::query()->create([
            'name' => 'Synthetic '.ucfirst($office).' Document Recovery Staff',
            'email' => $email,
            'password' => $password,
        ]);

        StaffProfile::query()->create([
            'user_id' => $user->getKey(),
            'institution_id' => $institution->getKey(),
            'office' => $office,
            'program_codes' => [],
            'active_cycle_code' => $cycle->code,
            'status' => StaffProfile::STATUS_ACTIVE,
            'deactivated_at' => null,
        ]);

        StaffAuthenticationState::query()->create([
            'user_id' => $user->getKey(),
            'activation_completed_at' => now(),
            'password_set_at' => now(),
            'must_change_password' => false,
            'session_version' => 1,
            'mfa_required' => false,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        foreach ($capabilities as $capability) {
            Permission::findOrCreate($capability, 'web');
        }
        $user->givePermissionTo($capabilities);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $compiledBuilder
     * @param  array<int, string>  $storedPaths
     * @return array{0: ResultSheetTemplate, 1: User}
     */
    private function createResultBuilder(
        string $name,
        string $marker,
        array $compiledBuilder,
        User $actor,
    ): array {
        unset($marker);

        return [
            ResultSheetTemplate::query()->create([
                'name' => $name,
                'mode' => ResultSheetTemplate::MODE_HTML,
                'source_kind' => ResultSheetTemplate::SOURCE_BUILDER,
                'paper_size' => ResultSheetTemplate::PAPER_A4,
                'orientation' => ResultSheetTemplate::ORIENTATION_PORTRAIT,
                'logical_unit' => ResultSheetTemplate::LOGICAL_FULL,
                'content' => $compiledBuilder['html'],
                'document_path' => null,
                'document_checksum' => null,
                'document_extension' => null,
                'watermark_text' => null,
                'builder_schema_version' => ResultSheetBuilderSchema::VERSION,
                'builder_json' => $compiledBuilder['document'],
                'compiled_html' => $compiledBuilder['html'],
                'compiler_version' => ResultSheetBuilderSchema::COMPILER_VERSION,
                'lock_version' => 1,
                'is_active' => false,
                'created_by_user_id' => $actor->getKey(),
            ]),
            $actor,
        ];
    }

    /**
     * @param  array<int, string>  $storedPaths
     */
    private function createReportWordTemplate(
        string $surfaceKey,
        object $surface,
        string $name,
        string $bytes,
        User $actor,
        array &$storedPaths,
    ): ReportTemplate {
        $template = ReportTemplate::query()->create([
            'surface_key' => $surfaceKey,
            'name' => $name,
            'mode' => 'docx',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'document_path' => null,
            'document_checksum' => hash('sha256', $bytes),
            'document_extension' => 'docx',
            'content' => null,
            'source_kind' => ReportTemplate::SOURCE_DOCX,
            'builder_schema_version' => null,
            'builder_json' => null,
            'compiled_html' => null,
            'compiler_version' => null,
            'lock_version' => 1,
            'is_active' => false,
            'created_by_user_id' => $actor->getKey(),
        ]);

        $path = 'report-templates/'.$surfaceKey.'/'.$template->getKey().'.docx';
        $this->storePrivateFile($path, $bytes, $storedPaths);
        $template->forceFill(['document_path' => $path])->save();

        $analysis = app(DocumentTemplateAnalyzer::class)->analyze(
            app(DocxTextExtractor::class)->text(Storage::disk('local')->path($path)),
            $surface->placeholderCatalog(),
        );
        if ($analysis['valid'] !== true) {
            throw new RuntimeException('The report Word fixture failed the real surface catalog analysis.');
        }

        return $template->fresh();
    }

    private function createReportBuilderTemplate(
        string $surfaceKey,
        string $name,
        array $compiledBuilder,
        User $actor,
    ): ReportTemplate {
        return ReportTemplate::query()->create([
            'surface_key' => $surfaceKey,
            'name' => $name,
            'mode' => 'html',
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'document_path' => null,
            'document_checksum' => null,
            'document_extension' => null,
            'content' => $compiledBuilder['html'],
            'source_kind' => ReportTemplate::SOURCE_BUILDER,
            'builder_schema_version' => ResultSheetBuilderSchema::VERSION,
            'builder_json' => $compiledBuilder['document'],
            'compiled_html' => $compiledBuilder['html'],
            'compiler_version' => 'document-blueprint-1',
            'lock_version' => 1,
            'is_active' => false,
            'created_by_user_id' => $actor->getKey(),
        ]);
    }

    /**
     * @param  array<int, string>  $storedPaths
     */
    private function storePrivateFile(string $path, string $bytes, array &$storedPaths): void
    {
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Unable to store an isolated private document source.');
        }

        $storedPaths[] = $path;
    }

    private function readFixtureFile(string $path): string
    {
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('A genuine Word fixture source could not be read.');
        }

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array<string, mixed>
     */
    private function resultCase(
        string $caseId,
        string $sourceKind,
        string $marker,
        ResultSheetTemplate $template,
        User $actor,
        string $password,
        string $sourceSha256,
        bool $isBuilder,
    ): array {
        $case = [
            'id' => $caseId,
            'surface' => 'result',
            'source_kind' => $sourceKind,
            'marker' => $marker,
            'source_sha256' => $sourceSha256,
            'actor' => [
                'id' => (int) $actor->getKey(),
                'email' => (string) $actor->email,
                'password' => $password,
            ],
            'template' => [
                'id' => (int) $template->getKey(),
                'name' => (string) $template->name,
                'is_active' => false,
                'list_url' => route('setup.result-templates.index', [], false),
            ],
            'libreoffice_available' => true,
        ];

        if ($isBuilder) {
            $case['builder_sha256'] = hash('sha256', json_encode($template->builder_json, JSON_THROW_ON_ERROR));
        }

        return $case;
    }

    /**
     * @return array<string, mixed>
     */
    private function reportCase(
        string $caseId,
        string $surfaceKey,
        string $sourceKind,
        string $marker,
        ReportTemplate $template,
        User $actor,
        string $password,
        string $sourceSha256,
        bool $isBuilder,
    ): array {
        $case = [
            'id' => $caseId,
            'surface' => $surfaceKey,
            'source_kind' => $sourceKind,
            'marker' => $marker,
            'source_sha256' => $sourceSha256,
            'actor' => [
                'id' => (int) $actor->getKey(),
                'email' => (string) $actor->email,
                'password' => $password,
            ],
            'template' => [
                'id' => (int) $template->getKey(),
                'name' => (string) $template->name,
                'is_active' => false,
                'list_url' => route('setup.report-templates.surface', ['surface' => $surfaceKey], false),
            ],
            'libreoffice_available' => true,
        ];

        if ($isBuilder) {
            $case['builder_sha256'] = hash('sha256', json_encode($template->builder_json, JSON_THROW_ON_ERROR));
        }

        return $case;
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @param  array<string, mixed>  $prepared
     */
    private function dispatchThumbnailJobs(array $cases, array $prepared): void
    {
        $thumbnails = app(DocumentThumbnailService::class);
        foreach ($cases as $case) {
            if ($case['surface'] === 'result') {
                /** @var ResultSheetTemplate $template */
                $template = $case['id'] === 'result-word'
                    ? $prepared['result_word']
                    : $prepared['result_builder'];
                $checksum = $template->printChecksum();
                RenderResultSheetThumbnailJob::dispatch(
                    (int) $template->getKey(),
                    $checksum,
                    $thumbnails->fingerprint($checksum),
                )->afterCommit();

                continue;
            }

            /** @var ReportTemplate $template */
            $template = $case['id'] === 'passing-report-word'
                ? $prepared['passing_word']
                : $prepared['quota_builder'];
            $checksum = $template->sourceChecksum();
            RenderReportTemplateThumbnailJob::dispatch(
                (string) $case['surface'],
                (int) $template->getKey(),
                $checksum,
                $thumbnails->fingerprint($checksum),
            )->afterCommit();
        }
    }

    private function assertIsolatedEnvironment(): void
    {
        if (! app()->environment('testing') || getenv('DOCUMENT_RECOVERY_MODE') !== '1') {
            throw new RuntimeException('Document recovery fixtures require the isolated testing runtime.');
        }

        $storage = realpath(app()->storagePath());
        $database = config('database.connections.sqlite.database');
        if (! is_string($storage)
            || ! is_string($database)
            || $database !== $storage.'/recovery.sqlite'
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.driver') !== 'sqlite'
            || config('session.driver') !== 'file'
            || config('cache.default') !== 'file'
            || config('queue.default') !== 'database'
            || config('mail.default') !== 'array') {
            throw new RuntimeException('Refusing to create document fixtures outside the private SQLite recovery runtime.');
        }

        foreach (['local', 'public', 'sensitive'] as $diskName) {
            $root = config('filesystems.disks.'.$diskName.'.root');
            $resolvedRoot = is_string($root) ? realpath($root) : false;
            if (! is_string($resolvedRoot)
                || ($resolvedRoot !== $storage && ! str_starts_with($resolvedRoot, $storage.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Refusing to create document fixtures on a non-isolated filesystem disk.');
            }
        }
    }

    private function assertTargetTablesAreEmpty(): void
    {
        foreach ([
            'institutions',
            'academic_cycles',
            'users',
            'staff_profiles',
            'staff_authentication_states',
            'permissions',
            'model_has_permissions',
            'result_sheet_templates',
            'report_templates',
        ] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Refusing to add recovery fixtures to a non-empty private database.');
            }
        }
    }
}
