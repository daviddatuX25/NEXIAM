<?php

namespace App\Http\Controllers\Setup;

use App\Assessment\Actions\Omr\ArchiveBookletVersion;
use App\Assessment\Actions\Omr\CopyBookletVersionToDraft;
use App\Assessment\Actions\Omr\CreateBookletDraft;
use App\Assessment\Actions\Omr\DiscardBookletDraft;
use App\Assessment\Actions\Omr\FinalizeBookletVersion;
use App\Assessment\Actions\Omr\GenerateBookletSets;
use App\Assessment\Actions\Omr\RestoreBookletVersion;
use App\Assessment\Actions\Omr\SaveAuthoredBookletVersion;
use App\Assessment\Actions\Omr\SaveBookletSetKeys;
use App\Assessment\Actions\Omr\SetBookletSetsMode;
use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Omr\Queries\ExamMaterialsQuery;
use App\Assessment\Support\KitCompositionContext;
use App\Assessment\Support\TestKitMockSession;
use App\Assessment\Support\TestKitNavigationContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Setup\Concerns\HasSetupCapabilities;
use App\Http\Requests\Setup\FinalizeBookletRequest;
use App\Http\Requests\Setup\GenerateBookletSetsRequest;
use App\Http\Requests\Setup\SaveBookletItemsRequest;
use App\Http\Requests\Setup\SaveBookletSetKeysRequest;
use App\Http\Requests\Setup\SetBookletSetsModeRequest;
use App\Http\Requests\Setup\StoreBookletRequest;
use App\Http\Requests\Setup\UpdateBookletSetAvailabilityRequest;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Setup\Actions\UpdateInstitutionExamFeaturePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

use function array_filter;
use function array_values;
use function http_build_query;
use function sprintf;
use function str_contains;

/**
 * HTTP adapter for Setup & Exam Materials Operations.
 *
 * This controller presents the setup workspace and coordinates the authorized
 * interaction boundary for the exam-material lifecycle. Its responsibilities
 * include:
 *
 * - managing versioned question booklets and their draft/finalized lifecycle;
 * - pairing booklet versions with compatible answer-sheet templates;
 * - authoring and maintaining master answer keys for booklet sets; and
 * - handing OMR diagnostic test-bench workflows to their dedicated services.
 *
 * Read models are assembled by {@see ExamMaterialsQuery}; mutations are
 * delegated to focused OMR actions and validated request objects. Setup
 * capability checks are applied at this HTTP boundary, while booklet
 * authorization and domain actions retain the authority for sensitive
 * operations and state transitions. The controller returns Inertia
 * projections for staff-facing setup and diagnostic surfaces; it does not
 * treat client-provided material state as authoritative.
 *
 * Booklet versioning, answer-sheet pairing, key authoring, and diagnostic
 * handoffs together preserve a traceable path from canonical exam content to
 * materials that can be used and verified in later examination workflows.
 */
final class ExamMaterialsController extends Controller
{
    use HasSetupCapabilities;

    public function omrTest(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ExamMaterialsQuery $materials,
    ): Response {
        $this->requireAssessment($authorizer, $request);
        $projection = $materials->get();
        $kitContext = null;
        $kitVersionId = $request->integer('test_kit_version_id') ?: $request->integer('version');
        if ($kitVersionId > 0) {
            $kitVersion = TestKitVersion::query()
                ->with(['testKit', 'templateVersion.template', 'bookletVersion.booklet', 'bookletSet.items', 'testInstrumentVersion.instrument'])
                ->findOrFail($kitVersionId);
            abort_unless($authorizer->allows(
                $request->user(),
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                [
                    'office' => 'guidance',
                    'institution_id' => (int) $kitVersion->testKit->institution_id,
                ],
            ), 404);

            $composition = app(KitCompositionContext::class)->resolveVersion($kitVersion);
            $mockSession = app(TestKitMockSession::class);
            $mockGuide = null;
            $mockGuideBlocker = null;
            try {
                $prepared = $mockSession->prepare($kitVersion, 1, 'guided_mix');
                $mockGuide = $prepared['mock_guide'];
                foreach ($mockGuide['pages'] as &$page) {
                    $page['reference_url'] = ($page['available'] ?? false) === true
                        ? route('setup.exam-materials.omr-test.runs.reference-asset', [
                            'templateVersion' => (int) $kitVersion->template_version_id,
                            'pageNo' => (int) $page['page_no'],
                        ])
                        : null;
                }
                unset($page);
            } catch (\InvalidArgumentException|RuntimeException $exception) {
                $mockGuideBlocker = $exception->getMessage();
            }
            $resumableRun = OmrDiagnosticRun::query()
                ->where('test_kit_version_id', (int) $kitVersion->getKey())
                ->where('created_by_user_id', (int) $request->user()->getAuthIdentifier())
                ->where('diagnostic_purpose', 'mock_rehearsal')
                ->whereNotIn('status', OmrDiagnosticRun::TERMINAL_STATUSES)
                ->latest('id')
                ->first();

            $template = $composition['template'] ?? null;
            $booklet = $composition['booklet'] ?? null;
            $set = $composition['set'] ?? null;
            $profile = $composition['profile'] ?? null;
            $kitContext = [
                'kit_id' => (int) $kitVersion->test_kit_id,
                'version_id' => (int) $kitVersion->id,
                'revision' => (int) $kitVersion->revision,
                'version_number' => (int) $kitVersion->version_number,
                'code' => (string) $kitVersion->testKit->code,
                'name' => (string) $kitVersion->testKit->name,
                'template_version_id' => (int) $kitVersion->template_version_id,
                'answer_sheet' => [
                    'name' => (string) ($template?->template?->name ?? ''),
                    'version_id' => (int) $kitVersion->template_version_id,
                    'version_number' => (int) ($template?->version_number ?? 0),
                    'page_count' => (int) ($template?->page_count ?? 1),
                ],
                'booklet' => [
                    'name' => (string) ($booklet?->booklet?->name ?? ''),
                    'version_id' => (int) $kitVersion->booklet_version_id,
                    'version_number' => (int) ($booklet?->version_number ?? 0),
                    'set_code' => (string) $kitVersion->booklet_set_code,
                    'set_a_item_count' => (int) ($set?->items?->count() ?? 0),
                ],
                'test_setup' => [
                    'name' => (string) ($profile?->instrument?->name ?? ''),
                    'version_id' => (int) $kitVersion->test_instrument_version_id,
                    'version_number' => (int) ($profile?->version_number ?? 0),
                ],
                'qr_mode' => $template?->qr_mode?->value,
                'registration_mode' => $template?->registration_mode?->value,
                'mock_guide' => $mockGuide,
                'mock_guide_blocker' => $mockGuideBlocker,
                'resumable_mock_run' => $resumableRun === null ? null : [
                    'public_id' => (string) $resumableRun->public_id,
                    'revision' => (int) $resumableRun->revision,
                    'status' => (string) $resumableRun->status,
                    'mock_config' => [
                        'instance_count' => (int) ($resumableRun->mock_manifest_json['config']['instance_count'] ?? 1),
                        'pattern_mode' => (string) ($resumableRun->mock_manifest_json['config']['pattern_mode'] ?? 'guided_mix'),
                    ],
                    'summary' => $mockSession->summarize($resumableRun),
                ],
                'return_url' => route('setup.exam-materials.test-kits.show', [
                    'testKit' => (int) $kitVersion->test_kit_id,
                    'version' => (int) $kitVersion->id,
                ]),
            ];
        }

        return Inertia::render('Setup/OmrTest', [
            'page_state' => $projection['omr_test']['status'],
            'test_kit_context' => $kitContext,
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function booklets(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ExamMaterialsQuery $materials,
        BookletAuthorization $bookletAuthorization,
        TestKitNavigationContext $navigationContext,
    ): Response {
        $actor = $request->user();
        $canViewQuestionContent = $bookletAuthorization->canQuestionContent($actor);
        $kitContext = $navigationContext->resolve($actor, $request);
        abort_if($this->hasKitContextQuery($request) && $kitContext === null, 404);
        $projection = $materials->get(
            $actor,
            false,
            $kitContext['authoring_booklet_version_id'] ?? $kitContext['booklet_version_id'] ?? null,
            (bool) ($kitContext['read_only'] ?? false) && ($kitContext['authoring_booklet_version_id'] ?? null) === null,
            $kitContext !== null,
            $kitContext['is_synthetic'] ?? null,
            $kitContext['test_instrument_version_id'] ?? null,
        );
        $institution = Institution::current();

        return Inertia::render('Setup/BookletsIndex', [
            'answer_sheets' => $projection['answer_sheets'],
            'booklets' => collect($projection['booklets'])
                ->map(function (array $booklet) use ($kitContext): array {
                    $workspace = $booklet['workspace'];
                    $latestVersion = $booklet['versions'][0] ?? null;
                    $sets = collect($workspace['sets'] ?? [])
                        ->map(fn (array $set): array => [
                            'code' => $set['set_code'],
                            'is_complete' => $set['is_complete'],
                        ])
                        ->all();

                    return [
                        'id' => $booklet['id'],
                        'name' => $booklet['name'],
                        'url' => $this->withKitContext($booklet['url'], $kitContext),
                        'mode' => $workspace['mode'],
                        'status' => $workspace['is_finalized'] ? 'finalized' : 'draft',
                        'version_id' => $workspace['version_id'],
                        'version' => $workspace['version'],
                        'lifecycle_status' => $workspace['lifecycle_status'],
                        'can_discard' => $workspace['can_discard'],
                        'can_archive' => $workspace['can_archive'],
                        'can_restore' => $workspace['can_restore'],
                        'is_in_use' => $workspace['is_in_use'],
                        'destroy_url' => $this->withKitContext($workspace['destroy_url'], $kitContext),
                        'archive_url' => $this->withKitContext($workspace['archive_url'], $kitContext),
                        'restore_url' => $this->withKitContext($workspace['restore_url'], $kitContext),
                        'question_count' => (int) ($latestVersion['items_count'] ?? 0),
                        'required_count' => collect($workspace['sections'])->sum('required_count'),
                        'section_count' => count($workspace['sections']),
                        'sets' => $sets,
                        'next_action' => $workspace['next_action'],
                        'read_only' => $workspace['read_only'] ?? false,
                        'thumbnail_url' => null,
                    ];
                })
                ->all(),
            'enable_multi_set_booklets' => $institution->usesMultiSetBooklets(),
            'exam_feature_policy_version' => $institution->examFeaturePolicyVersion(),
            'can_view_question_content' => $canViewQuestionContent,
            'capabilities' => $this->capabilities($authorizer, $request),
            'selected_booklet_id' => $kitContext === null
                || (($kitContext['authoring_booklet_version_id'] ?? null) === null && $kitContext['booklet_version_id'] === null)
                ? null
                : ($projection['booklets'][0]['id'] ?? null),
            'kit_context' => $kitContext,
        ]);
    }

    public function show(
        Request $request,
        int $booklet,
        CapabilityAuthorizer $authorizer,
        ExamMaterialsQuery $materials,
        BookletAuthorization $bookletAuthorization,
        TestKitNavigationContext $navigationContext,
    ): Response {
        $actor = $request->user();
        $kitContext = $navigationContext->resolve($actor, $request);
        abort_if($this->hasKitContextQuery($request) && $kitContext === null, 404);
        $authoringBookletVersionId = $kitContext['authoring_booklet_version_id'] ?? null;
        $selectedBookletVersionId = $authoringBookletVersionId ?? $kitContext['booklet_version_id'] ?? null;
        $projection = $materials->get(
            $actor,
            $bookletAuthorization->canQuestionContent($actor),
            $selectedBookletVersionId,
            (bool) ($kitContext['read_only'] ?? false) && $authoringBookletVersionId === null,
            $kitContext !== null,
            $kitContext['is_synthetic'] ?? null,
            $kitContext['test_instrument_version_id'] ?? null,
        );
        $selectedBookletId = $selectedBookletVersionId !== null
            ? (int) ($projection['booklets'][0]['id'] ?? 0)
            : $booklet;

        abort_unless($selectedBookletId === $booklet, 404);

        return Inertia::render('Setup/Booklets', [
            ...$projection,
            'selected_booklet_id' => $selectedBookletId,
            'kit_context' => $kitContext,
            'enable_multi_set_booklets' => Institution::current()->usesMultiSetBooklets(),
            'capabilities' => $this->capabilities($authorizer, $request),
        ]);
    }

    public function storeBooklet(
        StoreBookletRequest $request,
        CreateBookletDraft $action,
        TestKitNavigationContext $navigationContext,
    ): RedirectResponse {
        $validated = $request->validated();
        $kitContext = $navigationContext->resolve($request->user(), $request);
        abort_if($this->hasKitContextQuery($request) && $kitContext === null, 404);

        try {
            $version = $action->handle(
                (string) $validated['name'],
                (string) $validated['authoring_mode'],
                $request->user(),
                null,
                $validated['code'] ?? null,
                isSynthetic: (bool) $validated['is_synthetic'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        $url = route('setup.exam-materials.booklets.show', $version->booklet_id, false);

        return redirect()
            ->to($this->withKitContext(
                $url,
                $kitContext,
                $kitContext !== null && $kitContext['preparation_id'] !== null && ! $kitContext['read_only']
                    ? (int) $version->id
                    : null,
            ) ?? $url)
            ->with('status', sprintf('Booklet %s draft created.', $version->booklet?->name ?? ''));
    }

    public function saveBooklet(
        SaveBookletItemsRequest $request,
        OmrQuestionBookletVersion $version,
        SaveAuthoredBookletVersion $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                $version,
                array_values($validated['items']),
                $request->user(),
                isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
                (bool) ($validated['complete'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        return back()->with('status', 'Booklet draft saved.');
    }

    public function generateSets(
        GenerateBookletSetsRequest $request,
        OmrQuestionBookletVersion $version,
        GenerateBookletSets $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                $version,
                $request->user(),
                isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
                (string) ($validated['seed'] ?? 'secure-booklet-v1'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        return back()->with('status', 'Sets A–D generated deterministically.');
    }

    public function finalizeBooklet(
        FinalizeBookletRequest $request,
        OmrQuestionBookletVersion $version,
        FinalizeBookletVersion $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                $version,
                $request->user(),
                isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        return back()->with('status', 'Booklet finalized and ready for session publication.');
    }

    public function copyBooklet(
        Request $request,
        OmrQuestionBookletVersion $version,
        CopyBookletVersionToDraft $action,
        TestKitNavigationContext $navigationContext,
    ): RedirectResponse {
        $kitContext = $navigationContext->resolve($request->user(), $request);
        abort_if($this->hasKitContextQuery($request) && $kitContext === null, 404);

        try {
            $draft = $action->handle($version, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        $url = route('setup.exam-materials.booklets.show', $draft->booklet_id, false);

        return redirect()
            ->to($this->withKitContext(
                $url,
                $kitContext,
                $kitContext !== null && $kitContext['preparation_id'] !== null && ! $kitContext['read_only']
                    ? (int) $draft->id
                    : null,
            ) ?? $url)
            ->with('status', 'New Booklet draft created from the finalized version.');
    }

    public function destroyDraft(
        Request $request,
        OmrQuestionBookletVersion $version,
        DiscardBookletDraft $action,
        TestKitNavigationContext $navigationContext,
    ): RedirectResponse {
        $result = $action->handle($version, $request->user());

        if ($result['parent_booklet_deleted'] || $result['surviving_version_id'] === null) {
            $url = route('setup.exam-materials.booklets.index', [], false);

            return redirect()
                ->to($this->withKitContext($url, $navigationContext->resolve($request->user(), $request)) ?? $url)
                ->with('status', 'Booklet draft discarded.');
        }

        $url = route('setup.exam-materials.booklets.show', $version->booklet_id, false);

        return redirect()
            ->to($this->withKitContext($url, $navigationContext->resolve($request->user(), $request)) ?? $url)
            ->with('status', 'Booklet draft discarded.');
    }

    public function archiveVersion(
        Request $request,
        OmrQuestionBookletVersion $version,
        ArchiveBookletVersion $action,
    ): RedirectResponse {
        $action->handle($version, $request->user());

        return back()->with('status', 'Booklet version archived.');
    }

    public function restoreVersion(
        Request $request,
        OmrQuestionBookletVersion $version,
        RestoreBookletVersion $action,
    ): RedirectResponse {
        $action->handle($version, $request->user());

        return back()->with('status', 'Booklet version restored.');
    }

    public function saveSetKeys(
        SaveBookletSetKeysRequest $request,
        OmrQuestionBookletVersion $version,
        string $setCode,
        SaveBookletSetKeys $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                $version,
                $setCode,
                array_values($validated['items']),
                $request->user(),
                isset($validated['lock_version']) ? (int) $validated['lock_version'] : null,
                (bool) ($validated['complete'] ?? false),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        return back()->with('status', "Set {$setCode} keys saved.");
    }

    public function setBookletSetsMode(
        SetBookletSetsModeRequest $request,
        OmrQuestionBookletVersion $bookletVersion,
        SetBookletSetsMode $action,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $action->handle(
                $bookletVersion,
                $request->user(),
                (bool) $validated['allow_multiple_sets'],
                (int) $validated['lock_version'],
                (bool) $validated['confirm_discard_alternates'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['booklet' => $exception->getMessage()]);
        }

        return back()->with('status', 'Booklet Set mode updated.');
    }

    public function updateSetAvailability(
        UpdateBookletSetAvailabilityRequest $request,
        UpdateInstitutionExamFeaturePolicy $policy,
    ): RedirectResponse {
        $validated = $request->validated();
        $institution = $policy->updateAlternateBookletSetsAvailability(
            $request->user(),
            (bool) $validated['enable_multi_set_booklets'],
            (int) $validated['expected_version'],
        );

        return back()->with('status', $institution->usesMultiSetBooklets()
            ? 'Alternate Booklet Sets are available for new drafts.'
            : 'New Booklet drafts use Set A only.');
    }

    /**
     * @param  array<string, mixed>|null  $context
     */
    private function withKitContext(
        ?string $url,
        ?array $context,
        ?int $authoringBookletVersionId = null,
    ): ?string {
        if ($url === null || $context === null) {
            return $url;
        }

        $query = array_filter([
            'test_kit_id' => $context['test_kit_id'] ?? null,
            'preparation_id' => $context['preparation_id'] ?? null,
            'test_kit_version_id' => $context['test_kit_version_id'] ?? null,
            'authoring_booklet_version_id' => $authoringBookletVersionId
                ?? $context['authoring_booklet_version_id']
                ?? null,
        ], static fn (mixed $value): bool => $value !== null);

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    private function hasKitContextQuery(Request $request): bool
    {
        return $request->query->has('test_kit_id')
            || $request->query->has('preparation_id')
            || $request->query->has('test_kit_version_id')
            || $request->query->has('authoring_booklet_version_id');
    }
}
