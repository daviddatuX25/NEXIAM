<?php

namespace Database\Seeders;

use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\IssueApplicantAccessChallenge;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\NormalizedIdentityKey;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramPoolOption;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Release\ConsultationDecision;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit synthetic starting states, not evidence of real exams, decisions or clearance. */
final class ApplicantGuidanceDemoSeeder extends Seeder
{
    public const BIRTHDATE = '2004-05-15';

    /** @var array<string, string> */
    public const CASES = [
        'Complete' => 'GDES-4N7P',
        'Review' => 'GDES-5N7P',
        'Appearance' => 'GDES-6N7P',
        'Cleared' => 'GDES-7N7P',
        'Application' => 'GDES-8N7P',
        'Deferred' => 'GDES-9N7P',
    ];

    public function run(): void
    {
        if (! app()->runningInConsole()
            || ! app()->environment('local', 'testing')
            || DB::connection()->getDatabaseName() !== 'applicant_guidance_qa') {
            throw new RuntimeException('Applicant guidance fixtures require the dedicated applicant_guidance_qa database.');
        }

        if (! Institution::query()->where('code', 'SYN-INST-001')->exists()) {
            app(DatabaseSeeder::class)->run(DatabaseSeeder::testSeeders());
        }
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('is_active', true)->firstOrFail();
        $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
        $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
        $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
        $window = ApplicationWindow::query()->firstOrCreate(['code' => 'GUIDANCE-QA-WINDOW'], [
            'academic_cycle_id' => $cycle->id,
            'name' => 'Synthetic applicant guidance acceptance',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addMonth(),
            'status' => 'published',
        ]);

        foreach (self::CASES as $case => $key) {
            DB::transaction(function () use ($case, $key, $institution, $cycle, $program, $guidance, $registrar, $window): void {
                $email = 'guidance.'.strtolower($case).'@nexiam.test';
                $identity = ApplicantIdentity::query()->where('email', $email)->first();
                if ($identity !== null) {
                    $owned = AdmissionAttempt::query()->where('applicant_identity_id', $identity->id)->first();
                    if (($owned?->payload['source'] ?? null) !== self::class) {
                        throw new RuntimeException('A guidance fixture identity is already owned by another source.');
                    }

                    return;
                }
                $identity = ApplicantIdentity::query()->create([
                    'institution_id' => $institution->id,
                    'normalized_key' => NormalizedIdentityKey::make($case, 'Guidance Demo', self::BIRTHDATE, 'female'),
                    'first_name' => $case,
                    'last_name' => 'Guidance Demo',
                    'birthdate' => self::BIRTHDATE,
                    'sex' => 'female',
                    'email' => $email,
                ]);
                $attempt = AdmissionAttempt::query()->create([
                    'institution_id' => $institution->id,
                    'academic_cycle_id' => $cycle->id,
                    'application_window_id' => $window->id,
                    'applicant_identity_id' => $identity->id,
                    'reference_code' => 'AY26-'.(90001 + array_search($case, array_keys(self::CASES), true)),
                    'status' => $case === 'Application' ? AdmissionAttempt::STATUS_PENDING : AdmissionAttempt::STATUS_ACCEPTED,
                    'accepted_at' => $case === 'Application' ? null : now(),
                    'version' => 1,
                    'applicant_type' => 'new',
                    'preferred_program_code' => $program->code,
                    'program_preference_1_code' => $program->code,
                    'contact_email' => $email,
                    'payload' => ['source' => self::class, 'synthetic_demo' => true, 'workflow_proof' => false, 'terms_accepted' => false],
                ]);
                app(IssueApplicantAccessChallenge::class)->handle($identity, $attempt, ApplicantAccessKey::hash($key), 'access_key', 'guidance-qa-'.strtolower($case));
                app(AuthenticateApplicantWithAccessKey::class)->handle($key, self::BIRTHDATE);
                if ($case === 'Application') {
                    return;
                }
                ConsultationDecision::query()->create([
                    'admission_attempt_id' => $attempt->id,
                    'counselor_user_id' => $guidance->id,
                    'decision_type' => $case === 'Deferred' ? 'defer' : (in_array($case, ['Review', 'Appearance'], true) ? 'refer' : 'approve'),
                    'notes' => 'Synthetic starting state only; confidential note must never enter applicant guidance.',
                    'idempotency_key' => 'guidance-qa-decision-'.strtolower($case),
                ]);
                $pool = ProgramPool::query()->create([
                    'admission_attempt_id' => $attempt->id,
                    'version' => 1,
                    'actor_user_id' => $guidance->id,
                    'reason' => 'Explicit isolated synthetic starting state, not a real advising receipt.',
                    'source' => 'guidance',
                    'idempotency_key' => 'guidance-qa-pool-'.strtolower($case),
                    'created_at' => now(),
                ]);
                $option = ProgramPoolOption::query()->create([
                    'program_pool_id' => $pool->id, 'program_id' => $program->id, 'display_order' => 1,
                    'basis' => ProgramPoolOption::BASIS_PREFERENCE, 'created_at' => now(),
                ]);
                ProgramSelection::query()->create([
                    'admission_attempt_id' => $attempt->id, 'program_pool_id' => $pool->id,
                    'program_pool_option_id' => $option->id, 'program_id' => $program->id,
                    'academic_cycle_id' => $cycle->id, 'status' => ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT,
                    'source' => ProgramSelection::SOURCE_GUIDANCE_DEFAULT, 'actor_user_id' => $guidance->id,
                    'idempotency_key' => 'guidance-qa-selection-'.strtolower($case), 'version' => 1,
                ]);
                if (in_array($case, ['Review', 'Appearance'], true)) {
                    ProgramFollowUp::query()->create([
                        'admission_attempt_id' => $attempt->id, 'program_id' => $program->id,
                        'program_pool_id' => $pool->id, 'opened_by_user_id' => $guidance->id,
                        'status' => $case === 'Appearance' ? ProgramFollowUp::STATUS_APPEARANCE_REQUESTED : ProgramFollowUp::STATUS_OPEN,
                        'reason' => 'Private synthetic referral reason; not applicant instructions.', 'opened_at' => now(),
                        'idempotency_key' => 'guidance-qa-followup-'.strtolower($case), 'version' => 1,
                    ]);
                }
                if ($case === 'Cleared') {
                    AdmissionClearanceReceipt::query()->create([
                        'admission_attempt_id' => $attempt->id, 'program_id' => $program->id,
                        'receipt_number' => 'SYN-GUIDANCE-QA-CLEARANCE', 'status' => AdmissionClearanceReceipt::STATUS_CONFIRMED,
                        'evidence_vector' => ['synthetic_demo' => true, 'workflow_proof' => false, 'source' => self::class],
                        'confirmed_by_user_id' => $registrar->id, 'confirmed_at' => now(), 'idempotency_key' => 'guidance-qa-clearance',
                    ]);
                }
            });
            $this->command?->line($case.': '.$key.' / '.self::BIRTHDATE);
        }
    }
}
