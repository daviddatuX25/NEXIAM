<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        DB::transaction(function () use ($driver): void {
            DB::statement('CREATE UNIQUE INDEX omr_versions_id_template_id_unique ON omr_answer_sheet_template_versions (id, template_id)');
            DB::statement('CREATE UNIQUE INDEX omr_areas_id_version_unique ON omr_template_areas (id, template_version_id)');
            DB::statement('ALTER TABLE omr_template_questions ADD COLUMN template_version_id BIGINT NULL REFERENCES omr_answer_sheet_template_versions(id) ON DELETE RESTRICT');
            DB::statement('ALTER TABLE omr_template_questions ADD COLUMN physical_item_uid VARCHAR(36) NULL');
            DB::statement('ALTER TABLE omr_template_questions ADD COLUMN printed_section_uid VARCHAR(36) NULL');
            DB::statement('ALTER TABLE omr_template_questions ADD COLUMN native_label VARCHAR(100) NULL');
            DB::statement('ALTER TABLE omr_template_questions ADD COLUMN global_position INTEGER NULL');
            DB::statement('CREATE UNIQUE INDEX omr_questions_version_physical_uid_unique ON omr_template_questions (template_version_id, physical_item_uid)');
            DB::statement('CREATE UNIQUE INDEX omr_questions_id_version_physical_uid_unique ON omr_template_questions (id, template_version_id, physical_item_uid)');
            DB::statement('CREATE UNIQUE INDEX omr_questions_version_global_position_unique ON omr_template_questions (template_version_id, global_position)');

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE omr_template_questions ADD CONSTRAINT omr_questions_area_version_fk FOREIGN KEY (template_area_id, template_version_id) REFERENCES omr_template_areas(id, template_version_id) ON DELETE RESTRICT');
                DB::statement('ALTER TABLE omr_template_questions ADD CONSTRAINT omr_questions_physical_identity_all_or_none CHECK ((template_version_id IS NULL AND physical_item_uid IS NULL AND printed_section_uid IS NULL AND native_label IS NULL AND global_position IS NULL) OR (template_version_id IS NOT NULL AND physical_item_uid IS NOT NULL AND printed_section_uid IS NOT NULL AND native_label IS NOT NULL AND global_position IS NOT NULL))');

                return;
            }

            if ($driver !== 'sqlite') {
                throw new RuntimeException(sprintf('Physical inventory migration does not support database driver %s.', $driver));
            }

            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_physical_question_insert_integrity
                BEFORE INSERT ON omr_template_questions
                WHEN NOT (
                    (NEW.template_version_id IS NULL AND NEW.physical_item_uid IS NULL AND NEW.printed_section_uid IS NULL AND NEW.native_label IS NULL AND NEW.global_position IS NULL)
                    OR
                    (NEW.template_version_id IS NOT NULL AND NEW.physical_item_uid IS NOT NULL AND NEW.printed_section_uid IS NOT NULL AND NEW.native_label IS NOT NULL AND NEW.global_position IS NOT NULL
                        AND EXISTS (SELECT 1 FROM omr_template_areas a WHERE a.id = NEW.template_area_id AND a.template_version_id = NEW.template_version_id))
                )
                BEGIN SELECT RAISE(ABORT, 'physical sheet identity must be complete and belong to the selected sheet version'); END
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_physical_question_update_integrity
                BEFORE UPDATE OF template_area_id, template_version_id, physical_item_uid, printed_section_uid, native_label, global_position ON omr_template_questions
                WHEN NOT (
                    (NEW.template_version_id IS NULL AND NEW.physical_item_uid IS NULL AND NEW.printed_section_uid IS NULL AND NEW.native_label IS NULL AND NEW.global_position IS NULL)
                    OR
                    (NEW.template_version_id IS NOT NULL AND NEW.physical_item_uid IS NOT NULL AND NEW.printed_section_uid IS NOT NULL AND NEW.native_label IS NOT NULL AND NEW.global_position IS NOT NULL
                        AND EXISTS (SELECT 1 FROM omr_template_areas a WHERE a.id = NEW.template_area_id AND a.template_version_id = NEW.template_version_id))
                )
                BEGIN SELECT RAISE(ABORT, 'physical sheet identity must be complete and belong to the selected sheet version'); END
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_physical_area_update_integrity
                BEFORE UPDATE OF id, template_version_id ON omr_template_areas
                WHEN EXISTS (
                    SELECT 1 FROM omr_template_questions q
                    WHERE q.template_area_id = OLD.id AND q.template_version_id IS NOT NULL
                      AND (q.template_area_id <> NEW.id OR q.template_version_id <> NEW.template_version_id)
                )
                BEGIN SELECT RAISE(ABORT, 'area identity is pinned by physical sheet locations'); END
                SQL);
            DB::statement(<<<'SQL'
                CREATE TRIGGER omr_physical_area_delete_integrity
                BEFORE DELETE ON omr_template_areas
                WHEN EXISTS (
                    SELECT 1 FROM omr_template_questions q
                    WHERE q.template_area_id = OLD.id AND q.template_version_id IS NOT NULL
                )
                BEGIN SELECT RAISE(ABORT, 'area is pinned by physical sheet locations'); END
                SQL);
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            throw new RuntimeException('Physical sheet identity columns include native SQLite foreign keys; rollback requires an explicitly reviewed trigger-preserving table rebuild.');
        }
        if ($driver !== 'pgsql') {
            throw new RuntimeException(sprintf('Physical inventory rollback does not support database driver %s.', $driver));
        }

        DB::transaction(function (): void {
            DB::statement('ALTER TABLE omr_template_questions DROP CONSTRAINT omr_questions_area_version_fk');
            DB::statement('ALTER TABLE omr_template_questions DROP CONSTRAINT omr_questions_physical_identity_all_or_none');
            DB::statement('DROP INDEX omr_questions_version_physical_uid_unique');
            DB::statement('DROP INDEX omr_questions_id_version_physical_uid_unique');
            DB::statement('DROP INDEX omr_questions_version_global_position_unique');
            DB::statement('DROP INDEX omr_areas_id_version_unique');
            DB::statement('DROP INDEX omr_versions_id_template_id_unique');
            DB::statement('ALTER TABLE omr_template_questions DROP COLUMN global_position');
            DB::statement('ALTER TABLE omr_template_questions DROP COLUMN native_label');
            DB::statement('ALTER TABLE omr_template_questions DROP COLUMN printed_section_uid');
            DB::statement('ALTER TABLE omr_template_questions DROP COLUMN physical_item_uid');
            DB::statement('ALTER TABLE omr_template_questions DROP COLUMN template_version_id');
        });
    }
};
