<?php

declare(strict_types=1);

namespace Tests\Support;

use App\DocumentTemplate\Rendering\ComponentRendererRegistry;
use App\DocumentTemplate\Rendering\DocumentBlueprintCompiler;
use App\DocumentTemplate\Services\DocumentThumbnailService;
use App\DocumentTemplate\Support\DocxTextExtractor;
use App\DocumentTemplate\Validation\DocumentTemplateAnalyzer;
use App\Jobs\Reports\RenderReportTemplateThumbnailJob;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Reports\ReportTemplate;
use App\Models\StaffProfile;
use App\Models\User;
use App\Reports\Surfaces\CourseQuotaQualifiersSurface;
use App\Reports\Surfaces\PassingReportSurface;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use Throwable;

/**
 * Adds narrowly scoped operational-report records to the already isolated
 * document-recovery fixture run. These rows exercise historical query
 * compatibility; they are not canonical scoring or transition evidence.
 */
final class DocumentRecoveryReportFixtures
{
    private const SURFACES = ['passing_report', 'course_quota_qualifiers'];

    /**
     * @param  list<array<string, mixed>>  $previewCases
     * @return array{run_id: string, source_kind: string, cases: list<array<string, mixed>>, baseline_side_effect_counts: array<string, int>}
     */
    public function prepare(string $runId, string $sourceKind, array $previewCases): array
    {
        $this->assertIsolatedEnvironment();
        $this->assertSourceKind($sourceKind);
        $previewSnapshot = $this->previewTemplateSnapshot($previewCases);
        $context = $this->ownedContext($previewCases);
        $this->assertNoExistingAddonTemplates($previewCases);

        $suffix = strtoupper(substr(hash('sha256', $runId), 0, 10));
        $markers = [
            'passing_report' => 'RECOVERY_OPERATIONAL_PASSING_'.$suffix,
            'course_quota_qualifiers' => 'RECOVERY_OPERATIONAL_QUOTA_'.$suffix,
        ];
        $surfaceObjects = [
            'passing_report' => app(PassingReportSurface::class),
            'course_quota_qualifiers' => app(CourseQuotaQualifiersSurface::class),
        ];
        $sourceFiles = [];
        $storedPaths = [];
        foreach (self::SURFACES as $surfaceKey) {
            if ($sourceKind === ReportTemplate::SOURCE_DOCX) {
                $sourceFiles[$surfaceKey] = $this->createWordSource(
                    $surfaceObjects[$surfaceKey]->placeholderCatalog(),
                    $markers[$surfaceKey],
                );
            }
        }

        try {
            $sourceBytes = [];
            foreach ($sourceFiles as $surfaceKey => $path) {
                $bytes = file_get_contents($path);
                if (! is_string($bytes) || $bytes === '') {
                    throw new RuntimeException('Unable to read a report-local Word source.');
                }
                $sourceBytes[$surfaceKey] = $bytes;
            }

            $bySurface = [];
            foreach ($previewCases as $previewCase) {
                if (in_array($previewCase['surface'] ?? null, self::SURFACES, true)) {
                    $bySurface[$previewCase['surface']] = $previewCase;
                }
            }
            $passwords = [
                'passing_report' => (string) ($bySurface['passing_report']['actor']['password'] ?? bin2hex(random_bytes(24))),
                'course_quota_qualifiers' => (string) ($bySurface['course_quota_qualifiers']['actor']['password'] ?? bin2hex(random_bytes(24))),
            ];
            $baselineCounts = $this->sideEffectCounts();
            $created = DB::transaction(function () use (
                $context,
                $markers,
                $surfaceObjects,
                $sourceKind,
                $sourceBytes,
                $passwords,
                &$storedPaths,
            ): array {
                $this->createHistoricalReportGraph($context);
                $this->assertQuotaSelection($context);

                $reportCases = [];
                foreach (self::SURFACES as $surfaceKey) {
                    $actor = $context['actors'][$surfaceKey];
                    $template = $sourceKind === ReportTemplate::SOURCE_DOCX
                        ? $this->createDocxTemplate(
                            $surfaceKey,
                            $surfaceObjects[$surfaceKey],
                            $markers[$surfaceKey],
                            $sourceBytes[$surfaceKey],
                            $actor,
                            $storedPaths,
                        )
                        : $this->createBuilderTemplate(
                            $surfaceKey,
                            $markers[$surfaceKey],
                            $surfaceObjects[$surfaceKey],
                            $actor,
                        );

                    // This isolated harness begins with one selected saved source per surface.
                    $template->forceFill(['is_active' => true])->save();
                    $reportCases[] = $this->caseManifest(
                        $surfaceKey,
                        $markers[$surfaceKey],
                        $sourceKind,
                        $template->fresh(),
                        $actor,
                        $passwords[$surfaceKey],
                        $context,
                    );
                }

                return $reportCases;
            });

            $this->dispatchThumbnails($created);
            $block = [
                'run_id' => $runId,
                'source_kind' => $sourceKind,
                'institution_id' => $context['institution']->getKey(),
                'academic_cycle_id' => $context['cycle']->getKey(),
                'program_code' => 'BSIT',
                'cases' => $created,
                'baseline_side_effect_counts' => $baselineCounts,
            ];
            $this->assertManifestBlock($block, $runId, $previewCases);
            $this->assertPreviewTemplatesUnchanged($previewSnapshot, $previewCases);

            return $block;
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        } finally {
            foreach ($sourceFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    /**
     * Verify the add-on's run/source binding, selected sources, actual private
     * assets, provider data scope, and the four pre-existing inactive templates.
     *
     * @param  array<string, mixed>  $block
     * @param  list<array<string, mixed>>  $previewCases
     */
    public function assertManifestBlock(array $block, string $runId, array $previewCases): void
    {
        $this->assertIsolatedEnvironment();
        if (($block['run_id'] ?? null) !== $runId || ! in_array($block['source_kind'] ?? null, [ReportTemplate::SOURCE_DOCX, ReportTemplate::SOURCE_BUILDER], true)) {
            throw new RuntimeException('The operational report manifest is not bound to this run and supported source.');
        }
        $this->assertSourceKind((string) $block['source_kind']);
        $context = $this->ownedContext($previewCases);
        $allowIds = [];
        foreach ($block['cases'] ?? [] as $case) {
            if (isset($case['template']['id'])) {
                $allowIds[] = (int) $case['template']['id'];
            }
        }
        $this->assertNoExistingAddonTemplates($previewCases, allowOperationalIds: $allowIds);

        if ((int) ($block['institution_id'] ?? 0) !== (int) $context['institution']->getKey()
            || (int) ($block['academic_cycle_id'] ?? 0) !== (int) $context['cycle']->getKey()
            || ($block['program_code'] ?? null) !== 'BSIT') {
            throw new RuntimeException('Operational report fixtures escaped their preview-owned institution, cycle, or program scope.');
        }
        if (($block['baseline_side_effect_counts'] ?? null) !== $this->sideEffectCountsExpectedShape($block['baseline_side_effect_counts'] ?? null)) {
            throw new RuntimeException('Operational report side-effect baseline is malformed.');
        }

        $expectedIds = ['passing-report-operational', 'course-quota-operational'];
        $actualIds = array_column($block['cases'] ?? [], 'id');
        sort($actualIds);
        sort($expectedIds);
        if ($actualIds !== $expectedIds) {
            throw new RuntimeException('Operational report fixture identities are incomplete or contain unexpected cases.');
        }
        foreach ($block['cases'] as $case) {
            $surfaceKey = (string) ($case['surface'] ?? '');
            $templateId = (int) ($case['selected_template']['id'] ?? 0);
            if (! in_array($surfaceKey, self::SURFACES, true)
                || ($case['source_kind'] ?? null) !== $block['source_kind']
                || ($case['run_id'] ?? null) !== $runId
                || (int) ($case['institution_id'] ?? 0) !== (int) $context['institution']->getKey()
                || (int) ($case['academic_cycle_id'] ?? 0) !== (int) $context['cycle']->getKey()
                || ($case['program_code'] ?? null) !== 'BSIT') {
                throw new RuntimeException('An operational report case is outside its declared run/source/institution/cycle scope.');
            }
            $actor = $context['actors'][$surfaceKey];
            $manifestActor = $case['actor'] ?? [];
            if ((int) ($manifestActor['id'] ?? 0) !== (int) $actor->getKey()
                || ($manifestActor['email'] ?? null) !== $actor->email
                || (string) ($case['selected_template']['source_kind'] ?? '') !== $block['source_kind']) {
                throw new RuntimeException('The operational report actor or selected source does not match its owned preview actor.');
            }
            $template = ReportTemplate::query()->where('surface_key', $surfaceKey)->find($templateId);
            if ($template === null
                || ! $template->is_active
                || $template->source_kind !== $block['source_kind']
                || $template->name !== ($case['selected_template']['name'] ?? null)
                || $template->sourceChecksum() !== ($case['selected_template']['source_sha256'] ?? null)
                || ($case['source_sha256'] ?? null) !== $template->sourceChecksum()
                || (int) $template->created_by_user_id !== (int) $actor->getKey()) {
                throw new RuntimeException('The selected operational report template identity/source checksum does not match SQLite.');
            }
            $this->assertSourceFile($template, $case);
            $this->assertCaseProviderContract($case);
        }
        $this->assertPreviewTemplatesUnchanged($this->previewTemplateSnapshot($previewCases), $previewCases);
    }

    /**
     * Return only operational identity/digest/active-choice facts; credentials
     * and report row contents are deliberately omitted from inspection output.
     *
     * @param  array<string, mixed>  $block
     * @param  list<array<string, mixed>>  $previewCases
     * @return array<string, mixed>
     */
    public function inspection(array $block, array $previewCases): array
    {
        $this->assertManifestBlock($block, (string) ($block['run_id'] ?? ''), $previewCases);
        $selected = [];
        foreach ($block['cases'] as $case) {
            $selected[] = [
                'case_id' => $case['id'],
                'surface' => $case['surface'],
                'template_id' => (int) $case['selected_template']['id'],
                'source_kind' => $case['source_kind'],
                'source_sha256' => $case['source_sha256'],
                'active' => true,
                'institution_id' => (int) $case['institution_id'],
                'academic_cycle_id' => (int) $case['academic_cycle_id'],
                'program_code' => $case['program_code'],
            ];
        }

        return [
            'run_id' => $block['run_id'],
            'source_kind' => $block['source_kind'],
            'selected_sources' => $selected,
            'preview_template_ids' => array_map(static fn (array $case): int => (int) $case['template']['id'], $previewCases),
            'preview_templates_inactive' => true,
            'baseline_side_effect_counts' => $block['baseline_side_effect_counts'],
        ];
    }

    /** @param list<array<string, mixed>> $previewCases */
    private function ownedContext(array $previewCases): array
    {
        $bySurface = [];
        foreach ($previewCases as $case) {
            if (($case['surface'] ?? null) === 'passing_report' && ($case['id'] ?? null) === 'passing-report-word') {
                $bySurface['passing_report'] = $case;
            }
            if (($case['surface'] ?? null) === 'course_quota_qualifiers' && ($case['id'] ?? null) === 'course-quota-builder') {
                $bySurface['course_quota_qualifiers'] = $case;
            }
        }
        if (array_keys($bySurface) !== self::SURFACES) {
            throw new RuntimeException('Operational report fixture setup requires the owned passing-report and quota preview cases.');
        }

        $actors = [];
        $institution = null;
        $cycle = null;
        foreach (self::SURFACES as $surfaceKey) {
            $caseActor = $bySurface[$surfaceKey]['actor'] ?? [];
            $caseTemplate = $bySurface[$surfaceKey]['template'] ?? [];
            $actor = User::query()->with('staffProfile')->find((int) ($caseActor['id'] ?? 0));
            $template = ReportTemplate::query()->where('surface_key', $surfaceKey)->find((int) ($caseTemplate['id'] ?? 0));
            $profile = $actor?->staffProfile;
            if ($actor === null || $profile === null || $template === null
                || $actor->email !== ($caseActor['email'] ?? null)
                || $template->is_active !== false
                || (int) $template->created_by_user_id !== (int) $actor->getKey()
                || $profile->status !== StaffProfile::STATUS_ACTIVE) {
                throw new RuntimeException('An owned report preview actor/template is absent, active, or mismatched.');
            }
            $currentInstitution = Institution::query()->find((int) $profile->institution_id);
            $currentCycle = AcademicCycle::query()->where('institution_id', $profile->institution_id)
                ->where('code', $profile->active_cycle_code)->first();
            if ($currentInstitution === null || $currentCycle === null || ! $currentCycle->is_active) {
                throw new RuntimeException('The preview actor does not resolve to an active owned academic cycle.');
            }
            if ($institution !== null && (int) $institution->getKey() !== (int) $currentInstitution->getKey()) {
                throw new RuntimeException('Report preview actors do not share one institution.');
            }
            if ($cycle !== null && (int) $cycle->getKey() !== (int) $currentCycle->getKey()) {
                throw new RuntimeException('Report preview actors do not share one active cycle.');
            }
            $institution = $currentInstitution;
            $cycle = $currentCycle;
            $actors[$surfaceKey] = $actor;
        }

        return compact('institution', 'cycle', 'actors');
    }

    private function createHistoricalReportGraph(array $context): void
    {
        $institution = $context['institution'];
        $cycle = $context['cycle'];
        $programs = [];
        foreach (['BSIT' => 'Bachelor of Science in Information Technology', 'BSN' => 'Bachelor of Science in Nursing'] as $code => $name) {
            $programs[$code] = Program::query()->create([
                'institution_id' => $institution->getKey(),
                'code' => $code,
                'name' => $name,
                'is_active' => true,
            ]);
        }
        $foreignCycle = AcademicCycle::query()->create([
            'institution_id' => $institution->getKey(),
            'code' => 'RECOVERY-2025-'.strtoupper(bin2hex(random_bytes(4))),
            'name' => 'Academic Year 2024–2025 · First Semester',
            'school_year' => '2024-2025',
            'semester' => '1',
            'opens_on' => '2024-01-01',
            'closes_on' => '2025-12-31',
            'is_active' => false,
        ]);
        ProgramQuota::query()->create([
            'program_id' => $programs['BSIT']->getKey(),
            'academic_cycle_id' => $cycle->getKey(),
            'slots' => 5,
        ]);
        $room = Room::query()->create([
            'institution_id' => $institution->getKey(),
            'code' => 'RECOVERY-REPORT-ROOM',
            'name' => 'Recovery Report Fixture Room',
            'capacity' => 12,
            'is_active' => true,
        ]);
        $areas = [];
        foreach (['VERBAL' => 'Verbal', 'QUANT' => 'Quantitative'] as $code => $name) {
            $areas[$code] = AptitudeArea::query()->create([
                'code' => $code,
                'name' => $name,
                'version' => 1,
                'is_active' => true,
            ]);
        }

        $currentExam = $this->createHistoricalExam($cycle, $room, 'CURRENT');
        $foreignExam = $this->createHistoricalExam($foreignCycle, $room, 'FOREIGN');
        $currentGrading = $this->createFinalizedHistoricalGrading($currentExam);
        $foreignGrading = $this->createFinalizedHistoricalGrading($foreignExam);

        foreach ([
            ['given' => 'Mara', 'family' => 'CurrentOne', 'reference' => 'RPT-CURRENT-001', 'gwa' => '1.75', 'accepted_at' => '2026-09-10 10:00:00', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => 91],
            ['given' => 'Noel', 'family' => 'CurrentTwo', 'reference' => 'RPT-CURRENT-002', 'gwa' => '1.82', 'accepted_at' => '2026-09-11 10:00:00', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => 91],
            ['given' => 'Bea', 'family' => 'NursingContrast', 'reference' => 'RPT-BSN-003', 'gwa' => '1.90', 'accepted_at' => '2026-09-12 10:00:00', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSN', 'first' => 'BSN', 'score' => 91],
            ['given' => 'Pia', 'family' => 'PendingContrast', 'reference' => 'RPT-PENDING-004', 'gwa' => '1.95', 'accepted_at' => null, 'status' => AdmissionAttempt::STATUS_PENDING, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => null],
        ] as $index => $row) {
            $attempt = $this->createAttempt($institution, $cycle, $row, $index);
            if ($row['score'] !== null) {
                $this->createHistoricalScores($currentGrading, $attempt, (int) $row['score'], $areas);
            }
        }
        foreach ([
            ['given' => 'Uma', 'family' => 'ForeignOne', 'reference' => 'RPT-FOREIGN-001', 'gwa' => '1.85', 'accepted_at' => '2025-09-10 10:00:00', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => 99],
            ['given' => 'Vic', 'family' => 'ForeignTwo', 'reference' => 'RPT-FOREIGN-002', 'gwa' => '1.85', 'accepted_at' => '2025-09-11 10:00:00', 'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred' => 'BSIT', 'first' => 'BSIT', 'score' => 98],
        ] as $index => $row) {
            $attempt = $this->createAttempt($institution, $foreignCycle, $row, $index + 10);
            $this->createHistoricalScores($foreignGrading, $attempt, (int) $row['score'], $areas);
        }
    }

    private function createHistoricalExam(AcademicCycle $cycle, Room $room, string $suffix): ExamSession
    {
        return ExamSession::query()->create([
            'academic_cycle_id' => $cycle->getKey(),
            'room_id' => $room->getKey(),
            'code' => 'RECOVERY-REPORT-'.$suffix.'-'.strtoupper(bin2hex(random_bytes(4))),
            'status' => ExamSession::STATUS_COMPLETED,
            'test_instrument_version_id' => null,
            'test_kit_version_id' => null,
            'booklet_version_id' => null,
            'seating_arrangement_id' => null,
            'starts_at' => $suffix === 'FOREIGN' ? '2025-09-08 08:00:00' : '2026-09-08 08:00:00',
            'effective_end_at' => $suffix === 'FOREIGN' ? '2025-09-08 10:00:00' : '2026-09-08 10:00:00',
            'version' => 1,
        ]);
    }

    private function createFinalizedHistoricalGrading(ExamSession $exam): GradingSession
    {
        return GradingSession::query()->create([
            'exam_session_id' => $exam->getKey(),
            'status' => GradingSession::STATUS_FINALIZED,
            'version' => 1,
            'opened_at' => $exam->starts_at,
            'finalized_at' => $exam->effective_end_at,
        ]);
    }

    private function createAttempt(Institution $institution, AcademicCycle $cycle, array $row, int $index): AdmissionAttempt
    {
        $identity = ApplicantIdentity::query()->create([
            'institution_id' => $institution->getKey(),
            'normalized_key' => hash('sha256', 'document-recovery-report-'.bin2hex(random_bytes(16))),
            'first_name' => $row['given'],
            'last_name' => $row['family'],
            'birthdate' => '2001-'.sprintf('%02d', ($index % 12) + 1).'-01',
            'sex' => 'female',
        ]);

        return AdmissionAttempt::query()->create([
            'applicant_identity_id' => $identity->getKey(),
            'institution_id' => $institution->getKey(),
            'academic_cycle_id' => $cycle->getKey(),
            'reference_code' => $row['reference'],
            'status' => $row['status'],
            'preferred_program_code' => $row['preferred'],
            'program_preference_1_code' => $row['first'],
            'applicant_type' => 'new',
            'strand' => 'STEM',
            'gwa' => $row['gwa'],
            'version' => 1,
            'accepted_at' => $row['accepted_at'],
            'created_at' => str_starts_with((string) $row['reference'], 'RPT-FOREIGN') ? '2025-09-01 09:00:00' : '2026-09-01 09:00:00',
            'updated_at' => $row['accepted_at'] ?? '2026-09-01 09:00:00',
        ]);
    }

    /** @param array<string, AptitudeArea> $areas */
    private function createHistoricalScores(GradingSession $grading, AdmissionAttempt $attempt, int $score, array $areas): void
    {
        foreach ($areas as $code => $area) {
            ScoreRecord::query()->create([
                'grading_session_id' => $grading->getKey(),
                'admission_attempt_id' => $attempt->getKey(),
                'aptitude_area_id' => $area->getKey(),
                'score' => $score,
                'source' => ScoreRecord::SOURCE_MANUAL,
                'idempotency_key' => 'document-recovery-report-'.$attempt->getKey().'-'.$code,
            ]);
        }
    }

    private function assertQuotaSelection(array $context): void
    {
        $bsitId = Program::query()->where('institution_id', $context['institution']->getKey())->where('code', 'BSIT')->value('id');
        $quota = ProgramQuota::query()->where('academic_cycle_id', $context['cycle']->getKey())->where('program_id', $bsitId)->first();
        if ($quota === null || (int) $quota->slots !== 5) {
            throw new RuntimeException('The operational report fixture must expose its real five-slot BSIT quota row.');
        }
    }

    private function createWordSource(array $catalog, string $marker): string
    {
        $path = tempnam(sys_get_temp_dir(), 'nexiam-report-recovery-');
        if (! is_string($path)) {
            throw new RuntimeException('Unable to create an operational report Word fixture.');
        }
        try {
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText($marker);
            $scalar = [];
            $repeat = [];
            foreach ($catalog['all'] ?? [] as $key) {
                if (! is_string($key)) {
                    continue;
                }
                if (str_contains($key, '.')) {
                    $repeat[] = $key;
                } else {
                    $scalar[] = $key;
                }
            }
            foreach ($scalar as $key) {
                $section->addText('Catalog: {{'.$key.'}}');
            }
            if ($repeat !== []) {
                $table = $section->addTable();
                $row = $table->addRow();
                // One wide cell (8500 twips) stacks all repeating macros cleanly
                // with readable staff labels, fitting within A4 printable width (9026 twips).
                $cell = $row->addCell(8500);
                foreach ($repeat as $key) {
                    $label = ucwords(str_replace(['.', '_'], [' ', ' '], $key));
                    $cell->addText($label.': {{'.$key.'}}');
                }
            }
            IOFactory::createWriter($word, 'Word2007')->save($path);
            $analysis = app(DocumentTemplateAnalyzer::class)->analyze(app(DocxTextExtractor::class)->text($path), $catalog);
            if (($analysis['valid'] ?? false) !== true) {
                throw new RuntimeException('The operational Word fixture does not satisfy the real report placeholder catalog.');
            }

            return $path;
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }

    private function createDocxTemplate(string $surfaceKey, object $surface, string $marker, string $bytes, User $actor, array &$storedPaths): ReportTemplate
    {
        $template = ReportTemplate::query()->create([
            'surface_key' => $surfaceKey,
            'name' => 'Operational '.$surfaceKey.' Word '.$marker,
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
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new RuntimeException('Unable to store the operational Word source on the isolated private disk.');
        }
        $storedPaths[] = $path;
        $template->forceFill(['document_path' => $path])->save();
        $analysis = app(DocumentTemplateAnalyzer::class)->analyze(
            app(DocxTextExtractor::class)->text(Storage::disk('local')->path($path)),
            $surface->placeholderCatalog(),
        );
        if (($analysis['valid'] ?? false) !== true) {
            throw new RuntimeException('Stored operational Word source failed real catalog analysis.');
        }

        return $template->fresh();
    }

    private function createBuilderTemplate(string $surfaceKey, string $marker, object $surface, User $actor): ReportTemplate
    {
        $builder = $surface->defaultBlueprint('a4', 'portrait');
        $builder['components'][0]['props']['title'] = $marker;
        $document = ResultSheetBuilderSchema::normalize($builder);
        $catalog = $surface->placeholderCatalog()['all'] ?? [];
        $diagnostics = ResultSheetBuilderSchema::diagnostics($document, null, $catalog);
        if ($diagnostics !== []) {
            throw new RuntimeException('The operational report builder failed the real binding/catalog validation.');
        }
        $html = (new DocumentBlueprintCompiler(ComponentRendererRegistry::withDefaults()))->compile($document);
        if (! is_string($html) || $html === '') {
            throw new RuntimeException('The operational report builder compiler returned no HTML.');
        }

        return ReportTemplate::query()->create([
            'surface_key' => $surfaceKey,
            'name' => 'Operational '.$surfaceKey.' Builder '.$marker,
            'mode' => ReportTemplate::MODE_HTML,
            'paper_size' => 'a4',
            'orientation' => 'portrait',
            'document_path' => null,
            'document_checksum' => null,
            'document_extension' => null,
            'content' => $html,
            'source_kind' => ReportTemplate::SOURCE_BUILDER,
            'builder_schema_version' => ResultSheetBuilderSchema::VERSION,
            'builder_json' => $document,
            'compiled_html' => $html,
            'compiler_version' => 'document-blueprint-1',
            'lock_version' => 1,
            'is_active' => false,
            'created_by_user_id' => $actor->getKey(),
        ]);
    }

    private function caseManifest(string $surfaceKey, string $marker, string $sourceKind, ReportTemplate $template, User $actor, string $password, array $context): array
    {
        $base = [
            'id' => $surfaceKey === 'passing_report' ? 'passing-report-operational' : 'course-quota-operational',
            'run_id' => (string) getenv('DOCUMENT_RECOVERY_RUN_ID'),
            'surface' => $surfaceKey,
            'source_kind' => $sourceKind,
            'marker' => $marker,
            'source_sha256' => $template->sourceChecksum(),
            'actor' => ['id' => (int) $actor->getKey(), 'email' => (string) $actor->email, 'password' => $password],
            'template' => ['id' => (int) $template->getKey(), 'name' => (string) $template->name, 'is_active' => true, 'list_url' => route('setup.report-templates.surface', ['surface' => $surfaceKey], false)],
            'selected_template' => [
                'id' => (int) $template->getKey(),
                'name' => (string) $template->name,
                'is_active' => true,
                'list_url' => route('setup.report-templates.surface', ['surface' => $surfaceKey], false),
                'source_kind' => $sourceKind,
                'source_sha256' => $template->sourceChecksum(),
            ],
            'institution_id' => (int) $context['institution']->getKey(),
            'academic_cycle_id' => (int) $context['cycle']->getKey(),
            'program_code' => 'BSIT',
            'filters' => ['format' => 'pdf', 'academic_cycle_id' => (int) $context['cycle']->getKey(), 'program_code' => 'BSIT', 'cutoff_score' => 75],
            'baseline_side_effect_counts' => $this->sideEffectCounts(),
        ];
        $base['expected'] = $surfaceKey === 'passing_report' ? [
            'institution_name' => (string) $context['institution']->name,
            'institution_code' => (string) $context['institution']->code,
            'academic_cycle' => (string) $context['cycle']->staffLabel(),
            'cutoff_score' => 75.0,
            'total_examinees' => 2,
            'total_passed' => 2,
            'passing_rate' => '100.00%',
            'passers' => [
                ['reference_code' => 'RPT-CURRENT-001', 'applicant_name' => 'Mara CurrentOne', 'sex' => 'female', 'strand' => 'STEM', 'program_code' => 'BSIT', 'composite_score' => 91.0, 'remarks' => 'Passed'],
                ['reference_code' => 'RPT-CURRENT-002', 'applicant_name' => 'Noel CurrentTwo', 'sex' => 'female', 'strand' => 'STEM', 'program_code' => 'BSIT', 'composite_score' => 91.0, 'remarks' => 'Passed'],
            ],
            'excluded_contrast_references' => ['RPT-BSN-003', 'RPT-PENDING-004', 'RPT-FOREIGN-001', 'RPT-FOREIGN-002'],
            'scope' => ['institution_id' => (int) $context['institution']->getKey(), 'academic_cycle_id' => (int) $context['cycle']->getKey(), 'program_code' => 'BSIT', 'compatibility_kind' => 'historical_unbound_scores'],
        ] : [
            'institution_name' => (string) $context['institution']->name,
            'institution_code' => (string) $context['institution']->code,
            'academic_cycle' => (string) $context['cycle']->staffLabel(),
            'target_quota' => 5,
            'accepted_count' => 2,
            'committed_reservations' => 0,
            'remaining_slots' => 3,
            'remaining_quota' => 5,
            'fill_rate' => '40.00%',
            'committed_fill_rate' => '0.00%',
            'qualifiers' => [
                ['reference_code' => 'RPT-CURRENT-001', 'applicant_name' => 'Mara CurrentOne', 'strand' => 'STEM', 'gwa' => '1.75', 'program_code' => 'BSIT', 'admission_status' => 'Accepted', 'accepted_at' => '2026-09-10'],
                ['reference_code' => 'RPT-CURRENT-002', 'applicant_name' => 'Noel CurrentTwo', 'strand' => 'STEM', 'gwa' => '1.82', 'program_code' => 'BSIT', 'admission_status' => 'Accepted', 'accepted_at' => '2026-09-11'],
            ],
            'excluded_contrast_references' => ['RPT-BSN-003', 'RPT-PENDING-004', 'RPT-FOREIGN-001', 'RPT-FOREIGN-002'],
            'scope' => ['institution_id' => (int) $context['institution']->getKey(), 'academic_cycle_id' => (int) $context['cycle']->getKey(), 'program_code' => 'BSIT', 'quota_slots' => 5],
        ];

        return $base;
    }

    private function dispatchThumbnails(array $cases): void
    {
        $thumbnails = app(DocumentThumbnailService::class);
        foreach ($cases as $case) {
            $template = ReportTemplate::query()->where('surface_key', $case['surface'])->findOrFail($case['template']['id']);
            $checksum = $template->sourceChecksum();
            RenderReportTemplateThumbnailJob::dispatch(
                (string) $case['surface'],
                (int) $template->getKey(),
                $checksum,
                $thumbnails->fingerprint($checksum),
            )->afterCommit();
        }
    }

    private function assertCaseProviderContract(array $case): void
    {
        $expectedFilters = ['format' => 'pdf', 'academic_cycle_id' => (int) $case['academic_cycle_id'], 'program_code' => 'BSIT', 'cutoff_score' => 75];
        if (($case['filters'] ?? null) !== $expectedFilters || ! is_array($case['expected'] ?? null)
            || ($case['expected']['scope']['institution_id'] ?? null) !== (int) $case['institution_id']
            || ($case['expected']['scope']['academic_cycle_id'] ?? null) !== (int) $case['academic_cycle_id']
            || ($case['expected']['scope']['program_code'] ?? null) !== 'BSIT') {
            throw new RuntimeException('An operational report fixture is missing exact provider filters or expected scope values.');
        }
        if ($case['surface'] === 'passing_report'
            && (($case['expected']['total_examinees'] ?? null) !== 2 || ($case['expected']['total_passed'] ?? null) !== 2 || count($case['expected']['passers'] ?? []) !== 2)) {
            throw new RuntimeException('Passing report expected provider output is incomplete.');
        }
        if ($case['surface'] === 'course_quota_qualifiers'
            && (($case['expected']['target_quota'] ?? null) !== 5 || ($case['expected']['accepted_count'] ?? null) !== 2 || count($case['expected']['qualifiers'] ?? []) !== 2)) {
            throw new RuntimeException('Quota report expected provider output is incomplete.');
        }
    }

    private function assertSourceFile(ReportTemplate $template, array $case): void
    {
        if ($template->source_kind === ReportTemplate::SOURCE_DOCX) {
            $root = realpath(config('filesystems.disks.local.root'));
            $asset = is_string($template->document_path) ? realpath(Storage::disk('local')->path($template->document_path)) : false;
            if (! is_string($root) || ! is_string($asset) || ! str_starts_with($asset, $root.DIRECTORY_SEPARATOR)
                || ! hash_equals((string) $template->document_checksum, hash_file('sha256', $asset))) {
                throw new RuntimeException('The active report Word source is absent, outside private storage, or checksum-mismatched.');
            }

            return;
        }
        if (! is_array($template->builder_json)
            || $template->builder_schema_version !== ResultSheetBuilderSchema::VERSION
            || $template->compiler_version !== 'document-blueprint-1'
            || ! hash_equals((string) $case['source_sha256'], $template->sourceChecksum())) {
            throw new RuntimeException('The active report builder source is absent or stale.');
        }
    }

    private function previewTemplateSnapshot(array $previewCases): array
    {
        $snapshot = [];
        foreach ($previewCases as $case) {
            if (($case['surface'] ?? null) === 'result') {
                continue;
            }
            $template = ReportTemplate::query()->where('surface_key', $case['surface'])->find((int) ($case['template']['id'] ?? 0));
            if ($template === null || $template->is_active) {
                throw new RuntimeException('The original operational report preview template must exist and remain inactive.');
            }
            $snapshot[(int) $template->getKey()] = [
                'id' => (int) $template->getKey(),
                'surface' => (string) $template->surface_key,
                'is_active' => false,
                'source_sha256' => $template->sourceChecksum(),
                'name' => (string) $template->name,
            ];
        }
        if (count($snapshot) !== 2) {
            throw new RuntimeException('Exactly two report templates belong to the original four preview cases.');
        }

        return $snapshot;
    }

    private function assertPreviewTemplatesUnchanged(array $snapshot, array $previewCases): void
    {
        foreach ($snapshot as $id => $expected) {
            $template = ReportTemplate::query()->where('surface_key', $expected['surface'])->find($id);
            if ($template === null || $template->is_active || $template->name !== $expected['name'] || $template->sourceChecksum() !== $expected['source_sha256']) {
                throw new RuntimeException('An original inactive report preview template was changed by the operational report add-on.');
            }
        }
        foreach ($previewCases as $case) {
            if (($case['surface'] ?? null) !== 'result' && (bool) ($case['template']['is_active'] ?? false) !== false) {
                throw new RuntimeException('An original report preview case manifest no longer describes an inactive template.');
            }
        }
    }

    private function assertNoExistingAddonTemplates(array $previewCases, array $allowOperationalIds = []): void
    {
        $ownedIds = [];
        foreach ($previewCases as $case) {
            if (in_array($case['surface'] ?? null, self::SURFACES, true)) {
                $ownedIds[] = (int) ($case['template']['id'] ?? 0);
            }
        }
        foreach (ReportTemplate::query()->get() as $template) {
            if (in_array((int) $template->getKey(), $ownedIds, true) || in_array((int) $template->getKey(), $allowOperationalIds, true)) {
                continue;
            }
            throw new RuntimeException('Refusing to add operational report fixtures when unowned report templates already exist.');
        }
        foreach (self::SURFACES as $surface) {
            $ownedActive = ReportTemplate::query()->where('surface_key', $surface)->whereIn('id', $ownedIds)->where('is_active', true)->exists();
            if ($ownedActive) {
                throw new RuntimeException('An original preview report template was unexpectedly activated.');
            }
        }
    }

    private function sideEffectCounts(): array
    {
        $counts = [];
        foreach ([
            'assessment_administrations',
            'result_documents',
            'result_document_print_receipts',
            'release_records',
            'notification_intents',
            'notification_deliveries',
            'jobs',
        ] as $table) {
            $counts[$table] = DB::getSchemaBuilder()->hasTable($table) ? DB::table($table)->count() : 0;
        }

        return $counts;
    }

    private function sideEffectCountsExpectedShape(mixed $counts): array
    {
        if (! is_array($counts)) {
            throw new RuntimeException('Operational report baseline side-effect counts are unavailable.');
        }
        foreach ($counts as $table => $count) {
            if (! is_string($table) || ! is_int($count) || $count < 0) {
                throw new RuntimeException('Operational report baseline side-effect counts are invalid.');
            }
        }

        return $counts;
    }

    private function assertSourceKind(string $sourceKind): void
    {
        if (! in_array($sourceKind, [ReportTemplate::SOURCE_DOCX, ReportTemplate::SOURCE_BUILDER], true)) {
            throw new RuntimeException('Operational report source kind must be exactly docx or builder.');
        }
    }

    private function assertIsolatedEnvironment(): void
    {
        if (! app()->environment('testing') || getenv('DOCUMENT_RECOVERY_MODE') !== '1') {
            throw new RuntimeException('Operational report fixtures require the isolated testing recovery runtime.');
        }
        $storage = realpath(app()->storagePath());
        $database = config('database.connections.sqlite.database');
        if (! is_string($storage) || ! is_string($database) || $database !== $storage.'/recovery.sqlite'
            || config('database.default') !== 'sqlite' || config('database.connections.sqlite.driver') !== 'sqlite'
            || config('session.driver') !== 'file' || config('cache.default') !== 'file'
            || config('queue.default') !== 'database' || config('mail.default') !== 'array'
            || config('queue.connections.database.after_commit') !== true) {
            throw new RuntimeException('Refusing operational report setup outside the private SQLite/after-commit recovery runtime.');
        }
        foreach (['local', 'public', 'sensitive'] as $diskName) {
            $root = config('filesystems.disks.'.$diskName.'.root');
            $resolved = is_string($root) ? realpath($root) : false;
            if (! is_string($resolved) || ($resolved !== $storage && ! str_starts_with($resolved, $storage.DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Refusing operational report setup on a non-private filesystem.');
            }
        }
        $interfaces = @scandir('/sys/class/net');
        if (! is_array($interfaces) || array_values(array_diff($interfaces, ['.', '..'])) !== ['lo']) {
            throw new RuntimeException('Operational report setup requires the loopback-only isolated network runtime.');
        }
        foreach (['APP_DEBUG' => 'false', 'DB_URL' => '', 'DB_HOST' => '', 'DB_USERNAME' => '', 'DB_PASSWORD' => ''] as $key => $value) {
            if (getenv($key) !== $value) {
                throw new RuntimeException('Refusing operational report setup with unsafe network/database runtime configuration.');
            }
        }
    }
}
