<?php

namespace App\Ai\Http;

use App\Ai\Adapters\LaravelAi\XiamProjectorRegistry;
use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Policy\EffectiveXiamAvailabilityResolver;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Policy\XiamModePreference;
use App\Ai\Surface\SurfaceProfileKey;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\User;
use Illuminate\Http\Request;
use RuntimeException;

final class XiamSessionViewBuilder
{
    public function __construct(
        private readonly SurfaceProfileResolver $surfaces,
        private readonly PageCapabilityPackRegistry $packs,
        private readonly EffectiveXiamAvailabilityResolver $availability,
        private readonly ContextEpochService $epochs,
        private readonly XiamModePreference $preferences,
        private readonly EmergencyStop $emergencyStop,
        private readonly UsageBudgetService $budgets,
        private readonly ?XiamProjectorRegistry $projector = null,
    ) {}

    public function build(Request $request): ?XiamSessionView
    {
        if ($request->user() === null) {
            return null;
        }

        $resolved = $this->surfaces->resolve($request);
        $profile = $resolved['profile'] ?? null;

        if ($profile === null) {
            return $this->hiddenView();
        }

        $pack = $this->packs->forRoute($request->route()?->getName());

        $availability = $this->availability->resolve(
            profile: $profile,
            pack: $pack,
            enabled: (bool) config('xiam.enabled', true),
            configMode: (string) config('xiam.mode', 'degraded'),
        );

        $actor = $request->user();
        $actorType = $profile->key === SurfaceProfileKey::Applicant ? 'applicant' : 'staff';
        $institutionId = null;
        if ($actor instanceof User) {
            $institutionId = $actorType === 'applicant'
                ? $actor->applicantAccount()->with('identity')->first()?->identity?->institution_id
                : $actor->actorScope()->institutionId;
        }

        if ($this->emergencyStop->active($institutionId === null ? null : (int) $institutionId)) {
            $availability = 'suspended';
        }

        if ($availability === 'hidden' || ! $profile->mountable) {
            return $this->hiddenView();
        }

        $actorId = (string) $actor->getAuthIdentifier();
        try {
            $preference = $this->preferences->view($request);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'mode_session_unavailable') {
                throw $exception;
            }

            $availability = 'suspended';
            $preference = [
                'active_mode' => 'inquiry',
                'interaction_modes' => [],
                'usage' => $this->budgets->view($actorType, $actorId),
            ];
        }
        $usage = $preference['usage'];

        if ($availability !== 'suspended' && $usage['state'] === 'exhausted') {
            $availability = 'quota_exhausted';
        }

        // A server-issued skill can change presentation availability only; it does not grant authority.
        if ($availability === 'degraded' && $pack !== null && $pack->skills !== []) {
            $availability = 'assistant_available';
        }

        $disclosure = [
            'key' => (string) config('xiam.disclosure.key', 'xiam.foundation.disclosure'),
            'version' => (int) config('xiam.disclosure.version', 1),
            'text' => (string) config(
                'xiam.disclosure.text',
                'Xiam is an assistant. It cannot grant access or change records without a confirmed typed command.'
            ),
        ];

        $conversationPayload = [
            'id' => null,
            'run_id' => null,
            'cursor' => 0,
        ];
        $contextEpoch = null;

        if ($profile->mountable && $availability !== 'hidden') {
            $open = XiamConversation::query()
                ->where('partition_key', $this->epochs->partitionKey($actorType, $actorId, $profile->key->value))
                ->where('status', 'open')
                ->latest('created_at')
                ->first();

            if ($open !== null) {
                $contextEpoch = $open->context_epoch;
                $latestRun = XiamRun::query()
                    ->where('conversation_id', $open->id)
                    ->latest('created_at')
                    ->first();
                $cursor = (int) XiamRunEvent::query()->where('conversation_id', $open->id)->max('seq');
                $conversationPayload = [
                    'id' => $open->id,
                    'run_id' => $latestRun?->id,
                    'cursor' => $cursor,
                ];
            } else {
                // Stable epoch preview without forcing a row until first turn.
                $contextEpoch = null;
            }
        }

        $proposals = [];
        if ($conversationPayload['id'] !== null) {
            $projector = $this->projector ?? app(XiamProjectorRegistry::class);
            $proposals = XiamProposal::query()
                ->where('conversation_id', $conversationPayload['id'])
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function (XiamProposal $p) use ($projector): array {
                    $rawSummary = is_array($p->summary) ? $p->summary : [];
                    $rawReview = is_array($p->proposal_view) ? $p->proposal_view : [];

                    $safeView = $projector->safeProposalView([
                        'id' => $p->id,
                        'proposal_id' => $p->id,
                        'run_id' => (string) ($p->run_id ?? ''),
                        'capability_id' => $p->capability_id,
                        'status' => $p->status,
                        'safety_tier' => $p->safety_tier,
                        'confirmation_phrase' => $p->confirmation_phrase,
                        'requires_reauth' => (bool) $p->requires_reauth,
                        'expires_at' => $p->expires_at?->toIso8601String(),
                        'proposal_view' => $rawReview,
                        'receipt' => is_array($p->receipt) ? $p->receipt : null,
                        'summary' => $rawSummary,
                    ]);

                    return [
                        'id' => $p->id,
                        'run_id' => $p->run_id,
                        'status' => $p->status,
                        'capability_id' => $p->capability_id,
                        'expected_version' => $p->expected_version,
                        'summary' => $this->stripInternalKeys($rawSummary),
                        'diff' => is_array($rawReview['diff'] ?? null) ? $this->stripInternalKeys($rawReview['diff']) : null,
                        'preflight_checks' => is_array($rawReview['preflight_checks'] ?? null) ? $this->stripInternalKeys($rawReview['preflight_checks']) : ($safeView['preflight']['checks'] ?? null),
                        'proposal_view' => $safeView,
                        'proposal_review' => $safeView['proposal_review'] ?? $safeView,
                        'expires_at' => $p->expires_at?->toIso8601String(),
                    ];
                })
                ->all();
        }

        return new XiamSessionView(
            surface: [
                'key' => $profile->key->value,
                'version' => $profile->version,
            ],
            contextEpoch: $contextEpoch,
            pagePack: $pack?->identity(),
            publicationVersion: null,
            availability: $availability,
            disclosure: $disclosure,
            conversation: $conversationPayload,
            usage: $usage,
            proposals: $proposals,
            manualFallbacks: $pack?->uiIntents ?? [],
            activeMode: $preference['active_mode'],
            interactionModes: $preference['interaction_modes'],
        );
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    private function stripInternalKeys(array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->stripInternalKeys($value) : $value;
        }

        return $clean;
    }

    private function hiddenView(): XiamSessionView
    {
        $disclosure = [
            'key' => (string) config('xiam.disclosure.key', 'xiam.foundation.disclosure'),
            'version' => (int) config('xiam.disclosure.version', 1),
            'text' => (string) config(
                'xiam.disclosure.text',
                'Xiam is an assistant. It cannot grant access or change records without a confirmed typed command.'
            ),
        ];

        return new XiamSessionView(
            surface: null,
            contextEpoch: null,
            pagePack: null,
            publicationVersion: null,
            availability: 'hidden',
            disclosure: $disclosure,
            conversation: ['id' => null, 'run_id' => null, 'cursor' => 0],
            usage: [
                'state' => 'unknown',
                'used' => null,
                'limit' => null,
                'remaining' => null,
                'credit_balance' => 0,
                'unit' => 'asks',
                'period' => (string) config('xiam.usage.period', 'today'),
                'resets_at' => null,
            ],
            proposals: [],
            manualFallbacks: [],
            activeMode: 'inquiry',
            interactionModes: [],
        );
    }
}
