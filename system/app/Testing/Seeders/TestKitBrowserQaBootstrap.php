<?php

declare(strict_types=1);

namespace App\Testing\Seeders;

use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\Omr\Canonical\ComposeOmrCanonicalPages;
use App\Assessment\Actions\Omr\Canonical\CreateOmrAuthoringDraft;
use App\Assessment\Actions\Omr\Canonical\MaterializeOmrCanonicalDraft;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalMapping;
use App\Assessment\Actions\Omr\Canonical\SaveOmrCanonicalStudioState;
use App\Assessment\Actions\SaveTestInstrumentDraft;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Omr\OmrQrMode;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Room;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\CapabilityCatalogSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceCatalogSeeder;
use Database\Seeders\StaffIdentitySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class TestKitBrowserQaBootstrap
{
    private const DATABASE_PATTERN = '/\Anexiam_test_kit_acceptance_[a-z0-9_]+\z/D';

    private const SETUP_ONLY_DATABASE = 'nexiam_test_kit_acceptance_composition_20261015';

    /** @return array<string, int|string> */
    public function run(bool $setupOnly = false): array
    {
        $this->assertQaDatabase($setupOnly);
        $database = (string) config('database.connections.pgsql.database');
        if ($setupOnly) {
            return $this->prepareSetupOnly($database);
        }

        return $this->preparePaperQa($database);
    }

    /** @return array<string, int|string> */
    private function prepareSetupOnly(string $database): array
    {
        return DB::transaction(function () use ($database): array {
            $staffEmail = 'assessment.composition.qa.current@nexiam.test';
            $staffPassword = 'AssessmentDemo-2026!';
            $kitCode = 'SYN-QA-SETUP-ONLY-COMPOSITION-CURRENT';
            $profileCode = 'SYN-QA-SETUP-COMPOSITION-CURRENT';

            $institution = Institution::current();
            $cycle = AcademicCycle::query()
                ->where('institution_id', $institution->getKey())
                ->where('is_active', true)
                ->firstOrFail();

            $role = Role::query()->where('name', 'guidance_counselor')->where('guard_name', 'web')->firstOrFail();
            $helperCapabilities = CapabilityCatalog::roleHelperGrants()['guidance_counselor'] ?? [];
            if (! in_array(CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG, $helperCapabilities, true)
                || ! $role->permissions->contains('name', CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG)) {
                throw new RuntimeException('The existing Guidance Counselor role does not hold its catalog-defined Test Kit capability; no role definition was changed.');
            }

            $actor = User::query()->where('email', $staffEmail)->first();
            if ($actor === null) {
                $actor = User::query()->create([
                    'name' => 'Synthetic Assessment Composition QA Counselor',
                    'email' => $staffEmail,
                    'password' => Hash::make($staffPassword),
                    'email_verified_at' => now(),
                ]);
                $actor->assignRole($role);
                StaffProfile::query()->create([
                    'user_id' => (int) $actor->getKey(),
                    'institution_id' => (int) $institution->getKey(),
                    'office' => 'guidance',
                    'program_codes' => [],
                    'active_cycle_code' => (string) $cycle->code,
                    'status' => StaffProfile::STATUS_ACTIVE,
                ]);
                StaffAuthenticationState::query()->create([
                    'user_id' => (int) $actor->getKey(),
                    'activation_completed_at' => now(),
                    'password_set_at' => now(),
                    'must_change_password' => false,
                    'session_version' => 1,
                    'mfa_required' => false,
                    'two_factor_generation' => (string) Str::uuid(),
                ]);
            } else {
                $profile = $actor->staffProfile()->first();
                if (! Hash::check($staffPassword, (string) $actor->password)
                    || $actor->hasRole($role) === false
                    || $profile === null
                    || (int) $profile->institution_id !== (int) $institution->getKey()
                    || $profile->office !== 'guidance'
                    || $profile->status !== StaffProfile::STATUS_ACTIVE
                    || $profile->active_cycle_code !== $cycle->code
                    || ! $actor->authenticationState()->exists()) {
                    throw new RuntimeException('The dedicated QA login already exists in a different state; it was left unchanged.');
                }
            }

            $kit = TestKit::query()->where('institution_id', $institution->getKey())->where('code', $kitCode)->first();
            if ($kit !== null) {
                if (! $kit->is_synthetic || (int) $kit->created_by_user_id !== (int) $actor->getKey()) {
                    throw new RuntimeException('The dedicated demo Kit has a different provenance or owner; it was left unchanged.');
                }
                $latestPreparation = $kit->preparations()->latest('id')->first();
                $setup = TestInstrument::query()->where('institution_id', $institution->getKey())->where('code', $profileCode)->firstOrFail();
                $setupVersion = $setup->currentApprovedVersion()->firstOrFail();
                if (! $setup->is_synthetic
                    || $setupVersion->status !== TestInstrumentVersion::STATUS_APPROVED
                    || $setupVersion->material_requirement !== TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER
                    || ($latestPreparation !== null && (int) $latestPreparation->test_instrument_version_id !== (int) $setupVersion->getKey())) {
                    throw new RuntimeException('The dedicated synthetic Setup and latest Kit preparation no longer match; existing state was left unchanged.');
                }

                return $this->setupOnlyResult($database, $institution, $cycle, $actor, $kit, $latestPreparation, $setupVersion, $staffPassword);
            }

            $instrument = new TestInstrument;
            $instrument->forceFill([
                'institution_id' => (int) $institution->getKey(),
                'is_synthetic' => true,
                'code' => $profileCode,
            ])->save();
            $draft = TestInstrumentVersion::query()->create([
                'test_instrument_id' => (int) $instrument->getKey(),
                'version_number' => 1,
                'status' => TestInstrumentVersion::STATUS_DRAFT,
                'display_name' => 'Synthetic setup-only manual/import scoring procedure',
                'lock_version' => 1,
                'created_by_user_id' => (int) $actor->getKey(),
            ]);
            $profile = app(SaveTestInstrumentDraft::class)->handle(
                $actor,
                (int) $instrument->getKey(),
                (int) $draft->getKey(),
                (int) $draft->lock_version,
                'test-kit-setup-only-profile-20261016',
                [
                    'profile' => [
                        'code' => $profileCode,
                        'display_name' => 'Synthetic setup-only manual/import scoring procedure',
                        'publisher_name' => 'Synthetic software fixture; no publisher represented',
                        'form_code' => 'SYN-SETUP-ONLY-20261016',
                        'edition' => 'Synthetic software fixture',
                        'language' => 'Synthetic fixture',
                        'source_reference' => 'Synthetic software-only fixture; no external source represented.',
                        'permitted_use_reference' => 'Synthetic software acceptance only; no institutional or publisher rights claim.',
                        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
                        'administration_procedure_reference' => 'Synthetic procedure: administer two keyed items, accept manual or imported numeric raw counts, and sum exact correct responses. No paper or external resource is required.',
                    ],
                    'subtests' => [[
                        'code' => 'SETUP-ONLY-LOGIC',
                        'name' => 'Synthetic setup-only logic count',
                        'display_order' => 1,
                        'response_mode' => 'keyed_items',
                        'item_order_policy' => 'fixed',
                        'items' => [
                            ['item_code' => 'SETUP-ONLY-LOGIC-1', 'local_number' => '1', 'display_order' => 1, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                            ['item_code' => 'SETUP-ONLY-LOGIC-2', 'local_number' => '2', 'display_order' => 2, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                        ],
                    ]],
                    'metrics' => [[
                        'code' => 'RAW_COUNT',
                        'name' => 'Correct response count',
                        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                        'subtest_code' => 'SETUP-ONLY-LOGIC',
                        'unit' => 'count',
                        'min_raw_value' => '0',
                        'max_raw_value' => '2',
                        'precision_scale' => 0,
                        'is_required' => true,
                        'required_output_scales' => [],
                        'procedure_reference' => 'Synthetic procedure: count correct item responses.',
                        'construct_aptitude_area_id' => null,
                        'input_codes' => [],
                        'input_contract_json' => [
                            'allowed_sources' => ['import', 'manual'],
                            'required_resources' => [],
                            'source_requirements' => [
                                'import' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                                'manual' => ['unit' => 'count', 'identity_requirements' => ['administration', 'metric']],
                            ],
                        ],
                    ]],
                ],
            );
            $profile = app(ApproveTestInstrumentVersion::class)->handle(
                $actor,
                (int) $instrument->getKey(),
                (int) $profile->getKey(),
                (int) $profile->lock_version,
                'test-kit-setup-only-profile-approval-20261016',
                sourceReviewAttestation: true,
                isDemo: true,
            );

            $kit = TestKit::query()->create([
                'public_id' => (string) Str::uuid(),
                'institution_id' => (int) $institution->getKey(),
                'code' => $kitCode,
                'name' => 'Synthetic Setup-only Assessment Composition QA',
                'is_synthetic' => true,
                'created_by_user_id' => (int) $actor->getKey(),
            ]);
            $preparation = app(StartTestKitPreparation::class)->handle($actor, (int) $kit->getKey(), [
                'expected_family_version' => 0,
                'idempotency_key' => '1e2d3c40-5b6a-4f70-8123-456789abcdef',
            ]);
            $preparation = app(SaveTestKitPreparation::class)->handle($actor, (int) $kit->getKey(), [
                'preparation_id' => (int) $preparation->getKey(),
                'expected_revision' => (int) $preparation->revision,
                'idempotency_key' => '2e2d3c40-5b6a-4f70-8123-456789abcdef',
                'test_instrument_version_id' => (int) $profile->getKey(),
                'norm_package_version_id' => null,
                'answer_sheet_draft_id' => null,
                'template_version_id' => null,
                'booklet_version_id' => null,
                'booklet_set_id' => null,
            ]);

            return $this->setupOnlyResult($database, $institution, $cycle, $actor, $kit, $preparation, $profile, $staffPassword);
        });
    }

    /**
     * @return array{database:string,staff_email:string,staff_password:string,expected_state:string,kit_url:string,institution_id:int,cycle_id:int,kit_id:int,profile_version_id:int,preparation_id:int}
     */
    private function setupOnlyResult(
        string $database,
        Institution $institution,
        AcademicCycle $cycle,
        User $actor,
        TestKit $kit,
        ?TestKitPreparation $preparation,
        TestInstrumentVersion $profile,
        string $staffPassword,
    ): array {
        return [
            'database' => $database,
            'staff_email' => (string) $actor->email,
            'staff_password' => $staffPassword,
            'expected_state' => $preparation === null
                ? 'No current editable preparation; existing Kit history was preserved.'
                : ($preparation->state === TestKitPreparation::STATE_PREPARING
                    ? 'Unlocked draft preparation #'.(int) $preparation->getKey().' selects approved Setup v'.(int) $profile->version_number.'. Save/reopen → Lock → Edit remains available.'
                    : 'Existing preparation #'.(int) $preparation->getKey().' is '.$preparation->state.'; it was preserved without reopening or rewriting it.'),
            'kit_url' => '/setup/exam-materials/test-kits/'.(int) $kit->getKey(),
            'institution_id' => (int) $institution->getKey(),
            'cycle_id' => (int) $cycle->getKey(),
            'kit_id' => (int) $kit->getKey(),
            'profile_version_id' => (int) $profile->getKey(),
            'preparation_id' => (int) ($preparation?->getKey() ?? 0),
        ];
    }

    /** @return array<string, int|string> */
    private function preparePaperQa(string $database): array
    {

        return DB::transaction(function () use ($database): array {
            if (! Institution::query()->where('code', 'SYN-INST-001')->exists()) {
                $this->assertFreshFoundation();
                app(DatabaseSeeder::class)->run([
                    ReferenceCatalogSeeder::class,
                    CapabilityCatalogSeeder::class,
                    StaffIdentitySeeder::class,
                ]);
            }

            $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
            $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
            $actor = User::query()->where('email', 'assessment.composition.qa.current@nexiam.test')->first()
                ?? User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
            $area = AptitudeArea::query()->where('code', 'LOGIC')->where('is_active', true)->firstOrFail();

            $suffix = 'LOGIC-COUNT';
            $existingKit = TestKit::query()->where('institution_id', $institution->id)
                ->where('code', 'SYN-KIT-BROWSER-QA-'.$suffix)->first();
            if ($existingKit !== null) {
                $existingProfile = TestInstrument::query()->where('institution_id', $institution->id)
                    ->where('code', 'SYN-QA-LOGIC-'.$suffix)->firstOrFail()->versions()->where('version_number', 1)->firstOrFail();
                $existingTemplate = OmrAnswerSheetTemplate::query()->where('institution_id', $institution->id)
                    ->where('code', 'SYN-KIT-BROWSER-QA-SHEET-'.$suffix)->firstOrFail();
                $existingDraft = OmrTemplateAuthoringDraft::query()->where('template_id', $existingTemplate->id)
                    ->where('owner_user_id', $actor->id)->sole();

                return [
                    'database' => $database, 'institution_id' => (int) $institution->id, 'cycle_id' => (int) $cycle->id,
                    'staff_email' => (string) $actor->email, 'kit_id' => (int) $existingKit->id,
                    'template_id' => (int) $existingTemplate->id, 'profile_version_id' => (int) $existingProfile->id,
                    'draft_id' => (int) $existingDraft->id, 'draft_stage' => (string) $existingDraft->stage,
                    'verification_status' => (string) data_get($existingDraft->authoring_json, 'verification.status', 'not_started'),
                ];
            }
            $kit = TestKit::query()->create([
                'public_id' => (string) Str::uuid(),
                'institution_id' => (int) $institution->getKey(),
                'code' => 'SYN-KIT-BROWSER-QA-'.$suffix,
                'name' => 'Synthetic Test Kit browser QA fixture',
                'is_synthetic' => true,
                'created_by_user_id' => (int) $actor->getKey(),
            ]);

            $instrument = new TestInstrument;
            $instrument->forceFill([
                'institution_id' => (int) $institution->getKey(),
                'is_synthetic' => true,
                'code' => 'SYN-QA-LOGIC-'.$suffix,
            ])->save();
            $profileDraft = TestInstrumentVersion::query()->create([
                'test_instrument_id' => (int) $instrument->getKey(),
                'version_number' => 1,
                'status' => TestInstrumentVersion::STATUS_DRAFT,
                'display_name' => 'Synthetic two-item logic count profile',
                'lock_version' => 1,
                'content_sha256' => null,
                'created_by_user_id' => (int) $actor->getKey(),
            ]);
            $subtestCode = 'LOGIC-'.$suffix;
            $itemOneCode = 'LOGIC-'.$suffix.'-Q1';
            $itemTwoCode = 'LOGIC-'.$suffix.'-Q2';
            $profile = app(SaveTestInstrumentDraft::class)->handle(
                $actor,
                (int) $instrument->getKey(),
                (int) $profileDraft->getKey(),
                (int) $profileDraft->lock_version,
                'test-kit-browser-qa-profile-'.$suffix,
                [
                    'profile' => [
                        'code' => (string) $instrument->code,
                        'display_name' => 'Synthetic two-item logic count profile',
                        'publisher_name' => 'Synthetic software fixture; no publisher represented',
                        'form_code' => 'SYN-LOGIC-'.$suffix,
                        'edition' => 'Synthetic fixture',
                        'language' => 'Synthetic fixture',
                        'source_reference' => 'repository-artifact:tests/Fixtures/TestKit/two-item-count-native-v1.json; synthetic software procedure only',
                        'permitted_use_reference' => 'Synthetic software acceptance only; no institutional source or rights claim',
                        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
                        'administration_procedure_reference' => 'Synthetic software procedure only: one page, exact fixed Set A, LOGIC_Q_1=A and LOGIC_Q_2=B, one point per exact match; no institutional or publisher procedure is represented.',
                    ],
                    'subtests' => [[
                        'code' => $subtestCode,
                        'name' => 'Synthetic two-item logic count',
                        'display_order' => 1,
                        'response_mode' => 'keyed_items',
                        'item_order_policy' => 'fixed',
                        'items' => [
                            ['item_code' => $itemOneCode, 'local_number' => '1', 'display_order' => 1, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                            ['item_code' => $itemTwoCode, 'local_number' => '2', 'display_order' => 2, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
                        ],
                    ]],
                    'metrics' => [[
                        'code' => 'RAW_COUNT',
                        'name' => 'Correct item count',
                        'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                        'subtest_code' => $subtestCode,
                        'unit' => 'count',
                        'min_raw_value' => '0',
                        'max_raw_value' => '2',
                        'precision_scale' => 0,
                        'is_required' => true,
                        'required_output_scales' => [],
                        'procedure_reference' => null,
                        'construct_aptitude_area_id' => (int) $area->getKey(),
                        'input_codes' => [],
                        'input_contract_json' => [
                            'allowed_sources' => ['import', 'manual', 'omr'],
                            'required_resources' => ['answer_sheet', 'booklet_form', 'key'],
                            'source_requirements' => array_fill_keys(['import', 'manual', 'omr'], [
                                'unit' => 'count',
                                'identity_requirements' => ['administration', 'answer_sheet', 'booklet_form', 'key', 'metric'],
                            ]),
                        ],
                    ]],
                ],
            );
            $profile = app(ApproveTestInstrumentVersion::class)->handle(
                $actor,
                (int) $instrument->getKey(),
                (int) $profile->getKey(),
                (int) $profile->lock_version,
                'test-kit-browser-qa-profile-approval-'.$suffix,
                sourceReviewAttestation: true,
                isDemo: true,
            );
            $profile->load(['subtests.items']);
            $subtest = $profile->subtests->firstOrFail();
            $items = $subtest->items->sortBy('display_order')->values();

            $template = OmrAnswerSheetTemplate::query()->create([
                'institution_id' => (int) $institution->getKey(),
                'is_synthetic' => true,
                'code' => 'SYN-KIT-BROWSER-QA-SHEET-'.$suffix,
                'name' => 'Synthetic Test Kit browser QA answer sheet',
            ]);
            $draft = app(CreateOmrAuthoringDraft::class)->handle(
                $actor,
                (string) $template->code,
                (string) $template->name,
                OmrTemplateAuthoringDraft::SOURCE_PRESET,
                OmrRegistrationMode::DocumentBound,
                OmrQrMode::Disabled,
                1,
                [
                    'pages' => [['page_no' => 1, 'confirmed' => true]],
                    'canonical' => ['settings' => ['paper_width_mm' => 210, 'paper_height_mm' => 297]],
                    'verification' => null,
                    'provenance' => ['kind' => 'synthetic_software_fixture', 'workflow_proof' => false],
                ],
            );
            $draft = app(ComposeOmrCanonicalPages::class)->handle($draft, $actor, [
                'parametric' => true,
                'page_count' => 1,
                'paper_width_mm' => 210,
                'paper_height_mm' => 297,
            ]);
            $draft = app(SaveOmrCanonicalMapping::class)->handle(
                $draft,
                $actor,
                [
                    'page_width' => 2480,
                    'page_height' => 3508,
                    'page_count' => 1,
                    'blocks' => [[
                        'name' => 'LOGIC_Q1_Q2',
                        'page_no' => 1,
                        'bubble_values' => ['A', 'B'],
                        'labels' => ['LOGIC_Q_1', 'LOGIC_Q_2'],
                        'rows' => 2,
                        'origin_x' => 295,
                        'origin_y' => 826,
                        'width' => 190,
                        'height' => 160,
                        'option_pitch_px' => 71,
                        'row_pitch_px' => 80,
                        'bubble_width_px' => 53,
                    ]],
                ],
                (int) $draft->lock_version,
            );
            $draft = app(SaveOmrCanonicalStudioState::class)->handle(
                $draft->fresh(), $actor, ['boxes' => []], null,
                ['physicalInventory' => [
                    'revision' => 1,
                    'sections' => [[
                        'printed_section_uid' => (string) Str::uuid(),
                        'name' => 'Synthetic logic items', 'page_no' => 1,
                        'numbering' => ['start' => 1, 'step' => 1], 'expected_item_count' => 2,
                        'items' => array_map(static fn (int $number): array => [
                            'physical_item_uid' => (string) Str::uuid(), 'page_no' => 1,
                            'printed_number' => $number, 'native_label' => 'LOGIC_Q_'.$number,
                            'option_tokens' => ['A', 'B'], 'guide_id' => 'LOGIC_Q1_Q2_'.$number,
                        ], [1, 2]),
                    ]],
                ]],
                (int) $draft->lock_version,
            );
            $draft = app(MaterializeOmrCanonicalDraft::class)->handle($draft->fresh(['assets', 'template']), $actor, (int) $draft->lock_version);

            if ($draft->stage !== OmrTemplateAuthoringDraft::STAGE_MAPPING_COMPLETE) {
                throw new RuntimeException('The real Test Kit QA builder producer did not stop at mapping-complete. No verification or compilation was fabricated.');
            }

            app(RecordAuditEvent::class)->handle('assessment.test_kit.qa_family_created', [
                'test_kit_id' => (int) $kit->getKey(),
                'institution_id' => (int) $institution->getKey(),
                'provenance' => 'synthetic_software_fixture',
            ], (string) $actor->getKey());

            return [
                'database' => $database,
                'institution_id' => (int) $institution->getKey(),
                'cycle_id' => (int) $cycle->getKey(),
                'staff_email' => (string) $actor->email,
                'kit_id' => (int) $kit->getKey(),
                'template_id' => (int) $template->getKey(),
                'profile_version_id' => (int) $profile->getKey(),
                'draft_id' => (int) $draft->getKey(),
                'draft_stage' => (string) $draft->stage,
                'verification_status' => 'not_started',
            ];
        });
    }

    public function assertQaDatabase(bool $setupOnly = false): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database", '');
        $configuredUrl = config("database.connections.{$connection}.url");
        $environmentUrl = getenv('DB_URL');
        $environmentUrl = $environmentUrl === false
            ? (string) ($_ENV['DB_URL'] ?? $_SERVER['DB_URL'] ?? '')
            : $environmentUrl;

        $this->assertQaTargetConfiguration(
            $this->qaModeEnabled(),
            $connection,
            $database,
            is_string($configuredUrl) ? $configuredUrl : null,
            $environmentUrl,
            (string) app()->environment(),
            $setupOnly,
        );

        $this->assertResolvedQaDatabase($database, (string) DB::scalar('select current_database()'), $setupOnly);
    }

    public function assertQaTargetConfiguration(
        bool $qaModeEnabled,
        string $connection,
        string $database,
        ?string $configuredUrl,
        string $environmentUrl,
        string $environment = 'testing',
        bool $setupOnly = false,
    ): void {
        $allowedDatabase = $setupOnly
            ? $database === self::SETUP_ONLY_DATABASE
            : preg_match(self::DATABASE_PATTERN, $database) === 1;

        if (! $qaModeEnabled
            || ! in_array($environment, ['testing', 'local'], true)
            || $connection !== 'pgsql'
            || ! $allowedDatabase
            || ($configuredUrl !== null && trim($configuredUrl) !== '')
            || trim($environmentUrl) !== '') {
            throw new RuntimeException('Test Kit browser QA requires TEST_KIT_BROWSER_QA=true, APP_ENV testing/local, PostgreSQL, an isolated nexiam_test_kit_acceptance_* DB_DATABASE (the setup-only path permits only the exact approved composition target), and an empty DB_URL.');
        }
    }

    public function assertResolvedQaDatabase(string $configuredDatabase, string $resolvedDatabase, bool $setupOnly = false): void
    {
        $allowedResolved = $setupOnly
            ? $resolvedDatabase === self::SETUP_ONLY_DATABASE
            : preg_match(self::DATABASE_PATTERN, $resolvedDatabase) === 1;
        if (! $allowedResolved || $resolvedDatabase !== $configuredDatabase) {
            throw new RuntimeException('Test Kit browser QA refused a database whose PostgreSQL current_database() does not exactly match the explicitly isolated configured database.');
        }
    }

    public function assertFreshFoundation(): void
    {
        foreach ([
            Institution::class,
            AcademicCycle::class,
            User::class,
            Role::class,
            Permission::class,
            StaffProfile::class,
            StaffAuthenticationState::class,
            Room::class,
            AptitudeArea::class,
            ApplicationWindow::class,
            TestInstrument::class,
            OmrAnswerSheetTemplate::class,
            OmrTemplateAuthoringDraft::class,
            TestKit::class,
        ] as $modelClass) {
            if ($modelClass::query()->exists()) {
                throw new RuntimeException('Test Kit browser QA requires a fresh isolated database; existing '.class_basename($modelClass).' rows were found and left untouched.');
            }
        }
    }

    private function qaModeEnabled(): bool
    {
        $value = $_ENV['TEST_KIT_BROWSER_QA'] ?? $_SERVER['TEST_KIT_BROWSER_QA'] ?? getenv('TEST_KIT_BROWSER_QA');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
