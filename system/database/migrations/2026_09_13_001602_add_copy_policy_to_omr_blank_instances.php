<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_blank_instances', function (Blueprint $table): void {
            $table->string('copy_policy', 24)->default('unique_instance')->after('page_count');
            $table->unsignedInteger('intended_copy_count')->default(1)->after('copy_policy');
            $table->index(['template_version_id', 'copy_policy', 'status'], 'omr_blank_instances_policy_status_idx');
        });
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                UPDATE omr_answer_sheet_template_versions
                SET status = 'retired', updated_at = NOW()
                WHERE status = 'active'
                  AND id <> (
                    SELECT id
                    FROM omr_answer_sheet_template_versions
                    WHERE status = 'active'
                    ORDER BY activated_at DESC NULLS LAST, id DESC
                    LIMIT 1
                  )
            SQL);
            Schema::getConnection()->statement(<<<'SQL'
                CREATE UNIQUE INDEX omr_one_active_answer_sheet_version
                ON omr_answer_sheet_template_versions (status)
                WHERE status = 'active'
            SQL);
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_blank_instances
                    ADD CONSTRAINT omr_blank_instances_copy_policy_check
                        CHECK (copy_policy IN ('unique_instance', 'reusable_master')),
                    ADD CONSTRAINT omr_blank_instances_intended_copy_count_check
                        CHECK (intended_copy_count > 0)
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement('DROP INDEX IF EXISTS omr_one_active_answer_sheet_version');
            Schema::getConnection()->statement('ALTER TABLE omr_blank_instances DROP CONSTRAINT IF EXISTS omr_blank_instances_copy_policy_check');
            Schema::getConnection()->statement('ALTER TABLE omr_blank_instances DROP CONSTRAINT IF EXISTS omr_blank_instances_intended_copy_count_check');
        }

        Schema::table('omr_blank_instances', function (Blueprint $table): void {
            $table->dropIndex('omr_blank_instances_policy_status_idx');
            $table->dropColumn(['copy_policy', 'intended_copy_count']);
        });
    }
};
