<?php

use App\Ai\Adapters\LaravelAi\LaravelAiGateway;
use App\Ai\Adapters\LaravelAi\XiamAgent;
use App\Ai\Applicant\ApplicantOwnDataProjector;
use App\Ai\Context\InvocationContext;
use App\Ai\Contracts\AiGateway;
use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\Actions\CreateKnowledgeDraft;
use App\Ai\Knowledge\EvidenceBundle;
use App\Ai\Knowledge\GroundingRouter;
use App\Ai\Knowledge\GroundingSourceDefinition;
use App\Ai\Knowledge\GroundingSourceRegistry;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\KnowledgeIndexEntry;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolCall;

beforeEach(function (): void {
    config(['xiam.process_inline' => true, 'xiam.ai.gateway' => 'fake']);
});

/** @return array<string, mixed> */
function registrarKnowledgeFilters(User $actor): array
{
    return [
        'actor_id' => (string) $actor->id,
        'actor_type' => 'staff',
        'institution_id' => (int) $actor->actorScope()->institutionId,
        'owning_office' => 'registrar',
        'audiences' => ['staff_only', 'staff_and_applicant_inquiry'],
        'classifications' => ['office_internal', 'public_institutional'],
        'purpose' => 'policy_inquiry',
        'source_classes' => ['institution_policy', 'public_faq'],
    ];
}

test('staff policy citations come from on-demand search and revoked policy has no support', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($registrar);
    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Quota ceiling policy',
        'body_text' => 'Program quota ceilings are enforced before release consultation for every applicant cohort.',
        'audience' => 'staff_and_applicant_inquiry',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($registrar, $version);
    $lifecycle->publish($registrar, $version->fresh());
    $version->refresh();

    $unpublished = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'Unpublished quota draft',
        'body_text' => 'This unpublished quota draft must never be cited.',
        'audience' => 'staff_and_applicant_inquiry',
    ]);

    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    app()->instance(AiGateway::class, new LaravelAiGateway);
    XiamAgent::fake([
        new ToolCall('call-policy-search-1', 'knowledge_search_1', ['query' => 'Quota ceiling policy']),
        'Published institutional policy says that quota ceilings are enforced before release consultation.',
    ]);

    $response = $this->actingAs($registrar)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Tell me about program quota ceilings before release',
            'idempotency_key' => 'policy-cite-1',
        ])
        ->assertAccepted();
    $run1 = XiamRun::query()->findOrFail($response->json('run_id'));
    $events = XiamRunEvent::query()->where('run_id', $run1->id)->get();
    $toolCall = $events->firstWhere('type', 'tool.call');
    $citations = $events->firstWhere('type', 'assistant.citations')?->payload['citations'] ?? [];
    $message = $events->firstWhere('type', 'assistant.message');

    expect($version->status)->toBe(KnowledgeSourceVersion::STATUS_ACTIVE)
        ->and($version->source->audience)->toBe('staff_and_applicant_inquiry')
        ->and($version->source->classification)->toBe('public_institutional')
        ->and($version->source->institution_id)->toBe($registrar->actorScope()->institutionId)
        ->and($unpublished->status)->toBe(KnowledgeSourceVersion::STATUS_DRAFT)
        ->and($toolCall?->payload['tool'] ?? null)->toBe('knowledge.search@1')
        ->and(collect($citations)->pluck('title')->all())->toBe(['Quota ceiling policy'])
        ->and(collect($citations)->pluck('source_class')->unique()->all())->toBe(['institution_policy'])
        ->and(collect($citations)->pluck('source_id')->all())->toBe([$version->knowledge_source_id])
        ->and($message?->payload['content'] ?? '')->toContain('quota ceilings')
        ->and($message?->payload['content'] ?? '')->not->toContain('Unpublished quota draft');

    $lifecycle->revoke($registrar, $version->fresh(), 'stale');
    expect($index->retrieve('quota ceilings', 5, registrarKnowledgeFilters($registrar)))->toBeEmpty();

    XiamAgent::fake([
        new ToolCall('call-policy-search-2', 'knowledge_search_1', ['query' => 'Quota ceiling policy']),
        'I cannot find current published evidence for that policy, so I cannot confirm its present status.',
    ]);
    $response2 = $this->actingAs($registrar)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'Tell me about program quota ceilings before release again',
            'idempotency_key' => 'policy-cite-2',
        ])
        ->assertAccepted();
    $run2 = XiamRun::query()->findOrFail($response2->json('run_id'));
    $events2 = XiamRunEvent::query()->where('run_id', $run2->id)->get();
    $citations2 = $events2->firstWhere('type', 'assistant.citations')?->payload['citations'] ?? [];
    $message2 = $events2->firstWhere('type', 'assistant.message');
    $toolResult2 = $events2->firstWhere('type', 'tool.result');

    expect($toolResult2?->payload['tool'] ?? null)->toBe('knowledge.search@1')
        ->and($citations2)->toBe([])
        ->and($message2?->payload['content'] ?? '')->toContain('cannot confirm')
        ->and($message2?->payload['content'] ?? '')->not->toContain('zero', 'none exist');
});

test('index includes title so program-name questions can match', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $lifecycle = app(KnowledgeLifecycle::class);
    $index = app(KnowledgeIndexPort::class);

    $this->actingAs($registrar);
    $version = app(CreateKnowledgeDraft::class)->handle($clerk, [
        'title' => 'BSMATH Admissions FAQ',
        'body_text' => 'Please ask the Registrar about the current published BSMATH admissions policy.',
    ]);
    $lifecycle->startExtraction($version);
    $version->refresh();
    $lifecycle->approve($registrar, $version);
    $lifecycle->publish($registrar, $version->fresh());

    expect($index->retrieve('BSMATH', 5, registrarKnowledgeFilters($registrar)))->not->toBeEmpty();
});

test('unsupported structured facts are not reported as zero or absence', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    config(['xiam.ai.gateway' => 'laravel']);
    app()->forgetInstance(AiGateway::class);
    XiamAgent::fake(static fn (): string => 'I do not have an authorized source for those institutional totals, so I cannot determine them.');

    $response = $this->actingAs($registrar)
        ->postJson(route('xiam.turns.store'), [
            'message' => 'How many admission quota application records and exam sessions exist?',
            'idempotency_key' => 'policy-no-support-1',
        ])
        ->assertAccepted();

    $run = XiamRun::query()->findOrFail($response->json('run_id'));
    $events = XiamRunEvent::query()->where('run_id', $run->id)->get();
    $message = $events->firstWhere('type', 'assistant.message');
    $citations = $events->firstWhere('type', 'assistant.citations')?->payload['citations'] ?? [];

    expect($events->firstWhere('type', 'tool.call'))->toBeNull()
        ->and($citations)->toBe([])
        ->and($message?->payload['content'] ?? '')->toContain('cannot determine')
        ->and($message?->payload['content'] ?? '')->not->toContain('0 application', 'no exam sessions');
});

test('local knowledge index rejects retired source class at every index boundary', function (): void {
    seedE2Foundation();

    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();
    $clerk = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();
    $institutionId = (int) $registrar->actorScope()->institutionId;

    $createActiveVersion = static function (
        string $sourceClass,
        string $title,
        string $bodyText,
    ) use ($registrar, $clerk, $institutionId): KnowledgeSourceVersion {
        $source = KnowledgeSource::query()->create([
            'id' => (string) Str::uuid(),
            'key' => 'index-boundary-'.Str::uuid(),
            'title' => $title,
            'source_class' => $sourceClass,
            'audience' => 'staff_only',
            'purpose' => 'policy_inquiry',
            'classification' => 'office_internal',
            'institution_id' => $institutionId,
            'owning_office' => 'registrar',
            'owner_user_id' => (string) $clerk->id,
        ]);

        return KnowledgeSourceVersion::query()->create([
            'id' => (string) Str::uuid(),
            'knowledge_source_id' => $source->id,
            'version' => 1,
            'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
            'title' => $title,
            'body_text' => $bodyText,
            'created_by' => (string) $clerk->id,
            'approved_by' => (string) $registrar->id,
            'approved_at' => now(),
            'published_at' => now(),
        ]);
    };

    $policyVersion = $createActiveVersion(
        'institution_policy',
        'Allowed policy',
        'Allowed policy remains discoverable through the local index.',
    );
    $candidateVersion = $createActiveVersion(
        'governed_document',
        'Candidate document',
        'Candidate document text must not become an index source.',
    );
    $index = app(KnowledgeIndexPort::class);
    $candidateDocument = [
        'version_id' => $candidateVersion->id,
        'source_id' => $candidateVersion->knowledge_source_id,
        'text' => 'Candidate document text must not become an index source.',
        'meta' => [],
    ];

    expect(fn () => $index->upsert($candidateDocument))
        ->toThrow(InvalidArgumentException::class, 'knowledge_source_class_not_indexable')
        ->and(KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $candidateVersion->id)
            ->exists())->toBeFalse();

    KnowledgeIndexEntry::query()->create([
        'id' => (string) Str::uuid(),
        'knowledge_source_version_id' => $candidateVersion->id,
        'knowledge_source_id' => $candidateVersion->knowledge_source_id,
        'status' => 'active',
        'search_text' => $candidateDocument['text'],
        'meta' => [],
    ]);

    expect($index->retrieve('Candidate document text', 5, [
        ...registrarKnowledgeFilters($registrar),
        'source_classes' => ['governed_document'],
    ]))->toBeEmpty();

    $rebuilt = $index->rebuildFromActive();
    expect($rebuilt)->toBeGreaterThan(0)
        ->and(KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $candidateVersion->id)
            ->exists())->toBeFalse()
        ->and(KnowledgeIndexEntry::query()
            ->where('knowledge_source_version_id', $policyVersion->id)
            ->exists())->toBeTrue()
        ->and($index->retrieve(
            'Allowed policy remains discoverable',
            5,
            registrarKnowledgeFilters($registrar),
        ))->not->toBeEmpty();
});

test('GroundingSourceRegistry defines all 8 fixed source classes and rejects unsupported classes', function (): void {
    $registry = new GroundingSourceRegistry;

    expect($registry->all())->toHaveCount(8);

    $unbacked = $registry->unbackedClasses();
    expect($unbacked)->toContain('institution_requirement')
        ->and($unbacked)->toContain('admission_model_fact')
        ->and($unbacked)->toContain('scoring_evaluation_fact')
        ->and($unbacked)->toContain('examination_fact')
        ->and($unbacked)->toContain('staff_operational_fact')
        ->and($unbacked)->toHaveCount(5);

    $indexed = $registry->activeIndexedClasses();
    expect($indexed)->toContain('public_faq')
        ->and($indexed)->toContain('institution_policy')
        ->and($indexed)->toHaveCount(2);

    $applicantRecord = $registry->get('actor_owned_record');
    expect($applicantRecord?->status)->toBe(GroundingSourceDefinition::STATUS_ACTIVE_PROJECTION);

    // Unsupported or retired source classes throw InvalidArgumentException
    expect(fn () => new GroundingSourceDefinition(
        sourceClass: 'governed_document',
        status: GroundingSourceDefinition::STATUS_ACTIVE_INDEXED,
        description: 'Unsupported PDF source',
    ))->toThrow(InvalidArgumentException::class, 'grounding_source_class_unsupported');

    expect(fn () => $registry->require('pdf_document'))
        ->toThrow(InvalidArgumentException::class, 'grounding_source_class_unsupported');
});

test('EvidenceBundle validates citation freshness and typed refusal reason', function (): void {
    // Valid bundle with freshness timestamp
    $bundle = new EvidenceBundle(
        decisionId: 'dec-1',
        actorId: 'user-1',
        contextEpoch: 'epoch-1',
        sourceClasses: ['public_faq'],
        citations: [
            [
                'citation_id' => 'cite-1',
                'source_class' => 'public_faq',
                'version_id' => 'v-1',
                'source_id' => 's-1',
                'title' => 'FAQ title',
                'excerpt' => 'FAQ excerpt',
                'score' => 0.95,
                'as_of' => now()->toIso8601String(),
            ],
        ],
        reason: null,
    );

    expect($bundle->hasSupport())->toBeTrue()
        ->and($bundle->citations[0]['as_of'])->not->toBeNull();

    // Far-future as_of timestamp rejected as invalid staleness
    expect(fn () => new EvidenceBundle(
        decisionId: 'dec-2',
        actorId: 'user-1',
        contextEpoch: 'epoch-1',
        sourceClasses: ['public_faq'],
        citations: [
            [
                'citation_id' => 'cite-2',
                'source_class' => 'public_faq',
                'version_id' => 'v-1',
                'source_id' => 's-1',
                'title' => 'FAQ title',
                'excerpt' => 'FAQ excerpt',
                'score' => 0.95,
                'as_of' => now()->addDays(5)->toIso8601String(),
            ],
        ],
    ))->toThrow(InvalidArgumentException::class, 'evidence_citation_staleness_invalid');

    // Unapproved refusal reason rejected
    expect(fn () => new EvidenceBundle(
        decisionId: 'dec-3',
        actorId: 'user-1',
        contextEpoch: 'epoch-1',
        sourceClasses: ['public_faq'],
        citations: [],
        reason: 'unauthorized_random_reason',
    ))->toThrow(InvalidArgumentException::class, 'evidence_refusal_reason_invalid');
});

test('GroundingRouter consults GroundingSourceRegistry and respects source statuses', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail();

    $customRegistry = new GroundingSourceRegistry([
        new GroundingSourceDefinition(
            sourceClass: EvidenceBundle::SOURCE_INSTITUTION_POLICY,
            status: GroundingSourceDefinition::STATUS_UNBACKED_STRUCTURED,
            description: 'Disabled for test',
        ),
    ]);

    $router = new GroundingRouter(
        index: app(KnowledgeIndexPort::class),
        ownData: app(ApplicantOwnDataProjector::class),
        registry: $customRegistry,
    );

    $context = new InvocationContext(
        actorId: (string) $registrar->id,
        actorType: 'staff',
        institutionId: (int) $registrar->actorScope()->institutionId,
        surfaceProfile: 'staff',
        mode: 'assistant',
        contextEpoch: 'router-test-epoch',
        purpose: 'policy_inquiry',
        correlationId: 'router-corr-1',
    );

    // Even if indexed documents exist in DB, router skips unbacked source classes
    $bundle = $router->resolve('quota ceilings', $context);
    expect($bundle->hasSupport())->toBeFalse()
        ->and($bundle->reason)->toBe('no_support');
});
