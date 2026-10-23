<?php

namespace App\Support\Navigation;

use App\Models\User;
use App\Testing\PracticeWorkspace;

final class NavigationBadgeProjector
{
    /** @var array<string, array{version: int, context_key: string, revision: string, as_of: string, items: array<string, array<string, mixed>|null>, groups: array{messaging: array{case_threads: int, program_support: int, offices: int}}}> */
    private array $memo = [];

    public function __construct(
        private readonly MessagingBadgeProvider $messaging,
        private readonly ?OperationalQueueBadgeProvider $operationalQueues = null,
    ) {}

    /**
     * @return array{
     *   version: 1,
     *   context_key: string,
     *   revision: string,
     *   as_of: string,
     *   items: array<string, array<string, mixed>|null>,
     *   groups: array{messaging: array{case_threads: int, program_support: int, offices: int}}
     * }
     */
    public function forUser(User $user): array
    {
        $contextKey = $this->contextKey($user);
        $memoKey = $user->getKey().':'.$contextKey;
        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        $projection = $this->messaging->forUser($user);
        $operational = ($this->operationalQueues ?? app(OperationalQueueBadgeProvider::class))->forUser($user);
        $items = array_merge(['messaging' => $projection['badge']], $operational['badges']);
        $groups = ['messaging' => $projection['groups']];
        $revision = hash('sha256', json_encode([
            'context_key' => $contextKey,
            'items' => $items,
            'groups' => $groups,
            'watermark_signature' => $projection['watermark_signature'],
            'operational_watermark' => $operational['watermark'],
        ], JSON_THROW_ON_ERROR));

        return $this->memo[$memoKey] = [
            'version' => 1,
            'context_key' => $contextKey,
            'revision' => $revision,
            'as_of' => now()->toIso8601String(),
            'items' => $items,
            'groups' => $groups,
        ];
    }

    private function contextKey(User $user): string
    {
        $scope = $user->actorScope();
        $institutionId = $scope->institutionId;
        $cycle = $scope->activeCycleCode;
        $office = $scope->office;

        if ($user->applicantAccount !== null) {
            $identity = $user->applicantAccount->identity;
            $institutionId = $identity?->institution_id;
            $attempt = $identity?->admissionAttempts()->with('academicCycle')->orderByDesc('id')->first();
            $cycle = $attempt?->academicCycle?->code;
            $office = 'applicant';
        }

        return hash('sha256', json_encode([
            'user_id' => (string) $user->getKey(),
            'institution_id' => $institutionId === null ? null : (string) $institutionId,
            'cycle' => $cycle,
            'office' => $office,
            'practice_set_id' => app(PracticeWorkspace::class)->currentId(),
        ], JSON_THROW_ON_ERROR));
    }
}
