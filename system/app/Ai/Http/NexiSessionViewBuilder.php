<?php

namespace App\Ai\Http;

use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Policy\EffectiveNexiAvailabilityResolver;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Models\Ai\NexiConversation;
use App\Models\Ai\NexiProposal;
use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiRunEvent;
use App\Models\User;
use Illuminate\Http\Request;

final class NexiSessionViewBuilder
{
    public function __construct(
        private readonly SurfaceProfileResolver $surfaces,
        private readonly PageCapabilityPackRegistry $packs,
        private readonly EffectiveNexiAvailabilityResolver $availability,
        private readonly ContextEpochService $epochs,
        private readonly UsageBudgetService $budgets,
        private readonly EmergencyStop $emergencyStop,
    ) {}

    public function build(Request $request): ?NexiSessionView
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
            enabled: (bool) config('nexi.enabled', true),
            configMode: (string) config('nexi.mode', 'degraded'),
        );

        if ($this->emergencyStop->active()) {
            $availability = 'suspended';
        }

        $actor = $request->user();
        $actorType = $profile->key === \App\Ai\Surface\SurfaceProfileKey::Applicant ? 'applicant' : 'staff';
        $actorId = (string) $actor->getAuthIdentifier();
        $used = $this->budgets->usedAsks($actorType, $actorId);
        $limit = $this->budgets->limit();

        if ($availability !== 'hidden' && $availability !== 'suspended' && $used >= $limit && $limit > 0) {
            $availability = 'quota_exhausted';
        }

        $modes = $pack?->allowedModes ?? $profile->allowedModes;
        if ($modes === []) {
            $modes = ['inquiry'];
        }

        $activeMode = (string) $request->session()->get('nexi.active_mode', $pack?->defaultMode ?? 'inquiry');
        if (! in_array($activeMode, $modes, true)) {
            $activeMode = $modes[0];
        }

        // Skill packs: surface assistant_available when assistant is an allowed mode
        // and the pack lists a server-issued skill (staff exam sessions, applicant schedule).
        if (
            $availability === 'degraded'
            && in_array('assistant', $modes, true)
            && $pack !== null
            && $pack->skills !== []
        ) {
            $availability = 'assistant_available';
        }

        $disclosure = [
            'key' => (string) config('nexi.disclosure.key', 'nexi.foundation.disclosure'),
            'version' => (int) config('nexi.disclosure.version', 1),
            'text' => (string) config(
                'nexi.disclosure.text',
                'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.'
            ),
        ];

        $conversationPayload = [
            'id' => null,
            'run_id' => null,
            'cursor' => 0,
        ];
        $contextEpoch = null;

        if ($profile->mountable && $availability !== 'hidden') {
            $institutionId = $actor instanceof User ? $actor->actorScope()->institutionId : null;
            $open = NexiConversation::query()
                ->where('partition_key', $this->epochs->partitionKey($actorType, $actorId, $profile->key->value))
                ->where('status', 'open')
                ->latest('created_at')
                ->first();

            if ($open !== null) {
                $contextEpoch = $open->context_epoch;
                $latestRun = NexiRun::query()
                    ->where('conversation_id', $open->id)
                    ->latest('created_at')
                    ->first();
                $cursor = (int) NexiRunEvent::query()->where('conversation_id', $open->id)->max('seq');
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

        $usageState = match (true) {
            $availability === 'quota_exhausted' => 'exhausted',
            $limit > 0 && $used >= (int) floor($limit * 0.8) => 'near_limit',
            default => 'available',
        };

        $proposals = [];
        if ($conversationPayload['id'] !== null) {
            $proposals = NexiProposal::query()
                ->where('conversation_id', $conversationPayload['id'])
                ->where('status', 'proposed')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (NexiProposal $p): array => [
                    'id' => $p->id,
                    'status' => $p->status,
                    'capability_id' => $p->capability_id,
                    'expected_version' => $p->expected_version,
                    'summary' => $p->summary,
                    'expires_at' => $p->expires_at?->toIso8601String(),
                ])
                ->all();
        }

        return new NexiSessionView(
            surface: [
                'key' => $profile->key->value,
                'version' => $profile->version,
            ],
            contextEpoch: $contextEpoch,
            pagePack: $pack?->identity(),
            publicationVersion: null,
            availability: $availability,
            modes: array_values($modes),
            activeMode: $activeMode,
            disclosure: $disclosure,
            conversation: $conversationPayload,
            usage: [
                'state' => $usageState,
                'used' => $used,
                'limit' => $limit,
                'unit' => (string) config('nexi.usage.unit', 'asks'),
                'resets_at' => null,
            ],
            proposals: $proposals,
            manualFallbacks: $pack?->uiIntents ?? [],
        );
    }

    private function hiddenView(): NexiSessionView
    {
        $disclosure = [
            'key' => (string) config('nexi.disclosure.key', 'nexi.foundation.disclosure'),
            'version' => (int) config('nexi.disclosure.version', 1),
            'text' => (string) config(
                'nexi.disclosure.text',
                'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.'
            ),
        ];

        return new NexiSessionView(
            surface: null,
            contextEpoch: null,
            pagePack: null,
            publicationVersion: null,
            availability: 'hidden',
            modes: ['inquiry'],
            activeMode: 'inquiry',
            disclosure: $disclosure,
            conversation: ['id' => null, 'run_id' => null, 'cursor' => 0],
            usage: [
                'state' => 'unknown',
                'used' => null,
                'limit' => null,
                'unit' => 'asks',
                'resets_at' => null,
            ],
            proposals: [],
            manualFallbacks: [],
        );
    }
}
