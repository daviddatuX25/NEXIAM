<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
            $table->string('registration_mode', 24)->nullable()->after('source_kind');
            $table->string('qr_mode', 24)->nullable()->after('registration_mode');
            $table->unsignedSmallInteger('requested_page_count')->nullable()->after('qr_mode');
        });

        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->string('registration_mode', 24)->nullable()->after('status');
            $table->string('qr_mode', 24)->nullable()->after('registration_mode');
            $table->unsignedSmallInteger('page_count')->nullable()->after('qr_mode');
        });

        Schema::create('omr_qr_disabled_issuances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignId('print_job_id')->nullable()->constrained('print_jobs')->nullOnDelete();
            $table->string('issuance_code', 40)->unique();
            $table->unsignedSmallInteger('page_count');
            $table->string('copy_policy', 24);
            $table->unsignedInteger('intended_copy_count');
            $table->string('status', 16)->default('queued');
            $table->char('artifact_manifest_hash', 64)->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(
                ['template_version_id', 'copy_policy', 'status'],
                'omr_qr_disabled_issuances_policy_status_idx',
            );
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver !== 'pgsql') {
            throw new RuntimeException("OMR QR-disabled issuance constraints require PostgreSQL; got {$driver}.");
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_qr_disabled_issuances
                ADD CONSTRAINT omr_qr_disabled_issuances_page_count_check
                    CHECK (page_count BETWEEN 1 AND 4),
                ADD CONSTRAINT omr_qr_disabled_issuances_copy_policy_check
                    CHECK (copy_policy IN ('unique_instance', 'reusable_master')),
                ADD CONSTRAINT omr_qr_disabled_issuances_copy_count_check
                    CHECK (intended_copy_count > 0),
                ADD CONSTRAINT omr_qr_disabled_issuances_status_check
                    CHECK (status IN ('queued', 'printed', 'void')),
                ADD CONSTRAINT omr_qr_disabled_issuances_artifact_check
                    CHECK (status <> 'printed' OR artifact_manifest_hash IS NOT NULL)
            SQL);
    }

    public function down(): void
    {
        // Additive policy and issuance data are forward-compatible and intentionally retained.
    }
};
