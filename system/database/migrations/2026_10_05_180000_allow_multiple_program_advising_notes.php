<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_advising_notes', function (Blueprint $table): void {
            $table->dropUnique('program_advising_notes_institution_id_program_id_academic_cycle_id_unique');
            $table->index(['institution_id', 'program_id', 'academic_cycle_id', 'updated_at'], 'program_advising_notes_scope_updated_index');
        });
    }

    public function down(): void
    {
        Schema::table('program_advising_notes', function (Blueprint $table): void {
            $table->dropIndex('program_advising_notes_scope_updated_index');
            $table->unique(['institution_id', 'program_id', 'academic_cycle_id']);
        });
    }
};
