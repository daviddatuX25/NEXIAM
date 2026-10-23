<?php

namespace App\Http\Controllers\ProgramHead;

use App\Admission\Actions\DeclineApplicantProgramSelection;
use App\Admission\Actions\ResolveProgramFollowUp;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\OpenApplicantSupportConversationRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProgramHeadActionController extends Controller
{
    public function declineSelection(
        Request $request,
        Program $program,
        AdmissionAttempt $attempt,
        CapabilityAuthorizer $authorizer,
        DeclineApplicantProgramSelection $action,
    ): RedirectResponse {
        abort_unless($this->canActForProgram($request->user(), $program, $authorizer, CapabilityCatalog::PROGRAM_HEAD_SELECTION_DECLINE), 403);
        abort_unless((int) (AttemptProgramFacts::currentSelection($attempt)?->program_id) === (int) $program->id, 404);

        try {
            $action->handle(
                $attempt,
                $request->user(),
                $program->code,
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['decline' => $exception->getMessage()]);
        }

        return back()->with('status', 'Program selection declined.');
    }

    public function resolveFollowUp(
        Request $request,
        Program $program,
        ProgramFollowUp $followUp,
        CapabilityAuthorizer $authorizer,
        ResolveProgramFollowUp $action,
    ): RedirectResponse {
        abort_unless($this->canActForProgram($request->user(), $program, $authorizer, CapabilityCatalog::PROGRAM_HEAD_FOLLOW_UP_RESOLVE), 403);
        abort_unless(
            (int) $followUp->program_id === (int) $program->id
                && (int) $followUp->attempt?->institution_id === (int) $program->institution_id,
            404,
        );

        try {
            $action->handle(
                $followUp,
                $request->user(),
                $request->string('outcome')->toString(),
                $request->input('reason'),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        return back()->with('status', 'Follow-up resolved.');
    }

    public function openInternalThread(
        OpenApplicantSupportConversationRequest $request,
        string $programCode,
        string $attemptId,
        SendAdmissionSupportMessage $action,
    ): JsonResponse|RedirectResponse {
        $program = Program::query()->where('code', $programCode)->first();
        $attempt = AdmissionAttempt::query()->find($attemptId);
        if ($program === null || $attempt === null) {
            return $this->conversationError($request, 'not_found', 'Conversation not found.', 404);
        }

        $input = $request->validated();

        try {
            $thread = $action->openInternalThread(
                $attempt,
                (int) $program->id,
                $input['subject'],
                $input['body'],
                $request->user(),
                $input['idempotency_key'],
            );
        } catch (ValidationException) {
            return $this->conversationError($request, 'validation_failed', 'Conversation request is invalid.', 422);
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            if ($message === 'Idempotency key was already used with a different request payload.') {
                return $this->conversationError($request, 'idempotency_conflict', 'This operation key was already used for different content.', 409);
            }

            if (in_array($message, [
                'You are not authorized to open this case thread.',
                'The practice case is retired.',
            ], true)) {
                return $this->conversationError($request, 'not_found', 'Conversation not found.', 404);
            }

            throw $exception;
        }

        $firstMessage = RecordThreadMessage::query()
            ->with('sender')
            ->where('record_thread_id', $thread->id)
            ->where('sequence', 1)
            ->firstOrFail();

        if (! $this->expectsJson($request)) {
            $response = back()->with('status', 'Internal case thread opened.');
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        return response()->json([
            'thread' => ['id' => (string) $thread->id, 'subject' => (string) $thread->subject],
            'message' => [
                'id' => (string) $firstMessage->id,
                'sequence' => (string) $firstMessage->sequence,
                'sender' => [
                    'display_name' => $firstMessage->sender?->name ?? 'Participant',
                    'is_self' => (int) $firstMessage->sender_user_id === (int) $request->user()->id,
                ],
                'body' => (string) $firstMessage->body,
                'created_at' => $firstMessage->created_at->toISOString(),
            ],
            'replayed' => ! $thread->wasRecentlyCreated,
            'as_of' => now()->toISOString(),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    private function canActForProgram(
        User $actor,
        Program $program,
        CapabilityAuthorizer $authorizer,
        string $capability,
    ): bool {
        $scope = $actor->actorScope();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $scope->institutionId)
            ->where('code', $scope->activeCycleCode)
            ->where('is_active', true)
            ->first();

        return $cycle !== null
            && $program->is_active
            && $scope->institutionId !== null
            && (int) $program->institution_id === (int) $scope->institutionId
            && in_array($program->code, $scope->programCodes, true)
            && $authorizer->decideFresh($actor, $capability, [
                'institution_id' => $program->institution_id,
                'program' => $program->code,
                'cycle' => $cycle->code,
            ])->allowed;
    }

    private function expectsJson(Request $request): bool
    {
        return $request->wantsJson() || $request->expectsJson();
    }

    private function conversationError(Request $request, string $code, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($this->expectsJson($request)) {
            return response()->json(
                ['code' => $code, 'message' => $message],
                $status,
                ['Cache-Control' => 'private, no-store'],
            );
        }

        $response = back()->withErrors(['thread' => $message]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
