<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_logistics_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->unsignedInteger('capacity_limit');
            $table->string('seating_mode', 32);
            $table->foreignId('seating_arrangement_id')->nullable()->constrained('seating_arrangements')->restrictOnDelete();
            $table->unsignedInteger('arrangement_lock_version')->nullable();
            $table->unsignedSmallInteger('arrangement_rows')->nullable();
            $table->unsignedSmallInteger('arrangement_columns')->nullable();
            $table->string('arrangement_booklet_pattern', 32)->nullable();
            $table->char('seat_manifest_hash', 64)->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_session_id', 'revision'], 'exam_logistics_plans_session_revision_unique');
            $table->unique(['id', 'exam_session_id'], 'exam_logistics_plans_id_session_unique');
            $table->index(['exam_session_id', 'is_current'], 'exam_logistics_plans_session_current_idx');
        });

        Schema::create('exam_session_logistics_seats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_plan_id')->constrained('exam_session_logistics_plans')->restrictOnDelete();
            $table->unsignedInteger('ordinal');
            $table->string('seat_label', 32);
            $table->unsignedSmallInteger('row_number')->nullable();
            $table->unsignedSmallInteger('column_number')->nullable();
            $table->timestamps();
            $table->unique(['logistics_plan_id', 'ordinal'], 'exam_logistics_seats_plan_ordinal_unique');
            $table->unique(['logistics_plan_id', 'seat_label'], 'exam_logistics_seats_plan_label_unique');
            $table->unique(['id', 'logistics_plan_id'], 'exam_logistics_seats_id_plan_unique');
            $table->index(['logistics_plan_id', 'row_number', 'column_number'], 'exam_logistics_seats_plan_position_idx');
        });

        Schema::table('exam_assignments', function (Blueprint $table): void {
            $table->unique(['id', 'exam_session_id'], 'exam_assignments_id_session_unique');
        });

        Schema::create('exam_assignment_logistics_seat_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_assignment_id')->constrained('exam_assignments')->restrictOnDelete();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->restrictOnDelete();
            $table->foreignId('logistics_plan_id')->constrained('exam_session_logistics_plans')->restrictOnDelete();
            $table->foreignId('logistics_seat_id')->constrained('exam_session_logistics_seats')->restrictOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id', 'exam_assignment_id', 'exam_session_id'], 'exam_logistics_alloc_id_assignment_session_unique');
            $table->index(['exam_assignment_id', 'effective_from'], 'exam_logistics_alloc_assignment_history_idx');
            $table->index(['logistics_seat_id', 'effective_from'], 'exam_logistics_alloc_seat_history_idx');
        });

        Schema::table('exam_assignment_logistics_seat_allocations', function (Blueprint $table): void {
            $table->foreign(['exam_assignment_id', 'exam_session_id'], 'exam_logistics_alloc_assignment_session_fk')
                ->references(['id', 'exam_session_id'])->on('exam_assignments')->restrictOnDelete();
            $table->foreign(['logistics_plan_id', 'exam_session_id'], 'exam_logistics_alloc_plan_session_fk')
                ->references(['id', 'exam_session_id'])->on('exam_session_logistics_plans')->restrictOnDelete();
            $table->foreign(['logistics_seat_id', 'logistics_plan_id'], 'exam_logistics_alloc_seat_plan_fk')
                ->references(['id', 'logistics_plan_id'])->on('exam_session_logistics_seats')->restrictOnDelete();
        });

        Schema::table('exam_submission_records', function (Blueprint $table): void {
            $table->foreignId('exam_session_id')->nullable()->after('exam_assignment_id');
            $table->foreignId('exam_assignment_logistics_seat_allocation_id')->nullable()->after('seat_allocation_id');
            $table->index(['exam_assignment_id', 'exam_session_id'], 'exam_submission_assignment_session_idx');
            $table->foreign(['exam_assignment_id', 'exam_session_id'], 'exam_submission_assignment_session_fk')
                ->references(['id', 'exam_session_id'])->on('exam_assignments')->restrictOnDelete();
            $table->foreign(
                ['exam_assignment_logistics_seat_allocation_id', 'exam_assignment_id', 'exam_session_id'],
                'exam_submission_logistics_allocation_tuple_fk',
            )->references(['id', 'exam_assignment_id', 'exam_session_id'])
                ->on('exam_assignment_logistics_seat_allocations')->restrictOnDelete();
        });

        $connection = DB::connection()->getDriverName();
        $currentPredicate = $connection === 'pgsql' ? 'is_current = TRUE' : 'is_current = 1';
        DB::statement("CREATE UNIQUE INDEX exam_logistics_plans_current_unique ON exam_session_logistics_plans (exam_session_id) WHERE {$currentPredicate}");
        DB::statement('CREATE UNIQUE INDEX exam_logistics_alloc_active_assignment_unique ON exam_assignment_logistics_seat_allocations (exam_assignment_id) WHERE effective_to IS NULL');
        DB::statement('CREATE UNIQUE INDEX exam_logistics_alloc_active_seat_unique ON exam_assignment_logistics_seat_allocations (logistics_seat_id) WHERE effective_to IS NULL');
        if ($connection === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE exam_submission_records
                ADD CONSTRAINT exam_submission_logistics_pair_check CHECK (
                    (exam_session_id IS NULL AND exam_assignment_logistics_seat_allocation_id IS NULL)
                    OR (exam_session_id IS NOT NULL AND exam_assignment_logistics_seat_allocation_id IS NOT NULL)
                )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE exam_session_logistics_plans
                ADD CONSTRAINT exam_logistics_plan_capacity_positive CHECK (capacity_limit > 0),
                ADD CONSTRAINT exam_logistics_plan_revision_positive CHECK (revision > 0),
                ADD CONSTRAINT exam_logistics_plan_seating_shape CHECK (
                    (seating_mode = 'simple_capacity' AND seating_arrangement_id IS NULL
                        AND arrangement_lock_version IS NULL AND arrangement_rows IS NULL
                        AND arrangement_columns IS NULL AND arrangement_booklet_pattern IS NULL
                        AND seat_manifest_hash IS NULL)
                    OR
                    (seating_mode = 'assigned_seating' AND seating_arrangement_id IS NOT NULL
                        AND arrangement_lock_version > 0 AND arrangement_rows > 0
                        AND arrangement_columns > 0
                        AND arrangement_booklet_pattern IN ('uniform', 'checkerboard', 'latin_four', 'linear_alternating')
                        AND seat_manifest_hash ~ '^[0-9a-f]{64}$')
                )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE exam_session_logistics_seats
                ADD CONSTRAINT exam_logistics_seat_identity_check CHECK (
                    ordinal > 0 AND length(trim(seat_label)) > 0
                    AND ((row_number IS NULL AND column_number IS NULL)
                        OR (row_number > 0 AND column_number > 0))
                )
            SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE exam_assignment_logistics_seat_allocations
                ADD CONSTRAINT exam_logistics_alloc_window_check CHECK (
                    effective_to IS NULL OR effective_to > effective_from
                ),
                ADD CONSTRAINT exam_logistics_alloc_reason_check CHECK (
                    reason IS NULL OR length(trim(reason)) > 0
                )
            SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION exam_logistics_plan_room_guard_fn() RETURNS trigger AS $$
                DECLARE session_room_id BIGINT; arrangement_ok BOOLEAN;
                BEGIN
                    SELECT room_id INTO session_room_id FROM exam_sessions WHERE id = NEW.exam_session_id FOR UPDATE;
                    IF NEW.seating_mode = 'assigned_seating' THEN
                        SELECT is_active AND (
                            availability_mode = 'all_rooms' OR EXISTS (
                                SELECT 1 FROM seating_arrangement_rooms
                                WHERE seating_arrangement_id = NEW.seating_arrangement_id
                                  AND room_id = session_room_id
                            )
                        ) INTO arrangement_ok
                        FROM seating_arrangements WHERE id = NEW.seating_arrangement_id FOR UPDATE;
                        IF NOT COALESCE(arrangement_ok, FALSE) OR session_room_id IS NULL THEN
                            RAISE EXCEPTION 'Logistics arrangement is not available for the session room';
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS exam_logistics_plan_room_guard ON exam_session_logistics_plans');
            DB::statement('CREATE TRIGGER exam_logistics_plan_room_guard BEFORE INSERT OR UPDATE OF exam_session_id, seating_mode, seating_arrangement_id ON exam_session_logistics_plans FOR EACH ROW EXECUTE FUNCTION exam_logistics_plan_room_guard_fn()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION exam_logistics_allocation_current_guard_fn() RETURNS trigger AS $$
                DECLARE plan_current BOOLEAN; plan_mode TEXT; plan_published_at TIMESTAMP; assignment_status TEXT;
                BEGIN
                    IF NEW.effective_to IS NOT NULL THEN RETURN NEW; END IF;
                    PERFORM 1 FROM exam_sessions WHERE id = NEW.exam_session_id FOR UPDATE;
                    SELECT is_current, seating_mode, published_at
                    INTO plan_current, plan_mode, plan_published_at
                    FROM exam_session_logistics_plans WHERE id = NEW.logistics_plan_id FOR UPDATE;
                    SELECT status INTO assignment_status FROM exam_assignments WHERE id = NEW.exam_assignment_id FOR UPDATE;
                    IF NOT COALESCE(plan_current, FALSE) OR plan_mode <> 'assigned_seating'
                        OR plan_published_at IS NULL OR assignment_status <> 'assigned' THEN
                        RAISE EXCEPTION 'Active logistics seat requires an active assignment and current assigned-seating plan';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS exam_logistics_allocation_current_guard ON exam_assignment_logistics_seat_allocations');
            DB::statement('CREATE TRIGGER exam_logistics_allocation_current_guard BEFORE INSERT OR UPDATE ON exam_assignment_logistics_seat_allocations FOR EACH ROW EXECUTE FUNCTION exam_logistics_allocation_current_guard_fn()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION exam_logistics_plan_freeze_fn() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        IF OLD.published_at IS NOT NULL THEN RAISE EXCEPTION 'Published logistics plan is immutable'; END IF;
                        RETURN OLD;
                    END IF;
                    IF OLD.published_at IS NOT NULL AND (
                        NEW.exam_session_id IS DISTINCT FROM OLD.exam_session_id OR NEW.revision IS DISTINCT FROM OLD.revision
                        OR NEW.capacity_limit IS DISTINCT FROM OLD.capacity_limit OR NEW.seating_mode IS DISTINCT FROM OLD.seating_mode
                        OR NEW.seating_arrangement_id IS DISTINCT FROM OLD.seating_arrangement_id
                        OR NEW.arrangement_lock_version IS DISTINCT FROM OLD.arrangement_lock_version
                        OR NEW.arrangement_rows IS DISTINCT FROM OLD.arrangement_rows
                        OR NEW.arrangement_columns IS DISTINCT FROM OLD.arrangement_columns
                        OR NEW.arrangement_booklet_pattern IS DISTINCT FROM OLD.arrangement_booklet_pattern
                        OR NEW.seat_manifest_hash IS DISTINCT FROM OLD.seat_manifest_hash
                        OR NEW.published_at IS DISTINCT FROM OLD.published_at
                    ) THEN RAISE EXCEPTION 'Published logistics plan is immutable'; END IF;
                    IF OLD.published_at IS NOT NULL AND NEW.is_current IS DISTINCT FROM OLD.is_current
                        AND NEW.is_current THEN RAISE EXCEPTION 'Superseded logistics plan cannot become current'; END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS exam_logistics_plan_freeze ON exam_session_logistics_plans');
            DB::statement('CREATE TRIGGER exam_logistics_plan_freeze BEFORE UPDATE OR DELETE ON exam_session_logistics_plans FOR EACH ROW EXECUTE FUNCTION exam_logistics_plan_freeze_fn()');
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION exam_logistics_seat_freeze_fn() RETURNS trigger AS $$
                DECLARE plan_published_at TIMESTAMP; plan_id BIGINT;
                BEGIN
                    plan_id := CASE WHEN TG_OP = 'DELETE' THEN OLD.logistics_plan_id ELSE NEW.logistics_plan_id END;
                    SELECT published_at INTO plan_published_at FROM exam_session_logistics_plans WHERE id = plan_id FOR UPDATE;
                    IF plan_published_at IS NOT NULL THEN RAISE EXCEPTION 'Published logistics seats are immutable'; END IF;
                    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::statement('DROP TRIGGER IF EXISTS exam_logistics_seat_freeze ON exam_session_logistics_seats');
            DB::statement('CREATE TRIGGER exam_logistics_seat_freeze BEFORE INSERT OR UPDATE OR DELETE ON exam_session_logistics_seats FOR EACH ROW EXECUTE FUNCTION exam_logistics_seat_freeze_fn()');
        } else {
            DB::statement("CREATE TRIGGER exam_logistics_plan_shape_insert BEFORE INSERT ON exam_session_logistics_plans BEGIN SELECT CASE WHEN NEW.capacity_limit <= 0 OR NEW.revision <= 0 OR NOT ((NEW.seating_mode = 'simple_capacity' AND NEW.seating_arrangement_id IS NULL AND NEW.arrangement_lock_version IS NULL AND NEW.arrangement_rows IS NULL AND NEW.arrangement_columns IS NULL AND NEW.arrangement_booklet_pattern IS NULL AND NEW.seat_manifest_hash IS NULL) OR (NEW.seating_mode = 'assigned_seating' AND NEW.seating_arrangement_id IS NOT NULL AND NEW.arrangement_lock_version > 0 AND NEW.arrangement_rows > 0 AND NEW.arrangement_columns > 0 AND NEW.arrangement_booklet_pattern IN ('uniform','checkerboard','latin_four','linear_alternating') AND length(NEW.seat_manifest_hash) = 64)) THEN RAISE(ABORT, 'Invalid session logistics plan') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_plan_shape_update BEFORE UPDATE ON exam_session_logistics_plans BEGIN SELECT CASE WHEN NEW.capacity_limit <= 0 OR NEW.revision <= 0 OR NOT ((NEW.seating_mode = 'simple_capacity' AND NEW.seating_arrangement_id IS NULL AND NEW.arrangement_lock_version IS NULL AND NEW.arrangement_rows IS NULL AND NEW.arrangement_columns IS NULL AND NEW.arrangement_booklet_pattern IS NULL AND NEW.seat_manifest_hash IS NULL) OR (NEW.seating_mode = 'assigned_seating' AND NEW.seating_arrangement_id IS NOT NULL AND NEW.arrangement_lock_version > 0 AND NEW.arrangement_rows > 0 AND NEW.arrangement_columns > 0 AND NEW.arrangement_booklet_pattern IN ('uniform','checkerboard','latin_four','linear_alternating') AND length(NEW.seat_manifest_hash) = 64)) THEN RAISE(ABORT, 'Invalid session logistics plan') END; SELECT CASE WHEN OLD.published_at IS NOT NULL AND (NEW.exam_session_id IS NOT OLD.exam_session_id OR NEW.revision IS NOT OLD.revision OR NEW.capacity_limit IS NOT OLD.capacity_limit OR NEW.seating_mode IS NOT OLD.seating_mode OR NEW.seating_arrangement_id IS NOT OLD.seating_arrangement_id OR NEW.arrangement_lock_version IS NOT OLD.arrangement_lock_version OR NEW.arrangement_rows IS NOT OLD.arrangement_rows OR NEW.arrangement_columns IS NOT OLD.arrangement_columns OR NEW.arrangement_booklet_pattern IS NOT OLD.arrangement_booklet_pattern OR NEW.seat_manifest_hash IS NOT OLD.seat_manifest_hash OR NEW.published_at IS NOT OLD.published_at OR (NEW.is_current = 1 AND OLD.is_current = 0)) THEN RAISE(ABORT, 'Published logistics plan is immutable') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_plan_no_delete BEFORE DELETE ON exam_session_logistics_plans BEGIN SELECT CASE WHEN OLD.published_at IS NOT NULL THEN RAISE(ABORT, 'Published logistics plan is immutable') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_seat_identity_insert BEFORE INSERT ON exam_session_logistics_seats BEGIN SELECT CASE WHEN NEW.ordinal <= 0 OR length(trim(NEW.seat_label)) = 0 OR ((NEW.row_number IS NULL) <> (NEW.column_number IS NULL)) OR (NEW.row_number IS NOT NULL AND (NEW.row_number <= 0 OR NEW.column_number <= 0)) THEN RAISE(ABORT, 'Invalid logistics seat identity') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_seat_freeze_insert BEFORE INSERT ON exam_session_logistics_seats WHEN (SELECT published_at FROM exam_session_logistics_plans WHERE id = NEW.logistics_plan_id) IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Published logistics seats are immutable'); END");
            DB::statement("CREATE TRIGGER exam_logistics_seat_freeze_update BEFORE UPDATE ON exam_session_logistics_seats WHEN (SELECT published_at FROM exam_session_logistics_plans WHERE id = OLD.logistics_plan_id) IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Published logistics seats are immutable'); END");
            DB::statement("CREATE TRIGGER exam_logistics_seat_freeze_delete BEFORE DELETE ON exam_session_logistics_seats WHEN (SELECT published_at FROM exam_session_logistics_plans WHERE id = OLD.logistics_plan_id) IS NOT NULL BEGIN SELECT RAISE(ABORT, 'Published logistics seats are immutable'); END");
            DB::statement("CREATE TRIGGER exam_logistics_alloc_window_insert BEFORE INSERT ON exam_assignment_logistics_seat_allocations BEGIN SELECT CASE WHEN (NEW.effective_to IS NOT NULL AND NEW.effective_to <= NEW.effective_from) OR (NEW.reason IS NOT NULL AND length(trim(NEW.reason)) = 0) THEN RAISE(ABORT, 'Invalid logistics allocation history') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_alloc_window_update BEFORE UPDATE ON exam_assignment_logistics_seat_allocations BEGIN SELECT CASE WHEN (NEW.effective_to IS NOT NULL AND NEW.effective_to <= NEW.effective_from) OR (NEW.reason IS NOT NULL AND length(trim(NEW.reason)) = 0) THEN RAISE(ABORT, 'Invalid logistics allocation history') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_plan_room_guard_insert BEFORE INSERT ON exam_session_logistics_plans WHEN NEW.seating_mode = 'assigned_seating' BEGIN SELECT CASE WHEN NOT EXISTS (SELECT 1 FROM exam_sessions s JOIN seating_arrangements a ON a.id = NEW.seating_arrangement_id WHERE s.id = NEW.exam_session_id AND s.room_id IS NOT NULL AND a.is_active = 1 AND (a.availability_mode = 'all_rooms' OR EXISTS (SELECT 1 FROM seating_arrangement_rooms ar WHERE ar.seating_arrangement_id = a.id AND ar.room_id = s.room_id))) THEN RAISE(ABORT, 'Logistics arrangement is not available for the session room') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_plan_room_guard_update BEFORE UPDATE OF exam_session_id, seating_mode, seating_arrangement_id ON exam_session_logistics_plans WHEN NEW.seating_mode = 'assigned_seating' BEGIN SELECT CASE WHEN NOT EXISTS (SELECT 1 FROM exam_sessions s JOIN seating_arrangements a ON a.id = NEW.seating_arrangement_id WHERE s.id = NEW.exam_session_id AND s.room_id IS NOT NULL AND a.is_active = 1 AND (a.availability_mode = 'all_rooms' OR EXISTS (SELECT 1 FROM seating_arrangement_rooms ar WHERE ar.seating_arrangement_id = a.id AND ar.room_id = s.room_id))) THEN RAISE(ABORT, 'Logistics arrangement is not available for the session room') END; END");
            DB::statement("CREATE TRIGGER exam_submission_logistics_pair_insert BEFORE INSERT ON exam_submission_records BEGIN SELECT CASE WHEN (NEW.exam_session_id IS NULL) <> (NEW.exam_assignment_logistics_seat_allocation_id IS NULL) THEN RAISE(ABORT, 'Logistics submission identity must be all-or-none') END; END");
            DB::statement("CREATE TRIGGER exam_submission_logistics_pair_update BEFORE UPDATE OF exam_session_id, exam_assignment_logistics_seat_allocation_id ON exam_submission_records BEGIN SELECT CASE WHEN (NEW.exam_session_id IS NULL) <> (NEW.exam_assignment_logistics_seat_allocation_id IS NULL) THEN RAISE(ABORT, 'Logistics submission identity must be all-or-none') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_allocation_current_insert BEFORE INSERT ON exam_assignment_logistics_seat_allocations WHEN NEW.effective_to IS NULL BEGIN SELECT CASE WHEN NOT EXISTS (SELECT 1 FROM exam_session_logistics_plans p JOIN exam_assignments a ON a.id = NEW.exam_assignment_id AND a.exam_session_id = NEW.exam_session_id WHERE p.id = NEW.logistics_plan_id AND p.exam_session_id = NEW.exam_session_id AND p.is_current = 1 AND p.published_at IS NOT NULL AND p.seating_mode = 'assigned_seating' AND a.status = 'assigned') THEN RAISE(ABORT, 'Active logistics seat requires an active assignment and current assigned-seating plan') END; END");
            DB::statement("CREATE TRIGGER exam_logistics_allocation_current_update BEFORE UPDATE ON exam_assignment_logistics_seat_allocations WHEN NEW.effective_to IS NULL BEGIN SELECT CASE WHEN NOT EXISTS (SELECT 1 FROM exam_session_logistics_plans p JOIN exam_assignments a ON a.id = NEW.exam_assignment_id AND a.exam_session_id = NEW.exam_session_id WHERE p.id = NEW.logistics_plan_id AND p.exam_session_id = NEW.exam_session_id AND p.is_current = 1 AND p.published_at IS NOT NULL AND p.seating_mode = 'assigned_seating' AND a.status = 'assigned') THEN RAISE(ABORT, 'Active logistics seat requires an active assignment and current assigned-seating plan') END; END");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Exam logistics history is retained; use a forward migration to change this schema.');
    }
};
