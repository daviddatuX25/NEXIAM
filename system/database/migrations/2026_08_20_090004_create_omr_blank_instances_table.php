<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_blank_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->string('instance_code', 40)->unique();
            $table->string('display_code', 24)->unique();
            $table->unsignedInteger('page_set_no');
            $table->unsignedSmallInteger('page_count');
            $table->string('status', 16)->default('printed');
            $table->foreignId('print_job_id')->nullable()->constrained('print_jobs')->nullOnDelete();
            $table->foreignId('superseded_by_instance_id')->nullable()->constrained('omr_blank_instances')->nullOnDelete();
            $table->char('qr_manifest_hash', 64);
            $table->string('signing_key_id', 32);
            $table->unsignedSmallInteger('signature_version')->default(1);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['template_version_id', 'page_set_no'], 'omr_blank_instances_version_page_set_unique');
            $table->index(['template_version_id', 'status'], 'omr_blank_instances_version_status_idx');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_blank_instances
                    ADD CONSTRAINT omr_blank_instances_status_check
                        CHECK (status IN ('printed', 'captured', 'superseded', 'void')),
                    ADD CONSTRAINT omr_blank_instances_page_count_check
                        CHECK (page_count > 0),
                    ADD CONSTRAINT omr_blank_instances_signature_version_check
                        CHECK (signature_version > 0),
                    ADD CONSTRAINT omr_blank_instances_instance_code_check
                        CHECK (instance_code ~ '^NI-[0-9A-HJKMNP-TV-Z]{26}$')
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_blank_instances');
    }
};
