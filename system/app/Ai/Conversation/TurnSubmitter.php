<?php

namespace App\Ai\Conversation;

use App\Ai\Grants\TaskGrantService;
use App\Ai\Orchestration\ProcessXiamRun;
use App\Ai\Policy\EmergencyStop;
use App\Ai\Policy\InputMinimizer;
use App\Ai\Policy\UsageBudgetService;
use App\Ai\Privacy\PiiTokenVault;
use App\Jobs\Ai\ProcessXiamRunJob;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamInstitutionPolicy;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use App\Models\Ai\XiamTurn;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TurnSubmitter
{
    public function __construct(
        private readonly ContextEpochService $epochs,
        private readonly UsageBudgetService $budgets,
        private readonly EmergencyStop $emergencyStop,
        private readonly TaskGrantService $grants,
        private readonly InputMinimizer $minimizer,
        private readonly PiiTokenVault $piiTokenVault,
    ) {}

    /**
     * @param  array{page_pack_key?: string|null, page_pack_version?: int|null, idempotency_key?: string|null, route_name?: string|null, browser_session_id?: string|null, surface_profile_id?: string|null, mode?: 'inquiry'|'assistant'}  $meta
     * @return array{conversation_id: string, turn_id: string, run_id: string, context_epoch: string, cursor: int}
     */
    public function submit(
        Authenticatable $actor,
        string $surfaceKey,
        string $inputText,
        array $meta = [],
    ): array {

        $text = trim($inputText);
        if ($text === '') {
            throw new RuntimeException('empty_input');
        }

        $this->minimizer->assertNoCredentials($text);
        $minimizedText = $this->minimizer->minimize($text);

        $actorId = (string) $actor->getAuthIdentifier();
        if (! $actor instanceof User) {
            throw new RuntimeException('surface_denied');
        }

        $isApplicantAccount = $actor->applicantAccount()->exists();
        $isStaffProfile = $actor->staffProfile()->exists();

        $actorType = match (true) {
            $surfaceKey === 'applicant' && $isApplicantAccount => 'applicant',
            $surfaceKey === 'staff' && $isStaffProfile => 'staff',
            $isApplicantAccount && ! $isStaffProfile => 'applicant',
            $isStaffProfile => 'staff',
            default => null,
        };
        if ($actorType === null) {
            throw new RuntimeException('surface_denied');
        }
        if ($surfaceKey === 'applicant' && $actorType !== 'applicant') {
            throw new RuntimeException('surface_denied');
        }
        if ($surfaceKey === 'staff' && $actorType !== 'staff') {
            throw new RuntimeException('surface_denied');
        }

        $institutionId = null;
        if ($actorType === 'staff') {
            $institutionId = $actor->actorScope()->institutionId;
        } else {
            $account = $actor->applicantAccount()->with('identity')->first();
            $institutionId = $account?->identity?->institution_id;
        }
        if ($this->emergencyStop->active($institutionId)) {
            throw new RuntimeException('emergency_stop');
        }

        if ($actorType === 'applicant' && $institutionId !== null && $institutionId > 0) {
            try {
                $policy = Schema::hasTable('xiam_institution_policies')
                    ? XiamInstitutionPolicy::query()->where('institution_id', $institutionId)->first()
                    : null;
            } catch (Throwable) {
                throw new RuntimeException('applicant_ai_disabled');
            }

            if ($policy?->applicant_inquiry_enabled === false) {
                throw new RuntimeException('applicant_ai_disabled');
            }
        }

        $idempotency = $meta['idempotency_key'] ?? null;
        if (is_string($idempotency) && $idempotency !== '') {
            $existing = XiamTurn::query()
                ->with(['run', 'conversation'])
                ->where('idempotency_key', $idempotency)
                ->whereHas('conversation', fn ($query) => $query->where('actor_type', $actorType)->where('actor_id', $actorId))
                ->first();
            if ($existing !== null && $existing->run !== null) {
                return [
                    'conversation_id' => $existing->conversation_id,
                    'turn_id' => $existing->id,
                    'run_id' => $existing->run->id,
                    'context_epoch' => $existing->conversation->context_epoch,
                    'cursor' => (int) XiamRunEvent::query()
                        ->where('conversation_id', $existing->conversation_id)
                        ->max('seq'),
                ];
            }
        }

        if (! $this->budgets->canReserve($actorType, $actorId)) {
            throw new RuntimeException('quota_exhausted');
        }
        $candidateNames = $actorType === 'staff' && $institutionId !== null
            ? ApplicantIdentity::query()
                ->where('institution_id', $institutionId)
                ->whereHas('admissionAttempts', fn ($query) => $query
                    ->where('institution_id', $institutionId)
                    ->whereIn('status', [
                        AdmissionAttempt::STATUS_PENDING,
                        AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                        AdmissionAttempt::STATUS_ACCEPTED,
                    ]))
                ->get(['id', 'institution_id', 'first_name', 'middle_name', 'last_name', 'suffix'])
                ->mapWithKeys(fn (ApplicantIdentity $identity): array => [$identity->fullName() => $identity->fullName()])
                ->all()
            : [];

        $mode = $meta['mode'] ?? 'assistant';
        if (! in_array($mode, ['inquiry', 'assistant'], true)) {
            throw new RuntimeException('mode_unavailable');
        }

        $result = DB::transaction(function () use ($actorType, $actorId, $institutionId, $surfaceKey, $minimizedText, $candidateNames, $meta, $idempotency, $mode): array {
            $this->useRepeatableRead();
            $conversation = $this->epochs->resolveOpenConversation(
                $actorType,
                $actorId,
                $institutionId,
                $surfaceKey,
                is_string($meta['route_name'] ?? null) ? $meta['route_name'] : null,
                is_string($meta['browser_session_id'] ?? null) ? $meta['browser_session_id'] : null,
                is_string($meta['surface_profile_id'] ?? null) ? $meta['surface_profile_id'] : null,
            );
            $conversation = XiamConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $cursor = (int) XiamRunEvent::query()
                ->where('conversation_id', $conversation->id)
                ->max('seq');

            $seq = (int) XiamTurn::query()->where('conversation_id', $conversation->id)->max('seq') + 1;
            $turnId = (string) Str::uuid();
            $runId = (string) Str::uuid();

            $turn = XiamTurn::query()->create([
                'id' => $turnId,
                'conversation_id' => $conversation->id,
                'seq' => $seq,
                'mode' => $mode,
                'page_pack_version' => $meta['page_pack_version'] ?? null,
                'input_text' => $this->piiTokenVault->tokenize(
                    $minimizedText,
                    $actorId,
                    $conversation->id,
                    $conversation->context_epoch,
                    $candidateNames,
                ),
                'idempotency_key' => is_string($idempotency) && $idempotency !== '' ? $idempotency : null,
            ]);

            XiamRun::query()->create([
                'id' => $runId,
                'turn_id' => $turn->id,
                'conversation_id' => $conversation->id,
                'status' => XiamRun::STATUS_QUEUED,
                'publication_version' => 'openspec3.v1',
                'gateway_route' => (string) config('xiam.ai.gateway', 'fake'),
            ]);

            $reservation = $this->budgets->reserve($actorType, $actorId, $institutionId, $runId);
            if ($reservation === null) {
                throw new RuntimeException('quota_exhausted');
            }

            return [
                'conversation_id' => $conversation->id,
                'turn_id' => $turnId,
                'run_id' => $runId,
                'context_epoch' => $conversation->context_epoch,
                'cursor' => $cursor,
            ];
        });
        $processInline = app()->environment('testing')
            || config('queue.default') === 'sync'
            || (bool) config('xiam.process_inline', false);

        if ($processInline) {
            app(ProcessXiamRun::class)->handle($result['run_id']);
        } else {
            ProcessXiamRunJob::dispatch($result['run_id']);
        }

        return $result;
    }

    private function useRepeatableRead(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('set transaction isolation level repeatable read');
        }
    }

    public function cancelRun(string $runId, Authenticatable $actor): bool
    {
        $run = XiamRun::query()->find($runId);
        if ($run === null) {
            return false;
        }

        $conversation = $run->turn?->conversation;
        if ($conversation === null) {
            return false;
        }

        if ((string) $actor->getAuthIdentifier() !== $conversation->actor_id) {
            return false;
        }

        if ($run->isTerminal()) {
            return true;
        }

        $run->forceFill(['cancel_requested_at' => now()])->save();

        return true;
    }
}
