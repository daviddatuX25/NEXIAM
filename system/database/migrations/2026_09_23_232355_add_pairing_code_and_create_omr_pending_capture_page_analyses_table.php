<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('omr_pending_capture_page_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_id')
                ->unique('omr_pending_page_analysis_evidence_unique')
                ->constrained('omr_pending_capture_page_evidence')
                ->restrictOnDelete();
            $table->string('detected_applicant_number')->nullable();
            $table->string('recognition_code')->nullable();
            $table->json('evidence_json');
            $table->timestamps();
        });

        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->string('pairing_code', 24)->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(<<<'SQL'
                ALTER TABLE omr_pending_capture_sets
                    ADD CONSTRAINT omr_capture_set_pairing_code_check CHECK (
                        pairing_code IS NULL OR pairing_code IN ('pairing_missing', 'pairing_conflict')
                    )
                SQL);
            Schema::getConnection()->statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_pending_page_analysis_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'omr_capture_page_analysis_immutable';
                END;
                $$ LANGUAGE plpgsql
                SQL);
            Schema::getConnection()->statement(<<<'SQL'
                CREATE TRIGGER omr_pending_page_analysis_immutable_trigger
                BEFORE UPDATE OR DELETE ON omr_pending_capture_page_analyses
                FOR EACH ROW EXECUTE FUNCTION omr_pending_page_analysis_immutable()
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('DROP TRIGGER IF EXISTS omr_pending_page_analysis_immutable_trigger ON omr_pending_capture_page_analyses');
            Schema::getConnection()->statement('DROP FUNCTION IF EXISTS omr_pending_page_analysis_immutable()');
            Schema::getConnection()->statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT omr_capture_set_pairing_code_check');
        }

        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->dropColumn('pairing_code');
        });

        Schema::dropIfExists('omr_pending_capture_page_analyses');
    }
};
