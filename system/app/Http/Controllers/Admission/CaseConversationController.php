<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\MarkAdmissionSupportMessageRead;
use App\Admission\Actions\OpenApplicantSupportConversation;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Queries\CaseConversationQuery;
use App\Admission\Queries\CaseConversationStartQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\CaseConversationApplicantsRequest;
use App\Http\Requests\Admission\MarkCaseConversationReadRequest;
use App\Http\Requests\Admission\OpenApplicantSupportConversationRequest;
use App\Http\Requests\Admission\ReadCaseConversationRequest;
use App\Http\Requests\Admission\SendCaseConversationMessageRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class CaseConversationController extends Controller
{
    public function show(
        ReadCaseConversationRequest $request,
        string $threadId,
        CaseConversationQuery $query,
    ): JsonResponse {
        $thread = RecordThread::query()->find($threadId);
        if ($thread === null) {
            return $this->error('not_found', 'Conversation not found.', 404);
        }

        $input = $request->validated();

        try {
            $data = $query->forThread(
                $thread,
                $request->user(),
                $input['after_sequence'] ?? null,
                $input['before_sequence'] ?? null,
            );
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() === 404) {
                return $this->error('not_found', 'Conversation not found.', 404);
            }

            throw $exception;
        } catch (ValidationException) {
            return $this->error('validation_failed', 'Conversation request is invalid.', 422);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'A started session and application key are required.') {
                return $this->error('session_expired', 'Your session has expired. Refresh and sign in again.', 401);
            }

            throw $exception;
        }

        return $this->json($data);
    }

    public function sendMessage(
        SendCaseConversationMessageRequest $request,
        string $threadId,
        SendAdmissionSupportMessage $action,
    ): JsonResponse|RedirectResponse {
        $thread = RecordThread::query()->find($threadId);
        if ($thread === null) {
            return $this->respondError($request, 'not_found', 'Conversation not found.', 404);
        }

        $input = $request->validated();
        try {
            $message = $action->handle($thread, $request->user(), $input['body'], $input['idempotency_key']);
        } catch (\Throwable $exception) {
            return $this->actionError($exception, $request);
        }

        if (! $this->expectsJson($request)) {
            return $this->redirect('Message sent.');
        }

        $message->loadMissing('sender');

        return $this->json([
            'message' => [
                'id' => (string) $message->id,
                'sequence' => (string) $message->sequence,
                'sender' => [
                    'display_name' => $message->sender?->name ?? 'Participant',
                    'is_self' => true,
                ],
                'body' => (string) $message->body,
                'created_at' => $message->created_at->toISOString(),
            ],
            'replayed' => ! $message->wasRecentlyCreated,
            'as_of' => now()->toISOString(),
        ]);
    }

    public function markRead(
        MarkCaseConversationReadRequest $request,
        string $threadId,
        MarkAdmissionSupportMessageRead $action,
    ): JsonResponse|RedirectResponse {
        $thread = RecordThread::query()->find($threadId);
        if ($thread === null) {
            return $this->respondError($request, 'not_found', 'Conversation not found.', 404);
        }

        $input = $request->validated();
        try {
            $action->handle($thread, $request->user(), $input['observed_sequence'], $input['observation_token']);
        } catch (\Throwable $exception) {
            return $this->actionError($exception, $request);
        }

        if (! $this->expectsJson($request)) {
            return $this->redirect('Conversation read state updated.');
        }

        $ownReadSequence = (string) (RecordThreadRead::query()
            ->where('record_thread_id', $thread->id)
            ->where('user_id', $request->user()->id)
            ->value('last_read_sequence') ?? 0);

        $unreadCount = RecordThreadMessage::query()
            ->where('record_thread_id', $thread->id)
            ->where('sequence', '>', $ownReadSequence)
            ->where('sender_user_id', '!=', $request->user()->id)
            ->count();

        return $this->json([
            'own_read_sequence' => $ownReadSequence,
            'unread_count' => $unreadCount,
            'as_of' => now()->toISOString(),
        ]);
    }

    public function applicants(CaseConversationApplicantsRequest $request, CaseConversationStartQuery $query): JsonResponse
    {
        $input = $request->validated();

        try {
            return $this->json($query->applicants(
                $request->user(),
                $input['program_code'],
                $input['cycle_code'],
                $input['search'] ?? null,
            ));
        } catch (HttpExceptionInterface $exception) {
            if ($exception->getStatusCode() !== 404) {
                throw $exception;
            }

            return $this->error('not_found', 'Conversation context not found.', 404);
        }
    }

    public function openSupport(
        OpenApplicantSupportConversationRequest $request,
        string $programCode,
        string $attemptId,
        OpenApplicantSupportConversation $action,
    ): JsonResponse {
        $program = Program::query()->where('code', $programCode)->first();
        $attempt = AdmissionAttempt::query()->find($attemptId);
        if ($program === null || $attempt === null) {
            return $this->error('not_found', 'Conversation not found.', 404);
        }

        $input = $request->validated();

        try {
            $thread = $action->handle(
                $attempt,
                $program->id,
                $input['subject'],
                $input['body'],
                $request->user(),
                $input['idempotency_key'],
            );
        } catch (\Throwable $exception) {
            return $this->actionError($exception, $request, true);
        }

        $firstMessage = RecordThreadMessage::query()
            ->with('sender')
            ->where('record_thread_id', $thread->id)
            ->where('sequence', 1)
            ->firstOrFail();

        return $this->json([
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
            'as_of' => now()->toISOString(),
        ]);
    }

    private function actionError(\Throwable $exception, Request $request, bool $opening = false): JsonResponse|RedirectResponse
    {
        if ($exception instanceof HttpExceptionInterface || $exception instanceof ModelNotFoundException) {
            if ($exception instanceof ModelNotFoundException || $exception->getStatusCode() === 404) {
                return $this->respondError($request, 'not_found', 'Conversation not found.', 404);
            }

            throw $exception;
        }

        if ($exception instanceof ValidationException) {
            return $this->respondError($request, 'validation_failed', 'Conversation request is invalid.', 422);
        }

        if (! $exception instanceof RuntimeException) {
            throw $exception;
        }

        $message = $exception->getMessage();

        if (in_array($message, [
            'You are not authorized to read this thread.',
            'You are not authorized to send messages to this thread.',
            'You are not authorized to open this applicant-support thread.',
            'You are not authorized to open this case thread.',
        ], true)) {
            return $this->respondError($request, 'not_found', 'Conversation not found.', 404);
        }

        if ($opening && in_array($message, [
            'Applicant support messaging ends once the applicant is enrolled.',
            'Applicant messaging requires a current selection or targeted follow-up for that program.',
        ], true)) {
            return $this->respondError($request, 'not_found', 'Conversation not found.', 404);
        }

        if ($message === 'Idempotency key was already used with a different request payload.') {
            return $this->respondError($request, 'idempotency_conflict', 'This operation key was already used for different content.', 409);
        }

        if ($message === 'Invalid or expired observation token.') {
            return $this->respondError($request, 'validation_failed', 'The conversation observation has expired. Refresh and try again.', 422);
        }

        if ($message === 'A started session and application key are required.') {
            return $this->respondError($request, 'session_expired', 'Your session has expired. Refresh and sign in again.', 401);
        }

        throw $exception;
    }

    private function expectsJson(Request $request): bool
    {
        return $request->wantsJson() || $request->expectsJson();
    }

    private function redirect(string $status): RedirectResponse
    {
        $response = back()->with('status', $status);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function respondError(Request $request, string $code, string $message, int $status): JsonResponse|RedirectResponse
    {
        if ($this->expectsJson($request)) {
            return $this->error($code, $message, $status);
        }

        $response = back()->withErrors(['conversation' => $message]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): JsonResponse
    {
        return response()->json($payload, 200, ['Cache-Control' => 'private, no-store']);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(
            ['code' => $code, 'message' => $message],
            $status,
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
