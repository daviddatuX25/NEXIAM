<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_answer_sheet_templates', function (Blueprint $table): void {
            $table->dropUnique('omr_answer_sheet_templates_code_unique');
            $table->foreignId('institution_id')->nullable()->after('id');
            $table->foreign('institution_id', 'omr_answer_sheet_templates_institution_fk')
                ->references('id')
                ->on('institutions')
                ->restrictOnDelete();
            $table->unique(['institution_id', 'code'], 'omr_answer_sheet_templates_institution_code_unique');
            $table->unique(['id', 'institution_id'], 'omr_answer_sheet_templates_id_institution_unique');
            $table->unique(['id', 'institution_id', 'is_synthetic'], 'omr_answer_sheet_templates_identity_unique');
        });

        Schema::table('omr_question_booklets', function (Blueprint $table): void {
            $table->dropUnique('omr_question_booklets_code_unique');
            $table->foreignId('institution_id')->nullable()->after('id');
            $table->boolean('is_synthetic')->nullable()->after('name');
            $table->foreign('institution_id', 'omr_question_booklets_institution_fk')
                ->references('id')
                ->on('institutions')
                ->restrictOnDelete();
            $table->unique(['institution_id', 'code'], 'omr_question_booklets_institution_code_unique');
            $table->unique(['id', 'institution_id'], 'omr_question_booklets_id_institution_unique');
        });

        Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
            $table->foreignId('institution_id')->nullable()->after('id');
            $table->boolean('is_synthetic')->nullable()->after('institution_id');
            $table->foreign('institution_id', 'omr_template_authoring_drafts_institution_fk')
                ->references('id')
                ->on('institutions')
                ->restrictOnDelete();
            $table->unique(['institution_id', 'code'], 'omr_template_authoring_drafts_institution_code_unique');
            $table->foreign(
                ['template_id', 'institution_id', 'is_synthetic'],
                'omr_template_authoring_drafts_template_identity_fk',
            )
                ->references(['id', 'institution_id', 'is_synthetic'])
                ->on('omr_answer_sheet_templates')
                ->restrictOnDelete();
        });

        $this->installMaterialIdentityGuards();
    }

    public function down(): void
    {

        if (DB::table('omr_answer_sheet_templates')
            ->select('code')
            ->groupBy('code')
            ->havingRaw('COUNT(*) > 1')
            ->exists()
            || DB::table('omr_question_booklets')
                ->select('code')
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->exists()) {
            throw new RuntimeException('Cannot restore global OMR material code uniqueness while duplicate codes exist.');
        }
        $this->removeMaterialIdentityGuards();

        Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
            $table->dropForeign('omr_template_authoring_drafts_template_identity_fk');
            $table->dropUnique('omr_template_authoring_drafts_institution_code_unique');
            $table->dropForeign('omr_template_authoring_drafts_institution_fk');
            $table->dropColumn(['institution_id', 'is_synthetic']);
        });

        Schema::table('omr_question_booklets', function (Blueprint $table): void {
            $table->dropUnique('omr_question_booklets_id_institution_unique');
            $table->dropUnique('omr_question_booklets_institution_code_unique');
            $table->dropForeign('omr_question_booklets_institution_fk');
            $table->dropColumn(['institution_id', 'is_synthetic']);
            $table->unique('code', 'omr_question_booklets_code_unique');
        });

        Schema::table('omr_answer_sheet_templates', function (Blueprint $table): void {
            $table->dropUnique('omr_answer_sheet_templates_identity_unique');
            $table->dropUnique('omr_answer_sheet_templates_id_institution_unique');
            $table->dropUnique('omr_answer_sheet_templates_institution_code_unique');
            $table->dropForeign('omr_answer_sheet_templates_institution_fk');
            $table->dropColumn('institution_id');
            $table->unique('code', 'omr_answer_sheet_templates_code_unique');
        });
    }

    private function installMaterialIdentityGuards(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_material_identity_guard() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'INSERT' THEN
                        IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL THEN
                            RAISE EXCEPTION 'New OMR material families require an institution owner and provenance.';
                        END IF;
                    ELSIF NEW.institution_id IS DISTINCT FROM OLD.institution_id
                        OR NEW.is_synthetic IS DISTINCT FROM OLD.is_synthetic THEN
                        RAISE EXCEPTION 'OMR material family ownership and provenance are immutable.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);

            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS omr_answer_sheet_templates_identity_guard ON omr_answer_sheet_templates;
                CREATE TRIGGER omr_answer_sheet_templates_identity_guard
                    BEFORE INSERT OR UPDATE OF institution_id, is_synthetic ON omr_answer_sheet_templates
                    FOR EACH ROW EXECUTE FUNCTION omr_material_identity_guard()
            SQL);
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS omr_question_booklets_identity_guard ON omr_question_booklets;
                CREATE TRIGGER omr_question_booklets_identity_guard
                    BEFORE INSERT OR UPDATE OF institution_id, is_synthetic ON omr_question_booklets
                    FOR EACH ROW EXECUTE FUNCTION omr_material_identity_guard()
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION omr_template_authoring_draft_identity_guard() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'INSERT' THEN
                        IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL THEN
                            RAISE EXCEPTION 'New OMR authoring drafts require an institution owner, user owner and provenance.';
                        END IF;
                    ELSIF OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.owner_user_id IS NULL THEN
                        RAISE EXCEPTION 'Unclassified legacy OMR authoring drafts cannot be updated.';
                    ELSIF NEW.institution_id IS DISTINCT FROM OLD.institution_id
                        OR NEW.is_synthetic IS DISTINCT FROM OLD.is_synthetic
                        OR NEW.owner_user_id IS DISTINCT FROM OLD.owner_user_id THEN
                        RAISE EXCEPTION 'OMR authoring draft ownership and provenance are immutable.';
                    END IF;

                    IF NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL THEN
                        RAISE EXCEPTION 'OMR authoring drafts require classified ownership and provenance.';
                    END IF;
                    IF NEW.template_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1
                        FROM omr_answer_sheet_templates
                        WHERE id = NEW.template_id
                            AND institution_id = NEW.institution_id
                            AND is_synthetic = NEW.is_synthetic
                    ) THEN
                        RAISE EXCEPTION 'OMR authoring draft template must match its institution ownership and provenance.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_guard ON omr_template_authoring_drafts;
                CREATE TRIGGER omr_template_authoring_drafts_identity_guard
                    BEFORE INSERT OR UPDATE ON omr_template_authoring_drafts
                    FOR EACH ROW EXECUTE FUNCTION omr_template_authoring_draft_identity_guard()
            SQL);

            return;
        }

        if ($driver === 'sqlite') {
            foreach (['omr_answer_sheet_templates', 'omr_question_booklets'] as $table) {
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$table}_identity_insert_guard
                    BEFORE INSERT ON {$table}
                    WHEN NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL
                    BEGIN
                        SELECT RAISE(ABORT, 'New OMR material families require an institution owner and provenance.');
                    END
                SQL);
                DB::unprepared(<<<SQL
                    CREATE TRIGGER {$table}_identity_update_guard
                    BEFORE UPDATE OF institution_id, is_synthetic ON {$table}
                    WHEN NEW.institution_id IS NOT OLD.institution_id OR NEW.is_synthetic IS NOT OLD.is_synthetic
                    BEGIN
                        SELECT RAISE(ABORT, 'OMR material family ownership and provenance are immutable.');
                    END
                SQL);
            }

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER omr_template_authoring_drafts_identity_insert_guard
                BEFORE INSERT ON omr_template_authoring_drafts
                WHEN NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL
                    OR (NEW.template_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_answer_sheet_templates
                        WHERE id = NEW.template_id
                            AND institution_id = NEW.institution_id
                            AND is_synthetic = NEW.is_synthetic
                    ))
                BEGIN
                    SELECT RAISE(ABORT, 'New OMR authoring drafts require an institution owner, user owner and provenance.');
                END
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER omr_template_authoring_drafts_identity_update_guard
                BEFORE UPDATE ON omr_template_authoring_drafts
                WHEN OLD.institution_id IS NULL OR OLD.is_synthetic IS NULL OR OLD.owner_user_id IS NULL
                    OR NEW.institution_id IS NULL OR NEW.is_synthetic IS NULL OR NEW.owner_user_id IS NULL
                    OR NEW.institution_id IS NOT OLD.institution_id
                    OR NEW.is_synthetic IS NOT OLD.is_synthetic
                    OR NEW.owner_user_id IS NOT OLD.owner_user_id
                    OR (NEW.template_id IS NOT NULL AND NOT EXISTS (
                        SELECT 1 FROM omr_answer_sheet_templates
                        WHERE id = NEW.template_id
                            AND institution_id = NEW.institution_id
                            AND is_synthetic = NEW.is_synthetic
                    ))
                BEGIN
                    SELECT RAISE(ABORT, 'OMR authoring draft ownership and provenance are immutable; unclassified legacy drafts cannot be updated.');
                END
            SQL);
        }
    }

    private function removeMaterialIdentityGuards(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS omr_answer_sheet_templates_identity_guard ON omr_answer_sheet_templates');
            DB::unprepared('DROP TRIGGER IF EXISTS omr_question_booklets_identity_guard ON omr_question_booklets');
            DB::unprepared('DROP FUNCTION IF EXISTS omr_material_identity_guard()');
            DB::unprepared('DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_guard ON omr_template_authoring_drafts');
            DB::unprepared('DROP FUNCTION IF EXISTS omr_template_authoring_draft_identity_guard()');

            return;
        }

        if ($driver === 'sqlite') {
            foreach (['omr_answer_sheet_templates', 'omr_question_booklets'] as $table) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_identity_insert_guard");
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_identity_update_guard");
            }
            DB::unprepared('DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_insert_guard');
            DB::unprepared('DROP TRIGGER IF EXISTS omr_template_authoring_drafts_identity_update_guard');
        }
    }
};
