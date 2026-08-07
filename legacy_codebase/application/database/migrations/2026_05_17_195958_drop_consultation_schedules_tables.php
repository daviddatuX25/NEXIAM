<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop pivot table first (has FK to consultation_schedules)
        Schema::dropIfExists('consultation_schedule_applicant');
        Schema::dropIfExists('consultation_schedules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // These tables were dead code — no rollback needed.
        // Original migrations in 2026_02_19_000007 and 2026_02_19_000008 can recreate if necessary.
    }
};
