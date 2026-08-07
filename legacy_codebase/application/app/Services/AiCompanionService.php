<?php

namespace App\Services;

use App\Models\AiCompanionMessage;
use App\Models\Applicant;
use App\Models\Course;
use App\Models\SystemSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use MoeMizrak\LaravelOpenrouter\DTO\ChatData;
use MoeMizrak\LaravelOpenrouter\DTO\ErrorData;
use MoeMizrak\LaravelOpenrouter\DTO\MessageData;
use MoeMizrak\LaravelOpenrouter\DTO\ResponseData;
use MoeMizrak\LaravelOpenrouter\Facades\LaravelOpenRouter;
use MoeMizrak\LaravelOpenrouter\Types\RoleType;

class AiCompanionService
{
    public const DEFAULT_MAX_HISTORY = 20;

    public const WARNING_THRESHOLD_LENGTH = 1800;

    public const WARNING_THRESHOLD_HISTORY = 17;

    /**
     * Patterns that indicate code generation requests.
     */
    private const CODE_GENERATION_PATTERNS = [
        '/write\s+(a\s+)?(php|javascript|python|sql|html|css)\s+(code|function|script)/i',
        '/generate\s+(a\s+)?(php|javascript|python|sql|html|css)\s+(code|function|script)/i',
        '/create\s+(a\s+)?(program|script|exploit|payload)/i',
        '/how\s+to\s+(hack|exploit|bypass|inject)/i',
    ];

    /**
     * Patterns that indicate prompt injection attempts.
     */
    private const PROMPT_INJECTION_PATTERNS = [
        '/ignore\s+(previous|all|above)\s+(instructions?|rules?|prompts?)/i',
        '/system\s*:/i',
        '/<\|.*\|>/',
        '/\[SYSTEM\]/i',
        '/\#\#\#\s*SYSTEM/i',
        '/override\s+(your\s+)?(instructions?|rules?)/i',
    ];

    /**
     * Patterns that indicate unsafe content.
     */
    private const UNSAFE_CONTENT_PATTERNS = [
        '/violence/i',
        '/threat/i',
        '/self[-\s]?harm/i',
    ];

    public function __construct(
        protected KnowledgeRetrievalService $retrieval
    ) {}

    /**
     * Check if the message contains a code generation request.
     */
    private function containsCodeGenerationRequest(string $message): bool
    {
        foreach (self::CODE_GENERATION_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the message contains a prompt injection attempt.
     */
    private function containsPromptInjection(string $message): bool
    {
        foreach (self::PROMPT_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the message contains unsafe content.
     */
    private function containsUnsafeContent(string $message): bool
    {
        foreach (self::UNSAFE_CONTENT_PATTERNS as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize user input by stripping HTML tags.
     */
    private function sanitizeInput(string $message): string
    {
        return strip_tags($message);
    }

    /**
     * Build applicant context summary (scores + course preferences) for the system prompt.
     */
    public function buildApplicantSummary(Applicant $applicant): string
    {
        $applicant->load(['applicantScores.domain', 'application']);

        $parts = [];

        $scores = $applicant->applicantScores;
        if ($scores->isEmpty()) {
            $parts[] = 'Applicant scores: Not yet available.';
        } else {
            $scoreLines = $scores->map(fn ($s) => sprintf(
                '%s: %s/%s',
                $s->domain?->name ?? $s->domain?->code ?? 'Domain',
                $s->raw_score ?? '—',
                $s->max_score ?? '—'
            ))->unique()->values()->all();
            $parts[] = 'Applicant scores: '.implode('; ', $scoreLines);
        }

        $application = $applicant->application;
        if ($application && (isset($application->course_preference_1) || isset($application->course_preference_2) || isset($application->course_preference_3))) {
            $prefs = [];
            foreach (['course_preference_1', 'course_preference_2', 'course_preference_3'] as $key) {
                $id = $application->{$key} ?? null;
                if (! empty($id)) {
                    $prefs[] = $this->resolveCourseName($id);
                }
            }
            if ($prefs !== []) {
                $parts[] = 'Course preferences: '.implode(', ', array_filter($prefs));
            }
        }

        return implode("\n", $parts);
    }

    private function resolveCourseName($courseId): string
    {
        if (is_numeric($courseId)) {
            $name = DB::table('courses')->where('id', $courseId)->value('name');

            return $name ?? "Course #{$courseId}";
        }

        return (string) $courseId;
    }

    /**
     * Build system prompt: persona + institutional data (retrieved by metadata) + applicant summary (T5).
     */
    public function buildSystemPrompt(Applicant $applicant, string $userMessage = ''): string
    {
        $persona = SystemSetting::personaPrompt();
        $institutional = $this->retrieval->retrieveForApplicant($applicant, $userMessage);
        $applicantSummary = $this->buildApplicantSummary($applicant);
        $coursesInfo = $this->buildCoursesInfo();

        return $persona
            ."\n\nInstitutional data (use only this; do not invent):\n"
            .$institutional
            ."\n\n--- Applicant data ---\n"
            .$applicantSummary
            ."\n--- End applicant data ---\n"
            ."\n--- Available courses ---\n"
            .$coursesInfo
            ."\n--- End available courses ---\n\nUse only the institutional, applicant, and course data above when giving advice. Do not invent statistics. If the data does not cover a question, say so.";
    }

    /**
     * Build a list of available courses for the AI context.
     */
    private function buildCoursesInfo(): string
    {
        $activeCourses = Course::where('is_active', true)
            ->orderBy('name')
            ->get(['name', 'code']);

        if ($activeCourses->isEmpty()) {
            return 'No courses are currently available for enrollment.';
        }

        $courseList = $activeCourses->map(fn ($c) => "{$c->name} ({$c->code})")->implode(', ');

        return "Available courses for application: {$courseList}";
    }

    /**
     * Send a single user message, persist user + assistant, and return the assistant reply (T7).
     * Loads last N messages for context; stores both user and assistant messages.
     *
     * @return array{reply: string, blocked?: bool}
     */
    public function chat(Applicant $applicant, string $userMessage, int $maxHistory = self::DEFAULT_MAX_HISTORY): array
    {
        // (0) Sanitize input
        $userMessage = $this->sanitizeInput($userMessage);

        // (0.1) Check for code generation requests
        if ($this->containsCodeGenerationRequest($userMessage)) {
            return [
                'reply' => "I'm sorry, but I cannot help with code generation. I can assist with questions about our courses, admission requirements, and application process.",
                'blocked' => true,
            ];
        }

        // (0.2) Check for prompt injection attempts
        if ($this->containsPromptInjection($userMessage)) {
            return [
                'reply' => "I noticed an unusual request. Let's focus on helping with your application questions.",
                'blocked' => true,
            ];
        }

        // (0.3) Check for unsafe content
        if ($this->containsUnsafeContent($userMessage)) {
            return [
                'reply' => "I'm here to help with admission-related questions. Please rephrase your question.",
                'blocked' => true,
            ];
        }

        // (1) Append user message to DB (T7.1)
        $userMsg = AiCompanionMessage::create([
            'applicant_id' => $applicant->id,
            'role' => AiCompanionMessage::ROLE_USER,
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        // (2) Load last N messages (T7.3), chronological order for API
        $history = AiCompanionMessage::lastForApplicant($applicant->id, $maxHistory)
            ->get()
            ->sortBy('created_at')
            ->values()
            ->all();

        $systemPrompt = $this->buildSystemPrompt($applicant, $userMessage);
        $model = config('services.openrouter.model', 'openrouter/free');

        $messageData = [
            new MessageData(content: $systemPrompt, role: RoleType::SYSTEM),
        ];

        foreach ($history as $m) {
            $role = $m->role === AiCompanionMessage::ROLE_ASSISTANT ? RoleType::ASSISTANT : RoleType::USER;
            $messageData[] = new MessageData(content: $m->content, role: $role);
        }

        $chatData = new ChatData(
            messages: $messageData,
            model: $model,
            max_tokens: 1024
        );

        $response = LaravelOpenRouter::chatRequest($chatData);

        if ($response instanceof ErrorData) {
            if ($response->code === 429) {
                throw new \RuntimeException('Rate limit reached. Please try again in a moment.');
            }
            throw new \RuntimeException($response->message ?: 'Request failed. Please try again.');
        }

        /** @var ResponseData $response */
        $choice = is_array($response->choices) ? ($response->choices[0] ?? null) : null;
        $content = $choice ? (Arr::get($choice, 'message.content') ?? (is_object($choice) && isset($choice->message) ? $choice->message->content : '')) : '';
        if (is_array($content)) {
            $content = Arr::get($content, '0.text', json_encode($content));
        }
        $reply = (string) $content;

        // (4) Append assistant reply to DB
        AiCompanionMessage::create([
            'applicant_id' => $applicant->id,
            'role' => AiCompanionMessage::ROLE_ASSISTANT,
            'content' => $reply,
            'created_at' => now(),
        ]);

        return ['reply' => $reply];
    }

    /**
     * Clear all chat history for an applicant (T7.4).
     */
    public function clearHistory(Applicant $applicant): void
    {
        AiCompanionMessage::where('applicant_id', $applicant->id)->delete();
    }
}
