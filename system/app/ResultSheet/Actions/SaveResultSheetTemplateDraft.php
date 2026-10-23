<?php

namespace App\ResultSheet\Actions;

use App\Models\ResultSheet\ResultSheetTemplateDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class SaveResultSheetTemplateDraft
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ResultSheetTemplateDraft
    {
        return DB::transaction(function () use ($data, $actor): ResultSheetTemplateDraft {
            return ResultSheetTemplateDraft::query()->create([
                'source_template_id' => $data['source_template_id'] ?? null,
                'owner_user_id' => $actor->id,
                'stage' => (int) $data['stage'],
                'state' => $this->normalizeState($data['state']),
                'lock_version' => 1,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ResultSheetTemplateDraft $draft, array $data, User $actor): ResultSheetTemplateDraft
    {
        if ((int) $draft->owner_user_id !== (int) $actor->id) {
            throw new RuntimeException('draft_forbidden');
        }

        return DB::transaction(function () use ($draft, $data): ResultSheetTemplateDraft {
            $draft = ResultSheetTemplateDraft::query()->lockForUpdate()->findOrFail($draft->id);

            if ((int) $data['lock_version'] !== (int) $draft->lock_version) {
                throw new RuntimeException('draft_conflict');
            }

            $draft->update([
                'stage' => (int) $data['stage'],
                'state' => $this->normalizeState($data['state']),
                'lock_version' => ((int) $draft->lock_version) + 1,
            ]);

            return $draft->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalizeState(array $state): array
    {
        return [
            'name' => (string) ($state['name'] ?? ''),
            'mode' => (string) ($state['mode'] ?? 'html'),
            'source_kind' => $state['source_kind'] ?? null,
            'paper_size' => $state['paper_size'] ?? 'a4',
            'orientation' => $state['orientation'] ?? 'portrait',
            'logical_unit' => $state['logical_unit'] ?? 'full',
            'content' => (string) ($state['content'] ?? ''),
            'builder_json' => $state['builder_json'] ?? null,
            'builder_schema_version' => $state['builder_schema_version'] ?? null,
            'compiler_version' => $state['compiler_version'] ?? null,
            'watermark_text' => (string) ($state['watermark_text'] ?? ''),
            'is_active' => (bool) ($state['is_active'] ?? false),
        ];
    }
}
