<?php

namespace App\Foundation;

use Illuminate\Support\Arr;
use RuntimeException;

final class ResearchSessionGuideCatalog
{
    private const ROLE_KEYS = ['student', 'registrar', 'guidance', 'academic-leader'];

    /** @var array<string, mixed>|null */
    private ?array $data = null;

    /** @return list<array{key: string, label: string, scenario: string, task_count: int}> */
    public function groups(): array
    {
        return array_map(fn (array $role): array => [
            'key' => $role['key'],
            'label' => $role['label'],
            'scenario' => $role['scenario'],
            'task_count' => count($role['tasks']),
        ], $this->roles());
    }

    /** @return array<string, mixed> */
    public function participant(string $roleKey, ?string $taskId): array
    {
        $role = $this->role($roleKey);
        $tasks = $role['tasks'];
        $selectedTask = $taskId ?? $tasks[0]['id'];
        abort_unless(array_any($tasks, fn (array $task): bool => $task['id'] === $selectedTask), 404);

        return [
            'scenario' => $role['scenario'],
            'title' => $role['title'],
            'context' => $role['context'],
            'briefing' => $role['briefing'],
            'standard_briefing' => $this->data()['facilitator']['standard_briefing'],
            'restrictions' => $role['restrictions'],
            'tasks' => array_map(fn (array $task): array => ['id' => $task['id'], 'text' => $task['text']], $tasks),
            'selected_task' => $selectedTask,
        ];
    }

    /** @return array<string, mixed> */
    public function facilitator(): array
    {
        $facilitator = $this->data()['facilitator'];
        $facilitator['roles'] = array_map(fn (array $role): array => [
            'key' => $role['key'],
            'label' => $role['label'],
            'scenario' => $role['scenario'],
            'context' => $role['context'],
            'briefing' => $role['briefing'],
            'restrictions' => $role['restrictions'],
            'tasks' => array_map(fn (array $task): array => [
                'id' => $task['id'],
                'text' => $task['text'],
                'item_ids' => $task['item_ids'],
            ], $role['tasks']),
        ], $facilitator['roles']);
        $tracePath = resource_path('views/capstone/research-session-guide/use-case-trace.json');
        if (is_file($tracePath)) {
            $trace = json_decode((string) file_get_contents($tracePath), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($trace) || ($trace['schema'] ?? null) !== 1 || ! isset($trace['tasks']) || ! is_array($trace['tasks'])) {
                throw new RuntimeException('Research use-case trace publication is invalid.');
            }
            $required = ['task_id', 'goal', 'workspace', 'capability', 'scope', 'prerequisites', 'outcome', 'limitations', 'source'];
            $expectedIds = [];
            foreach ($this->roles() as $role) {
                foreach ($role['tasks'] as $task) {
                    $expectedIds[] = $task['id'];
                }
            }
            $byTask = [];
            foreach ($trace['tasks'] as $task) {
                if (! is_array($task) || array_diff($required, array_keys($task)) !== [] || ! is_string($task['task_id'])) {
                    throw new RuntimeException('Research use-case trace row is incomplete.');
                }
                $byTask[$task['task_id']] = array_intersect_key($task, array_flip($required));
            }
            if (count($byTask) !== 24 || array_diff($expectedIds, array_keys($byTask)) !== []) {
                throw new RuntimeException('Research use-case trace does not cover the canonical task set.');
            }
            $facilitator['case_trace'] = array_map(fn (string $taskId): array => $byTask[$taskId], $expectedIds);
        } else {
            $facilitator['case_trace'] = [];
        }

        return $facilitator;
    }

    public function afterInstructions(): string
    {
        return $this->data()['after_instructions'];
    }

    /** @return array{id: string, title: string, markdown: string} */
    public function questionnaire(string $roleKey): array
    {
        $this->role($roleKey);

        return $this->data()['questionnaires'][$roleKey];
    }

    /** @return array<string, string> */
    public function sourceVersion(): array
    {
        return $this->data()['source_version'];
    }

    /** @return list<array<string, mixed>> */
    private function roles(): array
    {
        return $this->data()['roles'];
    }

    /** @return array<string, mixed> */
    private function role(string $roleKey): array
    {
        abort_unless(in_array($roleKey, self::ROLE_KEYS, true), 404);
        $role = Arr::first($this->roles(), fn (array $role): bool => $role['key'] === $roleKey);
        abort_if($role === null, 503, 'Research guide publication is incomplete.');

        return $role;
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path = resource_path('views/capstone/research-session-guide/research-data.json');
        abort_unless(is_file($path), 503, 'Research guide content has not been published.');
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)
            || ($data['schema'] ?? null) !== 1
            || count($data['roles'] ?? []) !== 4
            || array_sum(array_map(fn (array $role): int => count($role['tasks'] ?? []), $data['roles'])) !== 24
            || array_diff(self::ROLE_KEYS, array_column($data['roles'], 'key')) !== []) {
            throw new RuntimeException('Research guide publication is incomplete or unsupported.');
        }

        return $this->data = $data;
    }
}
