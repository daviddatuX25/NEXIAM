<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applicant_scores', 'domain_id')) {
            // Column already renamed (fresh install using updated create migration).
            return;
        }

        // Root cause analysis:
        // The composite unique index app_scores_gs_app_dom_unique covers
        // (grading_session_id, applicant_id, domain_id). When Laravel created the
        // table, MySQL used THIS index as the supporting index for the FK on
        // grading_session_id (leftmost column match) — no separate index was created
        // for that FK. Dropping the composite index therefore hits MySQL error 1553.
        //
        // Additionally, a prior partial migration run already dropped the domain_id FK,
        // leaving an orphaned standalone index applicant_scores_domain_id_foreign.
        //
        // Strategy:
        //   1. Dynamically drop any FK on grading_session_id or domain_id
        //   2. Drop the orphaned standalone domain_id index if present
        //   3. Drop composite unique + rename column (now unblocked)
        //   4. Re-add grading_session_id FK, aptitude_area_id FK, new unique

        // Only run MySQL-specific FK detection when using MySQL.
        // SQLite does not support information_schema and does not enforce FKs
        // the same way, so no FK-dropping is needed in that environment.
        if (DB::getDriverName() === 'mysql') {
            foreach (['grading_session_id', 'domain_id'] as $col) {
                $rows = DB::select("
                    SELECT CONSTRAINT_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME   = 'applicant_scores'
                      AND COLUMN_NAME  = ?
                      AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$col]);
                foreach ($rows as $row) {
                    DB::statement("ALTER TABLE `applicant_scores` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
                }
            }

            // Drop orphaned standalone index on domain_id (exists when domain_id FK was
            // created separately from the composite unique index).
            $orphan = DB::select("
                SELECT INDEX_NAME FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'applicant_scores'
                  AND INDEX_NAME   = 'applicant_scores_domain_id_foreign'
                LIMIT 1
            ");
            if ($orphan) {
                DB::statement('ALTER TABLE `applicant_scores` DROP INDEX `applicant_scores_domain_id_foreign`');
            }
        }

        // Composite unique index now has no FK using it — safe to drop + rename.
        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->dropUnique('app_scores_gs_app_dom_unique');
            $table->renameColumn('domain_id', 'aptitude_area_id');
        });

        // Restore grading_session_id FK (was temporarily dropped above),
        // add new aptitude_area_id FK + new unique constraint.
        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->foreign('grading_session_id')
                ->references('id')->on('grading_sessions')
                ->cascadeOnDelete();
            $table->foreign('aptitude_area_id')
                ->references('id')->on('aptitude_areas')
                ->cascadeOnDelete();
            $table->unique(
                ['grading_session_id', 'applicant_id', 'aptitude_area_id'],
                'app_scores_gs_app_area_unique'
            );
        });
    }

    public function down(): void
    {
        // Drop FK on aptitude_area_id dynamically (handles any FK name).
        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'applicant_scores'
              AND COLUMN_NAME  = 'aptitude_area_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        foreach ($rows as $row) {
            DB::statement("ALTER TABLE `applicant_scores` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
        }

        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->dropUnique('app_scores_gs_app_area_unique');
            $table->renameColumn('aptitude_area_id', 'domain_id');
        });

        Schema::table('applicant_scores', function (Blueprint $table) {
            $table->foreign('domain_id')
                ->references('id')->on('exam_domains')
                ->cascadeOnDelete();
            $table->unique(
                ['grading_session_id', 'applicant_id', 'domain_id'],
                'app_scores_gs_app_dom_unique'
            );
        });
    }
};
