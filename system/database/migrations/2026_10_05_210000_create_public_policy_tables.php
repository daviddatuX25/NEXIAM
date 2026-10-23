<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('publisher_key', 100);
            $table->string('publisher_label', 160);
            $table->foreignId('program_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('content');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->string('withdrawal_reason', 1000)->nullable();
            $table->timestampsTz();
            $table->index(['institution_id', 'program_id', 'status', 'updated_at'], 'public_policies_public_scope_idx');
            $table->index(['institution_id', 'publisher_key', 'status', 'updated_at'], 'public_policies_staff_scope_idx');
        });

        Schema::create('public_policy_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_policy_id')->constrained('public_policies')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('publisher_key', 100);
            $table->string('publisher_label', 160);
            $table->foreignId('program_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->text('content');
            $table->string('state', 16);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name', 160);
            $table->string('reason', 1000)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['public_policy_id', 'revision'], 'public_policy_revisions_version_unique');
            $table->unique(['public_policy_id', 'id'], 'public_policy_revisions_policy_id_unique');
        });

        Schema::table('public_policies', function (Blueprint $table): void {
            $table->foreign(['id', 'published_revision_id'], 'public_policies_published_revision_fk')
                ->references(['public_policy_id', 'id'])->on('public_policy_revisions')->restrictOnDelete();
        });

        Schema::create('public_policy_command_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('operation', 16);
            $table->foreignId('public_policy_id')->nullable()->constrained('public_policies')->restrictOnDelete();
            $table->string('required_capability', 100);
            $table->unsignedInteger('expected_revision')->nullable();
            $table->char('payload_hash', 64);
            $table->json('result_snapshot');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['actor_id', 'request_id'], 'public_policy_receipts_actor_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_policy_command_receipts');
        Schema::table('public_policies', function (Blueprint $table): void {
            $table->dropForeign('public_policies_published_revision_fk');
        });
        Schema::dropIfExists('public_policy_revisions');
        Schema::dropIfExists('public_policies');
    }
};
