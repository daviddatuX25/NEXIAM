<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Actions\ActivateTestKitVersion;
use App\Assessment\Actions\AssembleTestKitPreparation;
use App\Assessment\Actions\CreateTestKitPreparation;
use App\Assessment\Actions\DiscardTestKitPreparation;
use App\Assessment\Actions\RetireTestKitVersion;
use App\Assessment\Actions\RevokeTestKitVersion;
use App\Assessment\Actions\SaveTestKitComposition;
use App\Assessment\Actions\SaveTestKitPreparation;
use App\Assessment\Actions\SaveTestKitTestSetup;
use App\Assessment\Actions\SelectTestKitAcceptanceReceipt;
use App\Assessment\Actions\StartTestKitPreparation;
use App\Assessment\Queries\TestKitCompositionQuery;
use App\Assessment\Queries\TestKitDetailQuery;
use App\Assessment\Queries\TestKitTestSetupQuery;
use App\Assessment\Support\TestKitEligibility;
use App\Assessment\Support\TestKitNavigationContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\AssembleTestKitPreparationRequest;
use App\Http\Requests\Setup\CreateTestKitPreparationRequest;
use App\Http\Requests\Setup\DiscardTestKitPreparationRequest;
use App\Http\Requests\Setup\SaveTestKitCompositionRequest;
use App\Http\Requests\Setup\SaveTestKitPreparationRequest;
use App\Http\Requests\Setup\SaveTestKitTestSetupRequest;
use App\Http\Requests\Setup\StartTestKitPreparationRequest;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class TestKitController extends Controller
{
    use HasSetupCapabilities;

    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        TestKitEligibility $eligibility,
    ): Response {
        $this->requireAssessment($authorizer, $request);
        $institutionId = (int) Institution::current()->id;
        $actor = $request->user();
        $canEditAnswerSheets = $actor instanceof User && $authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_SCORING_OMR,
            ['office' => 'guidance', 'institution_id' => $institutionId],
        )->allowed;
        $canManageBooklets = $actor instanceof User && $authorizer->decideFresh(
            $actor,
            CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE,
            ['office' => 'guidance', 'institution_id' => $institutionId],
        )->allowed;

        $testKits = TestKit::query()
            ->where('institution_id', $institutionId)
            ->with([
                'openPreparation',
                'versions' => fn ($query) => $query
                    ->with(['testInstrumentVersion.metrics', 'selectedAcceptanceReceipt'])
                    ->orderByDesc('version_number')
                    ->limit(1),
            ])
            ->orderBy('code')
            ->limit(100)
            ->get()
            ->map(function (TestKit $kit) use ($eligibility): array {
                $version = $kit->versions->first();
                $profile = $version?->testInstrumentVersion;
                $finalOnly = $profile?->isFinalOnly() ?? false;
                $omrApplicable = $profile?->metrics->contains(
                    fn ($metric): bool => in_array('omr', (array) ($metric->input_contract_json['allowed_sources'] ?? []), true),
                ) ?? false;
                $omrReadiness = 'not_applicable';
                if ($version !== null && ! $finalOnly && $omrApplicable) {
                    $omrReadiness = $eligibility->evaluate(
                        $version,
                        null,
                        TestKitEligibility::PURPOSE_ACTIVATION,
                        operation: 'omr',
                    )['eligible'] ? 'ready' : 'held';
                }

                return [
                    'id' => (int) $kit->id,
                    'code' => (string) $kit->code,
                    'name' => (string) $kit->name,
                    'is_synthetic' => (bool) $kit->is_synthetic,
                    'url' => route('setup.exam-materials.test-kits.show', ['testKit' => $kit->id], absolute: false),
                    'test_setup_url' => route('setup.exam-materials.test-kits.test-setup', [
                        'testKit' => $kit->id,
                        'test_kit_id' => $kit->id,
                    ], absolute: false),
                    'preparation' => $kit->openPreparation === null ? null : [
                        'id' => (int) $kit->openPreparation->id,
                        'revision' => (int) $kit->openPreparation->revision,
                        'state' => (string) $kit->openPreparation->state,
                    ],
                    'latest_version' => $version === null ? null : [
                        'id' => (int) $version->id,
                        'version_number' => (int) $version->version_number,
                        'status' => (string) $version->status,
                        'use_status' => $version->revoked_at !== null
                            ? 'revoked'
                            : match ($version->status) {
                                TestKitVersion::STATUS_CANDIDATE => 'draft',
                                TestKitVersion::STATUS_ACTIVE => 'active',
                                default => 'history',
                            },
                        'scoring_method' => $finalOnly ? 'final_scores_only' : 'raw_scoring',
                        'omr_readiness' => $finalOnly || ! $omrApplicable
                            ? 'not_applicable'
                            : $omrReadiness,
                        'revision' => (int) $version->revision,
                    ],
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Setup/TestKits/Index', [
            'test_kits' => $testKits,
            'actions' => [
                'create' => route('setup.exam-materials.test-kits.create'),
                'test_setup' => route('setup.test-instruments.index', [], false),
                'answer_sheets' => $canEditAnswerSheets
                    ? route('setup.exam-materials.answer-sheets.index', [], false)
                    : null,
                'booklets' => $canManageBooklets
                    ? route('setup.exam-materials.booklets.index', [], false)
                    : null,
            ],
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireAssessment($authorizer, $request);

        return Inertia::render('Setup/TestKits/Create', [
            'actions' => [
                'store' => route('setup.exam-materials.test-kits.store'),
                'index' => route('setup.exam-materials.test-kits.index'),
            ],
        ]);
    }

    public function store(CreateTestKitPreparationRequest $request, CreateTestKitPreparation $createPreparation): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $preparation = $createPreparation->handle($actor, $request->validated());

        return redirect()->route('setup.exam-materials.test-kits.show', [
            'testKit' => (int) $preparation->test_kit_id,
        ])->with('status', 'Test Kit preparation created.');
    }

    public function startPreparation(
        StartTestKitPreparationRequest $request,
        StartTestKitPreparation $action,
        int $testKit,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $preparation = $action->handle($actor, $testKit, $request->validated());

        return redirect()->route('setup.exam-materials.test-kits.show', [
            'testKit' => (int) $preparation->test_kit_id,
        ])->with('status', 'Test Kit preparation started.');
    }

    public function discardPreparation(
        DiscardTestKitPreparationRequest $request,
        DiscardTestKitPreparation $action,
        int $testKit,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $action->handle($actor, $testKit, $request->validated());

        return redirect()->route('setup.exam-materials.test-kits.show', [
            'testKit' => $testKit,
        ])->with('status', 'Draft preparation discarded. The locked Test Kit version is unchanged.');
    }

    public function savePreparation(
        SaveTestKitPreparationRequest $request,
        SaveTestKitPreparation $action,
        int $testKit,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $preparation = $action->handle($actor, $testKit, $request->validated());

        return redirect()->route('setup.exam-materials.test-kits.show', [
            'testKit' => (int) $preparation->test_kit_id,
        ])->with('status', 'Test Kit preparation saved.');
    }

    public function composition(
        Request $request,
        CapabilityAuthorizer $authorizer,
        TestKitCompositionQuery $query,
        int $testKit,
    ): JsonResponse {
        $this->requireAssessment($authorizer, $request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $kit = TestKit::query()
            ->where('institution_id', (int) Institution::current()->id)
            ->findOrFail($testKit);
        $preparation = TestKitPreparation::query()
            ->where('test_kit_id', $kit->id)
            ->where('state', TestKitPreparation::STATE_PREPARING)
            ->firstOrFail();

        return response()->json(
            $query->forPreparation($actor, $kit, $preparation),
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    public function saveComposition(
        SaveTestKitCompositionRequest $request,
        SaveTestKitComposition $action,
        TestKitCompositionQuery $query,
        int $testKit,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $preparation = $action->handle($actor, $testKit, $request->validated());
        $kit = TestKit::query()
            ->where('institution_id', (int) Institution::current()->id)
            ->findOrFail($testKit);

        return response()->json(
            $query->forPreparation($actor, $kit, $preparation),
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    public function assemblePreparation(
        AssembleTestKitPreparationRequest $request,
        AssembleTestKitPreparation $action,
        int $testKit,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $version = $action->handle($actor, $testKit, $request->validated());

        return redirect()->route('setup.exam-materials.test-kits.show', [
            'testKit' => (int) $version->test_kit_id,
            'version' => (int) $version->id,
        ])->with('status', 'Test Kit candidate assembled.');
    }

    public function testSetup(
        Request $request,
        CapabilityAuthorizer $authorizer,
        TestKitTestSetupQuery $query,
        TestKitNavigationContext $navigationContext,
        int $testKit,
    ): Response {
        $this->requireAssessment($authorizer, $request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $kitContext = $navigationContext->resolve($actor, $request);
        abort_unless($kitContext !== null && (int) $kitContext['test_kit_id'] === $testKit, 404);
        $projection = $query->query($actor, $testKit, $kitContext['test_kit_version_id']);
        $projection['kit_context'] = $kitContext;

        return Inertia::render('Setup/TestKits/TestSetup', $projection);
    }

    public function saveTestSetup(
        SaveTestKitTestSetupRequest $request,
        SaveTestKitTestSetup $action,
        TestKitNavigationContext $navigationContext,
        int $testKit,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $kitContext = $navigationContext->resolve($actor, $request);
        abort_unless($kitContext !== null && (int) $kitContext['test_kit_id'] === $testKit, 404);
        $input = $request->validated();
        abort_unless(
            $kitContext['preparation_id'] !== null
                && (int) $kitContext['preparation_id'] === (int) $input['preparation_id']
                && $kitContext['test_kit_version_id'] === null,
            404,
        );
        $action->handle($actor, $testKit, $input);

        return redirect()->route('setup.exam-materials.test-kits.test-setup', [
            'testKit' => $testKit,
            'test_kit_id' => $kitContext['test_kit_id'],
            'preparation_id' => $kitContext['preparation_id'],
        ])->with('status', 'Test Setup saved.');
    }

    public function show(
        Request $request,
        CapabilityAuthorizer $authorizer,
        TestKitDetailQuery $query,
        int $testKit,
        ?int $version = null,
    ): Response {
        $this->requireAssessment($authorizer, $request);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return Inertia::render('Setup/TestKits/Show', $query->query($actor, $testKit, $version));
    }

    public function selectReceipt(
        Request $request,
        CapabilityAuthorizer $authorizer,
        SelectTestKitAcceptanceReceipt $action,
        int $testKit,
        int $version,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);
        $validated = Validator::make($request->all(), [
            'acceptance_receipt_id' => ['required', 'integer'],
            'expected_revision' => ['required', 'integer'],
            'idempotency_key' => ['nullable', 'string'],
        ])->validate();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($actor, $testKit, $version, $validated);

        return back()->with('status', 'Acceptance receipt selected.');
    }

    public function activate(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ActivateTestKitVersion $action,
        int $testKit,
        int $version,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);
        $validated = Validator::make($request->all(), [
            'expected_revision' => ['required', 'integer'],
            'academic_cycle_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string'],
        ])->validate();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($actor, $testKit, $version, $validated);

        return back()->with('status', 'Test Kit activated for live exams.');
    }

    public function retire(
        Request $request,
        CapabilityAuthorizer $authorizer,
        RetireTestKitVersion $action,
        int $testKit,
        int $version,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);
        $validated = Validator::make($request->all(), [
            'expected_revision' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string'],
        ])->validate();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($actor, $testKit, $version, $validated);

        return back()->with('status', 'Test Kit retired.');
    }

    public function revoke(
        Request $request,
        CapabilityAuthorizer $authorizer,
        RevokeTestKitVersion $action,
        int $testKit,
        int $version,
    ): RedirectResponse {
        $this->requireAssessment($authorizer, $request);
        $validated = Validator::make($request->all(), [
            'expected_revision' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string'],
        ])->validate();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $action->handle($actor, $testKit, $version, $validated);

        return back()->with('status', 'Test Kit candidate revoked.');
    }
}
