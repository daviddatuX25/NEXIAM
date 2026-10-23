<?php

use App\Ai\Streaming\VercelUiMessageStreamProjector;
use App\Models\Ai\XiamRunEvent;

function xiamProjectorStreamParts(array $events): array
{
    $stream = implode('', iterator_to_array((new VercelUiMessageStreamProjector)->project($events)));

    return collect(explode("\n", $stream))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: {'))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true, flags: JSON_THROW_ON_ERROR))
        ->values()
        ->all();
}

test('assistant mood cues are stripped from text and projected as avatar data', function (): void {
    $parts = xiamProjectorStreamParts([
        new XiamRunEvent([
            'run_id' => 'mood-cue-run',
            'type' => 'assistant.message',
            'payload' => ['content' => 'That is funny. <!--xiam:mood="laughing"-->'],
        ]),
    ]);

    $textDelta = collect($parts)->firstWhere('type', 'text-delta');
    $avatar = collect($parts)->firstWhere('type', 'data-xiam-avatar');

    expect($textDelta['delta'])->toBe('That is funny. ')
        ->and($textDelta['delta'])->not->toContain('xiam:mood')
        ->and($avatar)->toBe([
            'type' => 'data-xiam-avatar',
            'data' => [
                'emotion' => 'laughing',
                'event_type' => 'assistant.mood_cue',
            ],
        ]);
});

test('tool calls project operational avatar postures', function (string $toolName, string $emotion): void {
    $parts = xiamProjectorStreamParts([
        new XiamRunEvent([
            'run_id' => 'tool-call-run',
            'type' => 'tool.call',
            'payload' => ['tool' => $toolName],
        ]),
    ]);

    $avatar = collect($parts)->firstWhere('type', 'data-xiam-avatar');

    expect($avatar)->toBe([
        'type' => 'data-xiam-avatar',
        'data' => [
            'emotion' => $emotion,
            'event_type' => 'tool.call',
            'tool' => $toolName,
        ],
    ]);
})->with([
    'result sheet templates' => ['result_sheet.templates.list@1', 'searching-paper'],
    'exam slot reservation' => ['exams.slot.reserve@1', 'searching-phone'],
]);

test('tool result labels truthfully reflect success or failure', function (): void {
    $parts = xiamProjectorStreamParts([
        new XiamRunEvent([
            'run_id' => 'failed-tool-run',
            'type' => 'tool.result',
            'payload' => ['tool' => 'setup.readiness.read@1', 'ok' => false],
        ]),
        new XiamRunEvent([
            'run_id' => 'successful-tool-run',
            'type' => 'tool.result',
            'payload' => ['tool' => 'knowledge.search@1', 'ok' => true],
        ]),
    ]);

    $labels = collect($parts)
        ->where('type', 'data-xiam-steps')
        ->pluck('data.label')
        ->all();

    expect($labels)->toBe(['Operational check failed.', 'Information verified.']);
});
