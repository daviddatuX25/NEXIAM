<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Actions\ApproveAssessmentNormVersion;
use App\Assessment\Actions\RetireAssessmentNormVersion;
use App\Assessment\Actions\SaveAssessmentNormDraft;
use App\Assessment\Support\AssessmentNormAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ApproveAssessmentNormVersionRequest;
use App\Http\Requests\Setup\RetireAssessmentNormVersionRequest;
use App\Http\Requests\Setup\SaveAssessmentNormDraftRequest;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class NormPackageController extends Controller
{
    public function __construct(
        private readonly AssessmentNormAccess $access,
    ) {}

    public function index(?Request $request = null): Response|JsonResponse
    {
        $request ??= request();
        $institutionId = $this->authorizeGuidanceCatalog($request);

        $packages = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->with([
                'draftVersion.tables',
                'currentApprovedVersion.tables',
                'latestVersion.tables',
                'versions.tables',
            ])
            ->orderBy('code')
            ->get();

        $mappedPackages = $packages->map(function (AssessmentNormPackage $package): array {
            $draft = $package->draftVersion;
            $approved = $package->currentApprovedVersion;
            $latest = $package->latestVersion;
            $current = $draft ?? $approved ?? $latest;

            $status = (string) ($current?->status ?? AssessmentNormPackageVersion::STATUS_RETIRED);
            $isApproved = $status === AssessmentNormPackageVersion::STATUS_APPROVED;

            $formCode = $current?->form_code;
            $version = $current?->version_number;

            $readiness = $isApproved
                ? 'ready'
                : ($current?->source_reviewed_at === null ? 'unverified' : 'draft');

            return [
                'id' => (int) $package->id,
                'code' => (string) $package->code,
                'is_synthetic' => (bool) $package->is_synthetic,
                'current_approved_version_id' => $package->current_approved_version_id ? (int) $package->current_approved_version_id : null,
                'display_name' => $current?->display_name ?? (string) $package->code,
                'publisher_name' => $current?->publisher_name,
                'status' => $status,
                'form_code' => $formCode,
                'version' => $version,
                'version_number' => $version,
                'readiness' => $readiness,
                'source_readiness' => $current?->source_reviewed_at === null ? 'unverified' : 'reviewed',
                'conversion_readiness' => $isApproved ? 'ready' : 'not_ready',
                'table_count' => $current?->tables->count() ?? 0,
                'versions_count' => $package->versions->count(),
                'created_at' => $package->created_at?->toIso8601String(),
                'updated_at' => $package->updated_at?->toIso8601String(),
            ];
        })->values()->all();

        $payload = [
            'page_state' => $packages->isEmpty() ? 'empty' : 'ready',
            'norm_packages' => $mappedPackages,
            'packages' => $mappedPackages,
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Setup/NormPackages/Index', $payload);
    }

    public function create(Request $request): Response
    {
        $this->authorizeGuidanceCatalog($request);

        return Inertia::render('Setup/NormPackages/Create', [
            'page_state' => 'ready',
            'request_id' => (string) Str::uuid(),
        ]);
    }

    public function store(SaveAssessmentNormDraftRequest $request, SaveAssessmentNormDraft $action): RedirectResponse
    {
        $this->authorizeGuidanceCatalog($request);
        $input = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $version = $action->handle(
            $actor,
            null,
            null,
            null,
            $input['request_id'],
            $input,
        );

        return redirect()
            ->route('setup.norm-packages.show', ['normPackage' => $version->norm_package_id])
            ->with('status', 'Norm package draft saved. It is not available for conversion until approved.');
    }

    public function edit(Request $request, int $normPackage): Response
    {
        $institutionId = $this->authorizeGuidanceCatalog($request);
        $package = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->whereKey($normPackage)
            ->with([
                'draftVersion.tables',
                'currentApprovedVersion.tables',
                'latestVersion.tables',
            ])
            ->firstOrFail();

        $version = $package->draftVersion
            ?? $package->currentApprovedVersion
            ?? $package->latestVersion;
        abort_if($version === null, 404);

        return Inertia::render('Setup/NormPackages/Edit', [
            'page_state' => 'ready',
            'package' => [
                'id' => (int) $package->id,
                'code' => (string) $package->code,
                'is_synthetic' => (bool) $package->is_synthetic,
            ],
            'version' => [
                'id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'status' => (string) $version->status,
                'display_name' => (string) $version->display_name,
                'publisher_name' => $version->publisher_name,
                'form_code' => $version->form_code,
                'edition' => $version->edition,
                'language' => $version->language,
                'applicable_population' => $version->applicable_population,
                'source_reference' => $version->source_reference,
                'permitted_use_reference' => $version->permitted_use_reference,
                'lock_version' => (int) $version->lock_version,
                'tables' => $version->tables->map(fn (AssessmentNormTable $table): array => [
                    'metric_code' => (string) $table->metric_code,
                    'output_scale' => (string) $table->output_scale,
                    'intervals' => $table->intervals_json,
                ])->values()->all(),
            ],
            'is_successor' => $version->status !== AssessmentNormPackageVersion::STATUS_DRAFT,
            'request_id' => (string) Str::uuid(),
        ]);
    }

    public function update(
        SaveAssessmentNormDraftRequest $request,
        int $normPackage,
        SaveAssessmentNormDraft $action,
    ): RedirectResponse {
        $institutionId = $this->authorizeGuidanceCatalog($request);
        $package = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->whereKey($normPackage)
            ->with('draftVersion')
            ->firstOrFail();
        $draft = $package->draftVersion;
        $input = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $version = $action->handle(
            $actor,
            $package,
            $draft,
            isset($input['expected_revision']) ? (int) $input['expected_revision'] : null,
            $input['request_id'],
            $input,
        );

        return redirect()
            ->route('setup.norm-packages.show', ['normPackage' => $version->norm_package_id])
            ->with('status', $draft === null
                ? 'Successor norm package draft created. Existing versions remain unchanged.'
                : 'Norm package draft saved.');
    }

    public function approve(
        ApproveAssessmentNormVersionRequest $request,
        int $normPackage,
        int $version,
        ApproveAssessmentNormVersion $action,
    ): RedirectResponse {
        $this->authorizeGuidanceCatalog($request);
        $input = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $approved = $action->handle(
            $actor,
            $normPackage,
            $version,
            (int) $input['expected_revision'],
            $input['request_id'],
            $request->boolean('source_review_attestation'),
        );

        return redirect()
            ->route('setup.norm-packages.show', ['normPackage' => $approved->norm_package_id])
            ->with('status', 'Norm package version approved. Existing accepted conversions remain unchanged.');
    }

    public function retire(
        RetireAssessmentNormVersionRequest $request,
        int $normPackage,
        int $version,
        RetireAssessmentNormVersion $action,
    ): RedirectResponse {
        $this->authorizeGuidanceCatalog($request);
        $input = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $retired = $action->handle(
            $actor,
            $normPackage,
            $version,
            (int) $input['expected_revision'],
            $input['request_id'],
            $input['reason'],
        );

        return redirect()
            ->route('setup.norm-packages.show', ['normPackage' => $retired->norm_package_id])
            ->with('status', 'Norm package version retired. Accepted conversion snapshots remain unchanged.');
    }

    public function show(int|string|Request $id, int|string|Request|null $normPackage = null): Response|JsonResponse
    {
        $request = $id instanceof Request
            ? $id
            : ($normPackage instanceof Request ? $normPackage : request());

        $packageId = is_numeric($id)
            ? (int) $id
            : (is_numeric($normPackage)
                ? (int) $normPackage
                : (int) ($request->route('normPackage') ?? $request->route('id') ?? 0));

        $institutionId = $this->authorizeGuidanceCatalog($request);

        $package = AssessmentNormPackage::query()
            ->where('institution_id', $institutionId)
            ->whereKey($packageId)
            ->with([
                'draftVersion.tables',
                'currentApprovedVersion.tables',
                'latestVersion.tables',
                'versions.tables',
            ])
            ->firstOrFail();

        $versions = $package->versions->map(fn (AssessmentNormPackageVersion $version): array => [
            'id' => (int) $version->id,
            'norm_package_id' => (int) $version->norm_package_id,
            'version_number' => (int) $version->version_number,
            'status' => (string) $version->status,
            'display_name' => (string) $version->display_name,
            'publisher_name' => $version->publisher_name,
            'form_code' => $version->form_code,
            'edition' => $version->edition,
            'language' => $version->language,
            'applicable_population' => $version->applicable_population,
            'source_reference' => $version->source_reference,
            'permitted_use_reference' => $version->permitted_use_reference,
            'source_reviewed_at' => $version->source_reviewed_at?->toIso8601String(),
            'approved_at' => $version->approved_at?->toIso8601String(),
            'retired_at' => $version->retired_at?->toIso8601String(),
            'lock_version' => (int) $version->lock_version,
            'content_sha256' => $version->content_sha256,
            'tables' => $version->tables->map(fn (AssessmentNormTable $table): array => [
                'id' => (int) $table->id,
                'norm_package_version_id' => (int) $table->norm_package_version_id,
                'metric_code' => (string) $table->metric_code,
                'output_scale' => (string) $table->output_scale,
                'intervals_json' => $table->intervals_json,
            ])->values()->all(),
        ])->values()->all();

        $current = $package->draftVersion ?? $package->currentApprovedVersion ?? $package->latestVersion;
        $currentTables = $current?->tables->map(fn (AssessmentNormTable $table): array => [
            'id' => (int) $table->id,
            'norm_package_version_id' => (int) $table->norm_package_version_id,
            'metric_code' => (string) $table->metric_code,
            'output_scale' => (string) $table->output_scale,
            'intervals_json' => $table->intervals_json,
        ])->values()->all() ?? [];
        $isApproved = $current?->status === AssessmentNormPackageVersion::STATUS_APPROVED;

        $packageDetail = [
            'id' => (int) $package->id,
            'code' => (string) $package->code,
            'is_synthetic' => (bool) $package->is_synthetic,
            'current_approved_version_id' => $package->current_approved_version_id ? (int) $package->current_approved_version_id : null,
            'display_name' => $current?->display_name ?? (string) $package->code,
            'publisher_name' => $current?->publisher_name,
            'form_code' => $current?->form_code,
            'edition' => $current?->edition,
            'language' => $current?->language,
            'applicable_population' => $current?->applicable_population,
            'source_reference' => $current?->source_reference,
            'permitted_use_reference' => $current?->permitted_use_reference,
            'version_id' => $current?->id ? (int) $current->id : null,
            'version' => $current?->version_number,
            'version_number' => $current?->version_number,
            'lock_version' => $current?->lock_version ? (int) $current->lock_version : null,
            'status' => $current?->status ?? AssessmentNormPackageVersion::STATUS_RETIRED,
            'source_reviewed_at' => $current?->source_reviewed_at?->toIso8601String(),
            'readiness' => $isApproved ? 'ready' : 'draft',
            'created_at' => $package->created_at?->toIso8601String(),
            'updated_at' => $package->updated_at?->toIso8601String(),
            'versions' => $versions,
            'tables' => $currentTables,
        ];

        $payload = [
            'page_state' => 'ready',
            'norm_package' => $packageDetail,
            'package' => $packageDetail,
            'versions' => $versions,
            'tables' => $currentTables,
            'request_id' => (string) Str::uuid(),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($payload);
        }

        return Inertia::render('Setup/NormPackages/Show', $payload);
    }

    private function authorizeGuidanceCatalog(Request $request): int
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403, 'Unauthenticated.');
        }
        try {
            return $this->access->institutionId($actor);
        } catch (\Throwable $e) {
            abort(403, 'Guidance assessment catalog capability authorization is required.');
        }
    }
}
