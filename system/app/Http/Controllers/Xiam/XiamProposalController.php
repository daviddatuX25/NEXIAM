<?php

namespace App\Http\Controllers\Xiam;

use App\Ai\Adapters\LaravelAi\XiamProjectorRegistry;
use App\Ai\Policy\XiamModePreference;
use App\Ai\Proposals\ProposalService;
use App\Http\Controllers\Controller;
use App\Models\Ai\XiamProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class XiamProposalController extends Controller
{
    public function __construct(
        private ProposalService $proposals,
        private XiamModePreference $modePreference,
        private ?XiamProjectorRegistry $projector = null,
    ) {}

    public function confirm(Request $request, string $id): JsonResponse
    {
        try {
            $proposal = XiamProposal::query()->where('id', $id)->firstOrFail();
        } catch (ModelNotFoundException) {
            return $this->errorResponse('proposal_not_found');
        }

        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->errorResponse('unauthenticated');
        }

        $summary = is_array($proposal->summary) ? $proposal->summary : [];
        if ((string) ($summary['actor_id'] ?? '') !== (string) $actor->id) {
            return $this->errorResponse('proposal_actor_mismatch');
        }
        $validated = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:128'],
            'phrase' => ['sometimes', 'nullable', 'string'],
        ]);

        if ($proposal->safety_tier !== 't1' && $proposal->confirmation_phrase !== null) {
            $phrase = $request->input('phrase');

            if (! is_string($phrase) || trim($phrase) !== $proposal->confirmation_phrase) {
                return $this->errorResponse('phrase_mismatch', [
                    'expected_phrase' => $proposal->confirmation_phrase,
                ]);
            }
        }

        if ($proposal->requires_reauth) {
            $confirmedAt = $request->session()->get('auth.password_confirmed_at')
                ?? $request->session()->get('auth.recent_reauth_at');

            if ($confirmedAt === null || (time() - (int) $confirmedAt) > 300) {
                return $this->errorResponse('reauth_required', [
                    'reauth_timeout_seconds' => 300,
                ]);
            }
        }
        try {
            $activeMode = $this->modePreference->view($request)['active_mode'];
            $result = $this->proposals->confirm($actor, $id, $validated['idempotency_key'], $activeMode);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }

        $projector = $this->projector ?? app(XiamProjectorRegistry::class);
        $proposalModel = $result['proposal'];
        $proposalView = $projector->safeProposalView([
            'id' => $proposalModel->id,
            'proposal_id' => $proposalModel->id,
            'run_id' => (string) ($proposalModel->run_id ?? ''),
            'capability_id' => $proposalModel->capability_id,
            'status' => $proposalModel->status,
            'safety_tier' => $proposalModel->safety_tier,
            'confirmation_phrase' => $proposalModel->confirmation_phrase,
            'requires_reauth' => (bool) $proposalModel->requires_reauth,
            'expires_at' => $proposalModel->expires_at?->toIso8601String(),
            'proposal_view' => is_array($proposalModel->proposal_view) ? $proposalModel->proposal_view : [],
            'receipt' => is_array($proposalModel->receipt) ? $proposalModel->receipt : (is_array($result['result'] ?? null) ? $result['result'] : null),
            'summary' => is_array($proposalModel->summary) ? $proposalModel->summary : [],
        ]);

        return response()->json([
            'ok' => true,
            'proposal' => [
                'id' => $proposalModel->id,
                'status' => $proposalModel->status,
                'capability_id' => $proposalModel->capability_id,
                ...$proposalView,
            ],
            'proposal_view' => $proposalView,
            'result' => $result['result'],
        ]);
    }

    public function cancel(Request $request, string $proposal, ProposalService $proposals): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return $this->errorResponse('unauthenticated');
        }

        try {
            $cancelled = $proposals->cancel($actor, $proposal);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage());
        }

        $projector = $this->projector ?? app(XiamProjectorRegistry::class);
        $proposalView = $projector->safeProposalView([
            'id' => $cancelled->id,
            'proposal_id' => $cancelled->id,
            'run_id' => (string) ($cancelled->run_id ?? ''),
            'capability_id' => $cancelled->capability_id,
            'status' => $cancelled->status,
            'safety_tier' => $cancelled->safety_tier,
            'confirmation_phrase' => $cancelled->confirmation_phrase,
            'requires_reauth' => (bool) $cancelled->requires_reauth,
            'expires_at' => $cancelled->expires_at?->toIso8601String(),
            'proposal_view' => is_array($cancelled->proposal_view) ? $cancelled->proposal_view : [],
            'receipt' => is_array($cancelled->receipt) ? $cancelled->receipt : null,
            'summary' => is_array($cancelled->summary) ? $cancelled->summary : [],
        ]);

        return response()->json([
            'ok' => true,
            'proposal' => [
                'id' => $cancelled->id,
                'status' => $cancelled->status,
                'capability_id' => $cancelled->capability_id,
                ...$proposalView,
            ],
            'proposal_view' => $proposalView,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function errorResponse(string $rawCode, array $extra = []): JsonResponse
    {
        $publicError = $this->resolvePublicError($rawCode);
        $status = $this->resolveHttpStatus($publicError['category'], $rawCode);

        return response()->json([
            'ok' => false,
            'error' => $publicError['code'],
            'error_code' => $publicError['code'],
            'category' => $publicError['category'],
            'message' => $publicError['message'],
            'remediation' => $publicError['remediation'],
            'public_error' => $publicError,
            ...$extra,
        ], $status);
    }

    /**
     * Maps an internal error code or exception message into a structured public error.
     *
     * @return array{code: string, category: string, message: string, remediation: string}
     */
    private function resolvePublicError(string $rawCode): array
    {
        return match ($rawCode) {
            'unauthenticated' => [
                'code' => 'unauthenticated',
                'category' => 'denied',
                'message' => 'Authentication is required to perform this proposal action.',
                'remediation' => 'reauthenticate',
            ],
            'proposal_actor_mismatch' => [
                'code' => 'proposal_actor_mismatch',
                'category' => 'denied',
                'message' => 'This proposal was created by a different authenticated user.',
                'remediation' => 'none',
            ],
            'surface_denied' => [
                'code' => 'surface_denied',
                'category' => 'denied',
                'message' => 'Action confirmation is not permitted on this surface.',
                'remediation' => 'none',
            ],
            'envelope_denied', 'envelope_denied:read_allowed' => [
                'code' => 'envelope_denied',
                'category' => 'denied',
                'message' => 'Security policy prevents confirming this proposal.',
                'remediation' => 'none',
            ],
            'actor_revoked', 'conversation_denied', 'grant_unusable', 'grant_target_mismatch' => [
                'code' => 'access_denied',
                'category' => 'denied',
                'message' => 'Authority or task grant is no longer usable for this proposal.',
                'remediation' => 'repropose',
            ],
            'reauth_required' => [
                'code' => 'reauth_required',
                'category' => 'denied',
                'message' => 'Recent password re-authentication is required to confirm this proposal.',
                'remediation' => 'reauthenticate',
            ],
            'mode_inquiry_read_only', 'mode_unavailable' => [
                'code' => 'mode_read_only',
                'category' => 'unavailable',
                'message' => 'Assistant is in read-only mode and cannot confirm mutation proposals.',
                'remediation' => 'none',
            ],
            'mode_session_unavailable' => [
                'code' => 'mode_session_unavailable',
                'category' => 'unavailable',
                'message' => 'Session service is temporarily unavailable.',
                'remediation' => 'refresh',
            ],
            'emergency_stop' => [
                'code' => 'emergency_stop',
                'category' => 'unavailable',
                'message' => 'Assistant proposals are temporarily suspended by emergency stop.',
                'remediation' => 'none',
            ],
            'proposal_not_found' => [
                'code' => 'proposal_not_found',
                'category' => 'invalid',
                'message' => 'The requested proposal was not found.',
                'remediation' => 'repropose',
            ],
            'phrase_mismatch' => [
                'code' => 'phrase_mismatch',
                'category' => 'invalid',
                'message' => 'The entered confirmation phrase did not match the expected phrase.',
                'remediation' => 'manual',
            ],
            'proposal_not_confirmable' => [
                'code' => 'proposal_not_confirmable',
                'category' => 'invalid',
                'message' => 'This proposal has already been resolved or cancelled.',
                'remediation' => 'repropose',
            ],
            'proposal_expired' => [
                'code' => 'proposal_expired',
                'category' => 'expired',
                'message' => 'This proposal has expired. Please formulate a new proposal.',
                'remediation' => 'repropose',
            ],
            'stale_session_version', 'concurrency_conflict' => [
                'code' => 'stale_session_version',
                'category' => 'conflict',
                'message' => 'Target entity was modified by another user since proposal creation.',
                'remediation' => 'refresh',
            ],
            'idempotency_conflict' => [
                'code' => 'idempotency_conflict',
                'category' => 'conflict',
                'message' => 'Conflicting execution parameters recorded for this idempotency key.',
                'remediation' => 'reconcile',
            ],
            'only_draft_may_publish' => [
                'code' => 'only_draft_may_publish',
                'category' => 'invalid',
                'message' => 'Only examination sessions in draft status may be published.',
                'remediation' => 'refresh',
            ],
            'assign_required_before_publish' => [
                'code' => 'assign_required_before_publish',
                'category' => 'invalid',
                'message' => 'Candidates must be assigned before publishing an examination session.',
                'remediation' => 'refresh',
            ],
            'attempt_not_exam_eligible' => [
                'code' => 'attempt_not_exam_eligible',
                'category' => 'invalid',
                'message' => 'Applicant is not eligible for examination assignment.',
                'remediation' => 'repropose',
            ],
            'target_not_found', 'session_not_found' => [
                'code' => 'target_not_found',
                'category' => 'stale',
                'message' => 'The target record could not be found.',
                'remediation' => 'refresh',
            ],
            'capability_retired', 'registrar_stamp_retired' => [
                'code' => 'capability_retired',
                'category' => 'retired',
                'message' => 'This action capability is permanently retired and cannot be executed.',
                'remediation' => 'none',
            ],
            default => [
                'code' => 'confirmation_failed',
                'category' => 'failed',
                'message' => 'The proposal could not be executed due to an application failure.',
                'remediation' => 'repropose',
            ],
        };
    }

    private function resolveHttpStatus(string $category, string $code): int
    {
        if ($code === 'unauthenticated') {
            return 401;
        }
        if ($code === 'proposal_not_found') {
            return 404;
        }
        if ($code === 'reauth_required') {
            return 423;
        }

        return match ($category) {
            'denied' => 403,
            'unavailable' => 503,
            'invalid', 'conflict', 'stale', 'expired', 'retired', 'unsupported', 'failed' => 422,
            default => 422,
        };
    }
}
