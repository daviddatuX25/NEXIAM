<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ErrorData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\NonStreamingChoiceData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseFormatData;
use MoeMizrak\LaravelOpenrouter\DTO\StreamingChoiceData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use MoeMizrak\LaravelOpenrouter\Types\RoleType;

class ExamSchedulingAssistantService
{
    /**
     * JSON schema for the final exam schedule (used for structured output and validation).
     * Each session is either: (exam_session_id + applicant_ids) for existing draft, or
     * (room_id, date, start_time, end_time, applicant_ids) for a new session.
     */
    public static function scheduleJsonSchema(): array
    {
        return [
            'name' => 'exam_schedule',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'sessions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'action' => ['type' => 'string', 'description' => 'Action: "create" for new session, "assign" to add applicants to existing draft, "edit" to change room/date/time of existing draft, "delete" to remove an existing draft session'],
                                'exam_session_id' => ['type' => 'integer', 'description' => 'Required for assign/edit: existing draft exam session ID'],
                                'room_id' => ['type' => 'integer', 'description' => 'Room ID — required for create, optional for edit (to change room)'],
                                'date' => ['type' => 'string', 'description' => 'Date YYYY-MM-DD — required for create, optional for edit'],
                                'start_time' => ['type' => 'string', 'description' => 'Start time HH:MM — required for create, optional for edit'],
                                'end_time' => ['type' => 'string', 'description' => 'End time HH:MM — optional for create/edit'],
                                'applicant_ids' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'integer'],
                                    'description' => 'Applicant IDs to assign (required for create/assign, optional for edit)',
                                ],
                            ],
                            'required' => ['action'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['sessions'],
                'additionalProperties' => false,
            ],
        ];
    }

    /**
     * Build system prompt with applicant, room, and draft session context.
     *
     * @param  array<int, array{id: int, room?: array, date?: string, start_time?: string, end_time?: string, current_count: int, capacity: int}>  $draftSessions
     */
    public function buildSystemPrompt(int $applicantCount, array $rooms, array $applicantSummary = [], array $draftSessions = [], array $existingSessions = []): string
    {
        $roomList = collect($rooms)->map(fn ($r) => sprintf(
            'id: %s, name: %s, capacity: %s',
            $r['id'],
            $r['name'] ?? '—',
            $r['capacity'] ?? 0
        ))->join('; ');

        $summary = '';
        if (! empty($applicantSummary)) {
            $ids = collect($applicantSummary)->pluck('id')->take(50)->implode(', ');
            $summary = " Applicant IDs to schedule (sample): {$ids}.";
        }

        $draftList = '';
        if (! empty($draftSessions)) {
            $draftList = ' Existing DRAFT exam sessions (you may assign more applicants up to room capacity): '
                .collect($draftSessions)->map(fn ($s) => sprintf(
                    'id: %s, room: %s, date: %s, time: %s-%s, current: %s, capacity: %s',
                    $s['id'],
                    $s['room']['name'] ?? $s['room_id'] ?? '?',
                    $s['date'] ?? '—',
                    $s['start_time'] ?? '—',
                    $s['end_time'] ?? '—',
                    $s['current_count'] ?? 0,
                    $s['capacity'] ?? 0
                ))->join('; ').'.';
        }

        $existingList = '';
        if (! empty($existingSessions)) {
            $existingList = ' Existing SCHEDULED (non-draft) exam sessions — do NOT schedule applicants in these rooms/times: '
                .collect($existingSessions)->map(fn ($s) => sprintf(
                    'id: %s, room: %s, date: %s, time: %s-%s',
                    $s['id'],
                    $s['room']['name'] ?? $s['room_id'] ?? '?',
                    $s['date'] ?? '—',
                    $s['start_time'] ?? '—',
                    $s['end_time'] ?? '—'
                ))->join('; ').'.';
        }

        return 'You are an assistant helping an admin schedule exam sessions. '
            .'IMPORTANT — you MUST always derive factual claims (counts, room availability, session status) from the data provided below, NEVER from memory or guesswork. '
            ."There are {$applicantCount} applicants waiting to be scheduled.{$summary} "
            ."Available rooms: {$roomList}.{$draftList}{$existingList} "
            .'Constraints: '
            .'1) Each applicant must be assigned to exactly one session. '
            .'2) Each session cannot exceed room capacity. '
            .'3) The same room cannot be double-booked (no overlapping date/time). This constraint applies to ALL sessions: you cannot create a new session or edit an existing one to overlap with ANY existing draft session or scheduled session. '
            .'4) Prioritize utilizing existing DRAFT sessions: Before proposing to create a new session, you MUST check the existing DRAFT exam sessions (listed above). If there is already a draft session on the requested date/time (or if an existing draft session can be edited or assigned to), you MUST prioritize reusing that existing draft session (action "assign" or "edit") instead of creating a duplicate new session. '
            .'IMPORTANT — Assigned means confirmed placement: applicants assigned only to DRAFT sessions are still awaiting scheduling (draft sessions are not confirmed placements). Only applicants in non-draft sessions are considered fully assigned. '
            .'You support FOUR actions, each identified by an "action" field in the JSON: '
            .'1) "create" — Create a NEW session. Required fields: action, room_id, date, start_time, applicant_ids. Optional: end_time. '
            .'2) "assign" — Add applicants to an EXISTING DRAFT session. Required fields: action, exam_session_id, applicant_ids. '
            .'3) "edit" — Modify an EXISTING DRAFT session (change its room, date, or time). Required fields: action, exam_session_id, plus whichever fields to change (room_id, date, start_time, end_time). Optional: applicant_ids (to also assign applicants during the edit). '
            .'4) "delete" — Remove an EXISTING DRAFT session. Required fields: action, exam_session_id. Only draft sessions can be deleted. Never delete published or in-progress sessions. '
            .'CRITICAL: You NEVER apply changes yourself. You ONLY propose changes via JSON. The admin clicks "Apply" to execute them. NEVER say "I have applied", "Done", "Changes saved", or similar — say "Here is the proposed change" or "Review the changes below". '
            .'Ask clarifying questions (e.g. preferred dates, morning/afternoon slots) and only output the final schedule when the user confirms. '
            ."NEVER make absolute factual claims (e.g. 'there are no applicants', 'all rooms are full', 'no draft sessions') unless the data above explicitly shows zero. "
            ."If you are unsure, say 'The records show...' or 'Based on the data I have...' rather than making absolute statements. "
            .'CRITICAL FORMATTING REQUIREMENT: In the chat conversation, you MUST reply in plain conversational language. When outputting the schedule, you MUST append the valid JSON block at the very end of your response, wrapped inside a standard markdown code block: ```json\n...\n```. Do NOT put raw braces or brackets in the conversational text itself. '
            .'The JSON block must contain a "sessions" array. Each item MUST have an "action" field ("create", "assign", "edit", or "delete") and the corresponding required fields as described above.';
    }

    /**
     * Send chat request to OpenRouter and return reply content or throw/user-friendly error.
     *
     * @param  array<int, array{role: string, content: string}>  $messages  Conversation messages (role + content)
     * @param  array{applicant_count: int, rooms: array, applicant_summary?: array}  $context  For system prompt
     * @param  bool  $requestStructured  If true, request JSON schema response and return parsed schedule
     * @return array{reply: string, structured_schedule?: array}
     */
    public function chat(array $messages, array $context, bool $requestStructured = false): array
    {
        $model = config('services.openrouter.model', 'openrouter/free');
        $systemPrompt = $this->buildSystemPrompt(
            $context['applicant_count'],
            $context['rooms'],
            $context['applicant_summary'] ?? [],
            $context['draft_sessions'] ?? [],
            $context['existing_sessions'] ?? []
        );

        $messageData = [
            new MessageData(content: $systemPrompt, role: RoleType::SYSTEM),
        ];
        foreach ($messages as $m) {
            $role = $m['role'] === 'assistant' ? RoleType::ASSISTANT : RoleType::USER;
            $messageData[] = new MessageData(content: (string) $m['content'], role: $role);
        }

        // Use json_schema WITHOUT require_parameters — the latter restricts providers to
        // those that enforce strict schema output, which causes 404s on free-tier models.
        // Some free models still crash completely and return null when passing response_format,
        // so we bypass the json_schema request if using any free model to ensure stability.
        $responseFormat = null;
        if ($requestStructured && ! str_contains($model, 'free')) {
            $schema = self::scheduleJsonSchema();
            $responseFormat = new ResponseFormatData(
                type: 'json_schema',
                json_schema: $schema
            );
        }

        $chatData = new ChatData(
            messages: $messageData,
            model: $model,
            response_format: $responseFormat,
            max_tokens: 4096,
        );

        Log::info('[AI-SCHEDULER-DEBUG] Full payload to OpenRouter', [
            'model' => $model,
            'systemPrompt' => $systemPrompt,
            'messageCount' => count($messageData),
            'messages' => array_map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $messageData),
            'requestStructured' => $requestStructured,
        ]);

        // Use the LaravelOpenRouter facade for the HTTP request.
        // OpenRouterHelper::formChatResponse() has a bug where it passes raw arrays to
        // ResponseData::$choices (should be ChoiceData DTOs). This causes Spatie's
        // transform pipeline to fail when json_encode($response) is called during logging.
        // We work around it by using safeSerializeResponse() which manually extracts
        // fields from the ResponseData without triggering Spatie transformation.
        $response = $this->directChatRequest($chatData);

        if ($response instanceof ErrorData) {
            if ($response->code === 429) {
                throw new \RuntimeException('OpenRouter rate limit reached. Please try again in a moment.');
            }
            throw new \RuntimeException($response->message ?: 'OpenRouter request failed.');
        }

        /** @var ResponseData $response */
        $choice = is_array($response->choices) ? ($response->choices[0] ?? null) : null;
        if (is_object($choice) && isset($choice->message)) {
            $content = $choice->message->content ?? '';
        } elseif (is_array($choice)) {
            $content = $choice['message']['content'] ?? '';
        } else {
            $content = '';
        }
        if (is_array($content)) {
            $content = Arr::get($content, '0.text', json_encode($content));
        }
        $content = (string) $content;

        Log::info('[AI-SCHEDULER-DEBUG] OpenRouter response', [
            'content' => $content,
            'rawResponse' => $this->safeSerializeResponse($response),
        ]);

        $result = ['reply' => $content];

        // Always attempt to extract structured JSON from the AI's reply.
        // This way the AI can naturally output a schedule during conversation
        // and it gets auto-detected — no separate "Generate" step needed.
        if ($content !== '') {
            $schedule = $this->extractJsonFromText($content);
            if ($schedule !== null) {
                $result['structured_schedule'] = $schedule;
                // If the response is mostly JSON, suppress the text reply (avoid showing raw JSON to user)
                $jsonWeight = strlen(json_encode($schedule)) / max(1, strlen($content));
                if ($jsonWeight > 0.8) {
                    $result['reply'] = 'Here is the proposed schedule. Review it below and click Apply to confirm, or tell me what to change.';
                }
            }
        }

        return $result;
    }

    /**
     * Make a chat request via the LaravelOpenRouter facade, which properly handles
     * OpenRouter's API endpoint, authentication, and base URL.
     *
     * The only bug in the package is OpenRouterHelper::formChatResponse() passing raw
     * arrays to ResponseData::$choices — we work around that via safeSerializeResponse()
     * when logging. The HTTP request part of the facade is correct.
     */
    private function directChatRequest(ChatData $chatData): ErrorData|ResponseData
    {
        return LaravelOpenRouter::chatRequest($chatData);
    }

    /**
     * Extract a JSON object containing 'sessions' from free-form text.
     * Handles multiple formats the AI may produce:
     * 1. Clean `{"sessions": [...]}` wrapper
     * 2. JSON inside ```json ... ``` code fences
     * 3. Multiple separate JSON objects on different lines
     * 4. A bare array `[{...}, {...}]` of session objects
     * 5. A single session object `{...}` without wrapper
     */
    private function extractJsonFromText(string $text): ?array
    {
        // 1. Try direct parse — handles clean JSON as the full response
        $decoded = $this->tryJsonDecode($text);
        if ($decoded !== null) {
            $wrapped = $this->wrapAsSchedule($decoded);
            if ($wrapped !== null) {
                return $wrapped;
            }
        }

        // 2. Try extracting from ```json ... ``` code fences
        if (preg_match('/```json\s*([\s\S]+?)\s*```/is', $text, $m)) {
            $decoded = $this->tryJsonDecode($m[1]);
            if ($decoded !== null) {
                $wrapped = $this->wrapAsSchedule($decoded);
                if ($wrapped !== null) {
                    return $wrapped;
                }
            }
        }

        // 3. Try extracting multiple separate JSON objects from different lines
        //    e.g. the AI outputs `{ "room_id": 1, ... }\n{ "room_id": 2, ... }`
        $jsonObjects = [];
        preg_match_all('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $text, $matches);
        foreach ($matches[0] ?? [] as $fragment) {
            $obj = $this->tryJsonDecode($fragment);
            if (is_array($obj) && ! isset($obj['sessions'])) {
                // Looks like an individual session object
                if ($this->looksLikeSession($obj)) {
                    $jsonObjects[] = $obj;
                }
            } elseif (is_array($obj) && isset($obj['sessions'])) {
                return $obj;
            }
        }

        if (count($jsonObjects) > 0) {
            return ['sessions' => $jsonObjects];
        }

        return null;
    }

    /**
     * Attempt to JSON-decode a string, returning null on failure.
     */
    private function tryJsonDecode(string $text): mixed
    {
        try {
            return json_decode(trim($text), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * Wrap decoded JSON into the canonical `{"sessions": [...]}` format.
     * Accepts: already-wrapped object, bare array, or single session object.
     */
    private function wrapAsSchedule(mixed $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        // Already has sessions wrapper
        if (isset($decoded['sessions']) && is_array($decoded['sessions'])) {
            return $decoded;
        }

        // Bare array of session objects
        if (array_is_list($decoded) && count($decoded) > 0 && $this->looksLikeSession($decoded[0])) {
            return ['sessions' => $decoded];
        }

        // Single session object
        if ($this->looksLikeSession($decoded)) {
            return ['sessions' => [$decoded]];
        }

        return null;
    }

    /**
     * Heuristic: does this array look like a session object?
     * Checks for any of the known session-related keys.
     */
    private function looksLikeSession(mixed $data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        $sessionKeys = ['room_id', 'roomId', 'date', 'start_time', 'startTime', 'exam_session_id', 'session_id', 'applicant_ids', 'applicantIds', 'applicants'];

        foreach ($sessionKeys as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Safely serialize a ResponseData object for logging, avoiding JSON errors
     * when Spatie's Data transformation encounters raw arrays in choices.
     */
    private function safeSerializeResponse(mixed $response): string
    {
        if ($response === null) {
            return 'null';
        }

        // If it's our DTO, extract fields manually to avoid Spatie transformation
        if ($response instanceof ResponseData) {
            $choices = [];
            foreach (($response->choices ?? []) as $choice) {
                if ($choice instanceof NonStreamingChoiceData) {
                    $choices[] = [
                        'finish_reason' => $choice->finish_reason,
                        'message' => [
                            'role' => $choice->message->role,
                            'content' => $choice->message->content,
                        ],
                    ];
                } elseif ($choice instanceof StreamingChoiceData) {
                    $choices[] = [
                        'finish_reason' => $choice->finish_reason,
                        'delta' => ['content' => $choice->delta->content],
                    ];
                } elseif (is_array($choice)) {
                    $choices[] = $choice;
                }
            }

            return json_encode([
                'id' => $response->id,
                'model' => $response->model,
                'object' => $response->object,
                'created' => $response->created,
                'provider' => $response->provider,
                'choices' => $choices,
                'usage' => $response->usage ? [
                    'prompt_tokens' => $response->usage->prompt_tokens,
                    'completion_tokens' => $response->usage->completion_tokens,
                    'total_tokens' => $response->usage->total_tokens,
                    'cost' => $response->usage->cost,
                ] : null,
            ]);
        }

        // Fallback for other objects
        if (is_object($response)) {
            return get_class($response).'::'.json_encode(get_object_vars($response));
        }

        return json_encode($response);
    }
}
