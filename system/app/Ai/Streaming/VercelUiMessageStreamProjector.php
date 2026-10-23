<?php

namespace App\Ai\Streaming;

use App\Ai\Context\XiamNavigationTargets;
use App\Ai\Privacy\PiiTokenVault;
use App\Models\Ai\XiamRunEvent;
use App\Models\User;
use Generator;
use Illuminate\Contracts\Auth\Authenticatable;

final class VercelUiMessageStreamProjector
{
    public const STREAM_VERSION = 'v1';

    public function __construct(
        private readonly PiiTokenVault $piiTokenVault = new PiiTokenVault,
        private readonly ?XiamNavigationTargets $navigationTargets = null,
    ) {}

    /** @var list<string> */
    private const EMOTIONS = [
        'idle',
        'thinking',
        'searching-paper',
        'searching-phone',
        'hi',
        'thumbs-up',
        'celebrating',
        'star-eyes',
        'laughing',
        'shock',
        'sad',
        'angry',
        'talking-calm',
        'talking-presenting',
        'talking-expressive',
    ];

    /** @var array<string, string> */
    private const ACADEMIC_MICROSTATES = [
        'exams.rooms.available@1' => 'Surveying examination facilities & desk capacities…',
        'foundation.academic_cycles.list@1' => 'Resolving active academic calendar & term…',
        'assessment.unscored_examinees.query@1' => 'Auditing completed rosters for unrecorded scores…',
        'scheduling.candidate_pool.list@1' => 'Reviewing candidate pool for unassigned examinees…',
        'exams.sessions.list@1' => 'Checking scheduled examination timetable…',
        'exams.session.show@1' => 'Loading examination session details…',
        'exams.sessions.today@1' => 'Retrieving today’s scheduled sessions…',
        'workspace.summary@1' => 'Reviewing institutional workspace workload…',
        'setup.readiness.read@1' => 'Auditing institutional setup readiness…',
        'result_sheet.templates.list@1' => 'Inspecting result sheet templates…',
        'results.release.overview@1' => 'Checking grading & result release progress…',
        'knowledge.search@1' => 'Consulting approved institutional policies & FAQ…',
        'exams.session.create@1' => 'Formulating examination session proposal…',
        'exams.session.assign@1' => 'Preparing candidate session assignment…',
        'exams.session.publish@1' => 'Validating session materials & publication…',
        'exams.session.reservation.open@1' => 'Opening session reservation window…',
        'exams.slot.reserve@1' => 'Processing examination slot reservation…',
        'consultation.sessions.list@1' => 'Checking Release Day session timetable…',
        'consultation.session.create@1' => 'Preparing Release Day session draft…',
        'consultation.session.reservation.open@1' => 'Opening Release Day appointment reservations…',
        'consultation.slot.reserve@1' => 'Reserving Release Day appointment…',
    ];

    /** @return array<string, string> */
    public function headers(): array
    {
        return [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'x-vercel-ai-ui-message-stream' => self::STREAM_VERSION,
        ];
    }

    /**
     * @param  iterable<XiamRunEvent>  $events
     * @return Generator<int, string>
     */
    public function project(iterable $events, ?Authenticatable $actor = null, ?string $actorType = null): Generator
    {
        $messageId = null;
        $textStarted = false;
        $textEnded = false;
        $navigationEmitted = false;

        foreach ($events as $event) {
            if ($event->type === 'assistant.navigation') {
                $navigation = ($this->navigationTargets ?? app(XiamNavigationTargets::class))->reproject(
                    $actor instanceof User ? $actor : null,
                    is_array($event->payload['navigation'] ?? null) ? $event->payload['navigation'] : [],
                    $actorType,
                );
                if ($navigation !== []) {
                    yield $this->data(['type' => 'data-xiam-navigation', 'data' => ['navigation' => $navigation]]);
                    $navigationEmitted = true;
                }
            }
            $messageId ??= 'xiam-'.$event->run_id;

            if ($event->type === 'assistant.message') {
                $content = (string) ($event->payload['content'] ?? '');
                $extractedMood = isset($event->payload['mood']) ? strtolower((string) $event->payload['mood']) : null;
                if ($extractedMood === null || $extractedMood === '') {
                    if (preg_match('/<!--\s*xiam:mood\s*=\s*(["\'])([a-zA-Z0-9_-]+)\1\s*-->/i', $content, $moodMatch)) {
                        $extractedMood = strtolower($moodMatch[2]);
                    }
                }
                $content = preg_replace('/<!--\s*xiam:mood\s*=\s*(["\'])[a-zA-Z0-9_-]+\1\s*-->/i', '', $content) ?? $content;
                $content = $this->detokenize($content, $event);
                $navigationTargets = $this->navigationTargets ?? app(XiamNavigationTargets::class);
                $content = $navigationTargets->projectResponse(
                    $content,
                    $actor instanceof User ? $actor : null,
                    $actorType,
                )['text'];
                $messageNavigation = $navigationTargets->reproject(
                    $actor instanceof User ? $actor : null,
                    is_array($event->payload['navigation'] ?? null) ? $event->payload['navigation'] : [],
                    $actorType,
                );
                if (! $navigationEmitted && $messageNavigation !== []) {
                    yield $this->data(['type' => 'data-xiam-navigation', 'data' => ['navigation' => $messageNavigation]]);
                    $navigationEmitted = true;
                }
                if (! $textStarted) {
                    $textStarted = true;
                    yield $this->data(['type' => 'text-start', 'id' => $messageId]);
                    yield $this->data([
                        'type' => 'text-delta',
                        'id' => $messageId,
                        'delta' => $content,
                    ]);
                }
                if (! $textEnded) {
                    $textEnded = true;
                    yield $this->data(['type' => 'text-end', 'id' => $messageId]);
                }

                if ($extractedMood !== null && in_array($extractedMood, self::EMOTIONS, true)) {
                    yield $this->data([
                        'type' => 'data-xiam-avatar',
                        'data' => [
                            'emotion' => $extractedMood,
                            'event_type' => 'assistant.mood_cue',
                        ],
                    ]);
                }
            }

            foreach ($this->customParts($event) as $part) {
                yield $this->data($part);
            }

            if (in_array($event->type, ['run.completed', 'run.failed', 'run.cancelled'], true)) {
                if ($textStarted && ! $textEnded) {
                    $textEnded = true;
                    yield $this->data(['type' => 'text-end', 'id' => $messageId]);
                }
                yield "data: [DONE]\n\n";

                return;
            }
        }

        if ($textStarted && ! $textEnded) {
            yield $this->data(['type' => 'text-end', 'id' => $messageId ?? 'xiam-message']);
        }
        yield "data: [DONE]\n\n";
    }

    private function detokenize(string $text, XiamRunEvent $event): string
    {
        $conversation = $event->conversation;
        if ($conversation === null) {
            return $text;
        }

        return $this->piiTokenVault->detokenize($text, $conversation->id, $conversation->context_epoch);
    }

    /** @return list<array<string, mixed>> */
    private function customParts(XiamRunEvent $event): array
    {
        $parts = [];
        $emotion = $event->type === 'assistant.refusal'
            && ($event->payload['mode'] ?? null) === 'conversational_orientation'
                ? null
                : $this->emotionFor($event->type);
        if ($emotion !== null) {
            $parts[] = [
                'type' => 'data-xiam-avatar',
                'data' => [
                    'emotion' => $emotion,
                    'event_type' => $event->type,
                ],
            ];
        }

        if ($event->type === 'skill.proposal') {
            $parts[] = [
                'type' => 'data-xiam-proposal',
                'data' => [
                    'proposal_id' => $event->payload['proposal_id'] ?? null,
                    'capability_id' => $event->payload['capability_id'] ?? null,
                    'summary' => $event->payload['summary'] ?? [],
                    'diff' => $event->payload['diff'] ?? null,
                    'preflight_checks' => $event->payload['preflight_checks'] ?? null,
                    'expires_at' => $event->payload['expires_at'] ?? null,
                    'run_id' => $event->payload['run_id'] ?? $event->run_id,
                    'status' => $event->payload['status'] ?? 'pending',
                    'proposal_view' => $event->payload['proposal_view'] ?? null,
                ],
            ];
        }

        if (in_array($event->type, ['skill.plan', 'skill.tool', 'skill.result', 'tool.call', 'tool.result'], true)) {
            $phase = (string) ($event->payload['phase'] ?? '');
            $label = match ($phase) {
                'ambient_briefing' => 'Reading live workspace & session queues…',
                'conversational' => 'Preparing response…',
                'policy_inquiry' => 'Checking approved institutional policies…',
                'action_proposal' => 'Preparing operational proposal…',
                default => 'Thinking…',
            };
            $toolName = (string) ($event->payload['tool'] ?? $event->payload['capability_id'] ?? '');
            $isOk = $event->payload['ok'] ?? ($event->payload['successful'] ?? true);
            $stepData = [
                'event_type' => $event->type,
                'capability_id' => $toolName !== '' ? $toolName : null,
                'tool_call_id' => $event->payload['tool_call_id'] ?? null,
                'phase' => $event->payload['phase'] ?? ($event->type === 'tool.call' ? 'executing_tool' : null),
                'mode' => $event->payload['mode'] ?? null,
                'ok' => $isOk,
                'label' => match ($event->type) {
                    'tool.call' => self::ACADEMIC_MICROSTATES[$toolName] ?? 'Consulting institutional operational records…',
                    'tool.result' => $isOk ? 'Information verified.' : 'Operational check failed.',
                    default => $label,
                },
            ];
            if ($event->type === 'skill.plan') {
                $stepData['label'] = $label;
                $stepData['skill'] = $event->payload['skill'] ?? null;
            }

            $parts[] = [
                'type' => 'data-xiam-steps',
                'data' => $stepData,
            ];

            if ($event->type === 'tool.call') {
                $parts[] = [
                    'type' => 'data-xiam-avatar',
                    'data' => [
                        'emotion' => self::toolEmotion($toolName),
                        'event_type' => 'tool.call',
                        'tool' => $toolName,
                    ],
                ];
            }
            if ($event->type === 'tool.result') {
                $isOk = $event->payload['ok'] ?? ($event->payload['successful'] ?? true);
                $parts[] = [
                    'type' => 'data-xiam-avatar',
                    'data' => [
                        'emotion' => $isOk ? 'thumbs-up' : 'sad',
                        'event_type' => 'tool.result',
                        'tool' => $toolName !== '' ? $toolName : null,
                        'tool_end' => $toolName !== '' ? $toolName : null,
                        'incident' => ! $isOk,
                    ],
                ];
            }
        }

        if ($event->type === 'assistant.diff') {
            $parts[] = [
                'type' => 'data-xiam-diff',
                'data' => [
                    'kind' => $event->payload['kind'] ?? null,
                    'label' => $event->payload['label'] ?? null,
                    'value' => $event->payload['value'] ?? null,
                ],
            ];
        }

        if ($event->type === 'assistant.mode_request') {
            $mode = $event->payload['mode'] ?? null;
            if (in_array($mode, ['inquiry', 'assistant'], true)) {
                $parts[] = [
                    'type' => 'data-xiam-mode-request',
                    'data' => [
                        'mode' => $mode,
                        'reason' => $event->payload['reason'] ?? null,
                    ],
                ];
            }
        }

        if ($event->type === 'assistant.citations') {
            $parts[] = [
                'type' => 'data-xiam-citation',
                'data' => [
                    'citations' => $event->payload['citations'] ?? [],
                ],
            ];
        }

        return $parts;
    }

    private static function toolEmotion(string $toolName): string
    {
        return match (true) {
            preg_match('/^(result_sheet|assessment|setup\.readiness)\./', $toolName) === 1 => 'searching-paper',
            preg_match('/^(exams\.slot|consultation\.slot|scheduling)\./', $toolName) === 1 => 'searching-phone',
            default => 'thinking',
        };
    }

    private function emotionFor(string $eventType): ?string
    {
        $emotion = match ($eventType) {
            'run.started' => 'thinking',
            'skill.proposal' => 'star-eyes',
            'run.completed' => 'thumbs-up',
            'run.failed', 'skill.rejected', 'assistant.refusal' => 'sad',
            'run.cancelled' => 'shock',
            default => null,
        };

        return $emotion !== null && in_array($emotion, self::EMOTIONS, true) ? $emotion : null;
    }

    /** @param array<string, mixed> $payload */
    private function data(array $payload): string
    {
        return 'data: '.json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n\n";
    }
}
