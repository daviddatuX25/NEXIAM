<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->legacyTables() as $table) {
            Schema::dropIfExists($table);
        }

        foreach ($this->xiamTables() as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('xiam_conversations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type', 32);
            $table->string('actor_id', 64);
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            $table->string('surface_key', 64);
            $table->string('partition_key', 128);
            $table->uuid('context_epoch');
            $table->unsignedBigInteger('epoch_version')->default(1);
            $table->string('browser_session_id', 128)->nullable();
            $table->string('route_name', 128)->nullable();
            $table->string('surface_profile_id', 128)->nullable();
            $table->string('status', 32)->default('open');
            $table->timestamps();
            $table->index(['actor_type', 'actor_id', 'surface_key', 'status']);
            $table->index(['partition_key', 'status', 'epoch_version']);
        });

        Schema::create('xiam_turns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->index();
            $table->unsignedInteger('seq');
            $table->string('mode', 32)->default('inquiry');
            $table->string('page_pack_key')->nullable();
            $table->unsignedInteger('page_pack_version')->nullable();
            $table->text('input_text');
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamps();
            $table->unique(['conversation_id', 'seq']);
            $table->foreign('conversation_id')->references('id')->on('xiam_conversations')->cascadeOnDelete();
        });

        Schema::create('xiam_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('turn_id')->unique();
            $table->uuid('conversation_id')->index();
            $table->string('status', 32)->default('queued');
            $table->string('publication_version', 64)->nullable();
            $table->string('gateway_route', 64)->default('fake');
            $table->string('provider_request_id', 128)->nullable();
            $table->string('pricing_version', 64)->nullable();
            $table->string('usage_state', 32)->default('known');
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->foreign('turn_id')->references('id')->on('xiam_turns')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('xiam_conversations')->cascadeOnDelete();
        });

        Schema::create('xiam_run_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('conversation_id')->index();
            $table->unsignedInteger('seq');
            $table->string('type', 64);
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['run_id', 'seq']);
            $table->unique(['conversation_id', 'seq']);
            $table->foreign('run_id')->references('id')->on('xiam_runs')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('xiam_conversations')->cascadeOnDelete();
        });

        Schema::create('xiam_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('conversation_id')->index();
            $table->string('status', 32)->default('proposed');
            $table->string('capability_id')->nullable();
            $table->unsignedInteger('expected_version')->nullable();
            $table->uuid('execution_id')->nullable()->unique();
            $table->string('canonical_digest', 128)->nullable()->unique();
            $table->string('confirmation_idempotency_key', 128)->nullable()->unique();
            $table->json('summary')->nullable();
            $table->json('receipt')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->foreign('run_id')->references('id')->on('xiam_runs')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('xiam_conversations')->cascadeOnDelete();
        });

        Schema::create('xiam_usage_reservations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type', 32);
            $table->string('actor_id', 64);
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            $table->uuid('run_id')->nullable()->unique();
            $table->string('unit', 32)->default('asks');
            $table->unsignedInteger('amount')->default(1);
            $table->unsignedInteger('reserved_tokens')->default(0);
            $table->unsignedBigInteger('reserved_cost_micro')->default(0);
            $table->string('state', 32)->default('reserved');
            $table->string('provider_request_id', 128)->nullable();
            $table->timestamps();
            $table->index(['actor_type', 'actor_id', 'state']);
        });

        Schema::create('xiam_task_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type', 32);
            $table->string('actor_id', 64);
            $table->string('session_id', 128)->nullable();
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->string('surface_key', 64);
            $table->string('skill_key');
            $table->unsignedInteger('skill_version')->default(1);
            $table->json('allowed_capability_ids');
            $table->json('scope_bounds')->nullable();
            $table->string('origin_pack_key')->nullable();
            $table->unsignedInteger('origin_pack_version')->nullable();
            $table->uuid('context_epoch');
            $table->string('status', 32)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamps();
            $table->index(['actor_type', 'actor_id', 'status']);
            $table->index(['surface_key', 'status']);
        });

        Schema::create('xiam_consent_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type', 32);
            $table->string('actor_id', 64);
            $table->string('session_id', 128)->nullable();
            $table->string('surface_key', 64);
            $table->unsignedInteger('disclosure_version');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['actor_type', 'actor_id', 'surface_key']);
        });

        Schema::create('xiam_disclosure_ledgers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('context_epoch')->index();
            $table->uuid('task_grant_id')->nullable()->index();
            $table->uuid('run_id')->nullable()->index();
            $table->unsignedInteger('unique_targets')->default(0);
            $table->unsignedInteger('sensitive_fields')->default(0);
            $table->unsignedInteger('rows_pages')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ($this->xiamTables() as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** @return list<string> */
    private function legacyTables(): array
    {
        return [
            'nexi_disclosure_ledgers',
            'nexi_consent_records',
            'nexi_task_grants',
            'nexi_usage_reservations',
            'nexi_proposals',
            'nexi_run_events',
            'nexi_runs',
            'nexi_turns',
            'nexi_conversations',
        ];
    }

    /** @return list<string> */
    private function xiamTables(): array
    {
        return [
            'xiam_disclosure_ledgers',
            'xiam_consent_records',
            'xiam_task_grants',
            'xiam_usage_reservations',
            'xiam_proposals',
            'xiam_run_events',
            'xiam_runs',
            'xiam_turns',
            'xiam_conversations',
        ];
    }
};
