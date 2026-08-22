<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_blank_instance_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('instance_id')->constrained('omr_blank_instances')->cascadeOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->char('qr_payload_hash', 64);
            $table->string('signing_key_id', 32);
            $table->char('print_artifact_hash', 64);
            $table->string('status', 16)->default('printed');
            $table->foreignId('captured_page_id')->nullable()->constrained('omr_capture_sheet_pages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['instance_id', 'page_no'], 'omr_blank_instance_pages_instance_page_unique');
            $table->index(['instance_id', 'status'], 'omr_blank_instance_pages_instance_status_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_blank_instance_pages
                    ADD CONSTRAINT omr_blank_instance_pages_status_check
                        CHECK (status IN ('printed', 'captured', 'void')),
                    ADD CONSTRAINT omr_blank_instance_pages_page_no_check
                        CHECK (page_no >= 1)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_blank_instance_pages');
    }
};
