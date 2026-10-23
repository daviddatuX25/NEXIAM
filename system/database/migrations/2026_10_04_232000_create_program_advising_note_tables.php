<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_advising_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('current_version')->default(0);
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->unsignedBigInteger('published_publication_id')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'program_id', 'academic_cycle_id']);
            $table->index(['program_id', 'academic_cycle_id', 'published_revision_id']);
        });

        Schema::create('program_advising_note_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_advising_note_id')->constrained('program_advising_notes')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('predecessor_revision_id')->nullable()->constrained('program_advising_note_revisions')->restrictOnDelete();
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('program_advising_note_revisions')->restrictOnDelete();
            $table->foreignId('copied_from_revision_id')->nullable()->constrained('program_advising_note_revisions')->restrictOnDelete();
            $table->longText('content');
            $table->foreignId('authored_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->string('command', 64);
            $table->string('idempotency_operation_key', 255)->unique();
            $table->timestamps();
            $table->unique(['program_advising_note_id', 'version']);
            $table->index(['program_advising_note_id', 'created_at']);
        });

        Schema::create('program_advising_note_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_advising_note_id')->constrained('program_advising_notes')->restrictOnDelete();
            $table->foreignId('revision_id')->constrained('program_advising_note_revisions')->restrictOnDelete();
            $table->foreignId('retracts_publication_id')->nullable()->constrained('program_advising_note_publications')->restrictOnDelete();
            $table->string('operation', 16);
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 500);
            $table->unsignedInteger('expected_version');
            $table->string('idempotency_operation_key', 255)->unique();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['program_advising_note_id', 'recorded_at']);
        });

        Schema::create('program_advising_note_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained()->restrictOnDelete();
            $table->foreignId('program_advising_note_id')->constrained('program_advising_notes')->restrictOnDelete();
            $table->foreignId('revision_id')->constrained('program_advising_note_revisions')->restrictOnDelete();
            $table->foreignId('publication_id')->constrained('program_advising_note_publications')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_operation_key', 255)->unique();
            $table->timestamp('used_at');
            $table->timestamps();
            $table->unique(['admission_attempt_id', 'revision_id', 'actor_user_id', 'idempotency_operation_key'], 'advising_note_usage_identity_unique');
            $table->index(['admission_attempt_id', 'revision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_advising_note_usages');
        Schema::dropIfExists('program_advising_note_publications');
        Schema::dropIfExists('program_advising_note_revisions');
        Schema::dropIfExists('program_advising_notes');
    }
};
