<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Admission\Support\ApplicantAccessKey;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Communications\PublicPolicy;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Developer-ready clickable demo seeder for exam seating mode.
 *
 * Requirements:
 * 1. Targets SYN-INST-001 and SYN-CYCLE-2026.
 * 2. Leaves institution settings default-off (enable_seating_arrangements = false,
 *    enable_multi_set_booklets = false).
 * 3. Seeds an ordinary simple-capacity draft session EX-SIMPLE-DEMO-01 in RM-101
 *    with an accepted applicant assigned (DEMO-ACPT) using finalized A-only Booklet.
 * 4. Seeds an ordinary simple-capacity published session EX-SIMPLE-PUB-01 with
 *    frozen snapshot, logical slot allocation, Set A.
 * 5. Preserves existing advanced fixtures (EX-NOTICE-PERMIT, active seating arrangements)
 *    without silently forcing global settings on.
 * 6. Idempotent: firstOrCreate / updateOrCreate so it can run repeatedly without
 *    duplicating records or throwing.
 */
class ExamSeatingModeDemoSeeder extends Seeder
{
    public const DRAFT_SESSION_CODE = 'EX-SIMPLE-DEMO-01';

    public const PUB_SESSION_CODE = 'EX-SIMPLE-PUB-01';

    public const ROOM_CODE = 'RM-101';

    public const DRAFT_APPLICANT_KEY = 'DEMO-ACPT';

    public const PUB_APPLICANT_KEY = 'DEMO-PUB9';

    public function run(): void
    {
        // 1. Target SYN-INST-001 and SYN-CYCLE-2026.
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();

        // 2. Leave institution settings default-off (assigned seating off, alternate booklets off).
        $institution->update([
            'enable_seating_arrangements' => false,
            'enable_multi_set_booklets' => false,
        ]);

        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('code', 'SYN-CYCLE-2026')
            ->firstOrFail();

        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->first()
            ?? ApplicationWindow::query()->firstOrCreate(
                ['academic_cycle_id' => $cycle->id, 'code' => 'SYN-WINDOW-2026-A'],
                ['name' => 'Regular Admissions Intake Window', 'is_active' => true],
            );

        // Resolve staff actors
        $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->first()
            ?? (function () {
                if (class_exists(StaffIdentitySeeder::class)) {
                    (new StaffIdentitySeeder)->run();
                }

                return User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
            })();

        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->first()
            ?? User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

        // Resolve room RM-101 (exam room capacity 40)
        $room101 = Room::query()
            ->where('institution_id', $institution->id)
            ->where('code', self::ROOM_CODE)
            ->first()
            ?? Room::query()->firstOrCreate(
                ['institution_id' => $institution->id, 'code' => self::ROOM_CODE],
                [
                    'name' => 'Lecture Hall 101',
                    'capacity' => 40,
                    'space_kind' => SpaceKind::Room,
                    'is_active' => true,
                ],
            );

        // 3. Preserve existing advanced fixtures (EX-NOTICE-PERMIT, active SeatingArrangement)
        // without forcing global settings on or deleting existing layouts.
        $preservedArrangements = SeatingArrangement::query()->where('is_active', true)->get();
        $existingPermitSession = ExamSession::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', 'EX-NOTICE-PERMIT')
            ->first();

        // 4. Resolve or create finalized A-only Booklet
        $booklet = $this->resolveFinalizedSingleSetBooklet($counselor);

        // Verify Set A is finalized on the booklet
        $setA = OmrBookletSet::query()
            ->where('booklet_version_id', $booklet->id)
            ->where('set_code', 'A')
            ->where('status', OmrBookletSet::STATUS_FINALIZED)
            ->firstOrFail();

        // Non-overlapping schedule windows for demo sessions in RM-101
        $draftStartsAt = now()->addDays(14)->setTime(9, 0)->startOfMinute();
        $draftEndsAt = now()->addDays(14)->setTime(11, 0)->startOfMinute();

        $pubStartsAt = now()->addDays(14)->setTime(13, 0)->startOfMinute();
        $pubEndsAt = now()->addDays(14)->setTime(15, 0)->startOfMinute();

        // 5. Seed ordinary simple-capacity draft session EX-SIMPLE-DEMO-01 in RM-101
        // with accepted applicant assigned (DEMO-ACPT) and finalized A-only Booklet.
        $draftSession = ExamSession::query()->updateOrCreate(
            [
                'academic_cycle_id' => $cycle->id,
                'code' => self::DRAFT_SESSION_CODE,
            ],
            [
                'room_id' => $room101->id,
                'booklet_version_id' => $booklet->id,
                'seating_arrangement_id' => null,
                'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
                'status' => ExamSession::STATUS_DRAFT,
                'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'starts_at' => $draftStartsAt,
                'effective_end_at' => $draftEndsAt,
                'version' => 1,
            ],
        );

        $draftAttempt = $this->resolveDemoAcceptedAttempt($institution, $cycle, $window, $registrar);

        // Clear any stale assignment on another session to honor single-assignment invariant
        $draftAttempt->examAssignments()->where('exam_session_id', '!=', $draftSession->id)->get()
            ->each(function (ExamAssignment $assignment): void {
                $assignment->attendanceRecord()?->delete();
                $assignment->submissionRecord()?->delete();
                $assignment->seatAllocations()->delete();
                $assignment->delete();
            });

        $draftAssignment = ExamAssignment::query()->firstOrCreate(
            [
                'exam_session_id' => $draftSession->id,
                'admission_attempt_id' => $draftAttempt->id,
            ],
            [
                'status' => ExamAssignment::STATUS_ASSIGNED,
                'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                'origin' => ExamAssignment::ORIGIN_MANUAL,
                'version' => 1,
            ],
        );

        // 6. Seed ordinary simple-capacity published session EX-SIMPLE-PUB-01 with
        // frozen snapshot, logical slot allocation, Set A.
        $pubSession = ExamSession::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', self::PUB_SESSION_CODE)
            ->first();

        $isPublishedWithSnapshot = $pubSession !== null
            && $pubSession->status === ExamSession::STATUS_PUBLISHED
            && ExamSessionMaterialSnapshot::query()
                ->where('exam_session_id', $pubSession->id)
                ->where('is_current', true)
                ->exists();

        if (! $isPublishedWithSnapshot) {
            $pubSession = ExamSession::query()->updateOrCreate(
                [
                    'academic_cycle_id' => $cycle->id,
                    'code' => self::PUB_SESSION_CODE,
                ],
                [
                    'room_id' => $room101->id,
                    'booklet_version_id' => $booklet->id,
                    'seating_arrangement_id' => null,
                    'seating_mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY,
                    'status' => ExamSession::STATUS_DRAFT,
                    'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                    'starts_at' => $pubStartsAt,
                    'effective_end_at' => $pubEndsAt,
                    'version' => 1,
                ],
            );

            $pubAttempt = $this->ensureDedicatedAcceptedAttempt(
                $institution,
                $cycle,
                $window,
                $registrar,
                email: 'demo.simple.pub@nexiam.test',
                firstName: 'Simple',
                lastName: 'Published',
                referenceCode: 'APP-SIMPLE-PUB-01',
                accessKey: self::PUB_APPLICANT_KEY,
            );

            // Clear any stale assignment on another session to honor single-assignment invariant
            $pubAttempt->examAssignments()->where('exam_session_id', '!=', $pubSession->id)->get()
                ->each(function (ExamAssignment $assignment): void {
                    $assignment->attendanceRecord()?->delete();
                    $assignment->submissionRecord()?->delete();
                    $assignment->seatAllocations()->delete();
                    $assignment->delete();
                });

            ExamAssignment::query()->firstOrCreate(
                [
                    'exam_session_id' => $pubSession->id,
                    'admission_attempt_id' => $pubAttempt->id,
                ],
                [
                    'status' => ExamAssignment::STATUS_ASSIGNED,
                    'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
                    'origin' => ExamAssignment::ORIGIN_MANUAL,
                    'version' => 1,
                ],
            );

            $preflight = app(ExamSessionPublishPreflight::class)->evaluate($pubSession->fresh());
            if (! $preflight['ok']) {
                throw new RuntimeException('EX-SIMPLE-PUB-01 failed publish preflight: '.($preflight['reason'] ?? $preflight['code']));
            }

            app(PublishExamSession::class)->handle($pubSession->fresh(), $counselor, (int) $pubSession->fresh()->version);
            $pubSession->refresh();
        }

        $this->command?->info('ExamSeatingModeDemoSeeder: Clean ordinary simple demo sessions seeded successfully.');
        $this->command?->line('  Draft: '.self::DRAFT_SESSION_CODE.' (RM-101, Simple Capacity, A-only Booklet, Applicant: '.self::DRAFT_APPLICANT_KEY.')');
        $this->command?->line('  Published: '.self::PUB_SESSION_CODE.' (RM-101, Simple Capacity, Frozen Logical Slots, Set A)');
        $this->command?->line('  Institution settings preserved default-off (seating: false, alternate booklets: false).');
        if ($existingPermitSession !== null) {
            $this->command?->line('  Existing advanced fixture preserved: '.$existingPermitSession->code.' (Status: '.$existingPermitSession->status.')');
        }
    }

    /**
     * Resolves the intrinsic synthetic Set A demo without an Answer Sheet dependency.
     */
    private function resolveFinalizedSingleSetBooklet(User $actor): OmrQuestionBookletVersion
    {
        (new SecureBookletDemoSeeder)->run();
        $booklet = OmrQuestionBookletVersion::query()
            ->where('status', OmrQuestionBookletVersion::STATUS_FINALIZED)
            ->whereNotNull('finalized_at')
            ->where('allow_multiple_sets', false)
            ->whereHas('booklet', fn ($query) => $query->where('is_synthetic', true))
            ->whereHas('sets', fn ($query) => $query->where('set_code', 'A')->where('status', OmrBookletSet::STATUS_FINALIZED))
            ->orderByDesc('id')
            ->first();
        if ($booklet === null) {
            throw new RuntimeException('The intrinsic synthetic Set A Booklet demo could not be prepared.');
        }

        return $booklet;
    }

    /**
     * Resolves the DEMO-ACPT accepted attempt or creates a synthetic accepted attempt.
     */
    private function resolveDemoAcceptedAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
    ): AdmissionAttempt {
        $acceptedConfig = config('seed.applicants.accepted');
        $email = is_array($acceptedConfig) && isset($acceptedConfig['email'])
            ? (string) $acceptedConfig['email']
            : 'demo.accepted@nexiam.test';

        $attempt = AdmissionAttempt::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->whereHas('identity', fn ($query) => $query->where('email', $email))
            ->latest('id')
            ->first();

        if ($attempt === null) {
            $challenge = ApplicantAccessChallenge::query()
                ->where('token_hash', ApplicantAccessKey::hash(self::DRAFT_APPLICANT_KEY))
                ->latest('id')
                ->first();

            if ($challenge !== null && $challenge->admission_attempt_id !== null) {
                $attempt = AdmissionAttempt::query()->find($challenge->admission_attempt_id);
            }
        }

        if ($attempt === null) {
            try {
                if (class_exists(JourneyFixtureSeeder::class)) {
                    (new JourneyFixtureSeeder)->run();
                }
            } catch (\Throwable) {
                // Ignore and proceed to direct creation
            }

            $attempt = AdmissionAttempt::query()
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->whereHas('identity', fn ($query) => $query->where('email', $email))
                ->latest('id')
                ->first();
        }

        if ($attempt === null) {
            $attempt = $this->ensureDedicatedAcceptedAttempt(
                $institution,
                $cycle,
                $window,
                $registrar,
                email: $email,
                firstName: 'Demo',
                lastName: 'Accepted',
                referenceCode: 'APP-DEMO-ACPT',
                accessKey: self::DRAFT_APPLICANT_KEY,
            );
        }

        if ($attempt->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            $attempt = app(AcceptAdmissionAttempt::class)->handle(
                $attempt->fresh(),
                $registrar,
                (int) ($attempt->version ?? 1),
            );
        }

        return $attempt->fresh();
    }

    /**
     * Ensures an accepted admission attempt exists and has a pinned access key.
     */
    private function ensureDedicatedAcceptedAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
        string $email,
        string $firstName,
        string $lastName,
        string $referenceCode,
        ?string $accessKey = null,
    ): AdmissionAttempt {
        $existing = AdmissionAttempt::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where(function ($query) use ($email, $referenceCode): void {
                $query->where('reference_code', $referenceCode)
                    ->orWhereHas('identity', fn ($q) => $q->where('email', $email));
            })
            ->latest('id')
            ->first();

        if ($existing === null) {
            $policy = PublicPolicy::designatedIntake((int) $institution->getKey())
                ?? throw new RuntimeException('A designated published PublicPolicy revision is required for ExamSeatingModeDemoSeeder.');
            $revision = $policy->publishedRevision
                ?? throw new RuntimeException('The designated intake policy has no published revision.');

            $intake = new AdmissionIntakeData(
                firstName: $firstName,
                lastName: $lastName,
                birthdate: '2004-04-12',
                sex: 'female',
                contactEmail: $email,
                applicantType: 'new',
                programPreferenceCodes: ['BSIT'],
                middleName: null,
                suffix: null,
                contactPhone: '09171238888',
                lastSchoolEnrolled: 'Demo Senior High School',
                strand: 'STEM',
                addressLine: '123 Demo St',
                city: 'Tagudin',
                province: 'Ilocos Sur',
                zipCode: '2714',
                termsAccepted: true,
                publicPolicyId: (int) $policy->getKey(),
                publicPolicyRevisionId: (int) $revision->getKey(),
                publicPolicyRevisionDigest: $revision->contentDigest(),
            );

            $submitted = app(SubmitAdmissionAttempt::class)->handle(
                $institution,
                $cycle,
                $window,
                $intake,
                'exam-seating-mode-demo-seeder',
            );
            $existing = $submitted['attempt'];
        }

        if ($existing->reference_code !== $referenceCode) {
            $existing->forceFill(['reference_code' => $referenceCode])->save();
        }

        if ($existing->status !== AdmissionAttempt::STATUS_ACCEPTED) {
            $existing = app(AcceptAdmissionAttempt::class)->handle(
                $existing->fresh(),
                $registrar,
                (int) ($existing->version ?? 1),
            );
        }

        if ($accessKey !== null && ApplicantAccessKey::isValidShape($accessKey)) {
            $this->pinAccessKey($existing, $accessKey);
        }

        return $existing->fresh();
    }

    /**
     * Pins a deterministic access key to an admission attempt challenge.
     */
    private function pinAccessKey(AdmissionAttempt $attempt, string $accessKey): void
    {
        $hash = ApplicantAccessKey::hash($accessKey);

        $challenge = ApplicantAccessChallenge::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($challenge === null) {
            ApplicantAccessChallenge::query()->create([
                'applicant_identity_id' => $attempt->applicant_identity_id,
                'admission_attempt_id' => $attempt->id,
                'purpose' => 'access_key',
                'token_hash' => $hash,
                'request_key' => 'seed-pin-'.$attempt->id,
                'expires_at' => now()->addYears(5),
            ]);

            return;
        }

        $challenge->update([
            'token_hash' => $hash,
            'expires_at' => now()->addYears(5),
            'consumed_at' => null,
        ]);
    }
}
