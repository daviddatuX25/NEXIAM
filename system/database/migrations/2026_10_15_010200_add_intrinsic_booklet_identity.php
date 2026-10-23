<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqliteTriggers = $this->sqliteSealTriggers();

        Schema::create('omr_booklet_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booklet_version_id')->constrained('omr_question_booklet_versions')->cascadeOnDelete();
            $table->uuid('booklet_section_uid');
            $table->string('name', 120);
            $table->unsignedSmallInteger('display_order');
            $table->unsignedSmallInteger('expected_count');
            $table->json('option_tokens');
            $table->timestamps();
            $table->unique(['booklet_version_id', 'booklet_section_uid'], 'omr_booklet_sections_version_uid_unique');
            $table->unique(['booklet_version_id', 'display_order'], 'omr_booklet_sections_version_order_unique');
            $table->unique(['id', 'booklet_version_id'], 'omr_booklet_sections_id_version_unique');
            $table->unique(['booklet_version_id', 'id', 'booklet_section_uid'], 'omr_booklet_sections_identity_tuple_unique');
        });

        Schema::table('omr_booklet_items', function (Blueprint $table): void {
            $table->uuid('booklet_item_uid')->nullable();
            $table->uuid('booklet_section_uid')->nullable();
            $table->unsignedBigInteger('booklet_section_id')->nullable();
            $table->json('option_tokens')->nullable();
            $table->boolean('key_required')->nullable();
            $table->string('correct_option_code', 32)->nullable()->change();
            $table->unsignedBigInteger('aptitude_area_id')->nullable()->change();
            $table->unique(['booklet_version_id', 'booklet_item_uid'], 'omr_booklet_items_version_uid_unique');
            $table->unique(['booklet_version_id', 'booklet_section_uid', 'authored_position'], 'omr_booklet_items_section_position_unique');
            $table->unique(['id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'], 'omr_booklet_items_identity_tuple_unique');
            $table->foreign(['booklet_version_id', 'booklet_section_id', 'booklet_section_uid'], 'omr_booklet_items_exact_section_fk')
                ->references(['booklet_version_id', 'id', 'booklet_section_uid'])
                ->on('omr_booklet_sections')->cascadeOnDelete();
            $table->index(['booklet_version_id', 'booklet_section_uid'], 'omr_booklet_items_version_section_idx');
        });

        Schema::table('omr_booklet_sets', function (Blueprint $table): void {
            $table->unique(['id', 'booklet_version_id'], 'omr_booklet_sets_identity_tuple_unique');
        });

        Schema::table('omr_booklet_set_items', function (Blueprint $table): void {
            $table->uuid('booklet_item_uid')->nullable();
            $table->uuid('booklet_section_uid')->nullable();
            $table->foreignId('booklet_version_id')->nullable()->constrained('omr_question_booklet_versions')->restrictOnDelete();
            $table->unsignedBigInteger('aptitude_area_id')->nullable()->change();
            $table->string('correct_option_code', 32)->nullable()->change();
            $table->unique(['booklet_set_id', 'booklet_item_id'], 'omr_booklet_set_items_set_booklet_item_unique');
            $table->index(['booklet_set_id', 'booklet_section_uid'], 'omr_booklet_set_items_set_section_idx');
            $table->unique(
                ['id', 'booklet_set_id', 'booklet_version_id', 'booklet_item_id', 'booklet_item_uid', 'booklet_section_uid'],
                'omr_booklet_set_items_identity_tuple_unique',
            );
            $table->foreign(['booklet_set_id', 'booklet_version_id'], 'omr_booklet_set_items_exact_set_fk')
                ->references(['id', 'booklet_version_id'])
                ->on('omr_booklet_sets')->restrictOnDelete();
            $table->foreign(['booklet_item_id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'], 'omr_booklet_set_items_exact_item_fk')
                ->references(['id', 'booklet_version_id', 'booklet_item_uid', 'booklet_section_uid'])
                ->on('omr_booklet_items')->restrictOnDelete();
        });

        $this->installSectionSealGuards();
        $this->restoreSqliteSealTriggers($sqliteTriggers);
    }

    public function down(): void
    {
        if (DB::table('omr_booklet_sections')->exists()
            || DB::table('omr_booklet_items')->whereNotNull('booklet_item_uid')->exists()
            || DB::table('omr_booklet_set_items')->whereNotNull('booklet_version_id')->exists()) {
            throw new RuntimeException('Intrinsic Booklet records exist; preserve this additive migration and use a forward fix.');
        }
        $sqliteTriggers = $this->sqliteSealTriggers();

        Schema::table('omr_booklet_set_items', function (Blueprint $table): void {
            $table->dropForeign('omr_booklet_set_items_exact_item_fk');
            $table->dropForeign('omr_booklet_set_items_exact_set_fk');
            $table->dropUnique('omr_booklet_set_items_set_booklet_item_unique');
            $table->dropUnique('omr_booklet_set_items_identity_tuple_unique');
            $table->dropIndex('omr_booklet_set_items_set_section_idx');
            $table->dropConstrainedForeignId('booklet_version_id');
            $table->dropColumn(['booklet_item_uid', 'booklet_section_uid']);
            $table->string('correct_option_code', 32)->nullable(false)->change();
            $table->unsignedBigInteger('aptitude_area_id')->nullable(false)->change();
        });

        Schema::table('omr_booklet_items', function (Blueprint $table): void {
            $table->dropForeign('omr_booklet_items_exact_section_fk');
            $table->dropUnique('omr_booklet_items_version_uid_unique');
            $table->dropUnique('omr_booklet_items_section_position_unique');
            $table->dropUnique('omr_booklet_items_identity_tuple_unique');
            $table->dropIndex('omr_booklet_items_version_section_idx');
            $table->dropColumn(['booklet_item_uid', 'booklet_section_uid', 'booklet_section_id', 'option_tokens', 'key_required']);
            $table->string('correct_option_code', 32)->nullable(false)->change();
            $table->unsignedBigInteger('aptitude_area_id')->nullable(false)->change();
        });

        Schema::table('omr_booklet_sets', function (Blueprint $table): void {
            $table->dropUnique('omr_booklet_sets_identity_tuple_unique');
        });
        Schema::dropIfExists('omr_booklet_sections');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS nexiam_omr_booklet_section_seal_guard()');
        }
        $this->restoreSqliteSealTriggers($sqliteTriggers);
    }

    /** @return list<string> */
    private function sqliteSealTriggers(): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            return [];
        }
        $names = [
            'omr_component_omr_booklet_items_insert_seal_guard',
            'omr_component_omr_booklet_items_update_seal_guard',
            'omr_component_omr_booklet_items_delete_seal_guard',
            'omr_component_omr_booklet_set_items_insert_seal_guard',
            'omr_component_omr_booklet_set_items_update_seal_guard',
            'omr_component_omr_booklet_set_items_delete_seal_guard',
        ];
        $sqls = DB::table('sqlite_master')
            ->where('type', 'trigger')
            ->whereIn('name', $names)
            ->whereNotNull('sql')
            ->pluck('sql')
            ->all();

        foreach ($names as $name) {
            DB::statement("DROP TRIGGER IF EXISTS {$name}");
        }

        return $sqls;
    }

    /** @param list<string> $triggerSql */
    private function restoreSqliteSealTriggers(array $triggerSql): void
    {
        foreach ($triggerSql as $sql) {
            DB::unprepared((string) preg_replace('/^CREATE TRIGGER\\s+/i', 'CREATE TRIGGER IF NOT EXISTS ', $sql));
        }
    }

    private function installSectionSealGuards(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION nexiam_omr_booklet_section_seal_guard() RETURNS trigger AS $$
                DECLARE
                    old_version_id BIGINT;
                    new_version_id BIGINT;
                    version_record RECORD;
                BEGIN
                    IF TG_OP IN ('UPDATE', 'DELETE') THEN
                        old_version_id := OLD.booklet_version_id;
                    END IF;
                    IF TG_OP IN ('UPDATE', 'INSERT') THEN
                        new_version_id := NEW.booklet_version_id;
                    END IF;
                    FOR version_record IN
                        SELECT id, status
                        FROM omr_question_booklet_versions
                        WHERE id IN (old_version_id, new_version_id)
                        ORDER BY id
                        FOR SHARE
                    LOOP
                        IF version_record.status = 'finalized' THEN
                            RAISE EXCEPTION 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented';
                        END IF;
                    END LOOP;
                    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                SQL);
            DB::unprepared('CREATE TRIGGER omr_component_omr_booklet_sections_insert_seal_guard BEFORE INSERT ON omr_booklet_sections FOR EACH ROW EXECUTE FUNCTION nexiam_omr_booklet_section_seal_guard()');
            DB::unprepared('CREATE TRIGGER omr_component_omr_booklet_sections_update_seal_guard BEFORE UPDATE ON omr_booklet_sections FOR EACH ROW EXECUTE FUNCTION nexiam_omr_booklet_section_seal_guard()');
            DB::unprepared('CREATE TRIGGER omr_component_omr_booklet_sections_delete_seal_guard BEFORE DELETE ON omr_booklet_sections FOR EACH ROW EXECUTE FUNCTION nexiam_omr_booklet_section_seal_guard()');

            return;
        }

        DB::unprepared("CREATE TRIGGER IF NOT EXISTS omr_component_omr_booklet_sections_insert_seal_guard BEFORE INSERT ON omr_booklet_sections FOR EACH ROW WHEN EXISTS (SELECT 1 FROM omr_question_booklet_versions v WHERE v.id = NEW.booklet_version_id AND v.status = 'finalized') BEGIN SELECT RAISE(ABORT, 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS omr_component_omr_booklet_sections_update_seal_guard BEFORE UPDATE ON omr_booklet_sections FOR EACH ROW WHEN EXISTS (SELECT 1 FROM omr_question_booklet_versions v WHERE v.id IN (OLD.booklet_version_id, NEW.booklet_version_id) AND v.status = 'finalized') BEGIN SELECT RAISE(ABORT, 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented'); END");
        DB::unprepared("CREATE TRIGGER IF NOT EXISTS omr_component_omr_booklet_sections_delete_seal_guard BEFORE DELETE ON omr_booklet_sections FOR EACH ROW WHEN EXISTS (SELECT 1 FROM omr_question_booklet_versions v WHERE v.id = OLD.booklet_version_id AND v.status = 'finalized') BEGIN SELECT RAISE(ABORT, 'Sealed OMR component descendants cannot be inserted, changed, deleted or reparented'); END");
    }
};
