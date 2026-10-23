<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_supplier_stock_lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('page_count');
            $table->char('source_sha256', 64)->nullable();
            $table->string('source_kind', 32)->default('uploaded_original');
            $table->string('vendor_name', 190);
            $table->string('source_reference', 190);
            $table->unsignedInteger('received_quantity');
            $table->unsignedInteger('available_quantity');
            $table->json('page_inventory');
            $table->json('custody_evidence')->nullable();
            $table->unsignedBigInteger('inventory_revision')->default(1);
            $table->string('status', 24)->default('available');
            $table->foreignId('received_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['institution_id', 'academic_cycle_id', 'template_version_id', 'status'], 'omr_supplier_stock_lots_scope_idx');
        });

        Schema::create('omr_supplier_stock_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained('omr_supplier_stock_lots')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained('academic_cycles')->restrictOnDelete();
            $table->foreignId('grading_session_id')->constrained('grading_sessions')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('omr_capture_batches')->restrictOnDelete();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->unsignedSmallInteger('page_count');
            $table->string('copy_policy', 24)->default('supplier_stock');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('used_quantity')->default(0);
            $table->string('status', 24)->default('active');
            $table->uuid('idempotency_key');
            $table->foreignId('allocated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamps();

            $table->unique(['batch_id', 'allocated_by_user_id', 'idempotency_key'], 'omr_supplier_stock_alloc_replay_unique');
            $table->index(['batch_id', 'status'], 'omr_supplier_stock_alloc_batch_idx');
        });

        Schema::create('omr_supplier_stock_allocation_uses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('allocation_id')->constrained('omr_supplier_stock_allocations')->restrictOnDelete();
            $table->foreignId('lot_id')->constrained('omr_supplier_stock_lots')->restrictOnDelete();
            $table->foreignId('set_id')
                ->unique('omr_supplier_stock_use_set_unique')
                ->constrained('omr_pending_capture_sets')
                ->restrictOnDelete();
            $table->foreignId('capture_sheet_id')
                ->unique('omr_supplier_stock_use_sheet_unique')
                ->constrained('omr_capture_sheets')
                ->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->foreignId('supplier_stock_allocation_id')
                ->nullable()
                ->after('issuance_id')
                ->constrained('omr_supplier_stock_allocations')
                ->restrictOnDelete();
            $table->string('identity_mode', 24)->default('shaded_grid')->after('copy_policy');
            $table->json('manual_identity_json')->nullable()->after('pairing_code');
        });

        Schema::table('omr_qr_disabled_page_claims', function (Blueprint $table): void {
            $table->unsignedBigInteger('issuance_id')->nullable()->change();
            $table->foreignId('supplier_stock_allocation_id')
                ->nullable()
                ->after('issuance_id')
                ->constrained('omr_supplier_stock_allocations')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT IF EXISTS omr_capture_set_context_check');
            DB::statement(<<<'SQL'
                ALTER TABLE omr_pending_capture_sets
                    ADD CONSTRAINT omr_capture_set_context_check CHECK (
                        (
                            production_batch_id IS NOT NULL
                            AND diagnostic_run_id IS NULL
                            AND (
                                (issuance_id IS NOT NULL AND supplier_stock_allocation_id IS NULL AND copy_policy IN ('unique_instance', 'reusable_master'))
                                OR (issuance_id IS NULL AND supplier_stock_allocation_id IS NOT NULL AND copy_policy = 'supplier_stock')
                            )
                        )
                        OR (
                            production_batch_id IS NULL
                            AND diagnostic_run_id IS NOT NULL
                            AND issuance_id IS NULL
                            AND supplier_stock_allocation_id IS NULL
                            AND copy_policy = 'diagnostic_only'
                        )
                    )
                SQL);
            DB::statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT IF EXISTS omr_capture_set_pairing_code_check');
            DB::statement(<<<'SQL'
                ALTER TABLE omr_pending_capture_sets
                    ADD CONSTRAINT omr_capture_set_pairing_code_check CHECK (
                        pairing_code IS NULL OR pairing_code IN ('pairing_missing', 'pairing_conflict', 'identity_review_required')
                    )
                SQL);
            DB::statement(<<<'SQL'
                ALTER TABLE omr_qr_disabled_page_claims
                    ADD CONSTRAINT omr_qr_disabled_page_claims_source_xor_check CHECK (
                        (issuance_id IS NOT NULL AND supplier_stock_allocation_id IS NULL)
                        OR (issuance_id IS NULL AND supplier_stock_allocation_id IS NOT NULL)
                    )
                SQL);
            DB::statement(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_supplier_stock_allocation_use_immutable() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'omr_supplier_stock_allocation_use_immutable';
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_supplier_stock_allocation_use_immutable_trigger
                BEFORE UPDATE OR DELETE ON omr_supplier_stock_allocation_uses
                FOR EACH ROW EXECUTE FUNCTION omr_supplier_stock_allocation_use_immutable()
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS omr_supplier_stock_allocation_use_immutable_trigger ON omr_supplier_stock_allocation_uses');
            DB::statement('DROP FUNCTION IF EXISTS omr_supplier_stock_allocation_use_immutable()');
            DB::statement('ALTER TABLE omr_qr_disabled_page_claims DROP CONSTRAINT IF EXISTS omr_qr_disabled_page_claims_source_xor_check');
            DB::statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT IF EXISTS omr_capture_set_pairing_code_check');
            DB::statement(<<<'SQL'
                ALTER TABLE omr_pending_capture_sets
                    ADD CONSTRAINT omr_capture_set_pairing_code_check CHECK (
                        pairing_code IS NULL OR pairing_code IN ('pairing_missing', 'pairing_conflict')
                    )
                SQL);
            DB::statement('ALTER TABLE omr_pending_capture_sets DROP CONSTRAINT IF EXISTS omr_capture_set_context_check');
        }

        Schema::table('omr_qr_disabled_page_claims', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_stock_allocation_id');
        });

        Schema::table('omr_pending_capture_sets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_stock_allocation_id');
            $table->dropColumn(['identity_mode', 'manual_identity_json']);
        });

        Schema::dropIfExists('omr_supplier_stock_allocation_uses');
        Schema::dropIfExists('omr_supplier_stock_allocations');
        Schema::dropIfExists('omr_supplier_stock_lots');
    }
};
