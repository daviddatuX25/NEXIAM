<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_governance_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->string('topic', 24);
            $table->string('subject', 255);
            $table->string('status', 24)->default('open');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('admission_benchmark_revision_id')->nullable()->constrained('admission_benchmark_revisions')->restrictOnDelete();
            $table->foreignId('quota_change_id')->nullable()->constrained('program_quota_changes')->restrictOnDelete();
            $table->timestamps();

            $table->index(['institution_id', 'program_id', 'academic_cycle_id', 'topic', 'status', 'id'], 'pg_threads_scope_topic_status_idx');
            $table->index(['program_id', 'academic_cycle_id', 'created_at', 'id'], 'pg_threads_program_cycle_created_idx');
        });

        Schema::create('program_governance_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_governance_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['program_governance_thread_id', 'created_at', 'id'], 'pg_messages_thread_created_idx');
        });

        Schema::create('program_governance_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_governance_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_read_message_id')->nullable()->constrained('program_governance_messages')->nullOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['program_governance_thread_id', 'user_id'], 'pg_reads_thread_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_governance_reads');
        Schema::dropIfExists('program_governance_messages');
        Schema::dropIfExists('program_governance_threads');
    }
};
