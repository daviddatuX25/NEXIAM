<?php

namespace App\Http\Controllers\Knowledge;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Application\Capabilities\CapabilityInvocationRequest;
use App\Application\Capabilities\CapabilityKernel;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Staff knowledge admin — legacy list→create→show feel; GF-21 + AD-25 lifecycle authority.
 */
class KnowledgeSourceController extends Controller
{
    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        CapabilityKernel $capabilities,
    ): Response {
        if (! $this->canManage($authorizer, $request) && ! $this->canApprove($authorizer, $request)) {
            return Inertia::render('Knowledge/Index', [
                'page_state' => 'denied',
                'sources' => [],
                'filters' => [],
                'capabilities' => $this->caps($authorizer, $request),
            ]);
        }

        $filters = [
            'search' => $request->string('search')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
        ];

        $result = $capabilities->invoke(new CapabilityInvocationRequest(
            key: 'knowledge.sources.list',
            version: 1,
            input: array_filter([
                'search' => $filters['search'],
                'status' => $filters['status'],
            ], fn ($v) => $v !== null && $v !== ''),
            actor: $request->user(),
            channel: 'http_page',
            surfaceKey: 'staff',
            pagePackKey: 'staff.knowledge.sources@1',
            correlationId: $request->headers->get('X-Correlation-Id'),
        ));

        abort_unless($result->ok && is_array($result->data), 403, $result->reason ?? 'capability_denied');

        return Inertia::render('Knowledge/Index', [
            'page_state' => $result->data['page_state'] ?? 'ready',
            'sources' => $result->data['sources'] ?? [],
            'filters' => $filters,
            'capabilities' => $this->caps($authorizer, $request),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $this->requireManage($authorizer, $request);

        return Inertia::render('Knowledge/Create', [
            'page_state' => 'ready',
        ]);
    }

    public function store(
        Request $request,
        CapabilityAuthorizer $authorizer,
        KnowledgeLifecycle $lifecycle,
    ): RedirectResponse {
        $this->requireManage($authorizer, $request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body_text' => ['required', 'string', 'min:20', 'max:50000'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $version = $lifecycle->createDraft($actor, $data);
        $lifecycle->startExtraction($version);
        $version->refresh();

        // Approvers (registrar admin / guidance counselor): one-step create → active.
        // Clerks/support (manage only) stay in review_required until an approver acts.
        if ($this->canApprove($authorizer, $request)
            && $version->status === KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED) {
            $lifecycle->approve($actor, $version);
            $lifecycle->publish($actor, $version->fresh());

            return redirect()
                ->route('knowledge.show', $version->knowledge_source_id)
                ->with('status', 'Created and published to active corpus.');
        }

        return redirect()
            ->route('knowledge.show', $version->knowledge_source_id)
            ->with('status', 'Draft created and extraction started. An approver must review before publish.');
    }

    public function show(
        Request $request,
        KnowledgeSource $source,
        CapabilityAuthorizer $authorizer,
    ): Response {
        if (! $this->canManage($authorizer, $request) && ! $this->canApprove($authorizer, $request)) {
            abort(403);
        }

        $source->load([
            'versions' => fn ($q) => $q->orderByDesc('version')->with('fixtures'),
        ]);
        /** @var KnowledgeSourceVersion|null $latest */
        $latest = $source->versions->first();

        return Inertia::render('Knowledge/Show', [
            'page_state' => 'ready',
            'source' => [
                'id' => $source->id,
                'key' => $source->key,
                'title' => $source->title,
                'classification' => $source->classification,
                'audience' => $source->audience,
                'purpose' => $source->purpose,
            ],
            'version' => $latest === null ? null : $this->versionPayload($latest),
            'capabilities' => $this->caps($authorizer, $request),
        ]);
    }

    public function extract(
        Request $request,
        KnowledgeSource $source,
        CapabilityAuthorizer $authorizer,
        KnowledgeLifecycle $lifecycle,
    ): RedirectResponse {
        $this->requireManage($authorizer, $request);
        $version = $this->latestVersion($source);
        try {
            $lifecycle->startExtraction($version);
        } catch (RuntimeException $e) {
            return back()->with('status', 'Cannot extract: '.$e->getMessage());
        }

        return back()->with('status', 'Extraction started.');
    }

    public function approve(
        Request $request,
        KnowledgeSource $source,
        CapabilityAuthorizer $authorizer,
        KnowledgeLifecycle $lifecycle,
    ): RedirectResponse {
        $this->requireApprove($authorizer, $request);
        $version = $this->latestVersion($source);
        try {
            /** @var User $actor */
            $actor = $request->user();
            $lifecycle->approve($actor, $version);
        } catch (RuntimeException $e) {
            return back()->with('status', 'Approve denied: '.$e->getMessage());
        }

        return back()->with('status', 'Version approved (digest sealed).');
    }

    public function publish(
        Request $request,
        KnowledgeSource $source,
        CapabilityAuthorizer $authorizer,
        CapabilityKernel $capabilities,
    ): RedirectResponse {
        $this->requireApprove($authorizer, $request);
        $version = $this->latestVersion($source);

        try {
            $result = $capabilities->invoke(new CapabilityInvocationRequest(
                key: 'knowledge.source.publish',
                version: 1,
                input: ['version_id' => $version->id],
                actor: $request->user(),
                channel: 'http_page',
                surfaceKey: 'staff',
                pagePackKey: 'staff.knowledge.sources@1',
            ));
            abort_unless($result->ok, 403, $result->reason ?? 'publish_denied');
        } catch (RuntimeException $e) {
            return back()->with('status', 'Publish denied: '.$e->getMessage());
        }

        return back()->with('status', 'Published to active corpus.');
    }

    public function revoke(
        Request $request,
        KnowledgeSource $source,
        CapabilityAuthorizer $authorizer,
        CapabilityKernel $capabilities,
    ): RedirectResponse {
        $this->requireApprove($authorizer, $request);
        $version = $this->latestVersion($source);
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $capabilities->invoke(new CapabilityInvocationRequest(
                key: 'knowledge.source.revoke',
                version: 1,
                input: [
                    'version_id' => $version->id,
                    'reason' => $data['reason'] ?? 'revoked',
                ],
                actor: $request->user(),
                channel: 'http_page',
                surfaceKey: 'staff',
                pagePackKey: 'staff.knowledge.sources@1',
            ));
            abort_unless($result->ok, 403, $result->reason ?? 'revoke_denied');
        } catch (RuntimeException $e) {
            return back()->with('status', 'Revoke denied: '.$e->getMessage());
        }

        return back()->with('status', 'Revoked — retrieval denied immediately.');
    }

    private function latestVersion(KnowledgeSource $source): KnowledgeSourceVersion
    {
        $version = $source->versions()->orderByDesc('version')->first();
        abort_unless($version instanceof KnowledgeSourceVersion, 404);

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function versionPayload(KnowledgeSourceVersion $version): array
    {
        return [
            'id' => $version->id,
            'version' => $version->version,
            'status' => $version->status,
            'title' => $version->title,
            'body_text' => $version->body_text,
            'extraction_text' => $version->extraction_text,
            'content_checksum' => $version->content_checksum,
            'extraction_digest' => $version->extraction_digest,
            'publication_digest' => $version->publication_digest,
            'created_by' => $version->created_by,
            'approved_by' => $version->approved_by,
            'approved_at' => optional($version->approved_at)?->toIso8601String(),
            'published_at' => optional($version->published_at)?->toIso8601String(),
            'revoked_at' => optional($version->revoked_at)?->toIso8601String(),
            'revoke_reason' => $version->revoke_reason,
            'failure_reason' => $version->failure_reason,
            'fixtures' => $version->fixtures->map(fn ($f) => [
                'id' => $f->id,
                'kind' => $f->kind,
                'query_text' => $f->query_text,
                'expected_excerpt' => $f->expected_excerpt,
                'expected_outcome' => $f->expected_outcome,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
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

    /**
     * @return array{manage: bool, approve: bool}
     */
    private function caps(CapabilityAuthorizer $authorizer, Request $request): array
    {
        return [
            'manage' => $this->canManage($authorizer, $request),
            'approve' => $this->canApprove($authorizer, $request),
        ];
    }

    private function canManage(CapabilityAuthorizer $authorizer, Request $request): bool
    {
        return $authorizer->allows($request->user(), CapabilityCatalog::KNOWLEDGE_SOURCES_MANAGE);
    }

    private function canApprove(CapabilityAuthorizer $authorizer, Request $request): bool
    {
        return $authorizer->allows($request->user(), CapabilityCatalog::KNOWLEDGE_SOURCES_APPROVE);
    }

    private function requireManage(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($this->canManage($authorizer, $request), 403);
    }

    private function requireApprove(CapabilityAuthorizer $authorizer, Request $request): void
    {
        abort_unless($this->canApprove($authorizer, $request), 403);
    }
}
