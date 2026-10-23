<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('publisher_key', 100);
            $table->string('publisher_label', 160);
            $table->string('title', 80);
            $table->string('summary', 160);
            $table->text('body');
            $table->string('priority', 16)->default('normal');
            $table->boolean('is_featured')->default(false);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('withdrawal_reason', 500)->nullable();
            $table->timestampsTz();
            $table->index(['institution_id', 'status', 'starts_at', 'ends_at', 'id'], 'announcements_public_window_idx');
            $table->index(['institution_id', 'status', 'is_featured', 'priority', 'published_at', 'id'], 'announcements_featured_idx');
            $table->index(['institution_id', 'publisher_key', 'status', 'updated_at'], 'announcements_staff_scope_idx');
        });

        Schema::create('announcement_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('publisher_key', 100);
            $table->string('publisher_label', 160);
            $table->string('title', 80);
            $table->string('summary', 160);
            $table->text('body');
            $table->string('priority', 16);
            $table->boolean('is_featured');
            $table->string('status', 16);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name', 160);
            $table->string('reason', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['announcement_id', 'revision'], 'announcement_revision_unique');
            $table->index(['announcement_id', 'created_at']);
        });

        Schema::create('announcement_command_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('operation', 24);
            $table->string('required_capability', 100)->default('communications.announcements.manage');
            $table->unsignedBigInteger('announcement_id')->nullable();
            $table->unsignedInteger('expected_revision')->nullable();
            $table->char('payload_hash', 64);
            $table->json('result');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['actor_id', 'request_id'], 'announcement_receipt_actor_request_unique');
            $table->index(['announcement_id', 'operation']);
            $table->foreign('announcement_id')->references('id')->on('announcements')->restrictOnDelete();
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
ALTER TABLE announcements
    ADD CONSTRAINT announcements_status_check CHECK (status IN ('draft', 'published', 'withdrawn')),
    ADD CONSTRAINT announcements_priority_check CHECK (priority IN ('normal', 'urgent')),
    ADD CONSTRAINT announcements_revision_check CHECK (revision >= 1),
    ADD CONSTRAINT announcements_public_window_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE announcement_revisions
    ADD CONSTRAINT announcement_revisions_status_check CHECK (status IN ('draft', 'published', 'withdrawn')),
    ADD CONSTRAINT announcement_revisions_priority_check CHECK (priority IN ('normal', 'urgent')),
    ADD CONSTRAINT announcement_revisions_revision_check CHECK (revision >= 1),
    ADD CONSTRAINT announcement_revisions_public_window_check CHECK (starts_at IS NULL OR ends_at IS NULL OR ends_at > starts_at)
SQL);
            DB::statement(<<<'SQL'
ALTER TABLE announcement_command_receipts
    ADD CONSTRAINT announcement_receipts_operation_check CHECK (operation IN ('save', 'publish', 'withdraw')),
    ADD CONSTRAINT announcement_receipts_capability_check CHECK (required_capability IN ('communications.announcements.manage', 'communications.announcements.publish')),
    ADD CONSTRAINT announcement_receipts_expected_revision_check CHECK (expected_revision IS NULL OR expected_revision >= 1)
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_command_receipts');
        Schema::dropIfExists('announcement_revisions');
        Schema::dropIfExists('announcements');
    }
};
