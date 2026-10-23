<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_field_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('field_key', 64);
            $table->string('field_group', 32); // identity, contact, academic, program_choices
            $table->string('label', 128);
            $table->string('requirement_level', 32); // system_mandatory, institution_required, optional, disabled
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(100);
            $table->foreignId('last_mutated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['institution_id', 'academic_cycle_id', 'version', 'field_key'], 'uniq_intake_field_policies_key');
            $table->index(['institution_id', 'academic_cycle_id', 'version', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_field_policies');
    }
};
