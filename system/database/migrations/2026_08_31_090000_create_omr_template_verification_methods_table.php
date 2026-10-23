<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_template_verification_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('omr_template_verification_runs')->restrictOnDelete();
            $table->string('method', 16);
            $table->string('method_state', 24)->default('not_started');
            $table->string('target_kind', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('target_version', 64);
            $table->char('target_hash', 64);
            $table->string('plan_version', 16)->default('v1');
            $table->string('plan_seed', 128)->nullable();
            $table->char('plan_hash', 64);
            $table->char('expected_vector_hash', 64);
            $table->foreignId('challenge_id')->nullable()->constrained('omr_physical_challenges')->restrictOnDelete();
            $table->unsignedSmallInteger('required_page_count');
            $table->timestamp('challenge_ready_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['run_id', 'method'], 'omr_verification_methods_run_method_unique');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_template_verification_methods
                    ADD CONSTRAINT omr_verification_methods_method_check
                        CHECK (method IN ('pre_shaded', 'hand_shaded')),
                    ADD CONSTRAINT omr_verification_methods_state_check
                        CHECK (method_state IN ('not_started', 'challenge_ready', 'awaiting_capture', 'analyzing', 'needs_review', 'passed', 'failed')),
                    ADD CONSTRAINT omr_verification_methods_target_kind_check
                        CHECK (target_kind IN ('candidate', 'compiled_version')),
                    ADD CONSTRAINT omr_verification_methods_target_id_check
                        CHECK (target_id > 0),
                    ADD CONSTRAINT omr_verification_methods_page_count_check
                        CHECK (required_page_count > 0),
                    ADD CONSTRAINT omr_verification_methods_lock_version_check
                        CHECK (lock_version >= 0)
            SQL);
        } elseif ($driver === 'sqlite') {
            // SQLite has no PostgreSQL CHECK DDL; model guards provide parity.
        } else {
            throw new RuntimeException("OMR verification methods require pgsql or sqlite; got {$driver}.");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_verification_methods');
    }
};
