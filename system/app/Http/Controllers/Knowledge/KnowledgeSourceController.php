<?php

namespace App\Http\Controllers\Knowledge;

use App\Ai\Knowledge\Actions\ApproveKnowledgeSourceVersion;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\Actions\EstablishCrossOfficeKnowledgeScope;
use App\Ai\Knowledge\Actions\PublishKnowledgeSourceVersion;
use App\Ai\Knowledge\Actions\RecertifyFaqPolicyDependency;
use App\Ai\Knowledge\Actions\RevokeKnowledgeSourceVersion;
use App\Ai\Knowledge\Actions\SignKnowledgeSourceConsensus;
use App\Ai\Knowledge\Actions\StartKnowledgeExtraction;
use App\Ai\Knowledge\KnowledgeCatalogQuery;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Ai\Knowledge\KnowledgeSourceDetailQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\KnowledgeVersionRequest;
use App\Http\Requests\Knowledge\RevokeKnowledgeSourceRequest;
use App\Http\Requests\Knowledge\StoreKnowledgeSourceRequest;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Staff knowledge admin — legacy list→create→show flow with scoped Action writes. */
class KnowledgeSourceController extends Controller
{
    public function index(
        Request $request,
        KnowledgeCatalogQuery $catalog,
        KnowledgeSourceAccess $access,
    ): Response {
        $actor = $this->actor($request);
        $capabilities = $this->catalogCapabilities($access, $actor);
        if (! $access->canViewCatalog($actor)) {
            return Inertia::render('Knowledge/Index', [
                'page_state' => 'denied',
                'sources' => [],
                'filters' => [],
                'capabilities' => $capabilities,
                'statuses' => $this->statusOptions(),
                'audiences' => $this->creatableAudiences(),
                'source_types' => $this->sourceTypes(),
                'current_office' => $actor->actorScope()->office,
            ]);
        }

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];
        $projection = $catalog->handle($actor, $filters);

        return Inertia::render('Knowledge/Index', [
            'page_state' => $projection['page_state'],
            'sources' => $projection['sources'],
            'filters' => $filters,
            'capabilities' => $capabilities,
            'statuses' => $this->statusOptions(),
            'audiences' => $this->creatableAudiences(),
            'source_types' => $this->sourceTypes(),
            'current_office' => $actor->actorScope()->office,
        ]);
    }

    public function create(Request $request, KnowledgeSourceAccess $access): Response
    {
        $actor = $this->actor($request);
        $access->requireManageCatalog($actor);

        return Inertia::render('Knowledge/Create', [
            'page_state' => 'ready',
            'audiences' => $this->creatableAudiences(),
            'source_types' => $this->sourceTypes(),
            'current_office' => $actor->actorScope()->office,
            'capabilities' => $this->catalogCapabilities($access, $actor),
        ]);
    }

    public function store(
        StoreKnowledgeSourceRequest $request,
        CreateKnowledgeDraft $createDraft,
        StartKnowledgeExtraction $startExtraction,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $createDraft->handle($actor, $request->validated());
        $source = $version->source;
        abort_unless($source instanceof KnowledgeSource, 404);

        try {
            $startExtraction->handle($actor, $source, $version);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Draft saved; extraction could not start: '.$exception->getMessage());
        }

        $version->refresh();

        return redirect()
            ->route('knowledge.show', $version->knowledge_source_id)
            ->with('status', 'Draft created and extraction started. A separate approver must review before publish.');
    }

    public function show(
        Request $request,
        KnowledgeSource $source,
        KnowledgeSourceDetailQuery $detail,
    ): Response {
        return Inertia::render('Knowledge/Show', $detail->handle($this->actor($request), $source));
    }

    public function extract(
        KnowledgeVersionRequest $request,
        KnowledgeSource $source,
        StartKnowledgeExtraction $startExtraction,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));

        try {
            $startExtraction->handle($actor, $source, $version);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Cannot extract: '.$exception->getMessage());
        }

        return back()->with('status', 'Extraction started.');
    }

    public function establishCrossOffice(
        KnowledgeVersionRequest $request,
        KnowledgeSource $source,
        EstablishCrossOfficeKnowledgeScope $establishScope,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));

        try {
            $establishScope->handle($actor, $source, $version);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Cross-office scope denied: '.$exception->getMessage());
        }

        return back()->with('status', 'Cross-office scope established; awaiting independent Registrar and Guidance signatures.');
    }

    public function approve(
        KnowledgeVersionRequest $request,
        KnowledgeSource $source,
        ApproveKnowledgeSourceVersion $approveVersion,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));

        try {
            $complete = $approveVersion->handle($actor, $source, $version);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Approve denied: '.$exception->getMessage());
        }

        return back()->with('status', $complete
            ? 'Version approved (digest sealed).'
            : 'Office approval recorded; awaiting the other office head.');
    }

    public function publish(
        KnowledgeVersionRequest $request,
        KnowledgeSource $source,
        PublishKnowledgeSourceVersion $publishVersion,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));

        try {
            $publishVersion->handle($actor, $source, $version);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Publish denied: '.$exception->getMessage());
        }

        return back()->with('status', 'Published to active corpus. Applicant Xiam visibility remains subject to central inquiry and privacy gates.');
    }

    public function revoke(
        RevokeKnowledgeSourceRequest $request,
        KnowledgeSource $source,
        RevokeKnowledgeSourceVersion $revokeVersion,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));
        $data = $request->validated();

        try {
            $revokeVersion->handle($actor, $source, $version, $data['reason'] ?? 'revoked');
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Revoke denied: '.$exception->getMessage());
        }

        return back()->with('status', 'Revoked — retrieval denied immediately.');
    }

    public function recertify(
        KnowledgeVersionRequest $request,
        KnowledgeSource $source,
        RecertifyFaqPolicyDependency $recertifyAction,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));
        $dep = $version->policyDependencies()->first();
        if ($dep === null || $dep->policy === null) {
            return back()->with('status', 'No governing policy dependency to recertify.');
        }

        $policy = $dep->policy;
        $currentRevision = $policy->publishedRevision;
        if ($currentRevision === null) {
            return back()->with('status', 'Governing policy has no published revision.');
        }

        try {
            $recertifyAction->handle($actor, $source, $version, $currentRevision);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Recertification failed: '.$exception->getMessage());
        }

        return back()->with('status', 'Policy dependency recertified successfully.');
    }

    public function consensusSign(
        KnowledgeVersionRequest $request,
        KnowledgeSource $source,
        SignKnowledgeSourceConsensus $signAction,
    ): RedirectResponse {
        $actor = $this->actor($request);
        $version = $this->exactLatestVersion($source, $request->validated('version_id'));
        $offices = $request->input('participating_offices', [$actor->actorScope()->office]);

        try {
            $signAction->handle($actor, $source, $version, is_array($offices) ? $offices : [$offices]);
        } catch (RuntimeException $exception) {
            $this->rethrowAccessDenial($exception);

            return back()->with('status', 'Consensus signature failed: '.$exception->getMessage());
        }

        return back()->with('status', 'Consensus signature recorded successfully.');
    }

    private function exactLatestVersion(KnowledgeSource $source, string $versionId): KnowledgeSourceVersion
    {
        $version = $source->versions()->whereKey($versionId)->first();
        abort_unless($version instanceof KnowledgeSourceVersion, 404);

        $latestId = $source->versions()->orderByDesc('version')->value('id');
        abort_unless($latestId === $version->getKey(), 404);

        return $version;
    }

    /** @return list<array{value: string, label: string}> */
    private function statusOptions(): array
    {
        return [
            ['value' => '', 'label' => 'All statuses'],
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'review_required', 'label' => 'Review required'],
            ['value' => 'approved', 'label' => 'Approved'],
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'revoked', 'label' => 'Revoked'],
            ['value' => 'index_failed', 'label' => 'Index failed'],
            ['value' => 'rejected', 'label' => 'Rejected'],
        ];
    }

    /** @return list<string> */
    private function creatableAudiences(): array
    {
        return [
            KnowledgeSource::AUDIENCE_STAFF_ONLY,
            KnowledgeSource::AUDIENCE_STAFF_AND_APPLICANT_INQUIRY,
        ];
    }

    /** @return list<string> */
    private function sourceTypes(): array
    {
        return [
            KnowledgeSource::SOURCE_CLASS_POLICY,
            KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ,
        ];
    }

    /** @return array{manage: bool, approve: bool, cross_office_approve: bool} */
    private function catalogCapabilities(KnowledgeSourceAccess $access, User $actor): array
    {
        $office = $actor->actorScope()->office;

        return [
            'manage' => $access->canManageCatalog($actor),
            'approve' => $access->canApproveCatalog($actor),
            'cross_office_approve' => $office !== null && $access->isOfficeHead($actor, $office)
                && $access->canApproveCatalog($actor),
        ];
    }

    private function rethrowAccessDenial(RuntimeException $exception): void
    {
        if ($exception instanceof HttpExceptionInterface || $exception instanceof AuthorizationException) {
            throw $exception;
        }
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
