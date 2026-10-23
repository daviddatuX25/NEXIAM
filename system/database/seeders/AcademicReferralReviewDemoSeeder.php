<?php

namespace Database\Seeders;

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\Room;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Release\Actions\RecordConsultationDecision;
use App\Release\Actions\ReleaseResult;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Isolated synthetic academic referral review fixtures. Prerequisite exam,
 * score, result-document, and print-receipt rows are demonstration setup only;
 * they do not attest to an actual exam, physical print, or handover.
 */
final class AcademicReferralReviewDemoSeeder extends Seeder
{
    private const DATABASE = 'academic_review_isolated';

    private const RELEASED_REFERENCE = 'SYN-ARR-RELEASED-001';

    private const PRINT_ONLY_REFERENCE = 'SYN-ARR-PRINT-ONLY-001';

    private const RELEASED_ACCESS_KEY = 'ACRV-2A7B';

    private const PRINT_ONLY_ACCESS_KEY = 'ACRV-2C8D';

    private const DEAN_REFERENCE = 'SYN-ARR-DEAN-003';

    private const DEAN_ACCESS_KEY = 'ACRV-3D9E';

    public function run(): void
    {
        $this->assertIsolatedDatabase();

        if (! Institution::query()->where('code', 'SYN-INST-001')->exists()) {
            app(DatabaseSeeder::class)->run(DatabaseSeeder::testSeeders());
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'SYN-CYCLE-2026')
            ->where('is_active', true)
            ->firstOrFail();
        $window = $this->applicationWindow($cycle);
        $program = Program::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'BSIT')
            ->where('is_active', true)
            ->firstOrFail();
        $deanProgram = Program::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'BA-Psych')
            ->where('is_active', true)
            ->firstOrFail();
        $dean = User::query()->where('email', 'cas.dean@nexiam.test')->firstOrFail();
        $this->assertStaffBaseline($dean, 'program');
        if (! Department::query()->whereKey($deanProgram->department_id)
            ->where('institution_id', $institution->id)->where('dean_user_id', $dean->id)
            ->where('is_active', true)->exists()
            || ! in_array($deanProgram->code, $dean->actorScope()->programCodes, true)) {
            throw new RuntimeException('The existing CAS Dean does not currently own the second demo program; no scope was changed.');
        }
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $head = User::query()
            ->whereIn('email', ['program.head.bsit@nexiam.test', 'bsit.head@nexiam.test'])
            ->orderByRaw("CASE WHEN email = 'program.head.bsit@nexiam.test' THEN 0 ELSE 1 END")
            ->firstOrFail();
        $this->assertStaffBaseline($guidance, 'guidance');
        $this->assertStaffBaseline($head, 'program');
        $this->assertHeadOwnsProgram($head, $program);

        $attempts = DB::transaction(function () use ($institution, $cycle, $window, $program, $deanProgram, $guidance): array {
            $released = $this->applicantAttempt(
                $institution,
                $cycle,
                $window,
                $program,
                self::RELEASED_REFERENCE,
                'Synthetic Referral',
                'Applicant One',
                'synthetic.referral.one@nexiam.test',
                '2005-04-12',
                self::RELEASED_ACCESS_KEY,
            );
            $printOnly = $this->applicantAttempt(
                $institution,
                $cycle,
                $window,
                $program,
                self::PRINT_ONLY_REFERENCE,
                'Synthetic Print Only',
                'Applicant Two',
                'synthetic.print.only@nexiam.test',
                '2004-11-03',
                self::PRINT_ONLY_ACCESS_KEY,
            );
            $deanCase = $this->applicantAttempt(
                $institution,
                $cycle,
                $window,
                $deanProgram,
                self::DEAN_REFERENCE,
                'Synthetic Dean Scope',
                'Applicant Three',
                'synthetic.dean.three@nexiam.test',
                '2005-08-17',
                self::DEAN_ACCESS_KEY,
            );

            $room = $this->syntheticRoom($institution);
            $session = $this->syntheticExamSession($cycle, $room);
            $grading = $this->syntheticFinalizedGrading($session);
            foreach ([$released, $printOnly, $deanCase] as $attempt) {
                $this->syntheticScores($attempt, $grading, $guidance);
                $document = $this->readySyntheticDocument($attempt);
                $attempt->forceFill(['current_result_document_id' => $document->id])->save();
                $this->syntheticPrintReceipt($institution, $attempt, $document, $guidance);
            }

            $this->releaseAndRefer($released, $program, $guidance);
            $this->assertPrintOnlyHasNoRelease($printOnly);
            $this->releaseAndRefer($deanCase, $deanProgram, $guidance);

            return [$released->fresh(), $printOnly->fresh(), $deanCase->fresh()];
        });

        $this->command?->info('Synthetic academic referral review fixtures are ready: released/referral '.$attempts[0]->reference_code.' (applicant key '.self::RELEASED_ACCESS_KEY.'; birthdate 2005-04-12); print-only/no-release '.$attempts[1]->reference_code.' (applicant key '.self::PRINT_ONLY_ACCESS_KEY.'; birthdate 2004-11-03). Staff: guidance.counselor@nexiam.test and '.$head->email.' (existing password preserved). This synthetic data does not prove actual exam, physical print, or handover evidence.');
        $this->command?->info('Multi-program Dean case: '.$attempts[2]->reference_code.' / BA-Psych (applicant key '.self::DEAN_ACCESS_KEY.'; birthdate 2005-08-17). Dean: '.$dean->email.' (existing password preserved).');
    }

    private function assertIsolatedDatabase(): void
    {
        if (! app()->environment('local', 'testing') || DB::connection()->getDatabaseName() !== self::DATABASE) {
            throw new RuntimeException('Academic referral review demo seeding is restricted to local/testing on the academic_review_isolated database.');
        }
    }

    private function applicationWindow(AcademicCycle $cycle): ApplicationWindow
    {
        $window = ApplicationWindow::query()->where('code', 'SYN-ARR-2026')->first();
        if ($window !== null) {
            if ((int) $window->academic_cycle_id !== (int) $cycle->id) {
                throw new RuntimeException('The synthetic referral demo application window belongs to another cycle; no repair was made.');
            }

            return $window;
        }

        return ApplicationWindow::query()->create([
            'academic_cycle_id' => $cycle->id,
            'code' => 'SYN-ARR-2026',
            'name' => 'Synthetic academic referral review demo window',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addYear(),
            'status' => 'published',
        ]);
    }

    private function assertStaffBaseline(User $user, string $office): void
    {
        $profile = $user->staffProfile;
        if ($profile === null || ! $profile->isActive() || $profile->deactivated_at !== null || $profile->office !== $office) {
            throw new RuntimeException("The existing {$office} demo staff account is not currently active in its expected office; no account was changed.");
        }
    }

    private function assertHeadOwnsProgram(User $head, Program $program): void
    {
        if (! in_array($program->code, $head->actorScope()->programCodes, true)) {
            throw new RuntimeException('The existing Program Head demo account is not scoped to BSIT; no staff scope or password was changed.');
        }
    }

    private function applicantAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        Program $program,
        string $reference,
        string $firstName,
        string $lastName,
        string $email,
        string $birthdate,
        string $accessKey,
    ): AdmissionAttempt {
        $identity = ApplicantIdentity::query()->firstOrCreate(
            [
                'institution_id' => $institution->id,
                'normalized_key' => NormalizedIdentityKey::make($firstName, $lastName, $birthdate, 'female'),
            ],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'birthdate' => $birthdate,
                'sex' => 'female',
                'email' => $email,
                'phone' => '09170000000',
            ],
        );
        $this->assertSyntheticIdentity($identity, $firstName, $lastName, $birthdate, $email);
        $existingAccount = $identity->applicantAccount()->with('user.staffProfile')->first();
        if ($existingAccount?->user?->staffProfile !== null) {
            throw new RuntimeException('Synthetic applicant identity is linked to a staff account; no relinking was performed.');
        }

        $attempt = AdmissionAttempt::query()->firstOrCreate(
            ['reference_code' => $reference],
            [
                'applicant_identity_id' => $identity->id,
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'application_window_id' => $window->id,
                'status' => AdmissionAttempt::STATUS_ACCEPTED,
                'preferred_program_code' => $program->code,
                'program_preference_1_code' => $program->code,
                'applicant_type' => 'new',
                'last_school_enrolled' => 'Synthetic demonstration school',
                'strand' => 'STEM',
                'gwa' => '84.00',
                'contact_email' => $email,
                'contact_phone' => '09170000000',
                'address_line' => 'Synthetic demonstration address',
                'city' => 'Tagudin',
                'province' => 'Ilocos Sur',
                'zip_code' => '2714',
                'payload' => [
                    'synthetic_demo' => true,
                    'source' => self::class,
                    'workflow_proof' => false,
                    'note' => 'Accepted status is synthetic fixture setup, not an intake verification claim.',
                ],
                'version' => 1,
                'accepted_at' => now(),
            ],
        );
        if ((int) $attempt->applicant_identity_id !== (int) $identity->id
            || (int) $attempt->institution_id !== (int) $institution->id
            || (int) $attempt->academic_cycle_id !== (int) $cycle->id
            || $attempt->status !== AdmissionAttempt::STATUS_ACCEPTED
            || ($attempt->payload['source'] ?? null) !== self::class
            || ($attempt->payload['workflow_proof'] ?? true) !== false) {
            throw new RuntimeException("Synthetic attempt {$reference} changed or belongs to other data; no repair was made.");
        }

        ApplicantAccessChallenge::query()->firstOrCreate(
            ['request_key' => 'academic-referral-review-access-'.$reference],
            [
                'applicant_identity_id' => $identity->id,
                'admission_attempt_id' => $attempt->id,
                'purpose' => 'access_key',
                'token_hash' => ApplicantAccessKey::hash($accessKey),
                'expires_at' => now()->addYears(5),
            ],
        );
        $challenge = ApplicantAccessChallenge::query()->where('request_key', 'academic-referral-review-access-'.$reference)->firstOrFail();
        if ((int) $challenge->applicant_identity_id !== (int) $identity->id
            || (int) $challenge->admission_attempt_id !== (int) $attempt->id
            || ! hash_equals($challenge->token_hash, ApplicantAccessKey::hash($accessKey))) {
            throw new RuntimeException("Synthetic access challenge for {$reference} changed; no key was overwritten.");
        }

        return $attempt;
    }

    private function assertSyntheticIdentity(ApplicantIdentity $identity, string $firstName, string $lastName, string $birthdate, string $email): void
    {
        if ($identity->first_name !== $firstName
            || $identity->last_name !== $lastName
            || $identity->birthdate?->format('Y-m-d') !== $birthdate
            || $identity->email !== $email) {
            throw new RuntimeException('A synthetic applicant identity marker conflicts with existing data; no identity was overwritten.');
        }
    }

    private function syntheticRoom(Institution $institution): Room
    {
        $room = Room::query()->where('institution_id', $institution->id)->where('code', 'SYN-ARR-DEMO-ROOM')->first();
        if ($room !== null) {
            return $room;
        }

        return Room::query()->create([
            'institution_id' => $institution->id,
            'code' => 'SYN-ARR-DEMO-ROOM',
            'name' => 'Synthetic referral review fixture room (not a physical venue claim)',
            'capacity' => 2,
            'is_active' => true,
        ]);
    }

    private function syntheticExamSession(AcademicCycle $cycle, Room $room): ExamSession
    {
        $session = ExamSession::query()->firstOrCreate(
            ['academic_cycle_id' => $cycle->id, 'code' => 'SYN-ARR-DEMO-EXAM'],
            [
                'room_id' => $room->id,
                'label' => 'Synthetic prerequisite fixture only; not an actual exam session',
                'status' => ExamSession::STATUS_COMPLETED,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => now()->subDays(3),
                'effective_end_at' => now()->subDays(3)->addHour(),
                'published_at' => now()->subDays(4),
                'closed_at' => now()->subDays(3)->addHour(),
                'version' => 1,
            ],
        );
        if ($session->status !== ExamSession::STATUS_COMPLETED || (int) $session->room_id !== (int) $room->id) {
            throw new RuntimeException('The synthetic exam-session prerequisite changed; no repair was made.');
        }

        return $session;
    }

    private function syntheticFinalizedGrading(ExamSession $session): GradingSession
    {
        $grading = GradingSession::query()->firstOrCreate(
            ['exam_session_id' => $session->id],
            [
                'status' => GradingSession::STATUS_FINALIZED,
                'version' => 1,
                'opened_at' => now()->subDays(2),
                'finalized_at' => now()->subDay(),
            ],
        );
        if ($grading->status !== GradingSession::STATUS_FINALIZED) {
            throw new RuntimeException('The synthetic finalized-grading prerequisite changed; no repair was made.');
        }

        return $grading;
    }

    private function syntheticScores(AdmissionAttempt $attempt, GradingSession $grading, User $guidance): void
    {
        foreach (AptitudeArea::query()->where('is_active', true)->orderBy('id')->get() as $area) {
            $raw = (int) floor((int) $area->max_items * 0.8);
            $converted = $area->convertScore($raw)?->output_value;
            if ($converted === null) {
                throw new RuntimeException("A current conversion is required for synthetic score fixture area {$area->code}.");
            }

            $record = ScoreRecord::query()->firstOrCreate(
                [
                    'grading_session_id' => $grading->id,
                    'admission_attempt_id' => $attempt->id,
                    'aptitude_area_id' => $area->id,
                ],
                [
                    'score' => (string) $raw,
                    'converted_score' => $converted,
                    'recorded_by_user_id' => $guidance->id,
                    'idempotency_key' => 'academic-referral-review-score-'.$attempt->reference_code.'-'.$area->code,
                    'source' => ScoreRecord::SOURCE_MANUAL,
                    'version' => 1,
                ],
            );
            if ((int) $record->recorded_by_user_id !== (int) $guidance->id
                || (float) $record->score !== (float) $raw
                || (float) $record->converted_score !== (float) $converted) {
                throw new RuntimeException('A synthetic score fixture conflicts with existing data; no score was overwritten.');
            }
        }

        ExamAssignment::query()->firstOrCreate(
            ['exam_session_id' => $grading->exam_session_id, 'admission_attempt_id' => $attempt->id],
            [
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                'origin' => ExamAssignment::ORIGIN_MANUAL,
                'version' => 1,
            ],
        );
    }

    private function readySyntheticDocument(AdmissionAttempt $attempt): ResultDocument
    {
        $document = ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('template_version', 'academic-referral-review-synthetic-v1')
            ->first();
        if ($document !== null) {
            if ($document->status !== ResultDocument::STATUS_READY) {
                throw new RuntimeException('The synthetic ready-result prerequisite changed; no repair was made.');
            }

            return $document;
        }

        $snapshot = [
            'synthetic_demo' => true,
            'source' => self::class,
            'workflow_proof' => false,
            'reference_code' => $attempt->reference_code,
            'notice' => 'Synthetic fixture only. Not an institutional result, exam record, physical print, or handover evidence.',
        ];
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Synthetic result fixture</title></head><body><h1>Synthetic result fixture</h1><p>This developer fixture is not an institutional result, actual exam record, physical print, or handover evidence.</p><pre>'.e(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)).'</pre></body></html>';
        $checksum = hash('sha256', $html);
        $path = 'academic-referral-review/'.$attempt->reference_code.'/'.$checksum.'.html';
        if (! Storage::disk('local')->put($path, $html)) {
            throw new RuntimeException('Could not store the synthetic result fixture artifact.');
        }

        return ResultDocument::query()->create([
            'admission_attempt_id' => $attempt->id,
            'template_version' => 'academic-referral-review-synthetic-v1',
            'checksum' => $checksum,
            'status' => ResultDocument::STATUS_READY,
            'storage_path' => $path,
            'preview_path' => $path,
            'version' => 1,
            'snapshot_payload_json' => $snapshot,
            'artifact_manifest' => ['synthetic_demo' => true, 'workflow_proof' => false],
        ]);
    }

    private function syntheticPrintReceipt(Institution $institution, AdmissionAttempt $attempt, ResultDocument $document, User $guidance): void
    {
        $receipt = ResultDocumentPrintReceipt::query()->firstOrCreate(
            ['idempotency_key' => 'academic-referral-review-synthetic-print-'.$attempt->reference_code],
            [
                'institution_id' => $institution->id,
                'admission_attempt_id' => $attempt->id,
                'result_document_id' => $document->id,
                'confirmed_by_user_id' => $guidance->id,
                'confirmed_at' => now(),
                'operation' => 'synthetic_fixture_only',
                'correction_reason' => 'Synthetic prerequisite for the demo decision path; not evidence of a physical print or handover.',
                'evidence_revision' => 1,
                'is_current' => true,
            ],
        );
        if ((int) $receipt->admission_attempt_id !== (int) $attempt->id
            || (int) $receipt->result_document_id !== (int) $document->id
            || ! $receipt->is_current
            || $receipt->operation !== 'synthetic_fixture_only') {
            throw new RuntimeException('The synthetic print prerequisite conflicts with existing data; no receipt was overwritten.');
        }
    }

    private function releaseAndRefer(AdmissionAttempt $attempt, Program $program, User $guidance): void
    {
        $currentRelease = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->first();
        $document = ResultDocument::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('template_version', 'academic-referral-review-synthetic-v1')
            ->firstOrFail();
        $releaseKey = 'academic-referral-review-release-'.$attempt->reference_code;
        if ($currentRelease === null) {
            $currentRelease = app(ReleaseResult::class)->handle(
                $attempt,
                $document,
                $guidance,
                $releaseKey,
                ReleaseResult::MODE_F2F,
            );
        }
        if ((int) $currentRelease->result_document_id !== (int) $document->id || ! $currentRelease->is_current) {
            throw new RuntimeException('The current result release for the synthetic referral fixture does not match; no release was replaced.');
        }

        $decisionKey = 'academic-referral-review-referral-'.$attempt->reference_code;
        if (! ConsultationDecision::query()->where('idempotency_key', $decisionKey)->exists()) {
            app(RecordConsultationDecision::class)->handle(
                $attempt,
                $guidance,
                'refer',
                'Synthetic private counselor note; not an institutional assessment.',
                $decisionKey,
                $program->code,
                'Synthetic demo referral for '.$program->code.' program-fit review.',
            );
        }
        if (! $attempt->programFollowUps()->where('program_id', $program->id)->exists()) {
            throw new RuntimeException('The synthetic referral follow-up is missing; no review history was recreated.');
        }
    }

    private function assertPrintOnlyHasNoRelease(AdmissionAttempt $attempt): void
    {
        if (ResultRelease::query()->where('admission_attempt_id', $attempt->id)->where('is_current', true)->exists()) {
            throw new RuntimeException('The synthetic print-only case unexpectedly has a current result release.');
        }
    }
}
