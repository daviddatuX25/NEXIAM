<?php

namespace App\Release\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UnreleaseResult
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(ResultRelease $release, User $actor, string $reason): ResultRelease
    {
        return DB::transaction(function () use ($release, $actor, $reason): ResultRelease {
            $release = ResultRelease::query()->lockForUpdate()->findOrFail($release->id);

            if (! $release->is_current || $release->unreleased_at !== null) {
                throw new RuntimeException('Release is not current.');
            }

            $release->update([
                'is_current' => false,
                'unreleased_at' => now(),
                'unrelease_reason' => $reason,
            ]);

            $this->recordAuditEvent->handle('release.result_unreleased', [
                'release_id' => $release->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $release->fresh();
        });
    }
}
