<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->unsignedInteger('slots');
            $table->text('last_notice')->nullable();
            $table->foreignId('last_mutated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'academic_cycle_id']);
            $table->index(['academic_cycle_id', 'program_id']);
        });

        Schema::create('program_quota_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_quota_id')->constrained('program_quotas')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('from_slots')->nullable();
            $table->unsignedInteger('to_slots');
            $table->text('notice')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['program_quota_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_quota_changes');
        Schema::dropIfExists('program_quotas');
    }
};
