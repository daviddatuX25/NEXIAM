<?php

namespace Database\Seeders;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Data\AdmissionIntakeData;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
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
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Deterministic exam session publication authority demo seeder.
 *
 * - Ensures `DEMO-ROUTED-MATERIALS` is finalized with all four Booklet Sets.
 * - Seeds `EX-PUB-READY` as an Action-backed publish-ready draft session in
 *   RM-101 with finalized Booklet, compatible active seating arrangement, and
 *   one dedicated accepted applicant (`APP-PUB-READY01`) assigned via
 *   AssignApplicantToExamSession.
 * - Upgrades `EX-DIST-003` (if present from CohortDistributionDemoSeeder) into
 *   a genuine Action-published session with its own dedicated applicant
 *   (`APP-PUB-DIST003`) and frozen material/seat snapshot.
 * - Preserves `EX-DIST-002` as the incomplete draft negative fixture and leaves
 *   the 10 unassigned cohort distribution candidates untouched.
 */
final class ExamPublicationAuthorityDemoSeeder extends Seeder
{
    public const READY_SESSION_CODE = 'EX-PUB-READY';

    public const READY_APPLICANT_EMAIL = 'pub.ready.applicant@nexiam.test';

    public const DIST003_APPLICANT_EMAIL = 'pub.dist003.applicant@nexiam.test';

    public function run(): void
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        if (! $institution->usesSeatingArrangements() || ! $institution->usesMultiSetBooklets()) {
            $this->command?->info('Advanced publication demo skipped; enable assigned seating and alternate Booklet Sets explicitly.');

            return;
        }
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->firstOrFail();
        $room101 = Room::query()->whereIn('code', ['ITBR-105', 'RM-101'])->firstOrFail();
        $counselor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

        $booklet = OmrQuestionBookletVersion::query()
            ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
            ->whereNotNull('finalized_at')
            ->first();
        if ($booklet === null) {
            if (OmrAnswerSheetTemplateVersion::query()->where('status', OmrAnswerSheetTemplateVersion::STATUS_ACTIVE)->doesntExist()) {
                (new OmrDemoSeeder)->run();
            }
            (new ExamMaterialRoutingDemoSeeder)->run();
            $booklet = OmrQuestionBookletVersion::query()
                ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
                ->whereNotNull('finalized_at')
                ->firstOrFail();
        }

        $arrangement = SeatingArrangement::query()
            ->where('normalized_name', 'main examination layout')
            ->where('is_active', true)
            ->first()
            ?? SeatingArrangement::query()
                ->where('is_active', true)
                ->whereRaw('(rows * columns) <= ?', [$room101->capacity])
                ->orderBy('id')
                ->firstOrFail();

        // 1. Upgrade EX-DIST-003 (if present) into a genuine Action-published session.
        $dist003 = ExamSession::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', 'EX-DIST-003')
            ->first();
        if ($dist003 !== null) {
            $isPublishedWithSnapshot = $dist003->status === ExamSession::STATUS_PUBLISHED
                && ExamSessionMaterialSnapshot::query()
                    ->where('exam_session_id', $dist003->id)
                    ->where('is_current', true)
                    ->exists();

            if (! $isPublishedWithSnapshot) {
                $distAttempt = $this->ensureDedicatedAcceptedAttempt(
                    $institution,
                    $cycle,
                    $window,
                    $registrar,
                    self::DIST003_APPLICANT_EMAIL,
                    'Publication',
                    'DistThree',
                    'APP-PUB-DIST003',
                );
                $dist003->assignments()->with(['attendanceRecord', 'submissionRecord'])->get()
                    ->each(function (ExamAssignment $assignment): void {
                        $assignment->attendanceRecord()?->delete();
                        $assignment->submissionRecord()?->delete();
                        $assignment->seatAllocations()->delete();
                        $assignment->delete();
                    });
                $dist003->forceFill([
                    'room_id' => $room101->id,
                    'booklet_version_id' => $booklet->id,
                    'seating_arrangement_id' => $arrangement->id,
                    'status' => ExamSession::STATUS_DRAFT,
                    'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                    'auto_assign_enabled' => false,
                    'starts_at' => now()->addDays(11)->setTime(9, 0),
                    'effective_end_at' => now()->addDays(11)->setTime(11, 0),
                    'published_at' => null,
                    'version' => 1,
                ])->save();

                app(AssignApplicantToExamSession::class)->handle($dist003->fresh(), $distAttempt, $counselor);
                app(PublishExamSession::class)->handle($dist003->fresh(), $counselor, 1);
            }
        }

        // 2. Seed EX-PUB-READY as the deterministic Action-backed publish-ready draft session.
        $readyAttempt = $this->ensureDedicatedAcceptedAttempt(
            $institution,
            $cycle,
            $window,
            $registrar,
            self::READY_APPLICANT_EMAIL,
            'Publication',
            'ReadyOne',
            'APP-PUB-READY01',
        );

        $startsAt = now()->addDays(13)->setTime(9, 0);
        $endsAt = now()->addDays(13)->setTime(11, 0);

        $existingReady = ExamSession::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('code', self::READY_SESSION_CODE)
            ->first();

        if ($existingReady !== null) {
            $existingReady->assignments()->with(['attendanceRecord', 'submissionRecord'])->get()
                ->each(function (ExamAssignment $assignment): void {
                    $assignment->attendanceRecord()?->delete();
                    $assignment->submissionRecord()?->delete();
                    $assignment->seatAllocations()->delete();
                    $assignment->delete();
                });
            $existingReady->delete();
        }

        $readySession = app(CreateExamSession::class)->handle(
            $cycle,
            $room101,
            self::READY_SESSION_CODE,
            $startsAt->toDateTimeString(),
            $endsAt->toDateTimeString(),
            $counselor,
            ExamSession::SEATING_MODE_ASSIGNED_SEATING,
            $booklet,
            $arrangement,
        );

        app(AssignApplicantToExamSession::class)->handle($readySession, $readyAttempt, $counselor);

        $preflight = app(ExamSessionPublishPreflight::class)->evaluate($readySession->fresh());
        if (! $preflight['ok']) {
            throw new RuntimeException('EX-PUB-READY failed publish preflight: '.($preflight['reason'] ?? $preflight['code']));
        }
    }

    private function ensureDedicatedAcceptedAttempt(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        User $registrar,
        string $email,
        string $firstName,
        string $lastName,
        string $referenceCode,
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
                ?? throw new RuntimeException('A designated published PublicPolicy revision is required for ExamPublicationAuthorityDemoSeeder.');
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
                addressLine: '123 Publication Demo St',
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
                'exam-publication-demo-seeder',
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

        // Clear any auto-assignment triggered during acceptance so this dedicated
        // attempt can be assigned to its target session without conflict.
        $existing->examAssignments()->with(['attendanceRecord', 'submissionRecord'])->get()
            ->each(function (ExamAssignment $assignment): void {
                $assignment->attendanceRecord()?->delete();
                $assignment->submissionRecord()?->delete();
                $assignment->seatAllocations()->delete();
                $assignment->delete();
            });

        return $existing->fresh();
    }
}
