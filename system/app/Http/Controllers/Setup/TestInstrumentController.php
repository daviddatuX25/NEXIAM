<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Actions\ApproveTestInstrumentVersion;
use App\Assessment\Actions\CreateSuccessorTestInstrumentDraft;
use App\Assessment\Actions\RetireTestInstrumentVersion;
use App\Assessment\Actions\SaveTestInstrumentDraft;
use App\Assessment\Actions\SaveTestKitTestSetup;
use App\Assessment\Queries\TestInstrumentAnswerSheetConnectionsQuery;
use App\Assessment\Queries\TestInstrumentCatalogQuery;
use App\Assessment\Queries\TestInstrumentDetailQuery;
use App\Assessment\Queries\TestKitTestSetupQuery;
use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestKitNavigationContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ApproveTestInstrumentVersionRequest;
use App\Http\Requests\Setup\CreateSuccessorTestInstrumentDraftRequest;
use App\Http\Requests\Setup\RetireTestInstrumentVersionRequest;
use App\Http\Requests\Setup\SaveTestInstrumentDraftRequest;
use App\Http\Requests\Setup\SaveTestKitTestSetupRequest;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitPreparation;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class TestInstrumentController extends Controller
{
    public function setup(
        Request $request,
        CapabilityAuthorizer $authorizer,
        TestKitTestSetupQuery $query,
        int $instrument,
    ): Response {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($authorizer->allows($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);

        $projection = $query->queryForInstrument($actor, $instrument);

        return Inertia::render('Setup/TestKits/TestSetup', $projection);
    }

    public function saveSetup(
        SaveTestKitTestSetupRequest $request,
        CapabilityAuthorizer $authorizer,
        SaveTestKitTestSetup $action,
        int $instrument,
    ): RedirectResponse {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($authorizer->allows($actor, CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG), 403);

        $action->handleStandalone($actor, $instrument, $request->validated());

        return redirect()->route('setup.test-instruments.setup', [
            'instrument' => $instrument,
        ])->with('status', 'Test Setup saved.');
    }

    public function index(Request $request, TestInstrumentCatalogQuery $query): Response
    {
        return Inertia::render('Setup/TestInstruments/Index', $query->handle($request->user()));
    }

    public function create(Request $request, TestInstrumentAccess $access, TestKitNavigationContext $kitNavigationContext): Response
    {
        $access->institutionId($request->user());

        return Inertia::render('Setup/TestInstruments/Create', [
            'page_state' => 'ready',
            'request_id' => (string) Str::uuid(),
            'constructs' => $this->constructs(),
            'kit_context' => $kitNavigationContext->resolve($request->user(), $request),
        ]);
    }

    public function store(
        SaveTestInstrumentDraftRequest $request,
        SaveTestInstrumentDraft $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        $input = $request->validated();
        /** @var User $actor */
        $actor = $request->user();
        $version = $action->handle(
            $actor,
            null,
            null,
            null,
            $input['request_id'],
            [
                'profile' => $this->profilePayload($input['profile']),
                'subtests' => $input['subtests'],
                'metrics' => $input['metrics'],
                'tables' => $input['tables'] ?? null,
            ],
        );

        return redirect()->route(
            'setup.test-instruments.edit',
            ['instrument' => $version->test_instrument_id] + $this->kitContextRouteParameters(
                $kitNavigationContext->resolve($actor, $request),
            ),
        )->with('status', $version->isFinalOnly()
            ? 'Final-only aptitude score definition saved as draft.'
            : 'Test instrument and conversion tables saved as draft.');
    }

    public function show(
        Request $request,
        int $instrument,
        TestInstrumentDetailQuery $query,
        TestInstrumentAccess $access,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        $props = $this->projectMaterialDeclarations(
            $query->show($actor, $instrument),
            $instrument,
            $actor,
            $access,
        );
        $activeVersion = $props['instrument']['draft_version']
            ?? $props['instrument']['current_approved_version']
            ?? ($props['instrument']['versions'][0] ?? null);
        $isFinalOnly = (bool) ($activeVersion['profile_metadata']['is_final_only'] ?? false);
        $package = $isFinalOnly ? null : $this->resolveNormPackage(
            $access->institutionId($actor),
            $instrument,
            is_array($activeVersion) ? ($activeVersion['form_code'] ?? null) : null,
        );
        $normTables = $package?->draftVersion?->tables
            ?? $package?->currentApprovedVersion?->tables
            ?? collect();

        return Inertia::render('Setup/TestInstruments/Show', [
            ...$props,
            'request_id' => (string) Str::uuid(),
            'constructs' => $this->constructs(),
            'tables' => $normTables
                ->mapWithKeys(fn (AssessmentNormTable $table): array => [
                    (string) $table->metric_code => $table->intervals_json,
                ])
                ->all(),
        ]);
    }

    public function edit(
        Request $request,
        int $instrument,
        TestInstrumentDetailQuery $query,
        TestInstrumentAccess $access,
        TestInstrumentAnswerSheetConnectionsQuery $answerSheetConnections,
        TestKitNavigationContext $kitNavigationContext,
    ): Response {
        /** @var User $actor */
        $actor = $request->user();
        $props = $query->edit($actor, $instrument);
        $props['answer_sheet_connections'] = $answerSheetConnections->handle($actor, $instrument, (int) $props['version']['id']);
        $isFinalOnly = (bool) ($props['version']['profile_metadata']['is_final_only'] ?? false);
        $package = $isFinalOnly ? null : $this->resolveNormPackage(
            $access->institutionId($actor),
            $instrument,
            $props['version']['profile']['form_code'] ?? null,
        );
        $normTables = $package?->draftVersion?->tables
            ?? $package?->currentApprovedVersion?->tables
            ?? collect();
        $props['tables'] = $normTables
            ->mapWithKeys(fn (AssessmentNormTable $table): array => [
                (string) $table->metric_code => $table->intervals_json,
            ])
            ->all();
        $props['version']['tables'] = $normTables
            ->map(fn (AssessmentNormTable $table): array => [
                'metric_code' => (string) $table->metric_code,
                'output_scale' => (string) $table->output_scale,
                'intervals' => $table->intervals_json,
            ])
            ->values()
            ->all();

        return Inertia::render('Setup/TestInstruments/Edit', [
            ...$props,
            'request_id' => (string) Str::uuid(),
            'constructs' => $this->constructs(),
            'kit_context' => $kitNavigationContext->resolve($actor, $request),
        ]);
    }

    public function update(
        SaveTestInstrumentDraftRequest $request,
        int $instrument,
        int $version,
        SaveTestInstrumentDraft $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        $input = $request->validated();
        /** @var User $actor */
        $actor = $request->user();
        $savedVersion = $action->handle(
            $actor,
            $instrument,
            $version,
            (int) $input['expected_revision'],
            $input['request_id'],
            [
                'profile' => $this->profilePayload($input['profile']),
                'subtests' => $input['subtests'],
                'metrics' => $input['metrics'],
                'tables' => $input['tables'] ?? null,
            ],
        );

        return redirect()->route(
            'setup.test-instruments.edit',
            ['instrument' => $savedVersion->test_instrument_id] + $this->kitContextRouteParameters(
                $kitNavigationContext->resolve($actor, $request),
            ),
        )->with('status', $savedVersion->isFinalOnly()
            ? 'Final-only aptitude score definition saved as draft.'
            : 'Test instrument and conversion tables saved as draft.');
    }

    public function createSuccessor(
        CreateSuccessorTestInstrumentDraftRequest $request,
        int $instrument,
        int $version,
        CreateSuccessorTestInstrumentDraft $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $draft = $action->handle(
            $actor,
            $instrument,
            $version,
            (int) $request->validated('expected_revision'),
            $request->validated('request_id'),
        );

        return redirect()->route(
            'setup.test-instruments.edit',
            ['instrument' => $draft->test_instrument_id] + $this->kitContextRouteParameters(
                $kitNavigationContext->resolve($actor, $request),
            ),
        )->with('status', 'Successor draft created. The approved version remains unchanged.');
    }

    public function approve(
        ApproveTestInstrumentVersionRequest $request,
        int $instrument,
        int $version,
        ApproveTestInstrumentVersion $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $approved = $action->handle(
            $actor,
            $instrument,
            $version,
            (int) $request->validated('expected_revision'),
            $request->validated('request_id'),
            $request->boolean('source_review_attestation'),
        );

        return redirect()->route(
            'setup.test-instruments.show',
            ['instrument' => $approved->test_instrument_id] + $this->kitContextRouteParameters(
                $kitNavigationContext->resolve($actor, $request),
            ),
        )->with('status', $approved->isFinalOnly()
            ? 'Final-only aptitude score definition approved; local norm conversion is not applicable.'
            : 'Test instrument version approved. Norm readiness remains separate.');
    }

    public function retire(
        RetireTestInstrumentVersionRequest $request,
        int $instrument,
        int $version,
        RetireTestInstrumentVersion $action,
        TestKitNavigationContext $kitNavigationContext,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $retired = $action->handle(
            $actor,
            $instrument,
            $version,
            (int) $request->validated('expected_revision'),
            $request->validated('request_id'),
            $request->validated('reason'),
        );

        return redirect()->route(
            'setup.test-instruments.show',
            ['instrument' => $retired->test_instrument_id] + $this->kitContextRouteParameters(
                $kitNavigationContext->resolve($actor, $request),
            ),
        )->with('status', 'Approved version retired. Existing bound history remains unchanged.');
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function profilePayload(array $profile): array
    {
        return [
            'code' => $profile['code'],
            'display_name' => $profile['display_name'],
            'publisher_name' => $profile['publisher_name'] ?? null,
            'form_code' => $profile['form_code'] ?? null,
            'edition' => $profile['edition'] ?? null,
            'language' => $profile['language'] ?? null,
            'source_reference' => $profile['source_reference'] ?? null,
            'permitted_use_reference' => $profile['permitted_use_reference'] ?? null,
            'material_requirement' => $profile['material_requirement'] ?? 'unresolved',
            'administration_procedure_reference' => $profile['administration_procedure_reference'] ?? null,
        ];
    }

    /**
     * @param  array{test_kit_id:int,preparation_id:?int,test_kit_version_id:?int,booklet_version_id:?int,back_url:string,code:string,read_only:bool}|null  $context
     * @return array<string, int>
     */
    private function kitContextRouteParameters(?array $context): array
    {
        if ($context === null) {
            return [];
        }

        $parameters = ['test_kit_id' => $context['test_kit_id']];
        if ($context['preparation_id'] !== null) {
            $parameters['preparation_id'] = $context['preparation_id'];
        }
        if ($context['test_kit_version_id'] !== null) {
            $parameters['test_kit_version_id'] = $context['test_kit_version_id'];
        }

        return $parameters;
    }

    private function projectMaterialDeclarations(
        array $props,
        int $instrumentId,
        User $actor,
        TestInstrumentAccess $access,
    ): array {
        $institutionId = $access->institutionId($actor);
        $versions = TestInstrumentVersion::query()
            ->where('test_instrument_id', $instrumentId)
            ->whereHas(
                'instrument',
                fn (Builder $query) => $query->where('institution_id', $institutionId),
            )
            ->get([
                'id',
                'material_requirement',
                'administration_procedure_reference',
            ])
            ->keyBy('id');

        $projectVersion = static function (?array $version) use ($versions): ?array {
            if ($version === null) {
                return null;
            }

            $declaration = $versions->get($version['id']);
            $profile = is_array($version['profile'] ?? null) ? $version['profile'] : [];
            $profile['material_requirement'] = $declaration?->material_requirement ?? 'unresolved';
            $profile['administration_procedure_reference'] = $declaration?->administration_procedure_reference;

            return [
                ...$version,
                'profile' => $profile,
                'material_requirement' => $declaration?->material_requirement ?? 'unresolved',
                'administration_procedure_reference' => $declaration?->administration_procedure_reference,
            ];
        };

        $props['instrument']['versions'] = array_map(
            $projectVersion,
            $props['instrument']['versions'],
        );
        $props['instrument']['draft_version'] = $projectVersion(
            $props['instrument']['draft_version'],
        );
        $props['instrument']['current_approved_version'] = $projectVersion(
            $props['instrument']['current_approved_version'],
        );

        return $props;
    }

    private function resolveNormPackage(
        int $institutionId,
        int $instrumentId,
        ?string $formCode = null,
    ): ?AssessmentNormPackage {
        $direct = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->where('code', 'INSTRUMENT_'.$instrumentId)
            ->with(['draftVersion.tables', 'currentApprovedVersion.tables'])
            ->first();
        if ($direct !== null) {
            return $direct;
        }

        $versionIds = TestInstrumentVersion::query()
            ->where('test_instrument_id', $instrumentId)
            ->pluck('id');
        if ($versionIds->isNotEmpty()) {
            $linkedNormVersionId = TestKitPreparation::query()
                ->whereIn('test_instrument_version_id', $versionIds)
                ->whereNotNull('norm_package_version_id')
                ->orderByDesc('id')
                ->value('norm_package_version_id');
            if ($linkedNormVersionId !== null) {
                $linked = AssessmentNormPackage::query()
                    ->where('institution_id', $institutionId)
                    ->whereHas('versions', static fn (Builder $q): Builder => $q->whereKey($linkedNormVersionId))
                    ->with(['draftVersion.tables', 'currentApprovedVersion.tables'])
                    ->first();
                if ($linked !== null) {
                    return $linked;
                }
            }
        }

        if ($formCode !== null && $formCode !== '') {
            return AssessmentNormPackage::query()
                ->where('institution_id', $institutionId)
                ->whereHas('versions', static fn (Builder $q): Builder => $q->where('form_code', $formCode))
                ->with(['draftVersion.tables', 'currentApprovedVersion.tables'])
                ->first();
        }

        return null;
    }

    /** @return list<array{id: int, code: string, name: string}> */
    private function constructs(): array
    {
        return AptitudeArea::query()
            ->active()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(static fn (AptitudeArea $area): array => [
                'id' => (int) $area->id,
                'code' => (string) $area->code,
                'name' => (string) $area->name,
            ])
            ->all();
    }
}
