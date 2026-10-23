<?php

namespace Database\Seeders;

use App\Admission\Actions\ManageRegistrarQuotaGovernanceGrant;
use App\Admission\Actions\OpenProgramGovernanceDiscussion;
use App\Admission\Actions\PublishProgramAdvisingNoteRevision;
use App\Admission\Actions\SaveProgramAdvisingNote;
use App\Admission\Actions\SendProgramGovernanceMessage;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\ApplicantCapabilityGrants;
use App\Admission\Support\NormalizedIdentityKey;
use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\RecoverHistoricalProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\KnownHistoricalBenchmarkRecovery;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkExemplarValue;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Admission\ApplicantAccessChallenge;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\ProgramAdvisingNote;
use App\Models\Admission\ProgramAdvisingNotePublication;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramGovernanceMessage;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\ScoreRecord;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Department;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuota;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\Foundation\Room;
use App\Models\Release\ResultDocument;
use App\Models\Release\ResultRelease;
use App\Models\StaffProfile;
use App\Models\User;
use App\Setup\Actions\UpsertProgramQuota;
use App\Staffing\Actions\GrantInstitutionalSetupDelegation;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Isolated, synthetic governance fixtures; never an institutional calibration.
 *
 * All six *.governance@nexiam.test accounts start with password "password".
 * Existing passwords, MFA settings, revisions, grants, and case state are retained.
 * No printing, release, decision, pool, scheduling, or notification Action runs.
 * Seeded result facts are synthetic model fixtures, not workflow acceptance proof.
 */
class GovernanceDemoSeeder extends Seeder
{
    public const DATABASE_PREFIX = '/tmp/nexiam-governance-';

    public const PASSWORD = 'password';

    public const APPLICANT_ACCESS_KEY = 'GVDE-M234';

    public function run(): void
    {
        $this->assertIsolatedDatabase();

        DB::transaction(function (): void {
            $fresh = ! Institution::query()->exists() && ! User::query()->exists();
            if (! $fresh && ! Department::query()->where('code', 'GOV-DEMO')->where('name', 'Synthetic demo governance department')->exists()) {
                throw new RuntimeException('Governance demo requires an empty migrated database or its existing owned GOV-DEMO fixture. No shared foundation is reseeded.');
            }

            if ($fresh) {
                app(DatabaseSeeder::class)->run([
                    ReferenceCatalogSeeder::class,
                    CapabilityCatalogSeeder::class,
                ]);
            }

            $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
            $cycle = AcademicCycle::query()->where('institution_id', $institution->id)->where('code', 'SYN-CYCLE-2026')->firstOrFail();
            $guidance = $this->staff($institution, $cycle, 'guidance', 'guidance_counselor', 'guidance', []);
            $ta = $this->staff($institution, $cycle, 'ta', 'guidance_support_staff', 'guidance', []);
            app(GrantTestAdministratorCapabilities::class)->handle($guidance, $ta);
            $dean = $this->staff($institution, $cycle, 'dean', 'program_head', 'program', []);
            $ph = $this->staff($institution, $cycle, 'ph', 'program_head', 'program', ['GOV-DEMO-A']);
            $registrar = $this->staff($institution, $cycle, 'registrar', 'registrar_support_staff', 'registrar', []);
            $setupAdministrator = $this->staff($institution, $cycle, 'setup', 'institutional_admin', 'admin', []);
            app(GrantInstitutionalSetupDelegation::class)->handle(
                $setupAdministrator,
                $guidance,
                'Synthetic governance demo quota-grant fixture authority.',
                (int) $cycle->id,
                ['programs'],
            );

            $department = Department::query()->firstOrCreate(
                ['institution_id' => $institution->id, 'code' => 'GOV-DEMO'],
                ['name' => 'Synthetic demo governance department', 'dean_user_id' => $dean->id, 'is_active' => true],
            );
            if ($department->dean_user_id !== $dean->id || ! $department->is_active) {
                throw new RuntimeException('Owned demo Dean assignment changed; the seeder will not overwrite it.');
            }

            $programs = [];
            foreach (['A', 'B'] as $suffix) {
                $program = Program::query()->firstOrCreate(
                    ['institution_id' => $institution->id, 'code' => 'GOV-DEMO-'.$suffix],
                    ['name' => 'Synthetic demo program '.$suffix, 'department_id' => $department->id, 'is_active' => true],
                );
                if ((int) $program->department_id !== (int) $department->id || ! $program->is_active || $program->name !== 'Synthetic demo program '.$suffix) {
                    throw new RuntimeException('Owned demo program mapping changed; the seeder will not overwrite it.');
                }
                $programs[] = $program;
            }

            $this->benchmarks($institution, $cycle, $programs, $ph, $dean, $guidance);
            if (getenv('NEXIAM_GOVERNANCE_DEMO_HISTORICAL_RECOVERY') === '1') {
                $this->historicalRecovery($institution, $cycle, $department, $guidance);
            }
            foreach ($programs as $index => $program) {
                $author = $index === 0 ? $ph : $dean;
                $this->notes($program, $cycle, $author, $guidance);
                $quota = ProgramQuota::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->first();
                if ($quota === null) {
                    if (! Schema::hasColumn('program_quotas', 'version')) {
                        throw new RuntimeException('Governance demo quota seeding requires the versioned quota command migration.');
                    }
                    foreach (['version', 'reason', 'idempotency_key', 'payload_hash'] as $column) {
                        if (! Schema::hasColumn('program_quota_changes', $column)) {
                            throw new RuntimeException('Governance demo quota seeding requires the versioned quota command migration.');
                        }
                    }

                    $previousQuotaWritesEnabled = config('admission.quota_writes_enabled');
                    config()->set('admission.quota_writes_enabled', true);
                    try {
                        $change = app(UpsertProgramQuota::class)->handle(
                            $guidance,
                            $program,
                            $cycle,
                            40 + $index * 10,
                            'Synthetic demo initial capacity; not an institutional quota.',
                            0,
                            $index === 0
                                ? '4e8f6f10-8011-5b42-aef0-b2cf63c4a010'
                                : '4e8f6f10-8011-5b42-aef0-b2cf63c4a011',
                        );
                    } finally {
                        config()->set('admission.quota_writes_enabled', $previousQuotaWritesEnabled);
                    }
                } else {
                    $change = ProgramQuotaChange::query()
                        ->where('program_quota_id', $quota->id)
                        ->latest('id')
                        ->first();
                    if ($change === null) {
                        throw new RuntimeException('Existing program quota has no recorded change; governance demo will not fabricate history.');
                    }
                }
                app(ManageRegistrarQuotaGovernanceGrant::class)->issue(
                    $guidance, $registrar, $program, $cycle, 0,
                    'Synthetic demo quota read/history/discussion access only.',
                    'gov-demo-grant-'.$program->code,
                );
                $this->discussions($program, $cycle, $author, $guidance, $registrar, $change);
            }

            $this->applicantCase($institution, $cycle, $programs[0], $programs[1], $guidance);
        });

        $this->command?->info('Synthetic governance fixture created or retained. Staff: guidance/ta/dean/ph/registrar.governance@nexiam.test; initial password: password. Applicant access key: '.self::APPLICANT_ACCESS_KEY.'; birthdate: 2004-05-15. Seed creation is not workflow acceptance proof.');
    }

    private function assertIsolatedDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config('database.connections.'.$connection.'.database');
        $optIn = getenv('NEXIAM_GOVERNANCE_DEMO_DATABASE');
        $memory = app()->environment('testing') && $database === ':memory:';
        $temporaryFile = (str_starts_with($database, self::DATABASE_PREFIX)
                || str_starts_with($database, '/tmp/nexiam-governance-runtime/nexiam-governance-'))
            && str_ends_with($database, '.sqlite')
            && in_array(dirname($database), ['/tmp', '/tmp/nexiam-governance-runtime'], true)
            && ! is_link($database)
            && realpath($database) === $database;

        if (! app()->environment('local', 'testing') || ! app()->runningInConsole()
            || $connection !== 'sqlite' || DB::connection()->getDriverName() !== 'sqlite'
            || $optIn !== $database || (! $memory && ! $temporaryFile)) {
            throw new RuntimeException('Governance demo refused: local/testing console, SQLite, and NEXIAM_GOVERNANCE_DEMO_DATABASE exactly matching an existing /tmp/nexiam-governance-*.sqlite file (or testing :memory:) are required.');
        }
    }

    /** @param list<string> $programCodes */
    private function staff(Institution $institution, AcademicCycle $cycle, string $label, string $role, string $office, array $programCodes): User
    {
        $user = $this->account($label);
        $profile = StaffProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['institution_id' => $institution->id, 'office' => $office, 'program_codes' => $programCodes, 'active_cycle_code' => $cycle->code, 'status' => StaffProfile::STATUS_ACTIVE],
        );
        if ((int) $profile->institution_id !== (int) $institution->id || $profile->office !== $office
            || ! $profile->isActive() || $profile->active_cycle_code !== $cycle->code || $profile->program_codes !== $programCodes) {
            throw new RuntimeException('Owned demo staff scope changed; the seeder will not overwrite it.');
        }
        if ($user->wasRecentlyCreated) {
            $user->assignRole($role);
        }
        StaffAuthenticationState::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['activation_completed_at' => now(), 'password_set_at' => now(), 'must_change_password' => false, 'session_version' => 1, 'mfa_required' => false],
        );

        return $user;
    }

    private function account(string $label): User
    {
        $name = 'Synthetic demo '.ucfirst($label);
        $user = User::query()->firstOrCreate(
            ['email' => $label.'.governance@nexiam.test'],
            ['name' => $name, 'password' => self::PASSWORD],
        );
        if ($user->name !== $name) {
            throw new RuntimeException('Demo email belongs to an unowned account; no account changes were made.');
        }

        return $user;
    }

    /** @param list<Program> $programs */
    private function benchmarks(Institution $institution, AcademicCycle $cycle, array $programs, User $ph, User $dean, User $guidance): void
    {
        $exemplars = [];
        $contents = [];
        $areas = AptitudeArea::query()
            ->where('is_active', true)
            ->whereIn('code', BenchmarkInputSchema::canonicalAptitudeCodes())
            ->orderBy('id')
            ->get();
        foreach ($programs as $index => $program) {
            $tiers = [];
            foreach (range(1, 5) as $tier) {
                $values = ['gwa' => 96 - $tier * 4];
                foreach ($areas as $area) {
                    $values[$area->code] = 94 - $tier * 7 + $index;
                }
                $tiers[(string) $tier] = ['values' => $values];
                $exemplars[] = ['program_code' => $program->code, 'program_name' => $program->name, 'tier_level' => $tier, 'tier_label' => 'Synthetic demo tier '.$tier, 'target_class' => $tier === 5 ? 0 : 1, 'values' => $values];
            }
            $contents[$program->code] = ['tiers' => $tiers];
        }
        $context = BenchmarkExecutionContext::trustedSeed($institution, 'seed');
        $set = app(ImportAdmissionBenchmarkSet::class)->handle($institution, $cycle, $context, [
            'name' => 'Synthetic demo five-tier benchmarks; not validated institutional profiles',
            'change_reason' => 'Synthetic demo UI fixtures only; aptitude constructs, units, and model validity are unverified.',
            'programs' => array_map(static fn (Program $program): array => ['code' => $program->code, 'name' => $program->name], $programs),
            'synthesized_exemplars' => $exemplars,
        ], 'gov-demo-benchmarks-v1');
        if (! AdmissionBenchmarkActivation::query()->where('academic_cycle_id', $cycle->id)->where('benchmark_set_id', $set->id)->exists()) {
            if (AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id) !== null) {
                throw new RuntimeException('An unrelated benchmark is active; the demo will not supersede it.');
            }
            app(ActivateAdmissionBenchmarkSet::class)->handle($cycle, $set, $context, null, (string) $set->content_sha256, 'gov-demo-benchmarks-activate-v1');
        }
        foreach ($programs as $index => $program) {
            $draft = AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->first();
            if ($draft === null || (int) $draft->current_version === 0) {
                app(SaveProgramBenchmarkDraft::class)->handle($index === 0 ? $ph : $dean, $program, $cycle, $contents[$program->code], 0,
                    'Synthetic demo initial shared five-tier draft.', 'gov-demo-draft-'.$program->code.'-v1');
            }
            $draft = AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
            if ((int) $draft->current_version === 1) {
                $revised = $contents[$program->code];
                $revised['tiers']['3']['values']['gwa'] = 85;
                app(SaveProgramBenchmarkDraft::class)->handle($index === 0 ? $dean : $guidance, $program, $cycle, $revised, 1,
                    'Synthetic demo shared revision: tier 3 GWA changed from 84 to 85 for history inspection.', 'gov-demo-draft-'.$program->code.'-v2');
            }
            $draft->refresh();
            if (getenv('NEXIAM_GOVERNANCE_DEMO_RANGE_AUTHORING') === '1' && (int) $draft->current_version === 2) {
                $revision = $draft->currentRevision();
                $baseline = $contents[$program->code];
                $baseline['tiers']['3']['values']['gwa'] = 85;
                foreach ($baseline['tiers'] as &$tier) {
                    ksort($tier['values'], SORT_STRING);
                }
                unset($tier);
                if ($revision?->content === $baseline) {
                    $rangeDraft = $baseline;
                    $numericFeatures = AdmissionBenchmarkSet::query()->with('features')->findOrFail($draft->base_benchmark_set_id)
                        ->features->where('logical_type', 'numeric');
                    $ranges = ['honors' => [], 'regular' => [], 'cutoff' => []];
                    foreach ($numericFeatures as $feature) {
                        $key = (string) $feature->feature_key;
                        $ranges['honors'][$key] = [$baseline['tiers']['3']['values'][$key], $baseline['tiers']['1']['values'][$key]];
                        $ranges['regular'][$key] = [$baseline['tiers']['3']['values'][$key], $baseline['tiers']['1']['values'][$key]];
                        $ranges['cutoff'][$key] = [$baseline['tiers']['3']['values'][$key], $baseline['tiers']['1']['values'][$key]];
                    }
                    $rangeDraft['authoring'] = [
                        'version' => 1,
                        'ranges' => $ranges,
                        'preferred_strands' => [],
                        'interests' => [],
                    ];
                    app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $rangeDraft, 2,
                        'Synthetic complete range authoring demo; not institutionally validated.', 'gov-demo-range-authoring-'.$program->code.'-v3');
                }
            }
        }
    }

    /** @param list<Program> $programs */
    private function historicalRecovery(Institution $institution, AcademicCycle $cycle, Department $department, User $guidance): void
    {
        $programs = [];
        $codes = ['GOV-RECOVERY-A', 'GOV-RECOVERY-B'];
        foreach ($codes as $index => $code) {
            $program = Program::query()->firstOrCreate(
                ['institution_id' => $institution->id, 'code' => $code],
                ['name' => 'Synthetic historical recovery program '.($index === 0 ? 'A' : 'B'), 'department_id' => $department->id, 'is_active' => true],
            );
            if ((int) $program->department_id !== (int) $department->id || ! $program->is_active
                || $program->name !== 'Synthetic historical recovery program '.($index === 0 ? 'A' : 'B')) {
                throw new RuntimeException('Synthetic recovery program ownership changed; the seeder will not overwrite it.');
            }
            $programs[] = $program;
        }
        $programHead = $this->staff($institution, $cycle, 'recovery-ph', 'program_head', 'program', $codes);
        $sourceName = 'Synthetic historical TabFM source for explicit recovery; not institutional calibration';
        $source = AdmissionBenchmarkSet::query()
            ->where('institution_id', $institution->id)
            ->where('name', $sourceName)
            ->first();
        $createdSource = false;
        if ($source === null) {
            $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
            if ($activation === null) {
                throw new RuntimeException('Synthetic historical recovery fixture requires an existing isolated demo benchmark activation.');
            }
            $modernBase = AdmissionBenchmarkSet::query()->with(['features', 'exemplars.values.feature'])->findOrFail($activation->benchmark_set_id);
            $historicalAptitudeCodes = ['VERBAL', 'QUANT', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'LOGIC', 'SPATIAL', 'MEMORY', 'LANGUAGE'];
            $areas = AptitudeArea::query()->whereIn('code', $historicalAptitudeCodes)->get()->keyBy('code');
            if ($areas->count() !== count($historicalAptitudeCodes)) {
                throw new RuntimeException('Synthetic historical recovery fixture requires all eight recognized aptitude areas.');
            }
            $definitions = [];
            foreach ($historicalAptitudeCodes as $key) {
                $area = $areas->get($key);
                $definitions[] = [$key, $area->name, 'numeric', (int) $area->id];
            }
            foreach ([
                ['gwa', 'General Weighted Average', 'numeric'],
                ['shs_strand', 'Senior High School Strand', 'categorical'],
                ['shs_math', 'Senior High School Mathematics', 'numeric'],
                ['shs_sci', 'Senior High School Science', 'numeric'],
                ['shs_eng', 'Senior High School English', 'numeric'],
                ['riasec_r', 'RIASEC Realistic', 'binary'],
                ['riasec_i', 'RIASEC Investigative', 'binary'],
                ['riasec_a', 'RIASEC Artistic', 'binary'],
                ['riasec_s', 'RIASEC Social', 'binary'],
                ['riasec_e', 'RIASEC Enterprising', 'binary'],
                ['riasec_c', 'RIASEC Conventional', 'binary'],
            ] as [$key, $label, $type]) {
                $definitions[] = [$key, $label, $type, null];
            }
            $oldSchema = array_map(static fn (array $definition): array => [$definition[0], $definition[2], $definition[3]], $definitions);
            $source = AdmissionBenchmarkSet::query()->create([
                'institution_id' => $institution->id,
                'version' => (int) AdmissionBenchmarkSet::query()->where('institution_id', $institution->id)->max('version') + 1,
                'status' => 'draft',
                'name' => $sourceName,
                'change_reason' => 'Synthetic historical fixture only. Original authored payload integrity is intentionally unverified.',
                'schema_sha256' => hash('sha256', json_encode($oldSchema, JSON_THROW_ON_ERROR)),
                'content_sha256' => hash('sha256', 'synthetic historical payload digest; not reconstructible from rows'),
                'created_by_user_id' => $guidance->id,
                'published_by_user_id' => $guidance->id,
                'published_at' => now(),
                'idempotency_key' => 'gov-demo-historical-recovery-source',
                'import_payload_sha256' => hash('sha256', 'synthetic isolated recovery fixture payload'),
            ]);
            $features = [];
            foreach ($definitions as $position => [$key, $label, $type, $areaId]) {
                $features[$key] = $source->features()->create([
                    'position' => $position + 1,
                    'feature_key' => $key,
                    'label' => $label,
                    'logical_type' => $type,
                    'aptitude_area_id' => $areaId,
                    'is_required' => false,
                ]);
            }
            $oldTierLabels = [1 => 'Honors High', 2 => 'Honors Low', 3 => 'Regular', 4 => 'Cutoff', 5 => 'Below Cutoff'];
            foreach ($programs as $programIndex => $program) {
                $baseProgram = Program::query()
                    ->where('institution_id', $institution->id)
                    ->where('code', 'GOV-DEMO-'.($programIndex === 0 ? 'A' : 'B'))
                    ->firstOrFail();
                $baseRows = $modernBase->exemplars->where('program_id', $baseProgram->id);
                foreach (range(1, 5) as $tier) {
                    $exemplar = $source->exemplars()->create([
                        'program_id' => $program->id,
                        'tier_level' => $tier,
                        'tier_label' => $oldTierLabels[$tier],
                        'target_class' => $tier === 5 ? 0 : 1,
                    ]);
                    $baseRow = $baseRows->firstWhere('tier_level', $tier);
                    $baseValues = $baseRow?->values->keyBy(static fn (AdmissionBenchmarkExemplarValue $value): string => $value->feature->feature_key) ?? collect();
                    foreach ($definitions as [$key, , $type]) {
                        $baseValue = in_array($key, ['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'], true)
                            ? $baseValues->get($key)
                            : null;
                        $isMissing = in_array($key, ['VERBAL', 'QUANT', 'shs_math', 'shs_sci', 'shs_eng'], true)
                            || (in_array($key, ['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'], true)
                                && ($baseValue === null || $baseValue->is_missing));
                        $value = match (true) {
                            $key === 'shs_strand' => 'STEM',
                            str_starts_with($key, 'riasec_') => 0,
                            default => $baseValue?->numeric_value,
                        };
                        AdmissionBenchmarkExemplarValue::query()->create([
                            'exemplar_id' => $exemplar->id,
                            'benchmark_feature_id' => $features[$key]->id,
                            'is_missing' => $isMissing,
                            'numeric_value' => $isMissing || $type === 'categorical' ? null : $value,
                            'categorical_value' => $isMissing || $type !== 'categorical' ? null : $value,
                        ]);
                    }
                }
            }
            $source->forceFill(['status' => 'published'])->save();
            $createdSource = true;
        }
        if (! app(KnownHistoricalBenchmarkRecovery::class)->isKnownHistoricalSet($source)) {
            throw new RuntimeException('Synthetic historical recovery source no longer matches the recognized legacy schema; no source repair or overwrite was attempted.');
        }

        if ($createdSource) {
            $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
            if ($current === null || (int) $current->benchmark_set_id === (int) $source->id) {
                throw new RuntimeException('Synthetic historical source activation could not identify the demo predecessor.');
            }
            DB::table('admission_benchmark_activations')->where('id', $current->id)->update(['superseded_at' => now()]);
            AdmissionBenchmarkActivation::query()->create([
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'benchmark_set_id' => $source->id,
                'sequence' => (int) AdmissionBenchmarkActivation::query()->where('academic_cycle_id', $cycle->id)->max('sequence') + 1,
                'activated_by_user_id' => $guidance->id,
                'idempotency_key' => 'gov-demo-historical-recovery-activation',
                'activated_at' => now(),
            ]);
        }
        $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ($current === null || (int) $current->benchmark_set_id !== (int) $source->id) {
            return;
        }

        if (getenv('NEXIAM_GOVERNANCE_DEMO_RECOVERY_READY') !== '1'
            || AdmissionBenchmarkRecovery::query()->where('source_benchmark_set_id', $source->id)->exists()) {
            return;
        }

        $snapshotDigest = app(KnownHistoricalBenchmarkRecovery::class)->digest(
            app(KnownHistoricalBenchmarkRecovery::class)->sourceSnapshot($source->fresh()),
        );
        foreach ($programs as $program) {
            $revision = app(RecoverHistoricalProgramBenchmarkDraft::class)->handle(
                $programHead,
                $program,
                $cycle,
                0,
                $snapshotDigest,
                'Explicit synthetic historical recovery fixture for preview and full-manifest review.',
                'gov-demo-recovery-'.$program->code,
            );
            $content = $revision->content;
            foreach ($content['tiers'] as &$tier) {
                foreach ($tier['values'] as $featureKey => $value) {
                    if ($value === null) {
                        $tier['values'][$featureKey] = 78.0;
                    }
                }
                ksort($tier['values'], SORT_STRING);
            }
            unset($tier);
            app(SaveProgramBenchmarkDraft::class)->handle(
                $programHead,
                $program,
                $cycle,
                $content,
                1,
                'Synthetic required cells entered explicitly for recovery preview and full publication review.',
                'gov-demo-recovery-ready-'.$program->code,
            );
        }
    }

    private function notes(Program $program, AcademicCycle $cycle, User $author, User $guidance): void
    {
        $revision = app(SaveProgramAdvisingNote::class)->handle(
            $author, $program, $cycle,
            'Synthetic demo: discuss study interests and available support. Advisory information, not an admission decision.',
            0, 'Synthetic initial advising note.', 'gov-demo-sticky-'.$program->code.'-v1',
        );
        $note = ProgramAdvisingNote::query()->findOrFail($revision->program_advising_note_id);
        if ((int) $note->current_version === 1 && ! ProgramAdvisingNotePublication::query()->where('program_advising_note_id', $note->id)->exists()) {
            app(PublishProgramAdvisingNoteRevision::class)->handle($author, $note, $revision, 1,
                'Synthetic scoped publication of this exact revision.', 'gov-demo-sticky-share-'.$program->code.'-v1');
        }
        if ((int) $note->current_version === 1) {
            app(SaveProgramAdvisingNote::class)->handle($author, $program, $cycle,
                $revision->content.' New draft: discuss bridging resources.',
                1, 'Synthetic newer draft; shared revision remains unchanged.', 'gov-demo-sticky-'.$program->code.'-v2', (int) $note->id);
        }
        app(SaveProgramAdvisingNote::class)->handle(
            $guidance, $program, $cycle,
            'Synthetic second sticky note: ask which subject support would be useful.',
            0, 'Synthetic independent unpublished note.', 'gov-demo-sticky-second-'.$program->code.'-v1',
        );
    }

    private function discussions(Program $program, AcademicCycle $cycle, User $author, User $guidance, User $registrar, ProgramQuotaChange $quotaChange): void
    {
        $draft = AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->where('academic_cycle_id', $cycle->id)->firstOrFail();
        $revision = AdmissionBenchmarkRevision::query()->where('draft_id', $draft->id)->where('version', 2)->firstOrFail();
        foreach (ProgramGovernanceThread::TOPICS as $topic) {
            $thread = app(OpenProgramGovernanceDiscussion::class)->handle(
                $author, $program, $cycle, $topic, 'Synthetic demo '.$topic.' review for '.$program->code,
                'gov-demo-thread-'.$program->code.'-'.$topic,
                $topic === ProgramGovernanceThread::TOPIC_BENCHMARK ? $revision->id : null,
                $topic === ProgramGovernanceThread::TOPIC_QUOTA ? $quotaChange->id : null,
            );
            $messages = [
                [$author, 'Synthetic demo discussion: please review this '.$topic.' fixture. Messages do not change benchmarks, quotas, notes, or admission decisions.'],
                [$topic === ProgramGovernanceThread::TOPIC_QUOTA ? $registrar : $guidance,
                    $topic === ProgramGovernanceThread::TOPIC_QUOTA
                        ? 'Synthetic demo Registrar reply: quota history is visible through an explicit scoped grant; no mutation authority is granted.'
                        : 'Synthetic demo Guidance reply: use the separate reviewed publication controls for authoritative changes.'],
            ];
            foreach ($messages as $index => [$sender, $body]) {
                if (! ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id)->where('sender_user_id', $sender->id)->where('body', $body)->exists()) {
                    app(SendProgramGovernanceMessage::class)->handle($thread, $sender, $program, $cycle, $body,
                        'gov-demo-message-'.$program->code.'-'.$topic.'-'.$index);
                }
            }
        }
    }

    private function applicantCase(Institution $institution, AcademicCycle $cycle, Program $program, Program $alternate, User $guidance): void
    {
        $user = $this->account('applicant');
        if ($user->staffProfile()->exists()) {
            throw new RuntimeException('Synthetic applicant account must not have a staff profile.');
        }
        $identity = ApplicantIdentity::query()->firstOrCreate(
            ['institution_id' => $institution->id, 'normalized_key' => NormalizedIdentityKey::make('Synthetic', 'Demo Governance Applicant', '2004-05-15', 'female')],
            ['first_name' => 'Synthetic', 'last_name' => 'Demo Governance Applicant', 'birthdate' => '2004-05-15', 'sex' => 'female', 'email' => $user->email],
        );
        $account = ApplicantAccount::query()->firstOrCreate(
            ['applicant_identity_id' => $identity->id], ['user_id' => $user->id, 'setup_completed_at' => now()],
        );
        if ((int) $account->user_id !== (int) $user->id) {
            throw new RuntimeException('Synthetic identity is linked to a different account; no relinking was performed.');
        }
        if ($user->wasRecentlyCreated) {
            ApplicantCapabilityGrants::sync($user);
        }
        $window = ApplicationWindow::query()->where('academic_cycle_id', $cycle->id)->where('code', 'SYN-WINDOW-2026-A')->firstOrFail();
        $attempt = AdmissionAttempt::query()->firstOrCreate(
            ['applicant_identity_id' => $identity->id, 'academic_cycle_id' => $cycle->id],
            ['institution_id' => $institution->id, 'application_window_id' => $window->id, 'reference_code' => 'GOV-DEMO-APPLICANT-001',
                'status' => AdmissionAttempt::STATUS_ACCEPTED, 'preferred_program_code' => $program->code,
                'program_preference_1_code' => $program->code, 'program_preference_2_code' => $alternate->code,
                'applicant_type' => 'new', 'last_school_enrolled' => 'Synthetic demo senior high school', 'strand' => 'STEM',
                'gwa' => null, 'contact_email' => $user->email,
                'accepted_at' => now(), 'payload' => ['synthetic_demo' => true, 'source' => self::class]],
        );
        ApplicantAccessChallenge::query()->firstOrCreate(
            ['request_key' => 'gov-demo-applicant-access-'.$attempt->id],
            ['applicant_identity_id' => $identity->id, 'admission_attempt_id' => $attempt->id,
                'purpose' => 'access_key', 'token_hash' => ApplicantAccessKey::hash(self::APPLICANT_ACCESS_KEY),
                'expires_at' => now()->addYears(5)],
        );
        if (! $attempt->wasRecentlyCreated) {
            return;
        }
        $room = Room::query()->create(['institution_id' => $institution->id, 'code' => 'GOV-DEMO-ROOM', 'name' => 'Synthetic demo governance room', 'capacity' => 10, 'is_active' => true]);
        $session = ExamSession::query()->create(['academic_cycle_id' => $cycle->id, 'room_id' => $room->id, 'code' => 'GOV-DEMO-EXAM',
            'label' => 'Synthetic demo completed exam fixture', 'status' => ExamSession::STATUS_COMPLETED,
            'booking_mode' => ExamSession::BOOKING_STAFF_ONLY, 'starts_at' => now()->subDays(2), 'effective_end_at' => now()->subDays(2)->addHour(),
            'published_at' => now()->subDays(3), 'closed_at' => now()->subDays(2)->addHour()]);
        ExamAssignment::query()->create(['exam_session_id' => $session->id, 'admission_attempt_id' => $attempt->id,
            'status' => ExamAssignment::STATUS_ASSIGNED, 'source' => ExamAssignment::SOURCE_STAFF_ASSIGNED]);
        $grading = GradingSession::query()->create(['exam_session_id' => $session->id, 'status' => GradingSession::STATUS_FINALIZED,
            'opened_at' => now()->subDay(), 'finalized_at' => now()->subHour(), 'opened_by_user_id' => $guidance->id, 'finalized_by_user_id' => $guidance->id]);
        $series = [];
        foreach (AptitudeArea::query()->where('is_active', true)->orderBy('id')->get() as $area) {
            $score = (int) floor((int) $area->max_items * 0.8);
            $conversion = $area->convertScore($score) ?? throw new RuntimeException('Synthetic demo score has no current catalog conversion.');
            ScoreRecord::query()->create(['grading_session_id' => $grading->id, 'admission_attempt_id' => $attempt->id, 'aptitude_area_id' => $area->id,
                'score' => (string) $score, 'converted_score' => $conversion->output_value, 'source' => ScoreRecord::SOURCE_MANUAL,
                'recorded_by_user_id' => $guidance->id, 'idempotency_key' => 'gov-demo-score-'.$area->id]);
            $series[] = ['area_code' => $area->code, 'score' => $score, 'converted_score' => $conversion->output_value];
        }
        $snapshot = ['synthetic_demo' => true, 'source' => self::class, 'reference_code' => $attempt->reference_code,
            'applicant_name' => $identity->fullName(), 'scores' => $series];
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Synthetic demo result</title></head><body><h1>Synthetic demo result</h1><p>Developer fixture only. Not an institutional result, print receipt, admission decision, or model-validation claim.</p><pre>'.e(json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)).'</pre></body></html>';
        $checksum = hash('sha256', $html);
        $path = 'governance-demo/'.$attempt->id.'/'.$checksum.'.html';
        if (! Storage::disk('local')->put($path, $html)) {
            throw new RuntimeException('Synthetic demo result artifact could not be stored.');
        }
        $document = ResultDocument::query()->create(['admission_attempt_id' => $attempt->id, 'template_version' => 'synthetic-governance-demo-v1',
            'checksum' => $checksum, 'status' => ResultDocument::STATUS_READY, 'storage_path' => $path, 'preview_path' => $path,
            'snapshot_payload_json' => $snapshot, 'artifact_manifest' => ['synthetic_demo' => true]]);
        $attempt->forceFill(['current_result_document_id' => $document->id])->save();
        ResultRelease::query()->create(['admission_attempt_id' => $attempt->id, 'result_document_id' => $document->id,
            'released_by_user_id' => $guidance->id, 'release_version' => 1, 'is_current' => true, 'released_at' => now(),
            'idempotency_key' => 'gov-demo-result-release-fixture', 'mode' => 'online', 'channel' => 'online']);
        ProgramFollowUp::query()->create(['admission_attempt_id' => $attempt->id, 'program_id' => $program->id,
            'opened_by_user_id' => $guidance->id, 'status' => ProgramFollowUp::STATUS_OPEN,
            'reason' => 'Synthetic demo targeted program follow-up: discuss bridging support; no selection, decision, or quota commitment has been created.',
            'idempotency_key' => 'gov-demo-targeted-follow-up', 'version' => 1, 'opened_at' => now()]);
    }
}
