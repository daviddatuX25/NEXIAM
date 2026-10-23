<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->foreignId('adopted_sheet_id')
                ->nullable()
                ->unique('omr_capture_set_adopted_sheet_unique')
                ->constrained('omr_capture_sheets')
                ->restrictOnDelete();
            $table->string('resolution_code', 64)->nullable();
        });

        Schema::create('omr_qr_disabled_page_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issuance_id')->constrained('omr_qr_disabled_issuances')->restrictOnDelete();
            $table->foreignId('set_id')->constrained('omr_pending_capture_sets')->restrictOnDelete();
            $table->foreignId('evidence_id')
                ->unique('omr_qr_disabled_page_claims_evidence_unique')
                ->constrained('omr_pending_capture_page_evidence')
                ->restrictOnDelete();
            $table->foreignId('capture_sheet_page_id')
                ->unique('omr_qr_disabled_page_claims_capture_page_unique')
                ->constrained('omr_capture_sheet_pages')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('page_no');
            $table->char('input_hash', 64);
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['set_id', 'page_no'], 'omr_qr_disabled_page_claims_set_page_unique');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE omr_pending_capture_sets
                ADD CONSTRAINT omr_capture_set_resolution_code_check CHECK (
                    resolution_code IS NULL
                    OR (status = 'ready' AND length(trim(resolution_code)) > 0)
                )
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE omr_qr_disabled_page_claims
                ADD CONSTRAINT omr_qr_disabled_page_claims_page_check CHECK (page_no BETWEEN 1 AND 4),
                ADD CONSTRAINT omr_qr_disabled_page_claims_hash_check CHECK (input_hash ~ '^[a-f0-9]{64}$')
            SQL);
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION omr_qr_disabled_page_claim_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'omr_qr_disabled_page_claim_immutable';
            END;
            $$ LANGUAGE plpgsql
            SQL);
        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_qr_disabled_page_claim_immutable_trigger
            BEFORE UPDATE OR DELETE ON omr_qr_disabled_page_claims
            FOR EACH ROW EXECUTE FUNCTION omr_qr_disabled_page_claim_immutable()
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS omr_qr_disabled_page_claim_immutable_trigger ON omr_qr_disabled_page_claims');
            DB::statement('DROP FUNCTION IF EXISTS omr_qr_disabled_page_claim_immutable()');
            DB::statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT omr_capture_set_resolution_code_check');
        }

        Schema::dropIfExists('omr_qr_disabled_page_claims');

        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->dropUnique('omr_capture_set_adopted_sheet_unique');
            $table->dropConstrainedForeignId('adopted_sheet_id');
            $table->dropColumn('resolution_code');
        });
    }
};
