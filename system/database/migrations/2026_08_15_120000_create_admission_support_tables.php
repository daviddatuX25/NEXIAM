<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable pool-version headers (D-090/D-093). Highest committed version = current.
        Schema::create('admission_attempt_program_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('predecessor_id')->nullable()->constrained('admission_attempt_program_pools')->restrictOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reason', 500)->nullable();
            $table->string('source', 32); // guidance
            $table->string('idempotency_key', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['admission_attempt_id', 'version']);
            $table->unique(['idempotency_key'], 'program_pools_idempotency_unique');
        });

        // Immutable children of one pool header — never edited or deleted.
        Schema::create('admission_attempt_program_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_pool_id')->constrained('admission_attempt_program_pools')->cascadeOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->unsignedInteger('display_order');
            $table->string('basis', 32); // preference | guidance_option
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['program_pool_id', 'program_id']);
            $table->unique(['program_pool_id', 'display_order']);
        });

        // Pre-enrollment choices referencing their exact option/pool version.
        Schema::create('admission_attempt_program_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('program_pool_id')->constrained('admission_attempt_program_pools')->restrictOnDelete();
            $table->foreignId('program_pool_option_id')->constrained('admission_attempt_program_options')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->string('status', 32); // pending_acknowledgement|active|superseded|program_declined|cancelled|paper_finalized
            $table->string('source', 32); // student_self | guidance_default
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('applicant_acknowledged_at')->nullable();
            $table->string('commitment_identity', 64)->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->string('terminal_reason', 500)->nullable();
            $table->foreignId('enrollment_stamp_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            // One non-terminal selection per attempt (partial index; works on SQLite + Postgres).
            $table->unique(['admission_attempt_id', 'idempotency_key'], 'program_selections_attempt_idempotency_unique');
            $table->index(['admission_attempt_id', 'status']);
            $table->index(['program_id', 'status']);
        });

        // Targeted PH edge-case follow-ups. State never doubles as option exclusion.
        Schema::create('admission_attempt_program_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('program_pool_id')->nullable()->constrained('admission_attempt_program_pools')->restrictOnDelete();
            $table->foreignId('opened_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32); // open|appearance_requested|cleared_by_ph|declined_by_ph
            $table->string('reason', 500);
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resolution_reason', 500)->nullable();
            $table->string('idempotency_key', 255)->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['idempotency_key'], 'program_follow_ups_idempotency_unique');
            $table->index(['admission_attempt_id', 'program_id', 'status']);
        });

        // One open follow-up per attempt/program (D-089 invariant).
        DB::statement(
            'CREATE UNIQUE INDEX program_follow_ups_one_open_unique '
            .'ON admission_attempt_program_follow_ups (admission_attempt_id, program_id) '
            ."WHERE status IN ('open', 'appearance_requested')"
        );

        // Immutable PH decline outcomes (direct + follow-up write the same authority).
        Schema::create('admission_attempt_program_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('program_pool_id')->constrained('admission_attempt_program_pools')->restrictOnDelete();
            $table->foreignId('selection_id')->nullable()->constrained('admission_attempt_program_selections')->restrictOnDelete();
            $table->foreignId('follow_up_id')->nullable()->constrained('admission_attempt_program_follow_ups')->restrictOnDelete();
            $table->foreignId('declined_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('source', 32); // direct_selection | follow_up
            $table->string('reason', 500);
            $table->string('idempotency_key', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['admission_attempt_id', 'program_id', 'program_pool_id'], 'program_declines_attempt_program_pool_unique');
            $table->unique(['idempotency_key'], 'program_declines_idempotency_unique');
        });

        // Append-only Registrar enrollment stamps; the stamp owns final-program truth.
        Schema::create('admission_attempt_enrollment_stamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->string('stamp_type', 32); // enrolling|enrolled|reverse_enrolling|reverse_enrolled
            $table->foreignId('provisional_program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->foreignId('final_program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->foreignId('stamped_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('selection_id')->nullable()->constrained('admission_attempt_program_selections')->restrictOnDelete();
            $table->string('paper_reference', 255)->nullable();
            $table->string('paper_hash', 64)->nullable();
            $table->string('verifier', 255)->nullable();
            $table->string('reason', 500);
            $table->json('quota_variance')->nullable();
            $table->unsignedBigInteger('version')->default(0);
            $table->string('idempotency_key', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['idempotency_key'], 'enrollment_stamps_idempotency_unique');
            $table->index(['admission_attempt_id', 'created_at']);
        });

        Schema::table('admission_attempt_program_selections', function (Blueprint $table) {
            $table->foreign('enrollment_stamp_id')->references('id')->on('admission_attempt_enrollment_stamps')->restrictOnDelete();
        });

        // One non-terminal selection per attempt (D-090 invariant) — partial index,
        // identical syntax on Postgres and SQLite. Created AFTER the FK-add table
        // rebuild (Laravel's SQLite ALTER emulation drops raw DB::statement indexes).
        DB::statement(
            'CREATE UNIQUE INDEX program_selections_one_non_terminal_unique '
            .'ON admission_attempt_program_selections (admission_attempt_id) '
            ."WHERE status IN ('pending_acknowledgement', 'active')"
        );

        // Server-owned threads: internal PH<->Guidance, or bounded applicant support.
        Schema::create('record_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->restrictOnDelete();
            $table->foreignId('program_id')->nullable()->constrained('programs')->restrictOnDelete();
            $table->string('thread_type', 32); // internal_ph_guidance | applicant_support
            $table->string('subject', 500);
            $table->string('status', 32)->default('open'); // open|read_only|closed
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index(['admission_attempt_id', 'thread_type']);
        });

        Schema::create('record_thread_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_thread_id')->constrained('record_threads')->restrictOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['record_thread_id', 'created_at']);
        });

        Schema::create('record_thread_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_thread_id')->constrained('record_threads')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('last_read_at')->useCurrent();

            $table->unique(['record_thread_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_thread_reads');
        Schema::dropIfExists('record_thread_messages');
        Schema::dropIfExists('record_threads');
        Schema::dropIfExists('admission_attempt_program_declines');
        Schema::dropIfExists('admission_attempt_program_selections');
        Schema::dropIfExists('admission_attempt_program_follow_ups');
        Schema::dropIfExists('admission_attempt_enrollment_stamps');
        Schema::dropIfExists('admission_attempt_program_options');
        Schema::dropIfExists('admission_attempt_program_pools');
    }
};
