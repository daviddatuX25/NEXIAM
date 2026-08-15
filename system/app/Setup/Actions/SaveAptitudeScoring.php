<?php

namespace App\Setup\Actions;

use App\Assessment\Support\ConversionTableValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Transactionally replace an aptitude area's scoring configuration: the
 * guidance-locked `conversion_table` method + `max_items` + range rows.
 * Already-recorded grade snapshots (`score_records.converted_score`) are never
 * touched — the table only applies to scores entered after the save (SCV-3/7).
 */
final class SaveAptitudeScoring
{
    public function __construct(
        private readonly ConversionTableValidator $validator,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @param  array<int, array{min_score: int, max_score: int, output_value: string}>  $rows
     */
    public function handle(
        AptitudeArea $area,
        User $actor,
        string $scoringMethod,
        ?int $maxItems,
        array $rows,
    ): AptitudeArea {
        return DB::transaction(function () use ($area, $actor, $scoringMethod, $maxItems, $rows): AptitudeArea {
            $area = AptitudeArea::query()->lockForUpdate()->findOrFail($area->id);

            if ($scoringMethod !== AptitudeArea::SCORING_METHOD_CONVERSION_TABLE) {
                throw new \RuntimeException('Unsupported scoring method.');
            }

            if ($maxItems === null || $maxItems < 1) {
                throw new \RuntimeException('Maximum score is required for a conversion table.');
            }

            $this->validator->validate($rows, $maxItems);

            $area->update([
                'scoring_method' => $scoringMethod,
                'max_items' => $maxItems,
            ]);

            $area->conversionRows()->delete();

            foreach ($rows as $row) {
                $area->conversionRows()->create([
                    'min_score' => (int) $row['min_score'],
                    'max_score' => (int) $row['max_score'],
                    'output_value' => trim((string) $row['output_value']),
                ]);
            }

            $this->recordAuditEvent->handle('setup.aptitude_scoring_saved', [
                'area_id' => $area->id,
                'code' => $area->code,
                'max_items' => $maxItems,
                'row_count' => count($rows),
            ], (string) $actor->id);

            return $area->fresh(['conversionRows']) ?? $area;
        });
    }
}
