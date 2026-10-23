<?php

namespace Database\Seeders;

use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\Foundation\SpaceKind;
use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateReceipt;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\Policies\ExamNotificationPolicy;
use App\Notifications\Templates\Actions\ImportCommunicationTemplateDocument;
use App\Notifications\Templates\Actions\SaveCommunicationTemplateDraft;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;

/**
 * Additive synthetic prerequisite fixtures, not proof of admission, session publication or delivery.
 * No workflow emitter, provider, scheduler, template review or publication runs here.
 */
final class NoticeTemplateBridgeDemoSeeder extends Seeder
{
    public const CYCLE_CODE = 'SYN-NOTICE-BRIDGE';

    public const SESSION_CODE = 'EX-NOTICE-BRIDGE-24H';

    public const ROOM_CODE = 'RM-NOTICE-BRIDGE';

    public const CREATE_KEY = 'notice-bridge-demo-reminder-create-v1';

    public const IMPORT_KEY = 'notice-bridge-demo-reminder-import-v1';

    public const ASSESSMENT_EMAIL = 'notice.bridge.assessment@nexiam.test';

    public const AUTHOR_EMAIL = 'notice.bridge.author@nexiam.test';

    /** @var list<array{first_name:string,last_name:string,birthdate:string,sex:string,email:string,access_key:string,reference:string}> */
    public const APPLICANTS = [
        ['first_name' => 'Amara', 'last_name' => 'NoticeBridge', 'birthdate' => '2004-04-12', 'sex' => 'female',
            'email' => 'notice.bridge.amara@nexiam.test', 'access_key' => 'NBRA-2345', 'reference' => 'APP-NOTICE-BRIDGE-A'],
        ['first_name' => 'Basil', 'last_name' => 'NoticeBridge', 'birthdate' => '2003-09-25', 'sex' => 'male',
            'email' => 'notice.bridge.basil@nexiam.test', 'access_key' => 'NBRB-6789', 'reference' => 'APP-NOTICE-BRIDGE-B'],
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Notice bridge synthetic fixtures are restricted to local/testing environments.');
        }
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $fixture = (new NoticeTemplateBridgeWordFixture)->generate();

        DB::transaction(function () use ($institution, $guidance, $fixture): void {
            $this->ensureDemoStaff($institution, self::ASSESSMENT_EMAIL, 'Synthetic Notice Bridge Assessment Reviewer',
                [CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG]);
            $this->ensureDemoStaff($institution, self::AUTHOR_EMAIL, 'Synthetic Notice Bridge Notice Author',
                [CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE]);
            $policy = new ExamNotificationPolicy(version: 1, publicationEmailEnabled: false,
                reminderOffsetsMinutes: [1440], reminderEmailEnabled: true);
            $cycle = AcademicCycle::query()->firstOrCreate(['code' => self::CYCLE_CODE], [
                'institution_id' => $institution->id, 'name' => 'Synthetic Notice Bridge Demonstration',
                'opens_on' => now()->startOfYear(), 'closes_on' => now()->endOfYear(),
                'is_active' => false, 'exam_notification_policy' => $policy->toArray(),
            ]);
            $cycle = AcademicCycle::query()->whereKey($cycle->id)->lockForUpdate()->firstOrFail();
            if ((int) $cycle->institution_id !== (int) $institution->id) {
                throw new RuntimeException('Notice bridge fixture cycle belongs to a different institution.');
            }
            $window = ApplicationWindow::query()->firstOrCreate([
                'academic_cycle_id' => $cycle->id, 'code' => 'SYN-NOTICE-BRIDGE-WINDOW',
            ], ['name' => 'Synthetic Notice Bridge Fixture Window', 'status' => 'published',
                'opens_at' => now()->startOfYear(), 'closes_at' => now()->endOfYear()]);
            $room = Room::query()->firstOrCreate(['code' => self::ROOM_CODE], [
                'institution_id' => $institution->id, 'name' => 'Notice Bridge Demonstration Room',
                'space_kind' => SpaceKind::Room, 'capacity' => 2, 'is_active' => true,
            ]);
            if ((int) $room->institution_id !== (int) $institution->id) {
                throw new RuntimeException('Notice bridge fixture room belongs to a different institution.');
            }
            $session = ExamSession::query()->firstOrCreate([
                'academic_cycle_id' => $cycle->id, 'code' => self::SESSION_CODE,
            ], ['room_id' => $room->id, 'label' => 'Synthetic notice fixture; not publication proof',
                'status' => ExamSession::STATUS_PUBLISHED, 'booking_mode' => ExamSession::BOOKING_STAFF_ONLY,
                'auto_assign_enabled' => false, 'starts_at' => now()->addHours(48)->startOfMinute(),
                'effective_end_at' => now()->addHours(50)->startOfMinute(), 'published_at' => now(), 'version' => 1,
                'notification_policy_snapshot' => $policy->snapshot(1)]);

            foreach (self::APPLICANTS as $applicant) {
                $this->ensureParticipant($institution, $cycle, $window, $session, $applicant);
            }
            app(ProvisionCanonicalCommunicationTemplates::class)->provision($institution, $guidance);
            $this->ensureImportedDraft($institution, $guidance, $fixture);
        });

        $this->command?->info('Notice bridge synthetic fixtures prepared; no reminder emitted or template reviewed/published.');
        $this->command?->line('Word upload fixture: '.$fixture);
        $this->command?->line('Existing schedule, credentials, authored drafts and publications are preserved on rerun.');
        $this->command?->line('Fixture starts outside reminder windows. After reviewed publication, use demo:prepare-notice-bridge-reminder --template-version=<published-version>.');
    }

    /** @param list<string> $capabilities */
    private function ensureDemoStaff(Institution $institution, string $email, string $name, array $capabilities): void
    {
        $user = User::query()->firstOrCreate(['email' => $email], [
            'name' => $name, 'password' => 'password',
        ]);
        $created = $user->wasRecentlyCreated;
        if (! $created) {
            $profile = $user->staffProfile()->first();
            if ($profile === null || (int) $profile->institution_id !== (int) $institution->id || $profile->office !== 'guidance') {
                throw new RuntimeException('The dedicated notice bridge fixture email is already bound outside its expected staff scope.');
            }
        } else {
            $user->forceFill(['email_verified_at' => now()])->save();
            foreach ($capabilities as $capability) {
                $user->givePermissionTo(Permission::findOrCreate($capability, 'web'));
            }
        }
        sort($capabilities);
        if ($user->roles()->exists()
            || $user->getAllPermissions()->pluck('name')->sort()->values()->all() !== $capabilities) {
            throw new RuntimeException('The dedicated notice bridge staff grants changed; this seeder will not alter existing authority.');
        }
        StaffProfile::query()->firstOrCreate(['user_id' => $user->id], [
            'institution_id' => $institution->id, 'office' => 'guidance', 'program_codes' => [],
            'active_cycle_code' => AcademicCycle::active()?->code, 'status' => StaffProfile::STATUS_ACTIVE,
        ]);
        StaffAuthenticationState::query()->firstOrCreate(['user_id' => $user->id], [
            'activation_completed_at' => now(), 'password_set_at' => now(), 'must_change_password' => false,
            'session_version' => 1, 'mfa_required' => false, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null,
        ]);
    }

    /** @param array{first_name:string,last_name:string,birthdate:string,sex:string,email:string,access_key:string,reference:string} $applicant */
    private function ensureParticipant(Institution $institution, AcademicCycle $cycle, ApplicationWindow $window, ExamSession $session, array $applicant): void
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
            'payload' => ['synthetic_fixture' => 'notice-template-studio-document-bridge']]);
        ExamAssignment::query()->firstOrCreate([
            'exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id,
        ], ['status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED,
            'origin' => ExamAssignment::ORIGIN_MANUAL, 'version' => 1]);
        if (! ApplicantAccessKey::isValidShape($applicant['access_key'])) {
            throw new RuntimeException('Invalid notice bridge synthetic access key shape.');
        }
        ApplicantAccessChallenge::query()->firstOrCreate([
            'request_key' => 'notice-bridge-demo-access-'.$applicant['reference'],
        ], ['applicant_identity_id' => $identity->id, 'admission_attempt_id' => $attempt->id,
            'purpose' => 'access_key', 'token_hash' => ApplicantAccessKey::hash($applicant['access_key']),
            'expires_at' => now()->addYears(5)]);
    }

    private function ensureImportedDraft(Institution $institution, User $guidance, string $fixture): void
    {
        $family = CommunicationTemplate::query()->where('institution_id', $institution->id)
            ->where('domain', 'examination')->where('slug', 'reminder')->lockForUpdate()->firstOrFail();
        $seeded = CommunicationTemplateReceipt::query()->where('institution_id', $institution->id)
            ->where('domain', 'examination')->where('canonical_slug', 'reminder')
            ->where('idempotency_key', self::CREATE_KEY)->exists();
        if ($seeded || $family->draft()->exists() || $family->versions()->where('content_schema_version', 'notice-content-2')->exists()) {
            return;
        }
        $slot = CommunicationTemplateRegistry::findBySlug($family->domain, $family->slug)
            ?? throw new RuntimeException('The canonical reminder slot is unavailable.');
        $created = app(SaveCommunicationTemplateDraft::class)->save($family, [
            'base_version' => (int) $family->active_version, 'family_revision' => (int) $family->family_revision,
            'lock_version' => null, 'idempotency_key' => self::CREATE_KEY, 'stage' => 1,
            'state' => ['subject' => 'Examination reminder — {{session_code}}',
                'body' => '{{applicant_name}}, your exam is on {{exam_date}} at {{exam_time}} in {{room_name}}.',
                'action_label' => $slot['default_action_label'], 'action_route' => $slot['default_action_route'], 'email_blueprint' => null,
                'document_mode' => 'none', 'document_asset_id' => null, 'builder_json' => null, 'paper_size' => null,
                'orientation' => null, 'document_source_drafts' => null, 'import_provenance' => null],
        ], $guidance);
        $family->refresh();
        app(ImportCommunicationTemplateDocument::class)->handle($family, new UploadedFile(
            $fixture, 'reminder.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true,
        ), $guidance, false, [
            'base_version' => (int) $family->active_version, 'family_revision' => (int) $family->family_revision,
            'lock_version' => (int) $created['draft']['lock_version'], 'idempotency_key' => self::IMPORT_KEY,
        ]);
    }
}
