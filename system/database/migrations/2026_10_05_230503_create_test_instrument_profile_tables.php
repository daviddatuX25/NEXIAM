<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_instruments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->boolean('is_synthetic')->default(false);
            $table->unsignedBigInteger('current_approved_version_id')->nullable();
            $table->timestamps();
            $table->unique(['institution_id', 'code'], 'test_instruments_institution_code_unique');
            $table->unique(['id', 'institution_id'], 'test_instruments_id_institution_unique');
        });

        Schema::create('test_instrument_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_instrument_id')->constrained('test_instruments')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('draft');
            $table->string('display_name', 255);
            $table->string('publisher_name', 255)->nullable();
            $table->string('form_code', 64)->nullable();
            $table->string('edition', 128)->nullable();
            $table->string('language', 32)->nullable();
            $table->string('source_reference', 500)->nullable();
            $table->string('permitted_use_reference', 500)->nullable();
            $table->foreignId('source_reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('source_reviewed_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->unsignedBigInteger('predecessor_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('content_sha256', 64)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['test_instrument_id', 'version_number'], 'test_instrument_versions_number_unique');
            $table->unique(['id', 'test_instrument_id'], 'test_instrument_versions_id_owner_unique');
            $table->foreign(['predecessor_version_id', 'test_instrument_id'], 'test_instrument_versions_predecessor_owner_foreign')
                ->references(['id', 'test_instrument_id'])
                ->on('test_instrument_versions')
                ->restrictOnDelete();
            $table->index(['test_instrument_id', 'status'], 'test_instrument_versions_status_index');
        });

        Schema::table('test_instruments', function (Blueprint $table): void {
            $table->foreign(['current_approved_version_id', 'id'], 'test_instruments_current_version_owner_foreign')
                ->references(['id', 'test_instrument_id'])
                ->on('test_instrument_versions')
                ->restrictOnDelete();
        });

        Schema::create('test_instrument_subtests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_instrument_version_id')->constrained('test_instrument_versions')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->unsignedSmallInteger('display_order');
            $table->string('response_mode', 24);
            $table->string('item_order_policy', 32)->default('fixed');
            $table->timestamps();
            $table->unique(['test_instrument_version_id', 'code'], 'test_instrument_subtests_code_unique');
            $table->unique(['test_instrument_version_id', 'id'], 'test_instrument_subtests_id_owner_unique');
            $table->index(['test_instrument_version_id', 'display_order'], 'test_instrument_subtests_order_index');
        });

        Schema::create('test_instrument_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_instrument_version_id')->constrained('test_instrument_versions')->restrictOnDelete();
            $table->unsignedBigInteger('test_instrument_subtest_id');
            $table->string('item_code', 64);
            $table->string('local_number', 32);
            $table->unsignedSmallInteger('display_order');
            $table->boolean('is_scored')->default(true);
            $table->json('option_tokens')->nullable();
            $table->timestamps();
            $table->foreign(['test_instrument_version_id', 'test_instrument_subtest_id'], 'test_instrument_items_subtest_owner_foreign')
                ->references(['test_instrument_version_id', 'id'])
                ->on('test_instrument_subtests')
                ->restrictOnDelete();
            $table->unique(['test_instrument_version_id', 'test_instrument_subtest_id', 'item_code'], 'test_instrument_items_code_unique');
            $table->unique(['test_instrument_version_id', 'test_instrument_subtest_id', 'local_number'], 'test_instrument_items_local_number_unique');
            $table->unique(['test_instrument_version_id', 'test_instrument_subtest_id', 'display_order'], 'test_instrument_items_order_unique');
        });

        Schema::create('test_instrument_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_instrument_version_id')->constrained('test_instrument_versions')->restrictOnDelete();
            $table->string('code', 32);
            $table->string('name', 255);
            $table->string('rule_kind', 32);
            $table->unsignedBigInteger('test_instrument_subtest_id')->nullable();
            $table->string('unit', 64)->nullable();
            $table->decimal('min_raw_value', 18, 6)->nullable();
            $table->decimal('max_raw_value', 18, 6)->nullable();
            $table->unsignedTinyInteger('precision_scale')->nullable();
            $table->boolean('is_required')->default(true);
            $table->json('required_output_scales')->default('[]');
            $table->string('procedure_reference', 500)->nullable();
            $table->foreignId('construct_aptitude_area_id')->nullable()->constrained('aptitude_areas')->restrictOnDelete();
            $table->timestamps();
            $table->foreign(['test_instrument_version_id', 'test_instrument_subtest_id'], 'test_instrument_metrics_subtest_owner_foreign')
                ->references(['test_instrument_version_id', 'id'])
                ->on('test_instrument_subtests')
                ->restrictOnDelete();
            $table->unique(['test_instrument_version_id', 'code'], 'test_instrument_metrics_code_unique');
            $table->unique(['test_instrument_version_id', 'id'], 'test_instrument_metrics_id_owner_unique');
            $table->index(['test_instrument_version_id', 'is_required'], 'test_instrument_metrics_required_index');
        });

        Schema::create('test_instrument_metric_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_instrument_version_id')->constrained('test_instrument_versions')->restrictOnDelete();
            $table->unsignedBigInteger('metric_id');
            $table->unsignedBigInteger('input_metric_id');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->foreign(['test_instrument_version_id', 'metric_id'], 'test_instrument_metric_inputs_output_owner_foreign')
                ->references(['test_instrument_version_id', 'id'])
                ->on('test_instrument_metrics')
                ->restrictOnDelete();
            $table->foreign(['test_instrument_version_id', 'input_metric_id'], 'test_instrument_metric_inputs_input_owner_foreign')
                ->references(['test_instrument_version_id', 'id'])
                ->on('test_instrument_metrics')
                ->restrictOnDelete();
            $table->unique(['test_instrument_version_id', 'metric_id', 'input_metric_id'], 'test_instrument_metric_inputs_unique');
            $table->unique(['test_instrument_version_id', 'metric_id', 'position'], 'test_instrument_metric_inputs_order_unique');
        });

        DB::statement("CREATE UNIQUE INDEX test_instrument_one_draft_per_parent ON test_instrument_versions (test_instrument_id) WHERE status = 'draft'");
        DB::statement("CREATE UNIQUE INDEX test_instrument_one_approved_version_per_parent ON test_instrument_versions (test_instrument_id) WHERE status = 'approved'");
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE test_instrument_versions ADD CONSTRAINT test_instrument_versions_status_check CHECK (status IN ('draft', 'approved', 'retired'))");
            DB::statement("ALTER TABLE test_instrument_versions ADD CONSTRAINT test_instrument_versions_state_check CHECK ((status = 'draft' AND approved_by_user_id IS NULL AND approved_at IS NULL AND retired_by_user_id IS NULL AND retired_at IS NULL AND source_reviewed_by_user_id IS NULL AND source_reviewed_at IS NULL AND content_sha256 IS NULL) OR (status = 'approved' AND approved_by_user_id IS NOT NULL AND approved_at IS NOT NULL AND retired_by_user_id IS NULL AND retired_at IS NULL AND source_reviewed_by_user_id IS NOT NULL AND source_reviewed_at IS NOT NULL AND content_sha256 IS NOT NULL AND content_sha256 ~ '^[a-f0-9]{64}$') OR (status = 'retired' AND approved_by_user_id IS NOT NULL AND approved_at IS NOT NULL AND retired_by_user_id IS NOT NULL AND retired_at IS NOT NULL AND source_reviewed_by_user_id IS NOT NULL AND source_reviewed_at IS NOT NULL AND content_sha256 IS NOT NULL AND content_sha256 ~ '^[a-f0-9]{64}$'))");
            DB::statement('ALTER TABLE test_instrument_versions ADD CONSTRAINT test_instrument_versions_lock_check CHECK (lock_version > 0)');
            DB::statement("ALTER TABLE test_instrument_subtests ADD CONSTRAINT test_instrument_subtests_mode_check CHECK (response_mode IN ('keyed_items', 'recorded_measure'))");
            DB::statement("ALTER TABLE test_instrument_subtests ADD CONSTRAINT test_instrument_subtests_order_policy_check CHECK (item_order_policy IN ('fixed', 'within_subtest_permutable'))");
            DB::statement("ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_rule_check CHECK (rule_kind IN ('correct_item_count', 'sum_raw_metrics', 'recorded_measure'))");
            DB::statement('ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_precision_check CHECK (precision_scale IS NULL OR precision_scale BETWEEN 0 AND 6)');
            DB::statement('ALTER TABLE test_instrument_metrics ADD CONSTRAINT test_instrument_metrics_bounds_check CHECK ((min_raw_value IS NULL AND max_raw_value IS NULL) OR (min_raw_value IS NOT NULL AND max_raw_value IS NOT NULL AND min_raw_value <= max_raw_value))');
            DB::statement('ALTER TABLE test_instrument_metric_inputs ADD CONSTRAINT test_instrument_metric_inputs_position_check CHECK (position > 0 AND metric_id <> input_metric_id)');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION test_instrument_version_guard() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status <> 'draft' THEN
            RAISE EXCEPTION 'Approved test instrument versions are immutable';
        END IF;
        RETURN OLD;
    END IF;

    IF OLD.status = 'retired' THEN
        RAISE EXCEPTION 'Retired test instrument versions are immutable';
    END IF;

    IF OLD.status = 'draft' AND NEW.status NOT IN ('draft', 'approved') THEN
        RAISE EXCEPTION 'Draft test instrument versions may only be saved or approved';
    END IF;

    IF OLD.status = 'approved' AND NOT (
        NEW.status = 'retired'
        AND NEW.id = OLD.id
        AND NEW.test_instrument_id = OLD.test_instrument_id
        AND NEW.version_number = OLD.version_number
        AND NEW.display_name = OLD.display_name
        AND NEW.publisher_name IS NOT DISTINCT FROM OLD.publisher_name
        AND NEW.form_code IS NOT DISTINCT FROM OLD.form_code
        AND NEW.edition IS NOT DISTINCT FROM OLD.edition
        AND NEW.language IS NOT DISTINCT FROM OLD.language
        AND NEW.source_reference IS NOT DISTINCT FROM OLD.source_reference
        AND NEW.permitted_use_reference IS NOT DISTINCT FROM OLD.permitted_use_reference
        AND NEW.source_reviewed_by_user_id = OLD.source_reviewed_by_user_id
        AND NEW.source_reviewed_at = OLD.source_reviewed_at
        AND NEW.approved_by_user_id = OLD.approved_by_user_id
        AND NEW.approved_at = OLD.approved_at
        AND NEW.predecessor_version_id IS NOT DISTINCT FROM OLD.predecessor_version_id
        AND NEW.lock_version = OLD.lock_version
        AND NEW.content_sha256 = OLD.content_sha256
        AND NEW.created_by_user_id IS NOT DISTINCT FROM OLD.created_by_user_id
        AND NEW.created_at = OLD.created_at
    ) THEN
        RAISE EXCEPTION 'Approved test instrument versions permit retirement only';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION test_instrument_child_guard() RETURNS trigger AS $$
DECLARE
    version_id bigint;
    parent_status text;
BEGIN
    IF TG_OP = 'DELETE' THEN
        version_id := OLD.test_instrument_version_id;
    ELSE
        IF TG_OP = 'UPDATE' AND NEW.test_instrument_version_id <> OLD.test_instrument_version_id THEN
            RAISE EXCEPTION 'Test instrument version children cannot move between versions';
        END IF;
        version_id := NEW.test_instrument_version_id;
    END IF;

    SELECT status INTO parent_status
    FROM test_instrument_versions
    WHERE id = version_id
    FOR UPDATE;

    IF parent_status IS DISTINCT FROM 'draft' THEN
        RAISE EXCEPTION 'Test instrument version children are mutable only while the version is a draft';
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION test_instrument_identity_guard() RETURNS trigger AS $$
BEGIN
    IF NEW.id <> OLD.id
        OR NEW.institution_id <> OLD.institution_id
        OR NEW.code <> OLD.code
        OR NEW.is_synthetic <> OLD.is_synthetic THEN
        RAISE EXCEPTION 'Test instrument identity and synthetic status are immutable';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION test_instrument_current_version_guard() RETURNS trigger AS $$
DECLARE
    version_status text;
BEGIN
    IF NEW.current_approved_version_id IS NULL THEN
        RETURN NEW;
    END IF;

    SELECT status INTO version_status
    FROM test_instrument_versions
    WHERE id = NEW.current_approved_version_id
      AND test_instrument_id = NEW.id;

    IF version_status IS DISTINCT FROM 'approved' THEN
        RAISE EXCEPTION 'Current test instrument pointer must reference its approved version';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE OR REPLACE FUNCTION test_instrument_clear_retired_pointer() RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'approved' AND NEW.status = 'retired' THEN
        UPDATE test_instruments
        SET current_approved_version_id = NULL
        WHERE id = NEW.test_instrument_id
          AND current_approved_version_id = NEW.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER test_instrument_retirement_clears_current_pointer
    AFTER UPDATE OF status ON test_instrument_versions
    FOR EACH ROW EXECUTE FUNCTION test_instrument_clear_retired_pointer();

CREATE TRIGGER test_instrument_version_immutability
    BEFORE UPDATE OR DELETE ON test_instrument_versions
    FOR EACH ROW EXECUTE FUNCTION test_instrument_version_guard();
CREATE TRIGGER test_instrument_subtest_draft_guard
    BEFORE INSERT OR UPDATE OR DELETE ON test_instrument_subtests
    FOR EACH ROW EXECUTE FUNCTION test_instrument_child_guard();
CREATE TRIGGER test_instrument_item_draft_guard
    BEFORE INSERT OR UPDATE OR DELETE ON test_instrument_items
    FOR EACH ROW EXECUTE FUNCTION test_instrument_child_guard();
CREATE TRIGGER test_instrument_metric_draft_guard
    BEFORE INSERT OR UPDATE OR DELETE ON test_instrument_metrics
    FOR EACH ROW EXECUTE FUNCTION test_instrument_child_guard();
CREATE TRIGGER test_instrument_metric_input_draft_guard
    BEFORE INSERT OR UPDATE OR DELETE ON test_instrument_metric_inputs
    FOR EACH ROW EXECUTE FUNCTION test_instrument_child_guard();
CREATE TRIGGER test_instrument_identity_immutability
    BEFORE UPDATE ON test_instruments
    FOR EACH ROW EXECUTE FUNCTION test_instrument_identity_guard();
CREATE TRIGGER test_instrument_current_version_approval_guard
    BEFORE INSERT OR UPDATE OF current_approved_version_id ON test_instruments
    FOR EACH ROW EXECUTE FUNCTION test_instrument_current_version_guard();
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS test_instrument_current_version_approval_guard ON test_instruments;
DROP TRIGGER IF EXISTS test_instrument_identity_immutability ON test_instruments;
DROP TRIGGER IF EXISTS test_instrument_metric_input_draft_guard ON test_instrument_metric_inputs;
DROP TRIGGER IF EXISTS test_instrument_metric_draft_guard ON test_instrument_metrics;
DROP TRIGGER IF EXISTS test_instrument_item_draft_guard ON test_instrument_items;
DROP TRIGGER IF EXISTS test_instrument_subtest_draft_guard ON test_instrument_subtests;
DROP TRIGGER IF EXISTS test_instrument_version_immutability ON test_instrument_versions;
DROP TRIGGER IF EXISTS test_instrument_retirement_clears_current_pointer ON test_instrument_versions;
DROP FUNCTION IF EXISTS test_instrument_current_version_guard();
DROP FUNCTION IF EXISTS test_instrument_identity_guard();
DROP FUNCTION IF EXISTS test_instrument_child_guard();
DROP FUNCTION IF EXISTS test_instrument_version_guard();
DROP FUNCTION IF EXISTS test_instrument_clear_retired_pointer();
SQL);
        }

        Schema::dropIfExists('test_instrument_metric_inputs');
        Schema::dropIfExists('test_instrument_metrics');
        Schema::dropIfExists('test_instrument_items');
        Schema::dropIfExists('test_instrument_subtests');
        Schema::table('test_instruments', static function (Blueprint $table): void {
            $table->dropForeign('test_instruments_current_version_owner_foreign');
        });

        Schema::dropIfExists('test_instrument_versions');
        Schema::dropIfExists('test_instruments');
    }
};
