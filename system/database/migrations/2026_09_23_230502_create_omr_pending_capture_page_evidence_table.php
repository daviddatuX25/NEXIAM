<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_pending_capture_page_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('set_id')->constrained('omr_pending_capture_sets')->restrictOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->uuid('client_capture_id');
            $table->char('input_hash', 64);
            $table->string('asset_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_count');
            $table->foreignId('captured_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('replacement_reason', 500)->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('omr_pending_capture_page_evidence')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['set_id', 'client_capture_id'], 'omr_pending_page_capture_unique');
            $table->index(['set_id', 'page_no', 'id'], 'omr_pending_page_history_idx');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_pending_capture_page_evidence
                    ADD CONSTRAINT omr_pending_page_number_check CHECK (page_no BETWEEN 1 AND 4),
                    ADD CONSTRAINT omr_pending_page_replacement_check CHECK (
                        (supersedes_id IS NULL AND replacement_reason IS NULL)
                        OR (supersedes_id IS NOT NULL AND length(trim(replacement_reason)) > 0)
                    )
                SQL);
            Schema::getConnection()->statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT omr_capture_set_status_check');
            Schema::getConnection()->statement("ALTER TABLE omr_pending_capture_sets ADD CONSTRAINT omr_capture_set_status_check CHECK (status IN ('pending', 'captured', 'ready', 'expired', 'cancelled'))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT omr_capture_set_status_check');
            Schema::getConnection()->statement("ALTER TABLE omr_pending_capture_sets ADD CONSTRAINT omr_capture_set_status_check CHECK (status IN ('pending', 'ready', 'expired', 'cancelled'))");
        }

        Schema::dropIfExists('omr_pending_capture_page_evidence');
    }
};
