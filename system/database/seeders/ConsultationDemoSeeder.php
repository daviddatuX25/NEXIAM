<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\QuotaReservation;
use App\Consultation\Actions\AssignApplicantToConsultationSession;
use App\Consultation\Actions\CreateConsultationSession;
use App\Consultation\Actions\PublishConsultationSession;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramPool;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Communications\PublicPolicy;
use App\Models\Consultation\ConsultationSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Release\Actions\PrepareResultDocument;
use App\Release\Actions\RecordConsultationDecision;
use Illuminate\Database\Seeder;

/**
 * Developer-ready consultation demo seeder.
 *
 * Populates all key consultation scenarios across Stage 6:
 *   1. Today's Scheduled (Pending Advise):   Juan Dela Cruz (cons.pending@nexiam.test)
 *   2. Today's Scheduled (Already Advised):  Maria Santos (cons.advised@nexiam.test)
 *   3. Unscheduled / Direct Walk-in Ready:   Pedro Penduko (cons.walkin@nexiam.test)
 *   4. Re-Advising Queue (Deferred):         Clara Reyes (cons.readvise@nexiam.test)
 *   5. Direct Lookup Edge Case (Needs Print): Ana Bautista (cons.noprint@nexiam.test)
 *   6. Future Session (Open for Reservation): CONS-OPEN-RESERVATIONS
 *
 * Idempotent: re-running when CONS-TODAY-01 exists is a no-op.
 */
class ConsultationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $this->backfillGwas($cycle);
        $window = ApplicationWindow::query()->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $room = Room::query()->whereIn('code', ['CC-01', 'ITBR-105', 'RM-101'])->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        if (ConsultationSession::query()->where('code', 'CONS-TODAY-01')->exists()) {
            $this->ensureOverQuotaDemo($institution, $cycle, $guidance);
            $this->command?->info('Consultation demo already seeded (CONS-TODAY-01); over-quota BSIT fixture ensured.');

            return;
        }

        // 1. Candidate: Juan Dela Cruz (Today's queue, Pending consultation)
        $juan = $this->createAttempt($institution, $cycle, $window, $registrar, [
            'email' => 'cons.pending@nexiam.test',
            'firstName' => 'Juan',
            'lastName' => 'Dela Cruz',
            'phone' => '09171110001',
            'program' => 'BSIT',
            'gwa' => 91.20,
        ]);
        $this->ensureScoredAndResultReady($cycle, $room, $juan, $guidance, 88, printed: true);

        // 2. Candidate: Maria Santos (Today's queue, Already Advised)
        $maria = $this->createAttempt($institution, $cycle, $window, $registrar, [
            'email' => 'cons.advised@nexiam.test',
            'firstName' => 'Maria',
            'lastName' => 'Santos',
            'phone' => '09171110002',
            'program' => 'BSIT',
            'gwa' => 86.40,
        ]);
        $this->ensureScoredAndResultReady($cycle, $room, $maria, $guidance, 92, printed: true);

        // 3. Candidate: Pedro Penduko (Unscheduled, Ready for Direct Consultation)
        $pedro = $this->createAttempt($institution, $cycle, $window, $registrar, [
            'email' => 'cons.walkin@nexiam.test',
            'firstName' => 'Pedro',
            'lastName' => 'Penduko',
            'phone' => '09171110003',
            'program' => 'BA-Psych',
            'gwa' => 84.00,
        ]);
        $this->ensureScoredAndResultReady($cycle, $room, $pedro, $guidance, 85, printed: true);

        // 4. Candidate: Clara Reyes (Re-Advising Queue: Deferred)
        $clara = $this->createAttempt($institution, $cycle, $window, $registrar, [
            'email' => 'cons.readvise@nexiam.test',
            'firstName' => 'Clara',
            'lastName' => 'Reyes',
            'phone' => '09171110004',
            'program' => 'BSIT',
            'gwa' => 88.50,
        ]);
        $this->ensureScoredAndResultReady($cycle, $room, $clara, $guidance, 78, printed: true);
        app(RecordConsultationDecision::class)->handle(
            $clara,
            $guidance,
            'defer',
            'Borderline score for BSIT; deferred to discuss alternate programs with family.',
            'cons-decision-clara',
        );

        // 5. Candidate: Ana Bautista (Direct Lookup Edge Case: Needs Result Sheet Print)
        $ana = $this->createAttempt($institution, $cycle, $window, $registrar, [
            'email' => 'cons.noprint@nexiam.test',
            'firstName' => 'Ana',
            'lastName' => 'Bautista',
            'phone' => '09171110005',
            'program' => 'BSIT',
            'gwa' => 89.75,
        ]);
        $this->ensureScoredAndResultReady($cycle, $room, $ana, $guidance, 82, printed: false);

        // Session 1: Today's Published Session with Juan and Maria assigned
        $todaySession = app(CreateConsultationSession::class)->handle(
            $cycle,
            $room,
            'CONS-TODAY-01',
            now()->startOfDay()->setTime(9, 0)->toDateTimeString(),
            ConsultationSession::BOOKING_STAFF_ONLY,
            $guidance,
        );
        app(AssignApplicantToConsultationSession::class)->handle($todaySession, $juan, $guidance);
        app(AssignApplicantToConsultationSession::class)->handle($todaySession, $maria, $guidance);
        $todaySession = $todaySession->fresh();
        app(PublishConsultationSession::class)->handle($todaySession, $guidance, (int) $todaySession->version);

        app(RecordConsultationDecision::class)->handle(
            $maria,
            $guidance,
            'approve',
            'Qualified for BSIT first preference.',
            'cons-decision-maria',
            defaultProgramCode: 'BSIT',
        );

        $this->ensureOverQuotaDemo($institution, $cycle, $guidance);

        // Session 2: Future Session Open for Reservation (Published with 1 reservation)
        $openSession = app(CreateConsultationSession::class)->handle(
            $cycle,
            $room,
            'CONS-FUTURE-01',
            now()->addDays(5)->setTime(13, 0)->toDateTimeString(),
            ConsultationSession::BOOKING_OPEN_FOR_RESERVATION,
            $guidance,
        );
        $enrique = $this->createAttempt($institution, $cycle, $window, $registrar, [
            'email' => 'cons.reserved@nexiam.test',
            'firstName' => 'Enrique',
            'lastName' => 'Gil',
            'phone' => '09171110006',
            'program' => 'BSIT',
            'gwa' => 87.30,
        ]);
        $this->ensureScoredAndResultReady($cycle, $room, $enrique, $guidance, 89, printed: true);
        app(AssignApplicantToConsultationSession::class)->handle($openSession, $enrique, $guidance);
        $openSession = $openSession->fresh();
        app(PublishConsultationSession::class)->handle($openSession, $guidance, (int) $openSession->version);

        // Session 3: Draft Session (for testing draft publishing / session setup)
        app(CreateConsultationSession::class)->handle(
            $cycle,
            $room,
            'CONS-DRAFT-01',
            now()->addDays(6)->setTime(10, 0)->toDateTimeString(),
            ConsultationSession::BOOKING_STAFF_ONLY,
            $guidance,
        );

        $this->command?->info('ConsultationDemoSeeder: Seeded today session (CONS-TODAY-01), published future session (CONS-FUTURE-01), draft session (CONS-DRAFT-01), and 6 test personas.');
    }

    /**
     * Ensure Maria Santos holds an active BSIT commitment and BSIT's ProgramQuota
     * is set to the already-committed count (1 standalone, or N when combined with
     * AdmissionSupportDemoSeeder) so advising Juan Dela Cruz or Pedro Penduko into
     * BSIT immediately demonstrates the non-blocking Over-Quota (+1) flow.
     */
    private function ensureOverQuotaDemo(
        Institution $institution,
        AcademicCycle $cycle,
        User $guidance,
    ): void {
        $bsit = Program::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'BSIT')
            ->first();

        if ($bsit === null) {
            return;
        }

        $maria = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', 'cons.advised@nexiam.test'))
            ->where('academic_cycle_id', $cycle->id)
            ->first();

        if ($maria !== null && ProgramPool::currentFor($maria->id) === null) {
            app(SetGuidanceProgramPool::class)->handle(
                $maria,
                $guidance,
                'BSIT',
                [['program_code' => 'BSIT', 'display_order' => 1, 'basis' => 'preference']],
                'Qualified for BSIT first preference.',
                'cons-decision-maria-pool',
            );
        }

        $juan = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', 'cons.pending@nexiam.test'))
            ->where('academic_cycle_id', $cycle->id)
            ->first();

        $pedro = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', 'cons.walkin@nexiam.test'))
            ->where('academic_cycle_id', $cycle->id)
            ->first();

        $totalCommitted = QuotaReservation::committedForCycle($cycle->id, $bsit->id);
        $juanCommitted = $juan !== null
            ? ($totalCommitted - QuotaReservation::committedForCycle($cycle->id, $bsit->id, $juan->id))
            : 0;
        $pedroCommitted = $pedro !== null
            ? ($totalCommitted - QuotaReservation::committedForCycle($cycle->id, $bsit->id, $pedro->id))
            : 0;
        $baselineSlots = max(1, $totalCommitted - $juanCommitted - $pedroCommitted);

        ProgramQuota::query()->updateOrCreate(
            [
                'program_id' => $bsit->id,
                'academic_cycle_id' => $cycle->id,
            ],
            [
                'slots' => $baselineSlots,
                'last_notice' => 'Consultation demo at-capacity quota for non-blocking Over-Quota (+1) advising verification.',
                'last_mutated_by_user_id' => $guidance->id,
            ],
        );
    }

    /**
     * Backfill only missing grades on consultation demo attempts so existing
     * sessions and applicant records become immediately usable on reruns.
     */
    private function backfillGwas(AcademicCycle $cycle): void
    {
        $gradesByEmail = [
            'cons.pending@nexiam.test' => 91.20,
            'cons.advised@nexiam.test' => 86.40,
            'cons.walkin@nexiam.test' => 84.00,
            'cons.readvise@nexiam.test' => 88.50,
            'cons.noprint@nexiam.test' => 89.75,
            'cons.reserved@nexiam.test' => 87.30,
        ];

        foreach ($gradesByEmail as $email => $gwa) {
            $attempt = AdmissionAttempt::query()
                ->where('academic_cycle_id', $cycle->id)
                ->whereHas('identity', fn ($query) => $query->where('email', $email))
                ->first();

            if ($attempt !== null && $attempt->gwa === null) {
                $attempt->forceFill(['gwa' => $gwa])->save();
            }
        }
    }

    /**
     * @param  array{email: string, firstName: string, lastName: string, phone: string, program: string, gwa: float}  $data
     */
    private function createAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
        array $data,
    ): AdmissionAttempt {
        $existing = AdmissionAttempt::query()
            ->whereHas('identity', fn ($q) => $q->where('email', $data['email']))
            ->where('academic_cycle_id', $cycle->id)
            ->first();
        if ($existing !== null) {
            if ($existing->gwa === null && isset($data['gwa'])) {
                $existing->forceFill(['gwa' => $data['gwa']])->save();
            }

            return $existing;
        }

        $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
            ?? throw new \RuntimeException('A designated published PublicPolicy revision is required for seeded intake.');
        $revision = $policy->publishedRevision
            ?? throw new \RuntimeException('The designated intake policy has no published revision.');

        $result = app(SubmitAdmissionAttempt::class)->handle(
            $institution,
            $cycle,
            $window,
            new AdmissionIntakeData(
                firstName: $data['firstName'],
                lastName: $data['lastName'],
                birthdate: '2004-05-15',
                sex: 'female',
                contactEmail: $data['email'],
                applicantType: 'new',
                programPreferenceCodes: [$data['program']],
                middleName: null,
                suffix: null,
                contactPhone: $data['phone'],
                lastSchoolEnrolled: 'Tagudin National High School',
                strand: 'STEM',
                addressLine: '100 University Ave',
                city: 'Tagudin',
                province: 'Ilocos Sur',
                zipCode: '2714',
                termsAccepted: true,
                publicPolicyId: (int) $policy->getKey(),
                publicPolicyRevisionId: (int) $revision->getKey(),
                publicPolicyRevisionDigest: $revision->contentDigest(),
            ),
            'consultation-demo-seeder',
        );

        $attempt = $result['attempt'];

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            app(AcceptAdmissionAttempt::class)->handle($attempt, $registrar, (int) ($attempt->version ?? 1));
            $attempt->refresh();
        }
        if ($attempt->gwa === null && isset($data['gwa'])) {
            $attempt->forceFill(['gwa' => $data['gwa']])->save();
        }

        return $attempt;
    }

    private function ensureScoredAndResultReady(
        AcademicCycle $cycle,
        Room $room,
        AdmissionAttempt $attempt,
        User $guidance,
        int $scoreVal,
        bool $printed,
    ): void {
        // Ensure dedicated completed ExamSession with finalized GradingSession
        $assignment = ExamAssignment::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', '!=', ExamAssignment::STATUS_CANCELLED)
            ->first();

        if ($assignment === null) {
            $examSession = ExamSession::query()->firstOrCreate(
                [
                    'academic_cycle_id' => $cycle->id,
                    'code' => 'EX-CONS-'.$attempt->id,
                ],
                [
                    'room_id' => $room->id,
                    'status' => ExamSession::STATUS_COMPLETED,
                    'starts_at' => now()->subDays(3),
                    'effective_end_at' => now()->subDays(3)->addHours(2),
                    'closed_at' => now()->subDays(3),
                ],
            );

            $assignment = ExamAssignment::query()->firstOrCreate(
                [
                    'exam_session_id' => $examSession->id,
                    'admission_attempt_id' => $attempt->id,
                ],
                [
                    'status' => ExamAssignment::STATUS_ASSIGNED,
                    'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                ],
            );

            $grading = GradingSession::query()->firstOrCreate(
                ['exam_session_id' => $examSession->id],
                [
                    'status' => GradingSession::STATUS_FINALIZED,
                    'opened_at' => now()->subDays(2),
                    'finalized_at' => now()->subDays(1),
                ],
            );
            $aptitudes = AptitudeArea::query()->where('is_active', true)->get();
            foreach ($aptitudes as $area) {
                ScoreRecord::query()->updateOrCreate(
                    [
                        'grading_session_id' => $grading->id,
                        'admission_attempt_id' => $attempt->id,
                        'aptitude_area_id' => $area->id,
                    ],
                    [
                        'score' => $scoreVal,
                        'converted_score' => $scoreVal,
                        'recorded_by_user_id' => $guidance->id,
                        'idempotency_key' => 'cons-score-'.$attempt->id.'-'.$area->id,
                        'source' => ScoreRecord::SOURCE_MANUAL,
                        'version' => 1,
                    ],
                );
            }
        }

        // Ensure ResultDocument is prepared
        $document = ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('status', ResultDocument::STATUS_READY)
            ->latest('id')
            ->first();

        if ($document === null) {
            $document = app(PrepareResultDocument::class)->handle($attempt, $guidance);
        }
        $attempt->forceFill(['current_result_document_id' => $document->id])->save();

        if ($printed) {
            $currentReceipt = ResultDocumentPrintReceipt::query()
                ->where('result_document_id', $document->id)
                ->where('operation', 'confirmed')
                ->where('is_current', true)
                ->first();

            if ($currentReceipt === null) {
                ResultDocumentPrintReceipt::query()
                    ->where('admission_attempt_id', $attempt->id)
                    ->where('is_current', true)
                    ->update(['is_current' => false]);

                ResultDocumentPrintReceipt::query()->create([
                    'institution_id' => $attempt->institution_id,
                    'admission_attempt_id' => $attempt->id,
                    'result_document_id' => $document->id,
                    'confirmed_by_user_id' => $guidance->id,
                    'confirmed_at' => now(),
                    'operation' => 'confirmed',
                    'evidence_revision' => (int) ResultDocumentPrintReceipt::query()
                        ->where('result_document_id', $document->id)
                        ->max('evidence_revision') + 1,
                    'is_current' => true,
                    'idempotency_key' => 'cons-print-'.$attempt->id.'-'.$document->id,
                ]);
            }
        }
    }
}
