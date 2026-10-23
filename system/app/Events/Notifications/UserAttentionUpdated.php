<?php

namespace App\Events\Notifications;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class UserAttentionUpdated implements ShouldBroadcastNow
{
    /**
     * @param  list<string>  $domains
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $domains,
        public readonly string $workspaceId,
        public readonly string $revision,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.attention.updated';
    }

    /**
     * @return array{domains: list<string>, workspace_id: string, revision: string}
     */
    public function broadcastWith(): array
    {
        return [
            'domains' => $this->domains,
            'workspace_id' => $this->workspaceId,
            'revision' => $this->revision,
        ];
    }
}
