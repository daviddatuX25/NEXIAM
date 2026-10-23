<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Assessment\Actions\RecordScores;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Developer-ready grading demo (GradingWorkflow HANDOFF).
 *
 * Creates one completed exam session (EX-DEMO-1) with two accepted applicants,
 * closes it (auto-opens the grading session), and pre-scores the first
 * applicant so a human can finish scoring applicant two and click finalize.
 *
 * Run AFTER `foundation:reset --force`:
 *   php artisan db:seed --class=Database\\Seeders\\GradingDemoSeeder
 *
 * Idempotent: re-running when EX-DEMO-1 already has a grading session is a no-op.
 */
class GradingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $room = Room::query()->whereIn('code', ['ITBR-105', 'RM-101'])->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        $gradingSession = GradingSession::query()
            ->whereHas('examSession', fn ($q) => $q->where('code', 'EX-DEMO-1'))
            ->first();

        if ($gradingSession) {
            $this->command?->info('Grading demo already seeded (EX-DEMO-1).');
            $this->call(OmrDemoBatchSeeder::class);

            return;
        }

        $applicantA = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', (string) config('seed.applicants.accepted.email')))
            ->where('academic_cycle_id', $cycle->id)
            ->orderByDesc('id')
            ->firstOrFail();

        $applicantB = $this->ensureAcceptedApplicant($institution, $cycle, $window, $registrar);

        $session = ExamSession::query()->updateOrCreate(
            ['code' => 'EX-DEMO-1'],
            [
                'academic_cycle_id' => $cycle->id,
                'room_id' => $room->id,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => now()->subHours(2),
                'effective_end_at' => now()->addMinutes(30),
                'version' => 1,
                'booklet_version_id' => OmrQuestionBookletVersion::query()
                    ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
                    ->whereNotNull('finalized_at')
                    ->value('id'),
                'seating_arrangement_id' => $room->default_seating_arrangement_id,
            ],
        );

        foreach ([$applicantA, $applicantB] as $attempt) {
            $assigned = ExamAssignment::query()
                ->where('exam_session_id', $session->id)
                ->where('admission_attempt_id', $attempt->id)
                ->exists();

            if (! $assigned) {
                app(AssignApplicantToExamSession::class)
                    ->handle($session, $attempt, $guidance);
            }
        }

        app(PublishExamSession::class)->handle($session, $guidance, 1);

        // GRE-1/3 — applicant A is present + submitted (gradable), applicant B
        // is absent (no-show) and excluded from the grading roster.
        $session->assignments()->orderBy('admission_attempt_id')->each(function (ExamAssignment $assignment) use ($applicantA, $guidance): void {
            $outcome = $assignment->admission_attempt_id === $applicantA->id
                ? ExamAttendanceRecord::OUTCOME_PRESENT
                : ExamAttendanceRecord::OUTCOME_ABSENT;

            app(RecordExamAttendance::class)->handle($assignment, $guidance, $outcome, 'grading-demo-att-'.$assignment->id);

            if ($outcome === ExamAttendanceRecord::OUTCOME_PRESENT) {
                app(RecordExamSubmission::class)->handle($assignment, $guidance, 'grading-demo-sub-'.$assignment->id);
            }
        });

        app(CloseExamSession::class)->handle($session->fresh(), $guidance, 2);

        $grading = GradingSession::query()->where('exam_session_id', $session->id)->firstOrFail();

        $areas = AptitudeArea::query()->where('is_active', true)->pluck('id');

        app(RecordScores::class)->handle(
            $grading,
            $applicantA,
            $areas->map(fn (int $areaId): array => ['aptitude_area_id' => $areaId, 'score' => 85])->all(),
            $guidance,
            'grading-demo-'.$applicantA->id,
        );

        $this->command?->info('Grading demo ready: EX-DEMO-1 open grading session with 2 applicants (1 fully scored).');

        $this->call(OmrDemoBatchSeeder::class);
    }

    private function ensureAcceptedApplicant(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
    ): AdmissionAttempt {
        $email = 'grading.demo.two@nexiam.test';

        $attempt = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', $email))
            ->where('academic_cycle_id', $cycle->id)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new \RuntimeException('A designated published PublicPolicy revision is required for seeded intake.');
            $revision = $policy->publishedRevision
                ?? throw new \RuntimeException('The designated intake policy has no published revision.');
            $result = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                new AdmissionIntakeData(
                    firstName: 'Grading',
                    lastName: 'Demo Two',
                    birthdate: '2002-02-02',
                    sex: 'female',
                    contactEmail: $email,
                    applicantType: 'new',
                    programPreferenceCodes: ['BSIT', 'BA-Psych'],
                    middleName: null,
                    suffix: null,
                    contactPhone: '09171234568',
                    lastSchoolEnrolled: 'Tagudin National High School',
                    strand: 'STEM',
                    addressLine: '124 Demo St',
                    city: 'Tagudin',
                    province: 'Ilocos Sur',
                    zipCode: '2714',
                    termsAccepted: true,
                    publicPolicyId: (int) $policy->getKey(),
                    publicPolicyRevisionId: (int) $revision->getKey(),
                    publicPolicyRevisionDigest: $revision->contentDigest(),
                ),
                'grading-demo-seeder',
            );

            $attempt = $result['attempt'];
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) $attempt->version);
        }

        return $attempt;
    }
}
