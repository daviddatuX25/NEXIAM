<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restore the one-active-assignment invariant after SQLite table rebuilds.
     */
    public function up(): void
    {
        $this->createPartialIndex();
    }

    /**
     * Keep the previous logical schema invariant when rolling back.
     */
    public function down(): void
    {
        $this->createPartialIndex();
    }

    private function createPartialIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS exam_assignments_one_active_per_attempt');
        DB::statement(
            "CREATE UNIQUE INDEX exam_assignments_one_active_per_attempt ON exam_assignments (admission_attempt_id) WHERE status = 'assigned'"
        );
    }
};
