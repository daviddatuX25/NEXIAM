<?php

namespace App\Http\Controllers\Nexi;

use App\Ai\Conversation\ContextEpochService;
use App\Ai\Context\PageCapabilityPackRegistry;
use App\Ai\Skills\ApplicantChooseSlotSkill;
use App\Ai\Skills\ExamSessionSchedulingSkill;
use App\Ai\Skills\SkillEngine;
use App\Ai\Skills\SkillToolInvoker;
use App\Ai\Surface\SurfaceProfileResolver;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class NexiSkillController extends Controller
{
    public function start(
        Request $request,
        SkillEngine $skills,
        ContextEpochService $epochs,
        PageCapabilityPackRegistry $packs,
        SurfaceProfileResolver $surfaces,
    ): JsonResponse {
        $validated = $request->validate([
            'skill_key' => ['required', 'string', 'max:128'],
            'page_pack_key' => ['nullable', 'string', 'max:128'],
            'page_pack_version' => ['nullable', 'integer', 'min:1'],
            'session_id' => ['nullable', 'integer', 'min:1'],
            'admission_attempt_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $resolved = $surfaces->resolve($request);
        $profile = $resolved['profile'] ?? null;
        if ($profile === null || ! $profile->mountable) {
            return response()->json(['error' => 'surface_denied'], 403);
        }

        $skillKey = $validated['skill_key'];
        $isApplicantSkill = in_array($skillKey, [
            ApplicantChooseSlotSkill::KEY.'@'.ApplicantChooseSlotSkill::VERSION,
            ApplicantChooseSlotSkill::KEY,
        ], true);
        $isExamSkill = in_array($skillKey, [
            ExamSessionSchedulingSkill::KEY.'@'.ExamSessionSchedulingSkill::VERSION,
            ExamSessionSchedulingSkill::KEY,
        ], true);

        if (! $isApplicantSkill && ! $isExamSkill) {
            return response()->json(['error' => 'skill_unsupported'], 422);
        }

        if ($isApplicantSkill) {
            if (! $actor->applicantAccount()->exists()) {
                return response()->json(['error' => 'surface_denied'], 403);
            }
        } elseif (! $actor->staffProfile()->exists()) {
            return response()->json(['error' => 'surface_denied'], 403);
        }

        $pack = null;
        if (! empty($validated['page_pack_key'])) {
            $pack = $packs->forKey($validated['page_pack_key']);
        }

        $originKey = $pack?->key ?? ($validated['page_pack_key'] ?? ($isApplicantSkill ? 'applicant.schedule@1' : 'staff.exams.sessions.index@1'));
        $originVersion = $pack?->version ?? ($validated['page_pack_version'] ?? 1);

        $institutionId = $isApplicantSkill
            ? ($actor->applicantAccount()?->institution_id ?? $actor->actorScope()->institutionId)
            : ($actor->staffProfile()?->institution_id ?? $actor->actorScope()->institutionId);
        $conversation = $epochs->resolveOpenConversation(
            $isApplicantSkill ? 'applicant' : 'staff',
            (string) $actor->id,
            $institutionId,
            $isApplicantSkill ? 'applicant' : 'staff',
        );

        $grant = $isApplicantSkill
            ? $skills->startApplicantSlotSkill(
                actor: $actor,
                contextEpoch: $conversation->context_epoch,
                originPackKey: $originKey,
                originPackVersion: $originVersion,
                attemptId: $validated['admission_attempt_id'] ?? null,
            )
            : $skills->startExamSessionSkill(
                actor: $actor,
                contextEpoch: $conversation->context_epoch,
                originPackKey: $originKey,
                originPackVersion: $originVersion,
                sessionId: $validated['session_id'] ?? null,
            );

        return response()->json([
            'ok' => true,
            'skill_key' => $grant->skill_key.'@'.$grant->skill_version,
            'grant_id' => $grant->id,
            'conversation_id' => $conversation->id,
            'context_epoch' => $conversation->context_epoch,
            'cursor' => $grant->scope_bounds['cursor'] ?? ApplicantChooseSlotSkill::CURSOR_STARTED,
            'allowed_capability_ids' => $grant->allowed_capability_ids,
            'guidance_digest' => $grant->scope_bounds['guidance_digest'] ?? null,
        ], 201);
    }

    public function invoke(Request $request, SkillToolInvoker $invoker): JsonResponse
    {
        $validated = $request->validate([
            'grant_id' => ['required', 'string', 'max:64'],
            'capability_id' => ['required', 'string', 'max:128'],
            'input' => ['nullable', 'array'],
            'conversation_id' => ['nullable', 'string', 'max:64'],
        ]);

        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        try {
            $payload = $invoker->invoke(
                actor: $actor,
                grantId: $validated['grant_id'],
                capabilityId: $validated['capability_id'],
                input: is_array($validated['input'] ?? null) ? $validated['input'] : [],
                conversationId: $validated['conversation_id'] ?? null,
            );
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'grant_not_found' => 404,
                'grant_actor_mismatch', 'conversation_denied' => 403,
                'capability_not_on_skill_allowlist', 'capability_illegal_for_cursor',
                'skill_mismatch', 'grant_unusable' => 422,
                default => 422,
            };

            return response()->json(['error' => $code], $status);
        }

        return response()->json(['ok' => true, ...$payload]);
    }
}
