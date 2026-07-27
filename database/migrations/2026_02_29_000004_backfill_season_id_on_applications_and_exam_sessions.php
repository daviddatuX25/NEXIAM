<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a default season and assigns existing applications and exam_sessions to it.
     */
    public function up(): void
    {
        if (! $this->seasonTableExists()) {
            return;
        }

        $defaultSeasonId = $this->ensureDefaultSeason();

        if ($defaultSeasonId === null) {
            return;
        }

        if ($this->applicationsTableHasSeasonId()) {
            DB::table('applications')->whereNull('season_id')->update(['season_id' => $defaultSeasonId]);
        }

        if ($this->examSessionsTableHasSeasonId()) {
            DB::table('exam_sessions')->whereNull('season_id')->update(['season_id' => $defaultSeasonId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! $this->seasonTableExists()) {
            return;
        }

        $defaultSeasonId = DB::table('seasons')->where('academic_year', '2025-2026')->where('semester', '1')->value('id');
        if ($defaultSeasonId === null) {
            return;
        }

        if ($this->applicationsTableHasSeasonId()) {
            DB::table('applications')->where('season_id', $defaultSeasonId)->update(['season_id' => null]);
        }
        if ($this->examSessionsTableHasSeasonId()) {
            DB::table('exam_sessions')->where('season_id', $defaultSeasonId)->update(['season_id' => null]);
        }

        DB::table('seasons')->where('id', $defaultSeasonId)->delete();
    }

    private function seasonTableExists(): bool
    {
        return Schema::hasTable('seasons');
    }

    private function applicationsTableHasSeasonId(): bool
    {
        return Schema::hasTable('applications')
            && Schema::hasColumn('applications', 'season_id');
    }

    private function examSessionsTableHasSeasonId(): bool
    {
        return Schema::hasTable('exam_sessions')
            && Schema::hasColumn('exam_sessions', 'season_id');
    }

    private function ensureDefaultSeason(): ?int
    {
        $existing = DB::table('seasons')->where('academic_year', '2025-2026')->where('semester', '1')->first();
        if ($existing !== null) {
            if (! DB::table('seasons')->where('is_active', true)->exists()) {
                DB::table('seasons')->where('id', $existing->id)->update(['is_active' => true]);
            }
            return (int) $existing->id;
        }

        DB::table('seasons')->where('is_active', true)->update(['is_active' => false]);
        $id = DB::table('seasons')->insertGetId([
            'academic_year' => '2025-2026',
            'semester' => '1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) $id;
    }
};
