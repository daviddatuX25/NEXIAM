<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_blank_instances', function (Blueprint $table): void {
            $table->foreignId('institution_id')
                ->nullable()
                ->after('template_version_id')
                ->constrained('institutions')
                ->restrictOnDelete();
            $table->foreignId('academic_cycle_id')
                ->nullable()
                ->after('institution_id')
                ->constrained('academic_cycles')
                ->restrictOnDelete();
            $table->index(
                ['institution_id', 'academic_cycle_id', 'display_code'],
                'omr_blank_instances_scope_display_idx',
            );
        });

        DB::table('omr_blank_instances')
            ->whereNull('institution_id')
            ->whereNull('academic_cycle_id')
            ->orderBy('id')
            ->each(function (object $instance): void {
                $matches = DB::table('staff_profiles as staff')
                    ->join('academic_cycles as cycle', function ($join): void {
                        $join->on('cycle.institution_id', '=', 'staff.institution_id')
                            ->on('cycle.code', '=', 'staff.active_cycle_code');
                    })
                    ->where('staff.user_id', $instance->created_by_user_id)
                    ->select(['staff.institution_id', 'cycle.id as academic_cycle_id'])
                    ->get();

                // A single creator profile and cycle match is the only
                // provable historical scope. Unknown or ambiguous rows stay
                // unscoped and are therefore excluded from recovery lookup.
                if ($matches->count() !== 1) {
                    return;
                }

                $match = $matches->first();
                DB::table('omr_blank_instances')
                    ->where('id', $instance->id)
                    ->whereNull('institution_id')
                    ->whereNull('academic_cycle_id')
                    ->update([
                        'institution_id' => $match->institution_id,
                        'academic_cycle_id' => $match->academic_cycle_id,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('omr_blank_instances', function (Blueprint $table): void {
            $table->dropIndex('omr_blank_instances_scope_display_idx');
            $table->dropForeign(['institution_id']);
            $table->dropForeign(['academic_cycle_id']);
            $table->dropColumn(['institution_id', 'academic_cycle_id']);
        });
    }
};
