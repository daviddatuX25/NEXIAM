<?php

namespace App\Assessment\Actions\Omr;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Retires one OMR configuration artifact. Retiring a template or booklet
 * version also retires every active pair that uses it. Retired pairs stop
 * appearing for new batches; open batches and already-printed artifacts are
 * unaffected (they carry their own pins).
 */
final class RetireOmrConfiguration
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    public function handle(string $kind, int $id, User $actor): void
    {
        DB::transaction(function () use ($kind, $id, $actor): void {
            match ($kind) {
                'template_version' => $this->retireTemplateVersion($id, $actor),
                'booklet_version' => $this->retireBookletVersion($id, $actor),
                'pair' => $this->retirePair($id, $actor),
                default => throw new RuntimeException('Unknown OMR configuration artifact to retire.'),
            };
        });
    }

    private function retireTemplateVersion(int $id, User $actor): void
    {
        $version = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($id);

        if ($version->status === OmrAnswerSheetTemplateVersion::STATUS_RETIRED) {
            return;
        }

        $version->update([
            'status' => OmrAnswerSheetTemplateVersion::STATUS_RETIRED,
        ]);

        $pairs = OmrTemplateBookletPair::query()
            ->where('template_version_id', $version->id)
            ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
            ->get();
        foreach ($pairs as $pair) {
            $pair->update(['status' => OmrTemplateBookletPair::STATUS_RETIRED]);
        }

        $this->recordAuditEvent->handle('grading.omr_template_retired', [
            'template_version_id' => $version->id,
            'retired_pair_ids' => $pairs->pluck('id')->all(),
        ], (string) $actor->id);
    }

    private function retireBookletVersion(int $id, User $actor): void
    {
        $version = OmrQuestionBookletVersion::query()->lockForUpdate()->findOrFail($id);

        if ($version->status === OmrQuestionBookletVersion::STATUS_RETIRED) {
            return;
        }

        $version->update([
            'status' => OmrQuestionBookletVersion::STATUS_RETIRED,
        ]);

        $pairs = OmrTemplateBookletPair::query()
            ->where('booklet_version_id', $version->id)
            ->where('status', OmrTemplateBookletPair::STATUS_ACTIVE)
            ->get();
        foreach ($pairs as $pair) {
            $pair->update(['status' => OmrTemplateBookletPair::STATUS_RETIRED]);
        }

        $this->recordAuditEvent->handle('grading.omr_booklet_retired', [
            'booklet_version_id' => $version->id,
            'retired_pair_ids' => $pairs->pluck('id')->all(),
        ], (string) $actor->id);
    }

    private function retirePair(int $id, User $actor): void
    {
        $pair = OmrTemplateBookletPair::query()->lockForUpdate()->findOrFail($id);

        if ($pair->status === OmrTemplateBookletPair::STATUS_RETIRED) {
            return;
        }

        $pair->update(['status' => OmrTemplateBookletPair::STATUS_RETIRED]);

        $this->recordAuditEvent->handle('grading.omr_pair_retired', [
            'pair_id' => $pair->id,
            'template_version_id' => $pair->template_version_id,
            'booklet_version_id' => $pair->booklet_version_id,
        ], (string) $actor->id);
    }
}
