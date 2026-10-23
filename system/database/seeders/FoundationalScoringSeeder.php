<?php

namespace Database\Seeders;

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\Assessment\Actions\ApproveAssessmentNormVersion;
use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentMetricInput;
use App\Models\Assessment\TestInstrumentSubtest;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class FoundationalScoringSeeder extends Seeder
{
    private const KIT_CODE = 'TK-ICAT-2026';

    private const INSTRUMENT_CODE = 'OASIS-3-ICAT';

    private const NORM_CODE = 'OASIS3-NORMS-2026';

    /** @var list<array{reference: string, first_name: string, last_name: string, birthdate: string, sex: string, email: string, phone: string, strand: string, school: string, program: string}> */
    private const APPLICANTS = [
        ['reference' => 'APP-ICAT-2026-MIKA', 'first_name' => 'Mika', 'last_name' => 'Santos', 'birthdate' => '2005-02-14', 'sex' => 'female', 'email' => 'icat.mika@nexiam.test', 'phone' => '09171234001', 'strand' => 'STEM', 'school' => 'Synthetic Senior High School', 'program' => 'BSIT'],
        ['reference' => 'APP-ICAT-2026-PAOLO', 'first_name' => 'Paolo', 'last_name' => 'Reyes', 'birthdate' => '2004-07-22', 'sex' => 'male', 'email' => 'icat.paolo@nexiam.test', 'phone' => '09171234002', 'strand' => 'ABM', 'school' => 'Synthetic Senior High School', 'program' => 'BA-Psych'],
        ['reference' => 'APP-ICAT-2026-ALTHEA', 'first_name' => 'Althea', 'last_name' => 'Garcia', 'birthdate' => '2005-11-03', 'sex' => 'female', 'email' => 'icat.althea@nexiam.test', 'phone' => '09171234003', 'strand' => 'HUMSS', 'school' => 'Synthetic Senior High School', 'program' => 'BSIT'],
    ];

    /** @var array<string, array{label: string, maximum: int, construct: string, subtest: ?string}> */
    private const METRICS = [
        'VOCABULARY_RAW' => ['label' => 'Vocabulary Raw Score', 'maximum' => 40, 'construct' => 'VERBAL', 'subtest' => 'VOCABULARY'],
        'COMPUTATION_RAW' => ['label' => 'Computation Raw Score', 'maximum' => 30, 'construct' => 'QUANT', 'subtest' => 'COMPUTATION'],
        'SPATIAL_RAW' => ['label' => 'Spatial Raw Score', 'maximum' => 20, 'construct' => 'SPATIAL', 'subtest' => 'SPATIAL'],
        'WORD_COMP_RAW' => ['label' => 'Word Comparison Raw Score', 'maximum' => 100, 'construct' => 'MEMORY', 'subtest' => 'WORD_COMP'],
        'GENERAL_ABILITY_RAW' => ['label' => 'General Ability Raw Score', 'maximum' => 70, 'construct' => 'LOGIC', 'subtest' => null],
        'MANUAL_DEXTERITY_RAW' => ['label' => 'Manual Dexterity Raw Score', 'maximum' => 120, 'construct' => 'LANGUAGE', 'subtest' => null],
    ];

    /** @var array<string, array{label: string, count: int, options: list<string>}> */
    private const SUBTESTS = [
        'VOCABULARY' => ['label' => 'Vocabulary', 'count' => 40, 'options' => ['A-B', 'A-C', 'A-D', 'B-C', 'B-D', 'C-D']],
        'COMPUTATION' => ['label' => 'Computation', 'count' => 30, 'options' => ['A', 'B', 'C', 'D', 'E']],
        'SPATIAL' => ['label' => 'Spatial', 'count' => 20, 'options' => ['A', 'B', 'C', 'D']],
        'WORD_COMP' => ['label' => 'Word Comparison', 'count' => 100, 'options' => ['S', 'D']],
    ];

    public function run(): void
    {
        $institution = Institution::query()->orderBy('id')->first() ?? Institution::query()->create([
            'code' => 'SYN-INST-001',
            'name' => (string) config('public-site.institution.name', 'Ilocos Sur Polytechnic State College'),
        ]);

        DB::transaction(function () use ($institution): void {
            $cycle = AcademicCycle::query()->firstOrCreate(
                ['code' => 'SYN-CYCLE-2026'],
                [
                    'institution_id' => $institution->id,
                    'name' => '2025-2026 1st Semester',
                    'school_year' => '2025-2026',
                    'semester' => '1',
                    'opens_on' => '2026-08-01',
                    'closes_on' => '2026-12-31',
                    'is_active' => false,
                    'allow_guidance_all_in_one_intake' => false,
                ],
            );

            if ((int) $cycle->institution_id !== (int) $institution->id) {
                throw new RuntimeException('SYN-CYCLE-2026 belongs to a different institution.');
            }
            if (! $cycle->is_active) {
                $cycle->activate();
                $cycle->refresh();
            }

            $this->call([
                ReferenceCatalogSeeder::class,
                CapabilityCatalogSeeder::class,
                StaffIdentitySeeder::class,
            ]);

            $window = ApplicationWindow::query()
                ->where('academic_cycle_id', $cycle->id)
                ->where('code', 'SYN-WINDOW-2026-A')
                ->firstOrFail();
            $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
            (new FormulaScoringDemoSeeder)->seed($institution, $actor);
            $normVersion = $this->seedNormPackage($institution, $actor);
            $instrumentVersion = $this->seedInstrument($institution, $actor);
            $kit = $this->seedTestKit($institution, $actor);
            $this->seedPreparation($kit, $instrumentVersion, $normVersion, $actor);

            if (Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->exists()) {
                (new AdmissionBenchmarkSeeder)->run();
                $this->seedDecisionSupportDrafts($institution, $cycle, $actor);
            }
            $attempts = [];
            foreach (self::APPLICANTS as $applicant) {
                $attempts[] = $this->ensureAcceptedApplicant($institution, $cycle, $window, $applicant);
            }

        }, attempts: 3);
    }

    private function seedNormPackage(Institution $institution, User $actor): AssessmentNormPackageVersion
    {
        $package = AssessmentNormPackage::query()->firstOrNew([
            'institution_id' => $institution->id,
            'code' => self::NORM_CODE,
        ]);
        if (! $package->exists) {
            $package->forceFill([
                'institution_id' => $institution->id,
                'code' => self::NORM_CODE,
                'is_synthetic' => true,
            ])->save();
        }
        if (! $package->is_synthetic) {
            throw new RuntimeException('OASIS3-NORMS-2026 is already owned by a non-synthetic norm package.');
        }

        $existing = $package->versions()->orderByDesc('version_number')->first();
        if ($existing !== null) {
            return $existing;
        }
        $tables = [];
        foreach (self::METRICS as $code => $metric) {
            $tables[] = [
                'metric_code' => $code,
                'output_scale' => AssessmentNormTable::SCALE_PERCENTILE_RANK,
                'intervals' => $this->percentileIntervals($metric['maximum']),
            ];
        }
        $draft = app(SaveAssessmentNormDraft::class)->handle(
            actor: $actor, package: $package->id, version: null, expectedRevision: null,
            requestId: (string) Str::uuid(),
            input: [
                'code' => self::NORM_CODE,
                'display_name' => 'OASIS-3 Norm Package · Synthetic software fixture',
                'publisher_name' => 'Synthetic fixture',
                'form_code' => 'OASIS3_DEMO',
                'edition' => '2026 software fixture',
                'language' => 'English',
                'applicable_population' => 'Synthetic software testing only',
                'source_reference' => 'demo://oasis-3-icat/norms-2026',
                'permitted_use_reference' => 'Synthetic software fixture only; not official norms or for operational decisions.',
                'is_synthetic' => true,
                'tables' => $tables,
            ],
        );

        return app(ApproveAssessmentNormVersion::class)->handle(
            actor: $actor, package: $package->id, version: $draft->id,
            expectedRevision: $draft->lock_version, requestId: (string) Str::uuid(), attestationOrPayload: true,
        );
    }

    /** @return list<array{min_score: int, max_score: int, output_value: string}> */
    private function percentileIntervals(int $maximum): array
    {
        $intervals = [];
        for ($score = 0; $score <= $maximum; $score++) {
            $percentile = 5 + (int) round(($score / $maximum) * 94);
            $intervals[] = [
                'min_score' => $score,
                'max_score' => $score,
                'output_value' => (string) $percentile,
            ];
        }

        return $intervals;
    }

    private function seedInstrument(Institution $institution, User $actor): TestInstrumentVersion
    {
        $instrument = TestInstrument::query()->firstOrNew([
            'institution_id' => $institution->id,
            'code' => self::INSTRUMENT_CODE,
        ]);
        if (! $instrument->exists) {
            $instrument->forceFill([
                'institution_id' => $institution->id,
                'code' => self::INSTRUMENT_CODE,
                'is_synthetic' => true,
            ])->save();
        }
        if (! $instrument->is_synthetic) {
            throw new RuntimeException('OASIS-3-ICAT is already owned by a non-synthetic instrument.');
        }

        $latestVersion = TestInstrumentVersion::query()
            ->where('test_instrument_id', $instrument->id)
            ->orderByDesc('version_number')
            ->lockForUpdate()
            ->first();

        if ($latestVersion?->status === TestInstrumentVersion::STATUS_APPROVED
            && $latestVersion->subtests()->count() === count(self::SUBTESTS)
            && $latestVersion->metrics()->count() === count(self::METRICS)
            && $latestVersion->items()->count() === 190) {
            return $latestVersion;
        }

        if ($latestVersion !== null) {
            return $latestVersion;
        }
        $versionNumber = 1;
        $version = new TestInstrumentVersion;

        $version->forceFill([
            'test_instrument_id' => $instrument->id,
            'version_number' => $versionNumber,
            'status' => TestInstrumentVersion::STATUS_DRAFT,
            'display_name' => 'OASIS-3 College Entrance Test',
            'publisher_name' => 'Synthetic fixture',
            'form_code' => 'OASIS3_DEMO',
            'edition' => '2026 software fixture',
            'language' => 'English',
            'source_reference' => 'demo://oasis-3-icat/instrument-v'.$versionNumber,
            'permitted_use_reference' => 'Synthetic software fixture only; not an official test form or licensed scoring key.',
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'Synthetic direct-manual cohort fixture; administer only as software test data.',
            'lock_version' => 1,
            'created_by_user_id' => $actor->id,
        ])->save();
        $areas = AptitudeArea::query()
            ->whereIn('code', ['VERBAL', 'QUANT', 'SPATIAL', 'MEMORY', 'LOGIC', 'LANGUAGE'])
            ->get()
            ->keyBy('code');
        foreach (array_keys(self::METRICS) as $metric) {
            $areaCode = self::METRICS[$metric]['construct'];
            if (! $areas->has($areaCode)) {
                throw new RuntimeException("Missing seeded aptitude construct {$areaCode}.");
            }
        }

        $subtests = [];
        foreach (self::SUBTESTS as $order => $definition) {
            $code = $order;
            $subtest = TestInstrumentSubtest::query()->firstOrCreate(
                ['test_instrument_version_id' => $version->id, 'code' => $code],
                [
                    'name' => $definition['label'],
                    'display_order' => count($subtests) + 1,
                    'response_mode' => 'keyed_items',
                    'item_order_policy' => 'fixed',
                ],
            );
            $subtests[$code] = $subtest;

            for ($number = 1; $number <= $definition['count']; $number++) {
                TestInstrumentItem::query()->firstOrCreate(
                    ['test_instrument_version_id' => $version->id, 'item_code' => $code.'_Q_'.$number],
                    [
                        'test_instrument_subtest_id' => $subtest->id,
                        'local_number' => (string) $number,
                        'display_order' => $number,
                        'is_scored' => true,
                        'option_tokens' => $definition['options'],
                    ],
                );
            }
        }

        $metrics = [];
        foreach (self::METRICS as $code => $definition) {
            $subtest = $definition['subtest'] === null ? null : $subtests[$definition['subtest']];
            $metric = TestInstrumentMetric::query()->firstOrCreate(
                ['test_instrument_version_id' => $version->id, 'code' => $code],
                [
                    'name' => $definition['label'],
                    'rule_kind' => $definition['subtest'] === null
                        ? TestInstrumentMetric::RULE_SUM_RAW_METRICS
                        : TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                    'test_instrument_subtest_id' => $subtest?->id,
                    'unit' => 'count',
                    'min_raw_value' => 0,
                    'max_raw_value' => $definition['maximum'],
                    'precision_scale' => 0,
                    'is_required' => true,
                    'required_output_scales' => ['percentile_rank'],
                    'procedure_reference' => null,
                    'construct_aptitude_area_id' => $areas[$definition['construct']]->id,
                    'input_contract_json' => [
                        'allowed_sources' => ['import', 'manual'],
                        'required_resources' => [],
                        'source_requirements' => [
                            'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                            'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                        ],
                    ],
                ],
            );
            $metrics[$code] = $metric;
        }

        foreach ([
            'GENERAL_ABILITY_RAW' => ['VOCABULARY_RAW', 'COMPUTATION_RAW'],
            'MANUAL_DEXTERITY_RAW' => ['SPATIAL_RAW', 'WORD_COMP_RAW'],
        ] as $compositeCode => $inputCodes) {
            foreach ($inputCodes as $position => $inputCode) {
                TestInstrumentMetricInput::query()->firstOrCreate(
                    [
                        'test_instrument_version_id' => $version->id,
                        'metric_id' => $metrics[$compositeCode]->id,
                        'input_metric_id' => $metrics[$inputCode]->id,
                    ],
                    ['position' => $position + 1],
                );
            }
        }

        return app(ApproveTestInstrumentVersion::class)->handle(
            $actor,
            (int) $instrument->id,
            (int) $version->id,
            (int) $version->lock_version,
            (string) Str::uuid(),
            sourceReviewAttestation: true,
            isDemo: true,
        );
    }

    private function seedTestKit(Institution $institution, User $actor): TestKit
    {
        $kit = TestKit::query()->firstOrNew([
            'institution_id' => $institution->id,
            'code' => self::KIT_CODE,
        ]);
        if (! $kit->exists) {
            $kit->forceFill([
                'public_id' => (string) Str::uuid(),
                'institution_id' => $institution->id,
                'code' => self::KIT_CODE,
                'is_synthetic' => true,
                'created_by_user_id' => $actor->id,
            ]);
        }
        if (! $kit->is_synthetic) {
            throw new RuntimeException('TK-ICAT-2026 is already owned by a non-synthetic Test Kit.');
        }
        $kit->name = 'College Admission Test Kit (OASIS-3 Edition)';
        $kit->save();

        return $kit;
    }

    private function seedPreparation(
        TestKit $kit,
        TestInstrumentVersion $instrumentVersion,
        AssessmentNormPackageVersion $normVersion,
        User $actor,
    ): TestKitPreparation {
        if ($kit->versions()->exists() && $kit->preparations()->where('state', 'preparing')->doesntExist()) {
            return $kit->preparations()->latest('id')->firstOrFail();
        }
        $latestVersionNumber = (int) ($kit->versions()->max('version_number') ?? 0);
        $preparation = app(StartTestKitPreparation::class)->handle($actor, (int) $kit->id, [
            'expected_family_version' => $latestVersionNumber,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        return app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->id, [
            'preparation_id' => (int) $preparation->id,
            'expected_revision' => (int) $preparation->revision,
            'idempotency_key' => (string) Str::uuid(),
            'test_instrument_version_id' => (int) $instrumentVersion->id,
            'norm_package_version_id' => (int) $normVersion->id,
            'answer_sheet_draft_id' => null,
            'template_version_id' => null,
            'booklet_version_id' => null,
            'booklet_set_id' => null,
        ]);
    }

    /** @param array{reference: string, first_name: string, last_name: string, birthdate: string, sex: string, email: string, phone: string, strand: string, school: string, program: string} $applicant */
    private function ensureAcceptedApplicant(
        Institution $institution,
        AcademicCycle $cycle,
        ApplicationWindow $window,
        array $applicant,
    ): AdmissionAttempt {
        $identity = ApplicantIdentity::query()->updateOrCreate(
            [
                'institution_id' => $institution->id,
                'normalized_key' => hash('sha256', 'foundational-scoring:'.$applicant['email']),
            ],
            [
                'first_name' => $applicant['first_name'],
                'last_name' => $applicant['last_name'],
                'birthdate' => $applicant['birthdate'],
                'sex' => $applicant['sex'],
                'email' => $applicant['email'],
                'phone' => $applicant['phone'],
            ],
        );

        $attempt = AdmissionAttempt::query()->updateOrCreate(
            ['reference_code' => $applicant['reference']],
            [
                'applicant_identity_id' => $identity->id,
                'institution_id' => $institution->id,
                'academic_cycle_id' => $cycle->id,
                'application_window_id' => $window->id,
                'status' => AdmissionAttempt::STATUS_ACCEPTED,
                'preferred_program_code' => $applicant['program'],
                'program_preference_1_code' => $applicant['program'],
                'applicant_type' => 'new',
                'last_school_enrolled' => $applicant['school'],
                'strand' => $applicant['strand'],
                'gwa' => null,
                'contact_email' => $applicant['email'],
                'contact_phone' => $applicant['phone'],
                'accepted_at' => now(),
                'version' => 1,
            ],
        );

        return $attempt;
    }

    private function seedDecisionSupportDrafts(Institution $institution, AcademicCycle $cycle, User $actor): void
    {
        $activation = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
        if ($activation === null) {
            return;
        }
        $baseSet = $activation->benchmarkSet()->with(['features', 'exemplars.program', 'exemplars.values.feature'])->first();
        if ($baseSet === null) {
            return;
        }

        $fallbackProfiles = [
            'BSEd-Eng' => [
                'preferred_strands' => ['HUMSS', 'GAS'],
                'tiers' => [
                    '1' => ['values' => ['gwa' => 92.0, 'LOGIC' => 86.0, 'PROFILE_VERBAL' => 94.0, 'PROFILE_QUANT' => 76.0, 'SPATIAL' => 74.0, 'MEMORY' => 80.0, 'LANGUAGE' => 74.0]],
                    '2' => ['values' => ['gwa' => 87.0, 'LOGIC' => 80.0, 'PROFILE_VERBAL' => 88.0, 'PROFILE_QUANT' => 70.0, 'SPATIAL' => 70.0, 'MEMORY' => 75.0, 'LANGUAGE' => 70.0]],
                    '3' => ['values' => ['gwa' => 84.0, 'LOGIC' => 75.0, 'PROFILE_VERBAL' => 82.0, 'PROFILE_QUANT' => 66.0, 'SPATIAL' => 66.0, 'MEMORY' => 70.0, 'LANGUAGE' => 68.0]],
                    '4' => ['values' => ['gwa' => 88.0, 'LOGIC' => 82.0, 'PROFILE_VERBAL' => 85.0, 'PROFILE_QUANT' => 80.0, 'SPATIAL' => 74.0, 'MEMORY' => 74.0, 'LANGUAGE' => 70.0]],
                    '5' => ['values' => ['gwa' => 78.0, 'LOGIC' => 60.0, 'PROFILE_VERBAL' => 65.0, 'PROFILE_QUANT' => 60.0, 'SPATIAL' => 60.0, 'MEMORY' => 60.0, 'LANGUAGE' => 58.0]],
                ],
            ],
            'BSEd-Math' => [
                'preferred_strands' => ['STEM', 'GAS'],
                'tiers' => [
                    '1' => ['values' => ['gwa' => 92.5, 'LOGIC' => 88.0, 'PROFILE_VERBAL' => 82.0, 'PROFILE_QUANT' => 94.0, 'SPATIAL' => 86.0, 'MEMORY' => 82.0, 'LANGUAGE' => 75.0]],
                    '2' => ['values' => ['gwa' => 87.5, 'LOGIC' => 82.0, 'PROFILE_VERBAL' => 76.0, 'PROFILE_QUANT' => 88.0, 'SPATIAL' => 80.0, 'MEMORY' => 78.0, 'LANGUAGE' => 72.0]],
                    '3' => ['values' => ['gwa' => 84.0, 'LOGIC' => 76.0, 'PROFILE_VERBAL' => 72.0, 'PROFILE_QUANT' => 82.0, 'SPATIAL' => 74.0, 'MEMORY' => 72.0, 'LANGUAGE' => 68.0]],
                    '4' => ['values' => ['gwa' => 88.5, 'LOGIC' => 82.0, 'PROFILE_VERBAL' => 80.0, 'PROFILE_QUANT' => 86.0, 'SPATIAL' => 78.0, 'MEMORY' => 76.0, 'LANGUAGE' => 70.0]],
                    '5' => ['values' => ['gwa' => 78.0, 'LOGIC' => 62.0, 'PROFILE_VERBAL' => 64.0, 'PROFILE_QUANT' => 62.0, 'SPATIAL' => 60.0, 'MEMORY' => 60.0, 'LANGUAGE' => 58.0]],
                ],
            ],
        ];

        $preferredStrandsByCode = [
            'BSIT' => ['STEM', 'TVL-ICT'],
            'BSEd' => ['HUMSS', 'GAS'],
            'BEEd' => ['HUMSS', 'GAS'],
            'BPEd' => ['GAS', 'TVL-HE'],
            'BSBA' => ['ABM', 'GAS'],
            'BSEntrep' => ['ABM', 'TVL-HE'],
            'BSMath' => ['STEM', 'GAS'],
            'BA-EL' => ['HUMSS', 'GAS'],
            'BA-Psych' => ['HUMSS', 'STEM'],
            'BASS' => ['HUMSS', 'GAS'],
            'BPA' => ['HUMSS', 'ABM'],
        ];

        $saveDraft = app(SaveProgramBenchmarkDraft::class);
        $programs = Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->where('is_admission_offered', true)
            ->get();

        foreach ($programs as $program) {
            $existingDraft = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $institution->id)
                ->where('program_id', $program->id)
                ->where('academic_cycle_id', $cycle->id)
                ->first();
            if ($existingDraft !== null && $existingDraft->current_version > 0) {
                continue;
            }

            $exemplars = $baseSet->exemplars->where('program_id', $program->id);
            if ($exemplars->isNotEmpty()) {
                $tiers = [];
                foreach ($exemplars as $exemplar) {
                    $values = [];
                    foreach ($exemplar->values as $cell) {
                        $values[$cell->feature->feature_key] = $cell->is_missing ? null : (float) $cell->numeric_value;
                    }
                    $tiers[(string) $exemplar->tier_level] = ['values' => $values];
                }
                $content = [
                    'tiers' => $tiers,
                    'authoring' => [
                        'version' => 2,
                        'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []],
                        'preferred_strands' => $preferredStrandsByCode[$program->code] ?? ['GAS'],
                        'interests' => [],
                    ],
                ];
            } elseif (isset($fallbackProfiles[$program->code])) {
                $content = [
                    'tiers' => $fallbackProfiles[$program->code]['tiers'],
                    'authoring' => [
                        'version' => 2,
                        'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []],
                        'preferred_strands' => $fallbackProfiles[$program->code]['preferred_strands'],
                        'interests' => [],
                    ],
                ];
            } else {
                continue;
            }

            $saveDraft->handle(
                $actor,
                $program,
                $cycle,
                $content,
                0,
                'Initial ISPSC Tagudin undergraduate Decision Support benchmark calibration.',
                'seed-decision-support-draft-'.$program->code,
            );
        }

        $activeProgramCount = $baseSet->exemplars->pluck('program_id')->unique()->count();
        if ($activeProgramCount < $programs->count()) {
            $revisionIdsByProgram = AdmissionBenchmarkDraft::query()
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->whereIn('program_id', $programs->pluck('id'))
                ->where('current_version', '>', 0)
                ->get()
                ->mapWithKeys(static fn (AdmissionBenchmarkDraft $draft): array => [(int) $draft->program_id => (int) ($draft->currentRevision()?->id ?? 0)])
                ->filter(static fn (int $revId): bool => $revId > 0)
                ->all();
            if (count($revisionIdsByProgram) === $programs->count()) {
                $published = app(PublishProgramBenchmarkSet::class)->handle(
                    $actor,
                    $cycle,
                    $revisionIdsByProgram,
                    (string) $baseSet->content_sha256,
                    'Publish complete 13-program ISPSC Tagudin undergraduate Decision Support benchmark set.',
                    'seed-decision-support-publish-13-programs',
                );
                app(ActivateAdmissionBenchmarkSet::class)->handle(
                    $cycle,
                    $published,
                    BenchmarkExecutionContext::human($actor),
                    (int) $activation->id,
                    (string) $published->content_sha256,
                    'seed-decision-support-activate-13-programs',
                );
            }
        }
    }
}
