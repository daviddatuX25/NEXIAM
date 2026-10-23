<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException("Seating persistence requires pgsql or sqlite; got {$driver}.");
        }

        $connection->statement(<<<'SQL'
            CREATE UNIQUE INDEX exam_session_material_snapshots_current_unique
                ON exam_session_material_snapshots (exam_session_id)
                WHERE is_current = true
        SQL);
        $connection->statement(<<<'SQL'
            CREATE UNIQUE INDEX exam_assignment_seat_allocations_active_assignment_unique
                ON exam_assignment_seat_allocations (exam_assignment_id)
                WHERE effective_to IS NULL
        SQL);
        $connection->statement(<<<'SQL'
            CREATE UNIQUE INDEX exam_assignment_seat_allocations_active_seat_unique
                ON exam_assignment_seat_allocations (exam_session_seat_id)
                WHERE effective_to IS NULL
        SQL);

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE exam_session_material_snapshots
                ADD CONSTRAINT exam_session_material_snapshots_revision_check CHECK (revision >= 1),
                ADD CONSTRAINT exam_session_material_snapshots_current_state_check
                    CHECK (
                        (is_current = true AND superseded_at IS NULL)
                        OR (is_current = false AND superseded_at IS NOT NULL)
                    ),
                ADD CONSTRAINT exam_session_material_snapshots_arrangement_shape_check
                    CHECK (
                        (seating_arrangement_id IS NULL
                            AND arrangement_lock_version IS NULL
                            AND arrangement_rows IS NULL
                            AND arrangement_columns IS NULL
                            AND arrangement_booklet_pattern IS NULL
                            AND seat_manifest_hash IS NULL)
                        OR
                        (seating_arrangement_id IS NOT NULL
                            AND arrangement_lock_version >= 1
                            AND arrangement_rows >= 1
                            AND arrangement_columns >= 1
                            AND arrangement_booklet_pattern IN ('uniform', 'checkerboard', 'latin_four', 'linear_alternating')
                            AND char_length(seat_manifest_hash) = 64)
                    ),
                ADD CONSTRAINT exam_session_material_snapshots_backfill_status_check
                    CHECK (legacy_backfill_status IN ('not_attempted', 'backfilled', 'unresolved'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE exam_session_seats
                ADD CONSTRAINT exam_session_seats_ordinal_check CHECK (ordinal >= 1),
                ADD CONSTRAINT exam_session_seats_row_check CHECK (row_number >= 1),
                ADD CONSTRAINT exam_session_seats_column_check CHECK (column_number >= 1),
                ADD CONSTRAINT exam_session_seats_label_check CHECK (char_length(trim(seat_label)) > 0)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE exam_assignment_seat_allocations
                ADD CONSTRAINT exam_assignment_seat_allocations_window_check
                    CHECK (effective_to IS NULL OR effective_to > effective_from),
                ADD CONSTRAINT exam_assignment_seat_allocations_reason_check
                    CHECK (reason IS NULL OR char_length(trim(reason)) > 0)
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE exam_assignments
                ADD CONSTRAINT exam_assignments_legacy_seat_status_check
                    CHECK (legacy_seat_allocation_status IN ('not_attempted', 'backfilled', 'unresolved', 'not_applicable'))
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE exam_submission_records
                ADD CONSTRAINT exam_submission_records_legacy_seat_status_check
                    CHECK (legacy_seat_allocation_status IN ('not_attempted', 'backfilled', 'unresolved'))
        SQL);
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        $connection->statement('DROP INDEX IF EXISTS exam_assignment_seat_allocations_active_seat_unique');
        $connection->statement('DROP INDEX IF EXISTS exam_assignment_seat_allocations_active_assignment_unique');
        $connection->statement('DROP INDEX IF EXISTS exam_session_material_snapshots_current_unique');

        if ($connection->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE exam_submission_records DROP CONSTRAINT IF EXISTS exam_submission_records_legacy_seat_status_check');
        DB::statement('ALTER TABLE exam_assignments DROP CONSTRAINT IF EXISTS exam_assignments_legacy_seat_status_check');
        DB::statement('ALTER TABLE exam_assignment_seat_allocations DROP CONSTRAINT IF EXISTS exam_assignment_seat_allocations_reason_check');
        DB::statement('ALTER TABLE exam_assignment_seat_allocations DROP CONSTRAINT IF EXISTS exam_assignment_seat_allocations_window_check');
        DB::statement('ALTER TABLE exam_session_seats DROP CONSTRAINT IF EXISTS exam_session_seats_label_check');
        DB::statement('ALTER TABLE exam_session_seats DROP CONSTRAINT IF EXISTS exam_session_seats_column_check');
        DB::statement('ALTER TABLE exam_session_seats DROP CONSTRAINT IF EXISTS exam_session_seats_row_check');
        DB::statement('ALTER TABLE exam_session_seats DROP CONSTRAINT IF EXISTS exam_session_seats_ordinal_check');
        DB::statement('ALTER TABLE exam_session_material_snapshots DROP CONSTRAINT IF EXISTS exam_session_material_snapshots_backfill_status_check');
        DB::statement('ALTER TABLE exam_session_material_snapshots DROP CONSTRAINT IF EXISTS exam_session_material_snapshots_arrangement_shape_check');
        DB::statement('ALTER TABLE exam_session_material_snapshots DROP CONSTRAINT IF EXISTS exam_session_material_snapshots_current_state_check');
        DB::statement('ALTER TABLE exam_session_material_snapshots DROP CONSTRAINT IF EXISTS exam_session_material_snapshots_revision_check');
    }
};
