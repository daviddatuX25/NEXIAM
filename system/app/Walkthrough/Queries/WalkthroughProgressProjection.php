<?php

namespace App\Walkthrough\Queries;

use App\Models\Admission\ApplicantAccount;
use App\Models\User;
use App\Models\Walkthrough\WalkthroughProgress;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Facades\Schema;

class WalkthroughProgressProjection
{
    /**
     * @return array{
     *     scope: array{accountId: string, institutionId: string, workspaceId: ?string, surface: string, key: string},
     *     records: list<array{pageKey: string, revision: int, cursor: ?string, disposition: string, updatedAt: int}>,
     *     persistenceAvailable: bool
     * }
     */
    public function for(User $user, string $surface): array
    {
        $scope = $this->scopeFor($user, $surface);
        $persistenceAvailable = Schema::hasTable('walkthrough_progress');
        $records = $persistenceAvailable
            ? WalkthroughProgress::query()
                ->where('user_id', $user->getKey())
                ->where('institution_id', $scope['institutionId'])
                ->where('workspace_key', $scope['workspaceKey'])
                ->where('surface', $surface)
                ->orderBy('page_key')
                ->orderBy('revision')
                ->get(['page_key', 'revision', 'cursor', 'disposition', 'updated_at'])
                ->map(static fn (WalkthroughProgress $record): array => [
                    'pageKey' => (string) $record->page_key,
                    'revision' => (int) $record->revision,
                    'cursor' => $record->cursor,
                    'disposition' => (string) $record->disposition,
                    'updatedAt' => (int) $record->updated_at?->getTimestamp(),
                ])
                ->all()
            : [];

        unset($scope['workspaceKey']);

        return [
            'scope' => $scope,
            'records' => $records,
            'persistenceAvailable' => $persistenceAvailable,
        ];
    }

    /**
     * @return array{accountId: string, institutionId: string, workspaceId: ?string, surface: string, key: string, workspaceKey: string}
     */
    public function scopeFor(User $user, string $surface): array
    {
        abort_unless(in_array($surface, ['staff', 'applicant'], true), 403);

        if ($surface === 'applicant') {
            $account = $user->applicantAccount()->with('identity:id,institution_id')->first();
            $institutionId = $account?->identity?->institution_id;
            abort_unless($account instanceof ApplicantAccount && $institutionId !== null, 403);
        } else {
            $institutionId = $user->actorScope()->institutionId;
            abort_unless($institutionId !== null, 403);
        }

        $workspaceId = app(PracticeWorkspace::class)->currentId();
        $workspaceKey = $workspaceId === null ? 'default' : 'practice:'.$workspaceId;
        $key = hash('sha256', implode("\0", [
            (string) $user->getAuthIdentifier(),
            (string) $institutionId,
            $workspaceKey,
            $surface,
        ]));

        return [
            'accountId' => (string) $user->getAuthIdentifier(),
            'institutionId' => (string) $institutionId,
            'workspaceId' => $workspaceId,
            'surface' => $surface,
            'key' => $key,
            'workspaceKey' => $workspaceKey,
        ];
    }
}
