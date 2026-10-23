<?php

namespace App\Http\Controllers\Setup;

use App\Ai\Discovery\ModelDiscoveryService;
use App\Ai\Gateway\AssistantToolCatalog;
use App\Ai\Grants\TaskGrantService;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Support\EndpointSecurityGuard;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Setup\ToggleAiCapabilityRequest;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Ai\XiamModelTariff;
use App\Models\Ai\XiamProposal;
use App\Models\Ai\XiamProviderConfiguration;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamUsageReservation;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\AiManager;
use Throwable;

class AiControlCenterController extends Controller
{
    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        EmergencyStop $stop,
        UsageBudgetService $budget,
        AssistantToolCatalog $toolCatalog,
    ): Response {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($authorizer->allowsAny($actor, [
            CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE,
            CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE,
        ]), 403);

        $isUmbrellaAdmin = $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE);
        $scope = $actor->actorScope();
        $actorOffice = $scope->office;
        $institutionId = (int) $scope->institutionId;
        $institution = Institution::query()->findOrFail($institutionId);
        $policy = XiamInstitutionPolicy::forInstitution($institutionId);
        if (! $policy->exists) {
            $policy->save();
        }

        $today = XiamUsageReservation::query()
            ->where('institution_id', $institution->id)
            ->whereDate('created_at', today());
        $asks = (int) (clone $today)->where('unit', 'asks')->whereIn('state', [
            XiamUsageReservation::STATE_RESERVED,
            XiamUsageReservation::STATE_SETTLED,
            XiamUsageReservation::STATE_UNKNOWN,
        ])->sum('amount');
        $extensions = (int) (clone $today)->where('unit', 'asks_extension')->where('state', XiamUsageReservation::STATE_SETTLED)->sum('amount');
        $settled = (clone $today)->where('unit', 'asks')->where('state', XiamUsageReservation::STATE_SETTLED);
        $tokens = (int) (clone $settled)->sum('reserved_tokens');
        $costMicro = (int) (clone $settled)->sum('reserved_cost_micro');
        $offices = ['guidance', 'registrar', 'program', 'admin', 'applicant'];
        $officeUsage = [];
        foreach ($offices as $office) {
            $officeQuery = (clone $today)->where('office', $office);
            $officeUsage[$office] = [
                'asks' => (int) (clone $officeQuery)->where('unit', 'asks')->whereIn('state', [
                    XiamUsageReservation::STATE_RESERVED,
                    XiamUsageReservation::STATE_SETTLED,
                    XiamUsageReservation::STATE_UNKNOWN,
                ])->sum('amount'),
                'extensions' => (int) (clone $officeQuery)->where('unit', 'asks_extension')->where('state', XiamUsageReservation::STATE_SETTLED)->sum('amount'),
                'tokens' => (int) (clone $officeQuery)->where('unit', 'asks')->where('state', XiamUsageReservation::STATE_SETTLED)->sum('reserved_tokens'),
                'cost_micro' => (int) (clone $officeQuery)->where('unit', 'asks')->where('state', XiamUsageReservation::STATE_SETTLED)->sum('reserved_cost_micro'),
            ];
        }

        $personalLimit = 200;
        $staffQuery = User::query()
            ->whereHas('staffProfile', fn ($query) => $query->where('institution_id', $institutionId)->where('status', 'active'))
            ->with('staffProfile');
        if (! $isUmbrellaAdmin) {
            $staffQuery->whereHas('staffProfile', fn ($query) => $query->where('office', $actorOffice));
        }
        $staffQuotas = $staffQuery->orderBy('name')->get()->map(function (User $staff) use ($institutionId, $personalLimit, $actor, $isUmbrellaAdmin, $actorOffice): array {
            $profile = $staff->staffProfile;
            $used = (int) XiamUsageReservation::query()
                ->where('institution_id', $institutionId)
                ->where('actor_type', 'staff')
                ->where('actor_id', (string) $staff->id)
                ->where('unit', 'asks')
                ->whereDate('created_at', today())
                ->whereIn('state', [XiamUsageReservation::STATE_RESERVED, XiamUsageReservation::STATE_SETTLED, XiamUsageReservation::STATE_UNKNOWN])
                ->sum('amount');
            $granted = (int) XiamUsageReservation::query()
                ->where('institution_id', $institutionId)
                ->where('actor_type', 'staff')
                ->where('actor_id', (string) $staff->id)
                ->where('unit', 'asks_extension')
                ->where('state', XiamUsageReservation::STATE_SETTLED)
                ->whereDate('created_at', today())
                ->sum('amount');

            return [
                'id' => (string) $staff->id,
                'name' => $staff->name,
                'email' => $staff->email,
                'office' => $profile?->office,
                'used_asks' => $used,
                'limit' => $personalLimit + $granted,
                'granted_extensions_today' => $granted,
                'can_extend' => $isUmbrellaAdmin || ($profile?->office === $actorOffice && $staff->id !== $actor->id),
            ];
        })->all();

        $proposalsQuery = XiamProposal::query()
            ->whereHas('conversation', fn ($query) => $query->where('institution_id', $institutionId));
        $interventionsQuery = XiamRunEvent::query()
            ->whereIn('type', ['assistant.refusal', 'run.failed', 'skill.rejected'])
            ->whereHas('run.conversation', fn ($query) => $query->where('institution_id', $institutionId));
        $officeStaffIds = $isUmbrellaAdmin ? [] : User::query()
            ->whereHas('staffProfile', fn ($query) => $query->where('institution_id', $institutionId)->where('office', $actorOffice))
            ->pluck('id');
        if (! $isUmbrellaAdmin) {
            $proposalsQuery->whereHas('conversation', fn ($query) => $query->where('actor_type', 'staff')->whereIn('actor_id', $officeStaffIds));
            $interventionsQuery->whereHas('run.conversation', fn ($query) => $query->where('actor_type', 'staff')->whereIn('actor_id', $officeStaffIds));
        }
        $proposals = $proposalsQuery->orderByDesc('created_at')->limit(10)->get()->map(fn (XiamProposal $proposal): array => [
            'id' => $proposal->id,
            'capability_id' => $proposal->capability_id,
            'status' => $proposal->status,
            'execution_id' => $proposal->execution_id,
            'created_at' => $proposal->created_at?->diffForHumans() ?? '—',
            'expires_at' => $proposal->expires_at?->toIso8601String() ?? '—',
        ])->all();
        $interventions = $interventionsQuery->orderByDesc('created_at')->limit(10)->get()->map(fn (XiamRunEvent $event): array => [
            'id' => $event->id,
            'type' => $event->type,
            'run_id' => $event->run_id,
            'reason' => (string) ($event->payload['reason'] ?? ($event->payload['error'] ?? 'refusal')),
            'created_at' => $event->created_at?->diffForHumans() ?? '—',
        ])->all();

        $providerConfiguration = XiamProviderConfiguration::query()->where('institution_id', $institutionId)->first();
        $providerConfig = $providerConfiguration ? [
            'active_driver' => $providerConfiguration->driver,
            'base_url' => $providerConfiguration->base_url,
            'key_mode' => $providerConfiguration->key_mode,
            'has_custom_key' => $providerConfiguration->hasCustomApiKey(),
            'active_model_id' => $providerConfiguration->active_model_id,
            'fallback_model_id' => $providerConfiguration->fallback_model_id,
            'timeout_seconds' => $providerConfiguration->timeout_seconds,
            'temperature' => $providerConfiguration->temperature,
            'is_on_premises' => $providerConfiguration->is_on_premises,
            'discovered_models' => $providerConfiguration->discovered_models ?? [],
        ] : [
            'active_driver' => (string) config('xiam.ai.provider', 'openai'),
            'base_url' => null,
            'key_mode' => 'env',
            'has_custom_key' => false,
            'active_model_id' => (string) config('xiam.ai.model', 'gpt-4o-mini'),
            'fallback_model_id' => null,
            'timeout_seconds' => (int) config('xiam.ai.timeout', 30),
            'temperature' => 0.2,
            'is_on_premises' => false,
            'discovered_models' => [],
        ];
        $modelTariffs = $isUmbrellaAdmin ? XiamModelTariff::query()->where('is_active', true)->orderBy('provider_driver')->orderBy('model_id')->get()->map(fn (XiamModelTariff $tariff): array => $tariff->only([
            'model_id', 'provider_driver', 'name', 'supports_tools', 'context_window', 'prompt_micro_per_token', 'completion_micro_per_token', 'cache_read_micro_per_token', 'cache_write_micro_per_token', 'reasoning_micro_per_token', 'pricing_source', 'is_active',
        ]))->all() : [];

        $switchboard = [];
        foreach ($toolCatalog->all() as $tool) {
            $id = $tool->id();
            $metadata = $tool->capability;
            $governanceScope = $metadata->governance_scope ?? $this->inferGovernanceScope($id);
            if (! $isUmbrellaAdmin && $governanceScope !== $actorOffice) {
                continue;
            }
            $lockedByAdmin = in_array($id, $policy->admin_locked_tools ?? [], true);
            $switchboard[] = [
                'id' => $id,
                'version' => $tool->version,
                'governance_scope' => $governanceScope,
                'display_group' => $metadata->display_group ?? $this->displayGroup($governanceScope),
                'display_title' => $metadata->display_title ?? $this->friendlyTitle($tool->key),
                'title' => $metadata->display_title ?? $this->friendlyTitle($tool->key),
                'description' => $tool->description,
                'kind' => $tool->kind->value,
                'requires_confirmation' => $tool->isProposal(),
                'enabled' => $policy->isToolEnabled($id),
                'locked_by_admin' => $lockedByAdmin,
                'can_toggle' => $isUmbrellaAdmin || ($governanceScope === $actorOffice && ! $lockedByAdmin),
            ];
        }

        $limit = $isUmbrellaAdmin
            ? (int) ($policy->daily_institution_ask_ceiling ?? $budget->limit())
            : (int) (($policy->office_ask_allocations ?? [])[$actorOffice] ?? $budget->limit());
        $visibleUsage = $isUmbrellaAdmin
            ? ['asks' => $asks, 'extensions' => $extensions, 'tokens' => $tokens, 'cost_micro' => $costMicro, 'breakdown' => $officeUsage]
            : [
                ...($officeUsage[$actorOffice] ?? ['asks' => 0, 'extensions' => 0, 'tokens' => 0, 'cost_micro' => 0]),
                'breakdown' => [$actorOffice => $officeUsage[$actorOffice] ?? ['asks' => 0, 'extensions' => 0, 'tokens' => 0, 'cost_micro' => 0]],
            ];
        $modelInfo = [
            'gateway' => (string) config('xiam.ai.gateway', 'fake'),
            'provider' => $providerConfig['active_driver'],
            'model' => $providerConfig['active_model_id'],
            'timeout' => $providerConfig['timeout_seconds'],
            'emergency_stop' => $stop->active($institutionId),
            'emergency_stop_reason' => $policy->emergency_stop_reason,
            'emergency_stopped_at' => $policy->emergency_stopped_at?->diffForHumans(),
            'unit' => (string) config('xiam.usage.unit', 'asks'),
        ];
        $unknownHolds = $isUmbrellaAdmin ? XiamUsageReservation::query()
            ->where('institution_id', $institutionId)
            ->where('state', XiamUsageReservation::STATE_UNKNOWN)
            ->orderBy('created_at')
            ->get()
            ->map(fn (XiamUsageReservation $hold): array => [
                'id' => $hold->id,
                'run_id' => $hold->run_id,
                'actor_id' => $hold->actor_id,
                'office' => $hold->office,
                'amount' => $hold->amount,
                'reserved_tokens' => $hold->reserved_tokens,
                'reserved_cost_micro' => $hold->reserved_cost_micro,
                'created_at' => $hold->created_at?->toIso8601String(),
            ])->all() : [];

        return Inertia::render('Setup/AiCenter', [
            'is_umbrella_admin' => $isUmbrellaAdmin,
            'actor_office' => $actorOffice,
            'usage' => [
                'total_used' => $visibleUsage['asks'],
                'extensions' => $visibleUsage['extensions'],
                'limit' => $limit,
                'percentage' => $limit > 0 ? min(100, (int) round(($visibleUsage['asks'] / $limit) * 100)) : 0,
                'unit' => (string) config('xiam.usage.unit', 'asks'),
                'tokens' => $visibleUsage['tokens'],
                'cost_micro' => $visibleUsage['cost_micro'],
                'breakdown' => $visibleUsage['breakdown'],
            ],
            'model_info' => $modelInfo,
            'provider_config' => $isUmbrellaAdmin ? $providerConfig : null,
            'model_tariffs' => $modelTariffs,
            'policy' => $isUmbrellaAdmin
                ? $policy->only([
                    'applicant_inquiry_enabled', 'applicant_self_booking_enabled', 'daily_applicant_pool_asks', 'per_applicant_daily_ask_limit', 'daily_institution_ask_ceiling', 'office_ask_allocations', 'daily_institution_budget_micro', 'office_budget_allocations_micro', 'per_staff_daily_ask_limit',
                ])
                : $policy->only(['applicant_inquiry_enabled']),
            'staff_quotas' => $staffQuotas,
            'switchboard' => $switchboard,
            'policy_version' => $policy->policy_version,
            'proposals' => $proposals,
            'interventions' => $interventions,
            'unknown_holds' => $unknownHolds,
        ]);
    }

    public function toggleEmergencyStop(Request $request, CapabilityAuthorizer $authorizer, TaskGrantService $grants, RecordAuditEvent $recordAudit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:1000']]);
        $institutionId = (int) $actor->actorScope()->institutionId;
        $policy = XiamInstitutionPolicy::forInstitution($institutionId);
        if (! $policy->exists) {
            $policy->save();
        }
        $next = ! $policy->isEmergencyStopActive();
        $reason = trim((string) ($validated['reason'] ?? ''));
        $policy->update([
            'emergency_stop' => $next,
            'emergency_stopped_at' => $next ? now() : null,
            'emergency_stop_reason' => $next ? ($reason !== '' ? $reason : 'Tripped manually from AI Control Center') : null,
            'updated_by_user_id' => $actor->id,
            'policy_version' => $policy->policy_version + 1,
        ]);
        if ($next) {
            $grants->revokeAllActive('emergency_stop', $institutionId);
        }
        $recordAudit->handle($next ? 'ai.emergency_stop.tripped' : 'ai.emergency_stop.disarmed', [
            'institution_id' => $institutionId,
            'policy_version' => $policy->policy_version,
            'reason' => $policy->emergency_stop_reason,
        ], (string) $actor->id);

        return back()->with('success', $next ? 'Emergency Stop activated.' : 'Emergency Stop disarmed.');
    }

    public function toggleTool(ToggleAiCapabilityRequest $request, CapabilityAuthorizer $authorizer, AssistantToolCatalog $catalog, RecordAuditEvent $recordAudit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($authorizer->allowsAny($actor, [CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE, CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE]), 403);
        $isUmbrellaAdmin = $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE);
        $actorOffice = $actor->actorScope()->office;
        $institutionId = (int) $actor->actorScope()->institutionId;
        $validated = $request->validated();
        $toolId = $validated['tool_id'];
        $tool = $catalog->getById($toolId);
        abort_if($tool === null, 422, 'Unknown AI capability.');
        $toolScope = $tool->capability->governance_scope ?? $this->inferGovernanceScope($toolId);
        $policy = XiamInstitutionPolicy::forInstitution($institutionId);
        if (! $policy->exists) {
            $policy->save();
        }
        $lockedTools = $policy->admin_locked_tools ?? [];
        $locked = in_array($toolId, $lockedTools, true);
        if (! $isUmbrellaAdmin) {
            abort_unless($toolScope === $actorOffice && ! $locked, 403);
        }
        $disabled = $policy->disabled_tools ?? [];
        if ((bool) $validated['enabled']) {
            $disabled = array_values(array_diff($disabled, [$toolId]));
        } elseif (! in_array($toolId, $disabled, true)) {
            $disabled[] = $toolId;
        }
        if ($isUmbrellaAdmin && array_key_exists('lock_for_office', $validated)) {
            if ((bool) $validated['lock_for_office'] && ! $locked) {
                $lockedTools[] = $toolId;
            } elseif (! (bool) $validated['lock_for_office'] && $locked) {
                $lockedTools = array_values(array_diff($lockedTools, [$toolId]));
            }
        }
        $policy->update([
            'disabled_tools' => $disabled,
            'admin_locked_tools' => array_values($lockedTools),
            'policy_version' => $policy->policy_version + 1,
            'updated_by_user_id' => $actor->id,
        ]);
        $recordAudit->handle((bool) $validated['enabled'] ? 'ai.tool.enabled' : 'ai.tool.disabled', [
            'institution_id' => $institutionId,
            'tool_id' => $toolId,
            'enabled' => (bool) $validated['enabled'],
            'locked_by_admin' => in_array($toolId, $lockedTools, true),
            'policy_version' => $policy->policy_version,
        ], (string) $actor->id);

        return back()->with('success', 'AI capability updated.');
    }

    public function updateApplicantPolicy(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate([
            'applicant_inquiry_enabled' => ['required', 'boolean'],
            'applicant_self_booking_enabled' => ['required', 'boolean'],
            'daily_applicant_pool_asks' => ['required', 'integer', 'min:0'],
            'per_applicant_daily_ask_limit' => ['required', 'integer', 'min:0'],
        ]);
        $policy = XiamInstitutionPolicy::forInstitution((int) $actor->actorScope()->institutionId);
        if (! $policy->exists) {
            $policy->save();
        }
        $policy->update([...$validated, 'policy_version' => $policy->policy_version + 1, 'updated_by_user_id' => $actor->id]);
        $audit->handle('ai.applicant_policy.updated', ['institution_id' => $policy->institution_id, ...$validated, 'policy_version' => $policy->policy_version], (string) $actor->id);

        return back()->with('success', 'Applicant AI policy updated.');
    }

    public function updateBudget(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate([
            'daily_institution_ask_ceiling' => ['required', 'integer', 'min:0'],
            'office_ask_allocations' => ['required', 'array'],
            'office_ask_allocations.*' => ['integer', 'min:0'],
            'daily_institution_budget_micro' => ['required', 'integer', 'min:0'],
            'office_budget_allocations_micro' => ['required', 'array'],
            'office_budget_allocations_micro.*' => ['integer', 'min:0'],
            'per_staff_daily_ask_limit' => ['required', 'integer', 'min:0'],
        ]);
        $policy = XiamInstitutionPolicy::forInstitution((int) $actor->actorScope()->institutionId);
        if (! $policy->exists) {
            $policy->save();
        }
        $policy->update([...$validated, 'policy_version' => $policy->policy_version + 1, 'updated_by_user_id' => $actor->id]);
        $audit->handle('ai.budget.updated', ['institution_id' => $policy->institution_id, ...$validated, 'policy_version' => $policy->policy_version], (string) $actor->id);

        return back()->with('success', 'AI budgets updated.');
    }

    public function grantExtension(Request $request, CapabilityAuthorizer $authorizer, UsageBudgetService $budget): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        abort_unless($authorizer->allowsAny($actor, [CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE, CapabilityCatalog::AI_GOVERNANCE_OFFICE_MANAGE]), 403);
        $validated = $request->validate([
            'target_user_id' => ['required', 'string', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1', 'max:500'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $budget->grantExtension($actor, (string) $validated['target_user_id'], (int) $validated['amount'], $validated['reason']);

        return back()->with('success', 'Daily ask extension granted.');
    }

    public function reconcileHold(Request $request, XiamUsageReservation $reservation, CapabilityAuthorizer $authorizer, UsageBudgetService $budget, RecordAuditEvent $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate(['action' => ['required', 'in:settle,release']]);
        $institutionId = (int) $actor->actorScope()->institutionId;
        abort_unless((int) $reservation->institution_id === $institutionId && $reservation->state === XiamUsageReservation::STATE_UNKNOWN, 404);
        $budget->reconcileUnknown((string) $reservation->run_id, $validated['action'] === 'settle', $reservation->provider_request_id);
        $audit->handle('ai.usage.hold_reconciled', ['institution_id' => $institutionId, 'reservation_id' => $reservation->id, 'action' => $validated['action']], (string) $actor->id);

        return back()->with('success', 'Unknown usage hold reconciled.');
    }

    public function discoverModels(Request $request, CapabilityAuthorizer $authorizer, ModelDiscoveryService $discovery): JsonResponse|RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:gemini,openai,anthropic,openrouter,deepseek,groq,ollama,openai-compatible'],
            'base_url' => ['nullable', 'string', 'max:2048'],
            'key_mode' => ['required', 'in:env,custom'],
            'custom_api_key' => ['nullable', 'string', 'max:8192'],
        ]);
        $institutionId = (int) $actor->actorScope()->institutionId;
        $saved = XiamProviderConfiguration::query()->where('institution_id', $institutionId)->first();
        $key = $validated['key_mode'] === 'custom'
            ? (string) ($validated['custom_api_key'] ?? ($saved?->driver === $validated['driver'] ? $saved->custom_api_key : ''))
            : (string) config("ai.providers.{$validated['driver']}.key", '');
        try {
            $models = $discovery->discover($validated['driver'], $validated['base_url'] ?? null, $key);
        } catch (Throwable $exception) {
            if (! $request->wantsJson()) {
                throw ValidationException::withMessages(['base_url' => $exception->getMessage()]);
            }

            return response()->json(['message' => $exception->getMessage()], 422);
        }
        if ($saved !== null && $saved->driver === $validated['driver']) {
            $saved->update(['discovered_models' => $models, 'discovered_at' => now()]);
        }

        return $request->wantsJson() ? response()->json(['models' => $models]) : back()->with('success', 'Available models discovered.');
    }

    public function updateProvider(Request $request, CapabilityAuthorizer $authorizer, EndpointSecurityGuard $security, AiManager $aiManager, RecordAuditEvent $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate([
            'driver' => ['required', 'string', 'in:gemini,openai,anthropic,openrouter,deepseek,groq,ollama,openai-compatible'],
            'base_url' => ['nullable', 'string', 'max:2048'],
            'key_mode' => ['required', 'in:env,custom'],
            'custom_api_key' => ['nullable', 'string', 'max:8192'],
            'active_model_id' => ['required', 'string', 'max:255'],
            'fallback_model_id' => ['nullable', 'string', 'max:255'],
            'timeout_seconds' => ['required', 'integer', 'min:1', 'max:300'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'is_on_premises' => ['required', 'boolean'],
            'discovered_models' => ['sometimes', 'array'],
            'discovered_models.*.id' => ['required_with:discovered_models', 'string', 'max:255'],
            'discovered_models.*.name' => ['sometimes', 'string', 'max:255'],
            'discovered_models.*.has_tariff' => ['sometimes', 'boolean'],
            'discovered_models.*.supports_tools' => ['sometimes', 'boolean'],
        ]);
        $isOnPremises = (bool) $validated['is_on_premises'];
        if (($validated['base_url'] ?? '') !== '') {
            try {
                $normalizedUrl = $security->normalizeVersionedBaseUrl($validated['driver'], $validated['base_url']);
                $security->assertAndResolveSafeEndpoint($normalizedUrl, $validated['driver']);
                $validated['base_url'] = $normalizedUrl;
            } catch (Throwable $exception) {
                throw ValidationException::withMessages(['base_url' => $exception->getMessage()]);
            }
        }
        if (! $isOnPremises) {
            foreach (array_filter([$validated['active_model_id'], $validated['fallback_model_id'] ?? null]) as $modelId) {
                if (str_ends_with($modelId, ':free')) {
                    XiamModelTariff::query()->firstOrCreate(
                        ['model_id' => $modelId, 'provider_driver' => $validated['driver']],
                        [
                            'name' => $modelId,
                            'supports_tools' => true,
                            'context_window' => 128000,
                            'prompt_micro_per_token' => 0,
                            'completion_micro_per_token' => 0,
                            'cache_read_micro_per_token' => 0,
                            'cache_write_micro_per_token' => 0,
                            'reasoning_micro_per_token' => 0,
                            'pricing_source' => 'free_tier',
                            'is_active' => true,
                        ]
                    );
                }

                $hasTariff = XiamModelTariff::query()->where('model_id', $modelId)->where('provider_driver', $validated['driver'])->where('is_active', true)->exists();
                if (! $hasTariff) {
                    $field = $modelId === $validated['active_model_id'] ? 'active_model_id' : 'fallback_model_id';
                    throw ValidationException::withMessages([$field => "Pricing tariff is required for [{$modelId}] before activation. Set a tariff below or check on-premises."]);
                }
            }
        }
        $institutionId = (int) $actor->actorScope()->institutionId;
        $configuration = XiamProviderConfiguration::query()->where('institution_id', $institutionId)->first();
        $nextVersion = (int) ($configuration?->config_version ?? 0) + 1;
        $attributes = [
            'driver' => $validated['driver'],
            'base_url' => $validated['base_url'] ?? null,
            'key_mode' => $validated['key_mode'],
            'active_model_id' => $validated['active_model_id'],
            'fallback_model_id' => $validated['fallback_model_id'] ?? null,
            'timeout_seconds' => (int) $validated['timeout_seconds'],
            'temperature' => (float) $validated['temperature'],
            'is_on_premises' => $isOnPremises,
            'config_version' => $nextVersion,
            'discovered_models' => $validated['discovered_models'] ?? ($configuration?->discovered_models ?? []),
        ];
        if (isset($validated['custom_api_key']) && $validated['custom_api_key'] !== '') {
            $attributes['custom_api_key'] = $validated['custom_api_key'];
        }
        XiamProviderConfiguration::query()->updateOrCreate(['institution_id' => $institutionId], $attributes);
        $aiManager->forgetInstance("inst_{$institutionId}_v".($nextVersion - 1).'_'.($configuration?->driver ?? $validated['driver']));
        $audit->handle('ai.provider.reconfigured', [
            'institution_id' => $institutionId,
            'driver' => $validated['driver'],
            'active_model_id' => $validated['active_model_id'],
            'fallback_model_id' => $validated['fallback_model_id'] ?? null,
            'config_version' => $nextVersion,
            'is_on_premises' => $isOnPremises,
        ], (string) $actor->id);

        return back()->with('success', 'Provider and model configuration saved.');
    }

    public function upsertTariff(Request $request, CapabilityAuthorizer $authorizer, RecordAuditEvent $audit): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User && $authorizer->allows($actor, CapabilityCatalog::AI_GOVERNANCE_INSTITUTION_MANAGE), 403);
        $validated = $request->validate([
            'model_id' => ['required', 'string', 'max:255'],
            'provider_driver' => ['required', 'string', 'in:gemini,openai,anthropic,openrouter,deepseek,groq,ollama,openai-compatible'],
            'name' => ['required', 'string', 'max:255'],
            'supports_tools' => ['required', 'boolean'],
            'context_window' => ['required', 'integer', 'min:1'],
            'prompt_micro_per_token' => ['required', 'numeric', 'min:0'],
            'completion_micro_per_token' => ['required', 'numeric', 'min:0'],
            'cache_read_micro_per_token' => ['required', 'numeric', 'min:0'],
            'cache_write_micro_per_token' => ['required', 'numeric', 'min:0'],
            'reasoning_micro_per_token' => ['required', 'numeric', 'min:0'],
            'pricing_source' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $tariff = XiamModelTariff::query()->updateOrCreate(
            ['model_id' => $validated['model_id'], 'provider_driver' => $validated['provider_driver']],
            [...$validated, 'is_active' => (bool) ($validated['is_active'] ?? true)],
        );
        $audit->handle('ai.model_tariff.upserted', ['tariff_id' => $tariff->id, 'model_id' => $tariff->model_id, 'provider_driver' => $tariff->provider_driver], (string) $actor->id);

        return back()->with('success', 'Model tariff saved.');
    }

    private function inferGovernanceScope(string $toolId): string
    {
        if (str_starts_with($toolId, 'exams.slot.') || str_starts_with($toolId, 'exams.slots.') || str_starts_with($toolId, 'admission.office_inquiries.')) {
            return 'applicant_central';
        }
        if (str_starts_with($toolId, 'setup.')) {
            return 'institution_setup';
        }
        if (str_starts_with($toolId, 'registrar.') || str_starts_with($toolId, 'admissions.attempt.')) {
            return 'registrar';
        }
        if (str_starts_with($toolId, 'exams.') || str_starts_with($toolId, 'consultation.') || str_starts_with($toolId, 'scheduling.') || str_starts_with($toolId, 'assessment.') || str_starts_with($toolId, 'result_sheet.') || str_starts_with($toolId, 'results.') || str_starts_with($toolId, 'release.')) {
            return 'guidance';
        }

        return 'shared_staff';
    }

    private function displayGroup(string $governanceScope): string
    {
        return match ($governanceScope) {
            'applicant_central' => 'Applicant Portal',
            'institution_setup' => 'Institutional Setup',
            'registrar' => 'Registrar Office',
            'guidance' => 'Guidance & Testing Office',
            default => 'Shared Staff & Knowledge',
        };
    }

    private function friendlyTitle(string $key): string
    {
        return str($key)->afterLast('.')->replace(['_', '-'], ' ')->title()->toString();
    }
}
