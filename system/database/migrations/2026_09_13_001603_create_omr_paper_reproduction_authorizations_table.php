<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_paper_reproduction_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exam_session_id')->constrained('exam_sessions')->restrictOnDelete();
            $table->foreignId('blank_instance_page_id')->constrained('omr_blank_instance_pages')->restrictOnDelete();
            $table->unsignedSmallInteger('maximum_claims');
            $table->unsignedSmallInteger('used_claims')->default(0);
            $table->string('status', 16)->default('open');
            $table->text('reason');
            $table->timestampTz('expires_at');
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('authorized_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reconciled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestamps();

            $table->index(['exam_session_id', 'status', 'expires_at'], 'omr_reproduction_session_status_idx');
            $table->index(['blank_instance_page_id', 'status'], 'omr_reproduction_page_status_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_paper_reproduction_authorizations
                    ADD CONSTRAINT omr_reproduction_status_check
                        CHECK (status IN ('open', 'reconciled', 'void')),
                    ADD CONSTRAINT omr_reproduction_claim_count_check
                        CHECK (maximum_claims > 0 AND used_claims <= maximum_claims)
            SQL);
            Schema::getConnection()->statement(<<<'SQL'
                CREATE UNIQUE INDEX omr_reproduction_one_open_per_session_page
                ON omr_paper_reproduction_authorizations (exam_session_id, blank_instance_page_id)
                WHERE status = 'open'
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_paper_reproduction_authorizations');
    }
};
