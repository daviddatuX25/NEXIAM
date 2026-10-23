<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('program_advising_notes', function (Blueprint $table): void {
            $table->string('audience', 16)->default('program');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->index(
                ['institution_id', 'program_id', 'academic_cycle_id', 'audience', 'owner_user_id'],
                'program_advising_note_audience_scope_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('program_advising_notes')
            ->where(fn ($query) => $query->where('audience', '!=', 'program')->orWhereNotNull('owner_user_id'))
            ->exists()) {
            throw new RuntimeException('Cannot roll back note audience or ownership after audience-bound notes exist; use a forward-fix migration.');
        }

        Schema::table('program_advising_notes', function (Blueprint $table): void {
            $table->dropIndex('program_advising_note_audience_scope_index');
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn('audience');
        });
    }
};
