<?php

namespace App\Testing\Seeders\Stages;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\RequestApplicationCorrection;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Assessment\Actions\FinalizeGradingSession;
use App\Assessment\Actions\RecordScores;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\OpenExamSessionReservation;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Foundation\SeatingArrangementEligibility;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestKitVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use App\Testing\Queries\InstitutionalOmrBookletQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

abstract class BaseStageSeeder
{
    protected PracticeFixtureSet $set;

    protected Institution $institution;

    protected AcademicCycle $cycle;

    protected ApplicationWindow $window;

    protected User $guidance;

    protected User $registrar;

    public function forSet(PracticeFixtureSet $set): static
    {
        $this->set = $set;

        return $this;
    }

    /** @return array<string, mixed> */
    abstract public function seed(): array;

    protected function initContext(): void
    {
        if (! app()->environment(['local', 'testing']) || ! isset($this->set)) {
            throw new RuntimeException('An explicit local/testing practice set is required.');
        }
        $this->set = PracticeFixtureSet::query()->lockForUpdate()->findOrFail($this->set->id);
        $this->institution = Institution::current();
        $this->cycle = AcademicCycle::query()->where('institution_id', $this->institution->id)
            ->where('is_active', true)->whereKey($this->set->academic_cycle_id)->firstOrFail();
        if ($this->set->status !== 'active' || (int) $this->set->institution_id !== (int) $this->institution->id) {
            throw new RuntimeException('The practice set must be active in the current institution and cycle.');
        }
        $creator = User::query()->findOrFail($this->set->created_by_user_id);
        $this->authorize($creator, CapabilityCatalog::DEMO_STAGE_CONTROL);
    }

    protected function authorize(User $actor, string $capability): void
    {
        if (! app(CapabilityAuthorizer::class)->decideFresh($actor, $capability, [
            'institution_id' => $this->institution->id,
            'cycle' => $this->cycle->code,
        ])->allowed) {
            throw new RuntimeException('The assigned actor lacks current authority for '.$capability.'.');
        }
    }

    protected function actor(string $role, string $capability): User
    {
        $id = $this->set->actor_bindings[$role] ?? null;
        if (! is_int($id)) {
            throw new RuntimeException('Assign an existing scoped '.$role.' account before preparation.');
        }
        $actor = User::query()->findOrFail($id);
        $this->authorize($actor, $capability);

        return $actor;
    }

    /** @return array<string, mixed> */
    protected function buildStage(string $stage): array
    {
        return DB::transaction(function () use ($stage): array {
            $this->initContext();

            return app(PracticeWorkspace::class)->run($this->set, function () use ($stage): array {
                $saved = $this->set->material_snapshot['stages'][$stage] ?? null;
                if (is_array($saved)) {
                    return $this->result($stage, $saved);
                }
                $profiles = (array) config('seed.pipeline_cohorts.stages.'.$stage, []);
                if ($profiles === []) {
                    throw new RuntimeException('No approved starting profiles exist for '.$stage.'.');
                }
                $this->preflightIntake($profiles);
                $this->registrar = $this->actor('registrar', $stage === 'review'
                    ? CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW : CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT);
                $correctionScenario = ($this->set->purpose === 'demo' && $this->set->scenario === 'application-review')
                    || ($this->set->purpose === 'research' && in_array($this->set->scenario, ['student', 'registrar'], true));
                $withCorrection = $stage === 'review' && $correctionScenario
                    && app(CapabilityAuthorizer::class)->decideFresh($this->registrar,
                        CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT, [
                            'institution_id' => $this->institution->id,
                            'cycle' => $this->cycle->code,
                        ])->allowed;

                $session = null;
                $grading = null;
                $documents = [];
                $later = in_array($stage, ['grading', 'consultation', 'program', 'enrollment'], true);
                $withDocuments = in_array($stage, ['consultation', 'program', 'enrollment'], true);
                if ($stage !== 'review') {
                    $this->guidance = $this->actor('guidance', CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
                    if ($later) {
                        $this->authorize($this->guidance, CapabilityCatalog::GUIDANCE_SCORING_MANUAL);
                        $this->preflightScores();
                    }
                    if ($withDocuments) {
                        $this->authorize($this->guidance, CapabilityCatalog::GUIDANCE_RESULTS_PREPARE);
                        $template = ResultSheetTemplate::active();
                        if ($template === null) {
                            throw new RuntimeException('An active institutional result template is required.');
                        }
                        if ($template->mode === ResultSheetTemplate::MODE_DOCX
                            && ($template->document_path === null || ! Storage::disk('local')->exists($template->document_path))) {
                            throw new RuntimeException('The active institutional result template file is missing.');
                        }
                        $this->remember(['result_template_id' => $template->id, 'result_template_checksum' => $template->printChecksum()]);
                    }
                    $session = $this->createSession($stage);
                }
                $attempts = [];
                foreach ($profiles as $position => $profile) {
                    $attempt = $this->createApplicant($profile, $stage, $position);
                    if ($stage !== 'review') {
                        app(AcceptAdmissionAttempt::class)->handle($attempt, $this->registrar, (int) $attempt->version);
                        $attempt->refresh();
                    }
                    if ($withCorrection && $position === 0) {
                        $attempt = app(RequestApplicationCorrection::class)->handle($attempt, $this->registrar,
                            (int) $attempt->version, 'Please correct the fictional school contact details before submitting this practice application again.');
                    }
                    if ($session !== null && ! in_array($stage, ['reservation', 'seating'], true)) {
                        app(AssignApplicantToExamSession::class)->handle($session, $attempt, $this->guidance);
                    }
                    $attempts[] = $attempt;
                }
                if ($session !== null && ! in_array($stage, ['reservation', 'seating'], true)) {
                    $session = app(PublishExamSession::class)->handle($session->fresh(), $this->guidance, (int) $session->fresh()->version);
                    foreach ($session->assignments()->orderBy('id')->get() as $position => $assignment) {
                        $profile = $profiles[$position];
                        $outcome = $later ? 'present' : ($profile['status'] ?? 'unresolved');
                        if (in_array($outcome, ['present', 'absent'], true)) {
                            app(RecordExamAttendance::class)->handle($assignment, $this->guidance, $outcome,
                                'practice-attendance-'.$assignment->id);
                        }
                        if (! $later && $outcome === ExamAttendanceRecord::OUTCOME_PRESENT && ($profile['submitted'] ?? false)) {
                            app(RecordExamSubmission::class)->handle($assignment, $this->guidance, 'practice-submission-'.$assignment->id);
                        }
                    }
                    if ($later) {
                        $session = app(CloseExamSession::class)->handle($session, $this->guidance, (int) $session->version);
                        $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();
                        foreach ($attempts as $position => $attempt) {
                            if ($withDocuments || ($profiles[$position]['scored'] ?? false)) {
                                app(RecordScores::class)->handle(
                                    $grading, $attempt, $this->scorePayload(),
                                    $this->guidance, 'practice-scores-'.$attempt->id,
                                    testKitVersionId: (int) $this->set->material_snapshot['test_kit_version_id'],
                                    normPackageVersionId: isset($this->set->material_snapshot['norm_package_version_id'])
                                        ? (int) $this->set->material_snapshot['norm_package_version_id'] : null,
                                );
                            }
                        }
                        if ($withDocuments) {
                            app(FinalizeGradingSession::class)->handle($grading, $this->guidance, (int) $grading->version);
                            foreach ($attempts as $attempt) {
                                $documents[] = app(PrepareResultDocument::class)->handle($attempt, $this->guidance)->id;
                            }
                        }
                    }
                }
                $gates = $withDocuments ? [
                    ['key' => 'physical_print', 'status' => 'missing', 'reason' => 'Use the actual document print workflow; no physical print or handover was performed.'],
                    ['key' => 'match_provenance', 'status' => 'unverified', 'reason' => 'Preparation does not run an engine or establish approved match/model provenance.'],
                ] : [];
                if (in_array($stage, ['program', 'enrollment'], true)) {
                    $gates[] = ['key' => 'consultation_outcome', 'status' => 'missing', 'reason' => 'Record the actual consultation and permitted program choice through ordinary actions after their physical/encounter prerequisites.'];
                }
                if ($stage === 'enrollment') {
                    $gates[] = ['key' => 'signed_enrollment_slip', 'status' => 'missing', 'reason' => 'A genuine signed slip and attributable verification are required; enrollment stamps were not manufactured.'];
                }
                if ($stage === 'review' && $this->set->purpose === 'research' && $this->set->scenario === 'student' && ! $withStudentNotice) {
                    $gates[] = ['key' => 'student_notice', 'status' => 'missing', 'reason' => 'The bound Registrar lacks current correction authority; no portal notice was invented or grant repaired.'];
                }
                $saved = [
                    'attempt_ids' => array_map(fn (AdmissionAttempt $attempt): int => (int) $attempt->id, $attempts),
                    'session_id' => $session?->id,
                    'grading_id' => $grading?->id,
                    'document_ids' => $documents,
                    'gates' => $gates,
                ];
                $snapshot = $this->set->material_snapshot ?? [];
                $snapshot['stages'][$stage] = $saved;
                $this->set->update(['material_snapshot' => $snapshot]);

                return $this->result($stage, $saved);
            });
        });
    }

    /** @param list<array<string, mixed>> $profiles */
    protected function preflightIntake(array $profiles): void
    {
        $this->window = ApplicationWindow::query()->where('academic_cycle_id', $this->cycle->id)->where('status', 'published')
            ->where('opens_at', '<=', now())->where('closes_at', '>=', now())->first()
            ?? throw new RuntimeException('A currently open published application window is required.');
        $policy = PublicPolicy::designatedIntake((int) $this->institution->getKey())
            ?? throw new RuntimeException('A designated published public intake privacy policy is required.');
        $revision = $policy->publishedRevision
            ?? throw new RuntimeException('The designated intake policy has no published revision.');
        $codes = collect($profiles)->flatMap(fn (array $profile): array => $this->programCodes($profile))->unique()->values();
        $programs = Program::query()->where('institution_id', $this->institution->id)->where('is_active', true)
            ->where('is_admission_offered', true)->whereIn('code', $codes)->get();
        if ($programs->count() !== $codes->count()) {
            throw new RuntimeException('Every starting-case preference requires an active offered institutional program.');
        }
        $this->remember([
            'application_window_id' => $this->window->id,
            'public_policy_id' => $policy->getKey(),
            'public_policy_revision_id' => $revision->getKey(),
            'public_policy_revision_digest' => $revision->contentDigest(),
            'program_ids' => array_values(array_unique([...($this->set->material_snapshot['program_ids'] ?? []), ...$programs->modelKeys()])),
        ]);
    }

    /** @param array<string, mixed> $profile @return list<string> */
    protected function programCodes(array $profile): array
    {
        return array_values((array) ($profile['programs'] ?? [$profile['default_program'] ?? $profile['final_program'] ?? 'BSIT']));
    }

    /** @param array<string, mixed> $profile */
    protected function createApplicant(array $profile, string $stage, int $position): AdmissionAttempt
    {
        $policy = PublicPolicy::designatedIntake((int) $this->institution->getKey())
            ?? throw new RuntimeException('A designated published public intake privacy policy is required.');
        $revision = $policy->publishedRevision
            ?? throw new RuntimeException('The designated intake policy has no published revision.');
        $birthdate = '2003-01-01';
        $intake = new AdmissionIntakeData(
            firstName: (string) $profile['first_name'],
            lastName: ['Santos', 'Reyes', 'Mendoza', 'Cruz'][$position % 4],
            birthdate: $birthdate,
            sex: 'female',
            contactEmail: Str::uuid().'@example.test',
            applicantType: 'new',
            programPreferenceCodes: $this->programCodes($profile),
            strand: (string) ($profile['strand'] ?? 'STEM'),
            termsAccepted: true,
            publicPolicyId: (int) $policy->getKey(),
            publicPolicyRevisionId: (int) $revision->getKey(),
            publicPolicyRevisionDigest: $revision->contentDigest(),
        );
        $result = app(SubmitAdmissionAttempt::class)->handle($this->institution, $this->cycle, $this->window,
            $intake, (string) $this->set->created_by_user_id);
        $attempt = $result['attempt'];
        if ($attempt->practice_fixture_set_id !== $this->set->id
            || $attempt->identity->practice_fixture_set_id !== $this->set->id) {
            throw new RuntimeException('Ordinary intake did not preserve the selected practice ownership.');
        }
        $credentials = $this->set->credentials ?? [];
        $credentials[(string) $attempt->applicant_identity_id] = [
            'access_key' => $result['access_token'], 'birthdate' => $birthdate,
            'admission_attempt_id' => $attempt->id, 'stage' => $stage,
        ];
        $this->set->update(['credentials' => $credentials]);

        return $attempt;
    }

    protected function createSession(string $stage): ExamSession
    {
        $bookletQuery = app(InstitutionalOmrBookletQuery::class);
        $bookletId = $this->set->material_snapshot['booklet_version_id'] ?? null;
        $booklet = $bookletId === null ? $bookletQuery->getActiveFinalizedBooklet()
            : OmrQuestionBookletVersion::query()->find($bookletId);
        if ($booklet === null || ! $booklet->isFinalized() || $booklet->status !== OmrQuestionBookletVersion::STATUS_ACTIVE
            || $booklet->templateVersion?->status !== 'active' || $booklet->templateVersion?->template?->is_synthetic !== false) {
            throw new RuntimeException('An unambiguous active finalized non-synthetic institutional booklet is required.');
        }
        $futureDays = match ($stage) {
            'reservation' => 2, 'seating' => 3, default => 0
        };
        $starts = $futureDays > 0 ? now()->addDays($futureDays)->setTime(9, 0) : now()->subMinutes(10);
        $ends = $starts->copy()->addHours(2);
        $room = null;
        $arrangement = null;
        $roomQuery = Room::query()->where('institution_id', $this->institution->id)->where('is_active', true)->orderBy('id');
        if (array_key_exists('room_candidate_ids', $this->set->material_snapshot ?? [])) {
            $roomQuery->whereIn('id', $this->set->material_snapshot['room_candidate_ids']);
        }
        $rooms = $roomQuery->get();
        $layoutQuery = SeatingArrangement::query()->where('is_active', true)->orderBy('id');
        if (array_key_exists('seating_candidate_ids', $this->set->material_snapshot ?? [])) {
            $layoutQuery->whereIn('id', $this->set->material_snapshot['seating_candidate_ids']);
        }
        $layouts = $layoutQuery->get();
        foreach ($rooms as $candidate) {
            if (! $candidate->isExamRoom() || (int) $candidate->capacity < 3
                || ExamSession::query()->forPracticeSet($this->set->id)->where('room_id', $candidate->id)->where('status', '!=', ExamSession::STATUS_CANCELLED)
                    ->where('starts_at', '<', $ends)->where('effective_end_at', '>', $starts)->exists()) {
                continue;
            }
            $selected = null;
            if ($this->institution->usesSeatingArrangements()) {
                foreach ($layouts as $layout) {
                    if ((int) $layout->rows * (int) $layout->columns < 3) {
                        continue;
                    }
                    try {
                        app(SeatingArrangementEligibility::class)->assertEligible($candidate, $layout);
                        $selected = $layout;
                        break;
                    } catch (ValidationException) {
                        continue;
                    }
                }
                if ($selected === null || (int) $selected->rows * (int) $selected->columns < 3) {
                    continue;
                }
            }
            $room = $candidate;
            $arrangement = $selected;
            break;
        }
        if ($room === null) {
            throw new RuntimeException('No configured institutional exam room/layout is available without a schedule collision.');
        }
        $code = 'PR-'.str_replace('-', '', $this->set->id).'-'.$stage;
        $seatingMode = $arrangement === null
            ? ExamSession::SEATING_MODE_SIMPLE_CAPACITY
            : ExamSession::SEATING_MODE_ASSIGNED_SEATING;
        $session = app(CreateExamSession::class)->handle($this->cycle, $room, $code,
            $starts->toDateTimeString(), $ends->toDateTimeString(), $this->guidance, $seatingMode, $booklet, $arrangement,
            testKitVersionId: isset($this->set->material_snapshot['test_kit_version_id'])
                ? (int) $this->set->material_snapshot['test_kit_version_id'] : null);
        $session->forceFill(['practice_fixture_set_id' => $this->set->id])->save();
        $this->remember(['booklet_version_id' => $booklet->id, 'answer_sheet_version_id' => $booklet->template_version_id]);
        $snapshot = $this->set->material_snapshot ?? [];
        $snapshot['session_dependencies'][(string) $session->id] = [
            'room_id' => $room->id, 'seating_arrangement_id' => $arrangement?->id, 'booklet_version_id' => $booklet->id,
        ];
        $this->set->update(['material_snapshot' => $snapshot]);
        if ($stage === 'reservation' && ! ($this->set->purpose === 'research' && $this->set->scenario === 'guidance')) {
            $session = app(OpenExamSessionReservation::class)->handle($session, $this->guidance, (int) $session->version);
        }

        return $session;
    }

    protected function preflightScores(): void
    {
        $kitId = $this->set->material_snapshot['test_kit_version_id'] ?? null;
        if (! is_numeric($kitId)) {
            throw new RuntimeException('Select an explicit locked Test Kit in this practice fixture before preparing scoring stages.');
        }
        $kit = TestKitVersion::query()->with('testInstrumentVersion.metrics')->findOrFail((int) $kitId);
        $eligibility = app(TestKitEligibility::class)->evaluate(
            $kit, $this->cycle, TestKitEligibility::PURPOSE_NEW_USE, operation: 'manual',
        );
        if (! $eligibility['eligible']) {
            throw new RuntimeException('Practice scoring Kit is unavailable: '.implode(',', array_column($eligibility['blockers'], 'code')));
        }
        $normId = $this->set->material_snapshot['norm_package_version_id'] ?? null;
        if (is_numeric($normId)) {
            $norm = AssessmentNormPackageVersion::query()->with('package')->findOrFail((int) $normId);
            if ($norm->status !== 'approved' || (int) $norm->package->institution_id !== (int) $this->institution->id) {
                throw new RuntimeException('Practice scoring requires an explicitly selected approved norm in this institution.');
            }
        }
    }

    /** @return list<array{metric_code:string,score:string}> */
    protected function scorePayload(): array
    {
        $kit = TestKitVersion::query()
            ->with('testInstrumentVersion.metrics')->findOrFail((int) $this->set->material_snapshot['test_kit_version_id']);

        return $kit->testInstrumentVersion->metrics
            ->reject(fn ($metric): bool => in_array($metric->rule_kind, [
                TestInstrumentMetric::RULE_SUM_RAW_METRICS,
                TestInstrumentMetric::RULE_FORMULA_RAW_METRICS,
            ], true))
            ->map(fn ($metric): array => ['metric_code' => $metric->code, 'score' => (string) ($metric->min_raw_value ?? 0)])
            ->values()->all();
    }

    /** @param array<string, mixed> $values */
    protected function remember(array $values): void
    {
        $this->set->update(['material_snapshot' => [...($this->set->material_snapshot ?? []), ...$values]]);
    }

    /** @param array<string, mixed> $saved @return array<string, mixed> */
    protected function result(string $stage, array $saved): array
    {
        $attempts = AdmissionAttempt::query()->where('practice_fixture_set_id', $this->set->id)
            ->whereIn('id', $saved['attempt_ids'])->orderBy('id')->get();
        $session = empty($saved['session_id']) ? null : ExamSession::query()->where('practice_fixture_set_id', $this->set->id)->findOrFail($saved['session_id']);
        $grading = empty($saved['grading_id']) ? null : GradingSession::query()->where('exam_session_id', $session?->id)->findOrFail($saved['grading_id']);

        return ['stage' => $stage, 'set_id' => $this->set->id, 'count' => $attempts->count(),
            'attempts' => $attempts->all(), 'session' => $session, 'grading' => $grading,
            'documents' => ResultDocument::query()->whereIn('admission_attempt_id', $attempts->modelKeys())->whereIn('id', $saved['document_ids'])->get()->all(),
            'gates' => $saved['gates']];
    }
}
