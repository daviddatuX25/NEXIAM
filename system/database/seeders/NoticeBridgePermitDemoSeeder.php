<?php

namespace Database\Seeders;

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Support\ExamSessionPublishPreflight;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SeatingArrangement;
use App\Models\Foundation\SpaceKind;
use App\Models\User;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Scheduling\AssignmentPlanningMutex;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Synthetic accepted prerequisites and an Action-created/assigned draft only.
 * Publication, material snapshots, notices and provider delivery remain real workflow operations.
 */
final class NoticeBridgePermitDemoSeeder extends Seeder
{
    public const SESSION_CODE = 'EX-NOTICE-PERMIT';

    public const ROOM_CODE = 'RM-NOTICE-PERMIT';

    /** @var list<array{first_name:string,last_name:string,birthdate:string,sex:string,email:string,access_key:string,reference:string}> */
    public const APPLICANTS = [
        ['first_name' => 'Celia', 'last_name' => 'NoticePermit', 'birthdate' => '2004-06-18', 'sex' => 'female',
            'email' => 'notice.permit.celia@nexiam.test', 'access_key' => 'NPCA2345', 'reference' => 'APP-NOTICE-PERMIT-A'],
        ['first_name' => 'Dario', 'last_name' => 'NoticePermit', 'birthdate' => '2003-12-09', 'sex' => 'male',
            'email' => 'notice.permit.dario@nexiam.test', 'access_key' => 'NPCB6789', 'reference' => 'APP-NOTICE-PERMIT-B'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Notice permit fixtures are restricted to local/testing environments.');
        }
        if (! is_file(database_path(NoticeTemplateBridgeWordFixture::RELATIVE_PATH))) {
            throw new RuntimeException('Prepare the existing notice bridge Word fixture before seeding the permit proof.');
        }

        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        if (! $institution->usesSeatingArrangements() || ! $institution->usesMultiSetBooklets()) {
            throw new RuntimeException('Notice permit demo requires Allow assigned seating and Allow alternate Booklet Sets to be enabled explicitly before seeding its checkerboard fixture.');
        }
        $cycleId = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail()->id;
        $result = app(AssignmentPlanningMutex::class)->run(AssignmentPlanningMutex::DOMAIN_EXAM, (int) $cycleId, function () use ($institution, $cycleId): array {
            $cycle = AcademicCycle::query()->whereKey($cycleId)->where('institution_id', $institution->id)->where('is_active', true)
                ->lockForUpdate()->firstOrFail();
            $actor = $this->resolveActor($institution, $cycle);
            $existing = ExamSession::query()->where('academic_cycle_id', $cycle->id)->where('code', self::SESSION_CODE)
                ->lockForUpdate()->first();
            if ($existing !== null) {
                $this->assertOwnedParticipants($existing, $institution);

                return ['session' => $existing, 'actor' => $actor, 'replayed' => true];
            }

            $booklet = OmrQuestionBookletVersion::query()
                ->whereHas('booklet', fn ($query) => $query->where('code', ExamMaterialRoutingDemoSeeder::BOOKLET_CODE))
                ->where('status', OmrQuestionBookletVersion::STATUS_ACTIVE)->whereNotNull('finalized_at')
                ->with(['templateVersion', 'sets'])->orderByDesc('version')->firstOrFail();
            if ($booklet->templateVersion === null || ! $booklet->templateVersion->isUsable()) {
                throw new RuntimeException('The existing finalized permit Booklet must reference a currently usable Answer Sheet.');
            }
            $arrangement = SeatingArrangement::query()->where('normalized_name', 'main examination layout')
                ->where('is_active', true)->where('availability_mode', SeatingArrangement::AVAILABILITY_ALL_ROOMS)
                ->where('rows', 5)->where('columns', 6)->where('booklet_pattern', SeatingArrangement::PATTERN_CHECKERBOARD)
                ->firstOrFail();
            $room = Room::query()->firstOrCreate(['code' => self::ROOM_CODE], [
                'institution_id' => $institution->id, 'name' => 'Synthetic Notice Permit Demonstration Room',
                'space_kind' => SpaceKind::Room, 'capacity' => 30, 'is_active' => true,
                'default_seating_arrangement_id' => $arrangement->id,
            ]);
            if ((int) $room->institution_id !== (int) $institution->id || ! $room->is_active || ! $room->isExamRoom()
                || (int) $room->capacity !== 30 || (int) $room->default_seating_arrangement_id !== (int) $arrangement->id) {
                throw new RuntimeException('The dedicated permit fixture room changed; existing room metadata is preserved.');
            }
            $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->orderBy('id')->firstOrFail();
            $attempts = [];
            foreach (self::APPLICANTS as $applicant) {
                $attempts[] = $this->ensureAcceptedPrerequisite($institution, $cycle, $window, $applicant);
            }
            $cyclePolicy = ExamNotificationPolicy::fromStorage($cycle->exam_notification_policy);
            $override = $cyclePolicy->publicationEmailEnabled ? null : [
                'schema_version' => ExamNotificationPolicy::SCHEMA_VERSION, 'publication_email_enabled' => true,
            ];
            $cyclePolicy->withSessionOverride($override);
            $startsAt = now()->addHours(96)->startOfMinute();
            $session = app(CreateExamSession::class)->handle(
                $cycle, $room, self::SESSION_CODE, $startsAt->toDateTimeString(), $startsAt->copy()->addHours(2)->toDateTimeString(),
                $actor, ExamSession::SEATING_MODE_ASSIGNED_SEATING, $booklet, $arrangement, $override,
            );
            foreach ($attempts as $attempt) {
                app(AssignApplicantToExamSession::class)->handle($session, $attempt, $actor);
            }
            $session->refresh();
            $this->assertOwnedParticipants($session, $institution);
            $preflight = app(ExamSessionPublishPreflight::class)->evaluate($session);
            if (! $preflight['ok']) {
                throw new RuntimeException('The dedicated permit draft is not publish-ready: '.($preflight['reason'] ?? $preflight['code']));
            }

            return ['session' => $session, 'actor' => $actor, 'replayed' => false];
        });

        $session = $result['session'];
        $this->command?->info($result['replayed'] ? 'Existing permit fixture preserved; no session or assignment was rewritten.' : 'Permit draft created/assigned through Actions; accepted prerequisites remain synthetic. No publication or delivery performed.');
        $this->command?->line('Session: '.$session->code.'; id: '.$session->id.'; status: '.$session->status.'; version: '.$session->version);
        $this->command?->line('Starts at: '.$session->starts_at->toIso8601String().'; ends at: '.$session->effective_end_at->toIso8601String());
        $this->command?->line('Room id: '.$session->room_id.'; Booklet version id: '.$session->booklet_version_id.'; arrangement id: '.$session->seating_arrangement_id);
        $this->command?->line('Action authority: '.$result['actor']->email.'; Word fixture: '.database_path(NoticeTemplateBridgeWordFixture::RELATIVE_PATH));
    }

    private function resolveActor(Institution $institution, AcademicCycle $cycle): User
    {
        $scope = ['institution_id' => $institution->id, 'office' => 'guidance', 'cycle' => $cycle->code];
        foreach ([NoticeTemplateBridgeDemoSeeder::AUTHOR_EMAIL, 'guidance.counselor@nexiam.test'] as $email) {
            $actor = User::query()->where('email', $email)->first();
            if ($actor !== null && app(CapabilityAuthorizer::class)->decideFresh($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, $scope)->allowed) {
                return $actor;
            }
        }

        throw new RuntimeException('A currently authorized Guidance scheduling principal in the active cycle is required.');
    }

    /** @param array{first_name:string,last_name:string,birthdate:string,sex:string,email:string,access_key:string,reference:string} $applicant */
    private function ensureAcceptedPrerequisite(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window, array $applicant): AdmissionAttempt
    {
        $identity = ApplicantIdentity::query()->firstOrCreate([
            'institution_id' => $institution->id,
            'normalized_key' => NormalizedIdentityKey::make($applicant['first_name'], $applicant['last_name'], $applicant['birthdate'], $applicant['sex']),
        ], ['first_name' => $applicant['first_name'], 'last_name' => $applicant['last_name'],
            'birthdate' => $applicant['birthdate'], 'sex' => $applicant['sex'], 'email' => $applicant['email']]);
        $attempt = AdmissionAttempt::query()->firstOrCreate([
            'applicant_identity_id' => $identity->id, 'academic_cycle_id' => $cycle->id,
        ], ['institution_id' => $institution->id, 'application_window_id' => $window->id,
            'reference_code' => $applicant['reference'], 'status' => AdmissionAttempt::STATUS_ACCEPTED,
            'accepted_at' => now(), 'version' => 1, 'applicant_type' => 'new', 'contact_email' => $applicant['email'],
            'payload' => ['synthetic_fixture' => 'notice-bridge-permit-demo']]);
        if ((int) $attempt->institution_id !== (int) $institution->id || $attempt->reference_code !== $applicant['reference']
            || ! $attempt->isExamEligible() || ($attempt->payload['synthetic_fixture'] ?? null) !== 'notice-bridge-permit-demo') {
            throw new RuntimeException('An existing dedicated permit applicant changed; this seeder will not overwrite admission or identity state.');
        }
        if (! ApplicantAccessKey::isValidShape($applicant['access_key'])) {
            throw new RuntimeException('Invalid synthetic permit access key shape.');
        }
        ApplicantAccessChallenge::query()->firstOrCreate([
            'request_key' => 'notice-permit-demo-access-'.$applicant['reference'],
        ], ['applicant_identity_id' => $identity->id, 'admission_attempt_id' => $attempt->id,
            'purpose' => 'access_key', 'token_hash' => ApplicantAccessKey::hash($applicant['access_key']), 'expires_at' => now()->addYears(5)]);

        return $attempt;
    }

    private function assertOwnedParticipants(ExamSession $session, Institution $institution): void
    {
        $room = $session->room()->firstOrFail();
        $assignments = $session->assignments()->with('admissionAttempt.identity')->get();
        if ($room->code !== self::ROOM_CODE || (int) $room->institution_id !== (int) $institution->id || $assignments->count() !== 2) {
            throw new RuntimeException('The existing permit fixture no longer has its dedicated room and two participants.');
        }
        $references = [];
        foreach ($assignments as $assignment) {
            $attempt = $assignment->admissionAttempt;
            if ($attempt === null || (int) $attempt->institution_id !== (int) $institution->id
                || (int) $attempt->academic_cycle_id !== (int) $session->academic_cycle_id
                || (int) $attempt->identity?->institution_id !== (int) $institution->id
                || ($attempt->payload['synthetic_fixture'] ?? null) !== 'notice-bridge-permit-demo') {
                throw new RuntimeException('The dedicated permit assignments no longer resolve to scoped synthetic participants.');
            }
            $references[] = $attempt->reference_code;
        }
        sort($references);
        if ($references !== array_column(self::APPLICANTS, 'reference')) {
            throw new RuntimeException('The permit fixture contains a different roster; existing assignments are preserved.');
        }
    }
}
