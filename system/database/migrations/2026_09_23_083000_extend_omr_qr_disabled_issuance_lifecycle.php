<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_qr_disabled_issuances', function (Blueprint $table): void {
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->unique('print_job_id', 'omr_qr_disabled_issuance_print_job_unique');
            $table->index(['status', 'created_at'], 'omr_qr_disabled_issuances_history_idx');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }
        if ($driver !== 'pgsql') {
            throw new RuntimeException("QR-disabled issuance lifecycle constraints require PostgreSQL; got {$driver}.");
        }

        Schema::getConnection()->statement(<<<'SQL'
            ALTER TABLE omr_qr_disabled_issuances
                DROP CONSTRAINT omr_qr_disabled_issuances_status_check,
                ADD CONSTRAINT omr_qr_disabled_issuances_status_check
                    CHECK (status IN ('queued', 'rendering', 'printed', 'failed', 'void')),
                ADD CONSTRAINT omr_qr_disabled_issuances_artifact_hash_check
                    CHECK (artifact_manifest_hash IS NULL OR artifact_manifest_hash ~ '^[a-f0-9]{64}$')
        SQL);
    }

    public function down(): void
    {
        if (DB::table('omr_qr_disabled_issuances')->whereIn('status', ['rendering', 'failed'])->exists()) {
            throw new RuntimeException('QR-disabled issuance lifecycle history prevents this migration from being rolled back.');
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_qr_disabled_issuances
                    DROP CONSTRAINT IF EXISTS omr_qr_disabled_issuances_artifact_hash_check,
                    DROP CONSTRAINT IF EXISTS omr_qr_disabled_issuances_status_check,
                    ADD CONSTRAINT omr_qr_disabled_issuances_status_check
                        CHECK (status IN ('queued', 'printed', 'void'))
            SQL);
        }

        Schema::table('omr_qr_disabled_issuances', function (Blueprint $table): void {
            $table->dropUnique('omr_qr_disabled_issuance_print_job_unique');
            $table->dropIndex('omr_qr_disabled_issuances_history_idx');
            $table->dropColumn(['printed_at', 'failed_at', 'failure_code']);
        });
    }
};
