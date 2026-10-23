<?php

namespace App\Examination\Support;

use App\Foundation\SeatingArrangementPreview;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

final class ExamSnapshotInterpretation
{
    public function __construct(private readonly SeatingArrangementPreview $preview) {}

    /** @return array{capacity_limit: int, required_set_codes: list<string>, rows: list<array<string, mixed>>, codes: list<string>} */
    public function manifest(ExamSessionMaterialSnapshot $snapshot): array
    {
        $seats = $snapshot->seats()->orderBy('ordinal')->get();
        $pins = $snapshot->sets()->get()->keyBy('booklet_set_id');
        if ($seats->isEmpty()) {
            throw new RuntimeException('Frozen manifest is missing.');
        }
        $rows = [];
        $codes = [];
        foreach ($seats as $index => $seat) {
            $pin = $pins->get($seat->booklet_set_id);
            if ((int) $seat->ordinal !== $index + 1 || $pin === null || ! in_array($pin->set_code, ['A', 'B', 'C', 'D'], true)) {
                throw new RuntimeException('Frozen manifest is incomplete or has missing immutable Set pins.');
            }
            $rows[] = [
                'ordinal' => (int) $seat->ordinal,
                'row_number' => (int) $seat->row_number,
                'column_number' => (int) $seat->column_number,
                'seat_label' => (string) $seat->seat_label,
                'booklet_set_id' => (int) $seat->booklet_set_id,
            ];
            $codes[] = (string) $pin->set_code;
        }
        if (! is_string($snapshot->seat_manifest_hash) || ! hash_equals($snapshot->seat_manifest_hash, hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('Frozen manifest hash does not verify.');
        }
        $requiredCodes = array_values(array_unique($codes));
        sort($requiredCodes);

        return ['capacity_limit' => count($rows), 'required_set_codes' => $requiredCodes, 'rows' => $rows, 'codes' => $codes];
    }

    /** @return array{mode: ?string, reason: string} */
    public function classify(ExamSessionMaterialSnapshot $snapshot): array
    {
        try {
            $manifest = $this->manifest($snapshot);
            if ($snapshot->seating_arrangement_id === null && $snapshot->arrangement_rows === null && $snapshot->arrangement_columns === null && $snapshot->arrangement_booklet_pattern === null) {
                foreach ($manifest['rows'] as $index => $row) {
                    if ($row['seat_label'] !== 'logical-'.($index + 1) || $manifest['codes'][$index] !== 'A') {
                        throw new RuntimeException('No retained logical-capacity publication provenance.');
                    }
                }

                return ['mode' => ExamSession::SEATING_MODE_SIMPLE_CAPACITY, 'reason' => 'Verified complete logical manifest with no arrangement provenance.'];
            }
            if ($snapshot->seating_arrangement_id !== null && (int) $snapshot->arrangement_lock_version > 0) {
                if ($snapshot->seating_mode !== null && $snapshot->seating_mode !== ExamSession::SEATING_MODE_ASSIGNED_SEATING) {
                    throw new RuntimeException('Retained arrangement geometry does not prove deliberate assigned-seating intent.');
                }
                $rows = (int) $snapshot->arrangement_rows;
                $columns = (int) $snapshot->arrangement_columns;
                if ($rows * $columns !== $manifest['capacity_limit']) {
                    throw new RuntimeException('Retained physical geometry does not cover the frozen manifest.');
                }
                $desks = $this->preview->forGeometry($rows, $columns, (string) $snapshot->arrangement_booklet_pattern);
                foreach ($desks as $index => $desk) {
                    $row = $manifest['rows'][$index];
                    if ($row['row_number'] !== $desk['row'] || $row['column_number'] !== $desk['column'] || $row['seat_label'] !== $desk['label'] || $manifest['codes'][$index] !== $desk['booklet']) {
                        throw new RuntimeException('Frozen manifest differs from retained physical pattern.');
                    }
                }

                return ['mode' => ExamSession::SEATING_MODE_ASSIGNED_SEATING, 'reason' => 'Verified retained arrangement revision, geometry and physical pattern.'];
            }
            throw new RuntimeException('Retained publication intent is ambiguous.');
        } catch (RuntimeException|InvalidArgumentException $exception) {
            return ['mode' => null, 'reason' => $exception->getMessage()];
        }
    }

    public function enrich(ExamSessionMaterialSnapshot $snapshot, string $mode): void
    {
        if (! in_array($mode, ExamSession::SEATING_MODES, true) || $snapshot->seating_mode !== null || $snapshot->capacity_limit !== null || $snapshot->required_set_codes !== null) {
            throw new RuntimeException('Only previously unknown snapshot interpretation can be enriched.');
        }
        $manifest = $this->manifest($snapshot);
        $metadata = [
            'seating_mode' => $mode,
            'capacity_limit' => $manifest['capacity_limit'],
            'required_set_codes' => json_encode($manifest['required_set_codes'], JSON_THROW_ON_ERROR),
        ];
        if ($mode === ExamSession::SEATING_MODE_SIMPLE_CAPACITY) {
            $metadata['seating_arrangement_id'] = null;
        }
        $updated = DB::table($snapshot->getTable())->where('id', $snapshot->id)
            ->whereNull('seating_mode')->whereNull('capacity_limit')->whereNull('required_set_codes')
            ->update($metadata);
        if ($updated !== 1) {
            throw new RuntimeException('Snapshot interpretation changed concurrently.');
        }
    }
}
