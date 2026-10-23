<?php

namespace App\Ai\Policy;

use App\Ai\Context\InvocationContext;
use App\Ai\Conversation\ContextEpochService;
use App\Ai\Gateway\ToolPolicyGate;
use App\Ai\Surface\SurfaceProfileKey;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamTaskGrant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class XiamModePreference
{
    public function __construct(
        private readonly SurfaceProfileResolver $surfaces,
        private readonly ContextEpochService $epochs,
        private readonly ToolPolicyGate $policy,
        private readonly UsageBudgetService $budgets,
        private readonly EmergencyStop $emergencyStop,
    ) {}

    /**
     * @return array{
     *     active_mode: 'inquiry'|'assistant',
     *     interaction_modes: list<array{key: 'inquiry'|'assistant', label: string}>,
     *     context_epoch: string|null,
     *     usage: array<string, int|string|null>
     * }
     */
    public function view(Request $request): array
    {
        $identity = $this->identity($request);
        $conversation = $this->openConversation($identity);
        $modes = $this->interactionModes($request, $identity, $conversation);
        $storedMode = $this->storedMode($request, $identity);

        return [
            'active_mode' => $storedMode === 'assistant' && $this->containsMode($modes, 'assistant')
                ? 'assistant'
                : 'inquiry',
            'interaction_modes' => $modes,
            'context_epoch' => $conversation?->context_epoch === null ? null : (string) $conversation->context_epoch,
            'usage' => $this->budgets->view($identity['actor_type'], $identity['actor_id']),
        ];
    }

    /**
     * @return array{
     *     active_mode: 'inquiry'|'assistant',
     *     interaction_modes: list<array{key: 'inquiry'|'assistant', label: string}>,
     *     context_epoch: string|null,
     *     usage: array<string, int|string|null>
     * }
     */
    public function change(Request $request, string $mode, ?string $expectedEpoch, bool $hasExpectedEpoch): array
    {
        if (! in_array($mode, ['inquiry', 'assistant'], true)) {
            throw new RuntimeException('mode_unavailable');
        }

        $identity = $this->identity($request);
        $preferenceKey = $this->preferenceKey($request, $identity);
        $previousMode = $this->storedMode($request, $identity);

        $conversation = DB::transaction(function () use (
            $request,
            $identity,
            $mode,
            $expectedEpoch,
            $hasExpectedEpoch,
            $previousMode,
        ): ?XiamConversation {
            $candidate = $this->openConversation($identity);
            $conversation = null;
            $activeProposals = collect();

            if ($candidate !== null) {
                $activeProposals = XiamProposal::query()
                    ->where('conversation_id', $candidate->id)
                    ->whereIn('status', ['pending', 'executing'])
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id', 'status']);
                $conversation = XiamConversation::query()
                    ->whereKey($candidate->id)
                    ->where('status', 'open')
                    ->lockForUpdate()
                    ->first();
            }

            $currentEpoch = $conversation === null ? null : (string) $conversation->context_epoch;

            if ($hasExpectedEpoch && $expectedEpoch !== $currentEpoch) {
                throw new RuntimeException('context_epoch_conflict');
            }

            if ($conversation !== null && (
                XiamRun::query()
                    ->where('conversation_id', $conversation->id)
                    ->whereIn('status', [XiamRun::STATUS_QUEUED, XiamRun::STATUS_RUNNING])
                    ->exists()
                || $activeProposals->contains('status', 'executing')
            )) {
                throw new RuntimeException('mode_change_busy');
            }

            $modes = $this->interactionModes($request, $identity, $conversation);
            if (! $this->containsMode($modes, $mode)) {
                throw new RuntimeException('mode_unavailable');
            }

            $hasActiveGrant = $conversation !== null && XiamTaskGrant::query()
                ->where('actor_id', $identity['actor_id'])
                ->where('context_epoch', $currentEpoch)
                ->where('status', XiamTaskGrant::STATUS_ACTIVE)
                ->exists();

            if ($mode === 'inquiry'
                && $conversation !== null
                && ($previousMode === 'assistant' || $activeProposals->contains('status', 'pending') || $hasActiveGrant)) {
                $this->epochs->transition(
                    conversation: $conversation,
                    routeName: (string) ($conversation->route_name ?: 'xiam.mode'),
                    surfaceProfileId: $identity['surface'],
                    browserSessionId: $request->session()->getId(),
                    expectedEpoch: $currentEpoch,
                );
            }

            return $conversation?->fresh() ?? $conversation;
        });

        $modes = $this->interactionModes($request, $identity, $conversation);
        if (! $this->containsMode($modes, $mode)) {
            throw new RuntimeException('mode_unavailable');
        }

        try {
            $saved = Cache::put(
                $preferenceKey,
                $mode,
                now()->addMinutes(max(1, (int) config('session.lifetime', 120))),
            );
        } catch (Throwable) {
            throw new RuntimeException('mode_session_unavailable');
        }

        if (! $saved) {
            throw new RuntimeException('mode_session_unavailable');
        }

        return [
            'active_mode' => $mode,
            'interaction_modes' => $modes,
            'context_epoch' => $conversation?->context_epoch === null ? null : (string) $conversation->context_epoch,
            'usage' => $this->budgets->view($identity['actor_type'], $identity['actor_id']),
        ];

    }

    /**
     * Resolve the authenticated principal and allowed Xiam surface from server context.
     *
     * @return array{
     *     actor: User,
     *     actor_id: string,
     *     actor_type: 'applicant'|'staff',
     *     institution_id: int|null,
     *     surface: string,
     * }
     */
    private function identity(Request $request): array
    {
        $actor = $request->user();
        $profile = ($this->surfaces->resolve($request)['profile'] ?? null);

        if (! $actor instanceof User || $profile === null || ! $profile->mountable || ! $request->hasSession()) {
            throw new RuntimeException('surface_denied');
        }

        $actorType = match ($profile->key) {
            SurfaceProfileKey::Applicant => 'applicant',
            SurfaceProfileKey::Staff => 'staff',
            default => null,
        };

        if ($actorType === null) {
            throw new RuntimeException('surface_denied');
        }

        if (($actorType === 'applicant' && ! $actor->applicantAccount()->exists())
            || ($actorType === 'staff' && ! $actor->staffProfile()->exists())) {
            throw new RuntimeException('surface_denied');
        }

        $institutionId = $actorType === 'staff'
            ? $actor->actorScope()->institutionId
            : $actor->applicantAccount()->with('identity')->first()?->identity?->institution_id;

        return [
            'actor' => $actor,
            'actor_id' => (string) $actor->getAuthIdentifier(),
            'actor_type' => $actorType,
            'institution_id' => $institutionId === null ? null : (int) $institutionId,
            'surface' => $profile->key->value,
        ];
    }

    /**
     * @param  array{actor: User, actor_id: string, actor_type: 'applicant'|'staff', institution_id: int|null, surface: string}  $identity
     */
    private function openConversation(array $identity): ?XiamConversation
    {
        return XiamConversation::query()
            ->where('partition_key', $this->epochs->partitionKey(
                $identity['actor_type'],
                $identity['actor_id'],
                $identity['surface'],
            ))
            ->where('status', 'open')
            ->latest('created_at')
            ->first();
    }

    /**
     * @param  array{actor: User, actor_id: string, actor_type: 'applicant'|'staff', institution_id: int|null, surface: string}  $identity
     */
    private function interactionModes(Request $request, array $identity, ?XiamConversation $conversation): array
    {
        if (! (bool) config('xiam.enabled', true)
            || in_array((string) config('xiam.mode', 'degraded'), ['disabled', 'suspended'], true)
            || $this->emergencyStop->active($identity['institution_id'])) {
            return [];
        }

        $context = new InvocationContext(
            actorId: $identity['actor_id'],
            actorType: $identity['actor_type'],
            institutionId: $identity['institution_id'],
            surfaceProfile: $identity['surface'],
            mode: 'assistant',
            contextEpoch: (string) ($conversation?->context_epoch ?: 'xiam-mode-preference'),
            purpose: 'assistant_turn',
            correlationId: 'xiam-mode-preference',
            browserSessionId: $request->session()->getId(),
        );
        $envelope = $this->policy->resolveEnvelope($context, $identity['actor']);

        if (! in_array($envelope, [ToolPolicyGate::ENVELOPE_READ_ALLOWED, ToolPolicyGate::ENVELOPE_PROPOSE_ALLOWED], true)) {
            return [];
        }

        $modes = [
            ['key' => 'inquiry', 'label' => 'Inquiry'],
        ];

        if ($envelope === ToolPolicyGate::ENVELOPE_PROPOSE_ALLOWED) {
            $modes[] = ['key' => 'assistant', 'label' => 'Assistant'];
        }

        return $modes;
    }

    /**
     * @param  array{actor: User, actor_id: string, actor_type: 'applicant'|'staff', institution_id: int|null, surface: string}  $identity
     */
    private function storedMode(Request $request, array $identity): string
    {
        try {
            $mode = Cache::get($this->preferenceKey($request, $identity));
        } catch (Throwable) {
            throw new RuntimeException('mode_session_unavailable');
        }

        return in_array($mode, ['inquiry', 'assistant'], true) ? $mode : 'inquiry';
    }

    /**
     * @param  array{actor: User, actor_id: string, actor_type: 'applicant'|'staff', institution_id: int|null, surface: string}  $identity
     */
    private function preferenceKey(Request $request, array $identity): string
    {
        $binding = implode("\0", [
            $identity['actor_type'],
            $identity['actor_id'],
            $identity['surface'],
            $request->session()->getId(),
        ]);

        return 'xiam:mode_preference:'.hash('sha256', $binding);
    }

    /**
     * @param  list<array{key: 'inquiry'|'assistant', label: string}>  $modes
     */
    private function containsMode(array $modes, string $mode): bool
    {
        foreach ($modes as $availableMode) {
            if ($availableMode['key'] === $mode) {
                return true;
            }
        }

        return false;
    }
}
