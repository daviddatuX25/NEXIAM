<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Authorization\CapabilityCatalog;
use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicationOpeningAlertReceipt;
use App\Models\Admission\ApplicationOpeningOccurrence;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Admission\ApplicationOpeningToken;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\ApplicationOpeningAlertDelivery;
use App\Notifications\NotificationDelivery;
use App\Notifications\Templates\ProvisionCanonicalCommunicationTemplates;
use App\Setup\Actions\ActivateAcademicCycle;
use App\Setup\Actions\DeactivateAcademicCycle;
use App\Setup\Actions\UpdateAcademicCycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;

final class ApplicationOpeningAlertsDemoSeeder extends Seeder
{
    public const CYCLE_CODE = 'OPENING-ALERTS-DEMO';

    public function run(): void
    {
        self::guard();
        if (! Institution::query()->where('code', 'SYN-INST-001')->exists()) {
            (new DatabaseSeeder)->run([
                ReferenceCatalogSeeder::class,
                InstitutionProfileSeeder::class,
                CapabilityCatalogSeeder::class,
                StaffIdentitySeeder::class,
                PublicHomepageSeeder::class,
                PublicIntakeConsentDemoSeeder::class,
            ]);
        }
        $institution = Institution::current();
        $this->call(ApplicationSuggestionListSeeder::class);
        $cycle = AcademicCycle::query()->firstOrCreate(['code' => self::CYCLE_CODE], [
            'institution_id' => $institution->id,
            'name' => 'Synthetic opening-alert demonstration',
            'school_year' => '2026-2027',
            'semester' => '1',
            'opens_on' => now()->addDay()->toDateString(),
            'closes_on' => now()->addDays(20)->toDateString(),
            'is_active' => false,
        ]);
        ApplicationWindow::query()->firstOrCreate(['code' => self::CYCLE_CODE.'-WIN'], [
            'academic_cycle_id' => $cycle->id,
            'name' => 'Synthetic opening-alert application window',
            'opens_at' => $cycle->opens_on->copy()->startOfDay(),
            'closes_at' => $cycle->closes_on->copy()->endOfDay(),
            'status' => 'draft',
        ]);
        foreach (['registrar', 'guidance'] as $office) {
            $actor = User::query()->firstOrCreate(['email' => 'opening.'.$office.'@nexiam.test'], [
                'name' => 'Synthetic Opening Alerts '.ucfirst($office),
                'password' => 'Opening-Demo-2026!',
            ]);
            if ($actor->wasRecentlyCreated) {
                StaffProfile::query()->create([
                    'user_id' => $actor->id,
                    'institution_id' => $institution->id,
                    'office' => $office,
                    'active_cycle_code' => $cycle->code,
                    'status' => 'active',
                    'program_codes' => [],
                ]);
                StaffAuthenticationState::query()->create([
                    'user_id' => $actor->id,
                    'activation_completed_at' => now(),
                    'password_set_at' => now(),
                    'must_change_password' => false,
                    'session_version' => 1,
                    'mfa_required' => false,
                    'two_factor_generation' => (string) Str::uuid(),
                ]);
                $permissions = [CapabilityCatalog::SETUP_CYCLES_MANAGE];
                if ($office === 'registrar') {
                    $permissions[] = CapabilityCatalog::SETUP_INSTITUTION_MANAGE;
                }
                foreach ($permissions as $permission) {
                    $actor->givePermissionTo(Permission::findOrCreate($permission, 'web'));
                }
            }
        }
        app(ProvisionCanonicalCommunicationTemplates::class)->provision(
            $institution,
            User::query()->where('email', 'opening.registrar@nexiam.test')->firstOrFail(),
            'admissions',
            ['opening-alert-verify', 'opening-alert-opened'],
        );
        foreach (['pending', 'verified', 'consumed', 'unsubscribed', 'expired'] as $state) {
            $email = 'opening.'.$state.'@example.test';
            ApplicationOpeningSubscription::query()->firstOrCreate([
                'institution_id' => $institution->id,
                'normalized_email' => $email,
            ], [
                'email' => $email,
                'state' => $state,
                'generation' => 1,
                'consented_at' => now()->subDays(2),
                'consent_version' => 'opening-alerts-v1',
                'verified_at' => $state === 'pending' ? null : now()->subDay(),
                'consumed_at' => $state === 'consumed' ? now()->subHour() : null,
                'unsubscribed_at' => $state === 'unsubscribed' ? now()->subHour() : null,
                'expires_at' => $state === 'expired' ? now()->subHour() : now()->addMonths(12),
            ]);
        }
        $this->failureFixture($institution, $cycle);
        if ($cycle->wasRecentlyCreated) {
            self::scenario('upcoming');
        }
    }

    public static function scenario(string $state): void
    {
        self::guard();
        if (! in_array($state, ['no-active', 'upcoming', 'open', 'ended'], true)) {
            throw new RuntimeException('Choose no-active, upcoming, open or ended.');
        }
        $cycle = AcademicCycle::query()->where('code', self::CYCLE_CODE)->firstOrFail();
        $actor = User::query()->where('email', 'opening.registrar@nexiam.test')->firstOrFail();
        $start = $state === 'upcoming' ? now()->addDay() : now()->subDays(5);
        $end = $state === 'ended' ? now()->subDay() : now()->addDays(20);
        app(UpdateAcademicCycle::class)->handle($cycle, [
            'school_year' => $cycle->school_year,
            'semester' => $cycle->semester,
            'application_start_date' => $start->toDateString(),
            'application_end_date' => $end->toDateString(),
        ], $actor);
        app(ActivateAcademicCycle::class)->handle($cycle->fresh(), $actor);
        if ($state === 'no-active') {
            app(DeactivateAcademicCycle::class)->handle($cycle->fresh(), $actor);
        }
    }

    private function failureFixture(Institution $institution, AcademicCycle $cycle): void
    {
        $email = 'opening.failure@example.test';
        $subscription = ApplicationOpeningSubscription::query()->firstOrCreate([
            'institution_id' => $institution->id,
            'normalized_email' => $email,
        ], [
            'email' => $email,
            'state' => 'consumed',
            'generation' => 1,
            'consented_at' => now()->subDays(2),
            'consent_version' => 'opening-alerts-v1',
            'verified_at' => now()->subDay(),
            'consumed_at' => now()->subHour(),
            'expires_at' => now()->addYear(),
        ]);
        if ($subscription->receipts()->exists()) {
            return;
        }
        DB::transaction(function () use ($institution, $cycle, $subscription): void {
            $window = $cycle->primaryWindow();
            $occurrence = ApplicationOpeningOccurrence::query()->firstOrCreate([
                'availability_key' => hash('sha256', 'synthetic-failed-opening:'.$institution->id),
            ], [
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'application_window_id' => $window->id,
                'opened_at' => $window->opens_at,
                'closes_at' => $window->closes_at,
                'observed_at' => now(),
                'fanout_complete' => true,
            ]);
            $tokens = [];
            foreach (['unsubscribe', 'prefill'] as $purpose) {
                $plain = Str::random(64);
                $tokens[$purpose] = ApplicationOpeningToken::query()->create([
                    'id' => (string) Str::uuid(),
                    'subscription_id' => $subscription->id,
                    'generation' => 1,
                    'purpose' => $purpose,
                    'token_hash' => hash('sha256', $plain),
                    'encrypted_token' => $plain,
                    'expires_at' => now()->addDay(),
                ]);
            }
            $delivery = app(ApplicationOpeningAlertDelivery::class)->openingAfterCommit(
                $subscription, $occurrence, $cycle, $window, $institution,
                $tokens['unsubscribe'], $tokens['prefill'],
            );
            if ($delivery === null) {
                throw new RuntimeException('Canonical opening-alert templates must be provisioned before demo delivery fixtures.');
            }
            $delivery->update([
                'status' => NotificationDelivery::STATUS_FAILED_TERMINAL,
                'attempts' => ProcessNotificationDelivery::MAX_ATTEMPTS,
                'last_error' => 'synthetic_demo_terminal_failure_no_external_send',
            ]);
            ApplicationOpeningAlertReceipt::query()->create([
                'subscription_id' => $subscription->id,
                'generation' => 1,
                'occurrence_id' => $occurrence->id,
                'delivery_id' => $delivery->id,
                'status' => 'claimed',
                'claimed_at' => now(),
                'consumed_at' => now(),
            ]);
        });
    }

    private static function guard(): void
    {
        $name = DB::connection()->getDatabaseName();
        if (! app()->environment(['local', 'testing'])
            || ! str_starts_with($name, 'nexiam_opening_alerts_')
            || (DB::getDriverName() === 'pgsql'
                && (string) DB::selectOne('select current_database() as name')->name !== $name)) {
            throw new RuntimeException('Opening-alert fixtures require a verified dedicated nexiam_opening_alerts_* database. Shared/public seeding is forbidden.');
        }
    }
}
