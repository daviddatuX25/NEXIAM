<?php

namespace App\Walkthrough\Actions;

use App\Models\User;
use App\Models\Walkthrough\WalkthroughProgress;
use App\Walkthrough\Queries\WalkthroughProgressProjection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreWalkthroughProgress
{
    public function __construct(
        private readonly WalkthroughProgressProjection $projection,
    ) {}

    /**
     * @param  array{scope_key: string, page_key: string, revision: int, cursor: ?string, disposition: string}  $validated
     * @return array{scope_key: string, record: array{pageKey: string, revision: int, cursor: ?string, disposition: string, updatedAt: int}}
     */
    public function handle(User $user, string $surface, array $validated): array
    {
        abort_unless(Schema::hasTable('walkthrough_progress'), 503, 'Walkthrough progress storage is unavailable.');
        $scope = $this->projection->scopeFor($user, $surface);
        abort_unless(hash_equals($scope['key'], $validated['scope_key']), 409);

        return DB::transaction(function () use ($user, $scope, $validated): array {
            $timestamp = now();
            WalkthroughProgress::query()->insertOrIgnore([
                'user_id' => $user->getAuthIdentifier(),
                'institution_id' => $scope['institutionId'],
                'workspace_key' => $scope['workspaceKey'],
                'surface' => $scope['surface'],
                'page_key' => $validated['page_key'],
                'revision' => $validated['revision'],
                'cursor' => $validated['cursor'],
                'disposition' => 'in_progress',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $progress = WalkthroughProgress::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('institution_id', $scope['institutionId'])
                ->where('workspace_key', $scope['workspaceKey'])
                ->where('surface', $scope['surface'])
                ->where('page_key', $validated['page_key'])
                ->where('revision', $validated['revision'])
                ->lockForUpdate()
                ->firstOrFail();

            $canAdvance = $progress->disposition === 'in_progress'
                || ($progress->disposition === 'skipped' && $validated['disposition'] === 'completed');
            if ($canAdvance) {
                $progress->fill([
                    'cursor' => $validated['cursor'] ?? $progress->cursor,
                    'disposition' => $validated['disposition'],
                ])->save();
            }

            return [
                'scope_key' => $scope['key'],
                'record' => [
                    'pageKey' => (string) $progress->page_key,
                    'revision' => (int) $progress->revision,
                    'cursor' => $progress->cursor,
                    'disposition' => (string) $progress->disposition,
                    'updatedAt' => (int) $progress->updated_at?->getTimestamp(),
                ],
            ];
        });
    }
}
