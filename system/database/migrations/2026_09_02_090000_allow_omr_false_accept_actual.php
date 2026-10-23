<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CAPTURE_TABLE = 'omr_template_verification_captures';

    private const ACTUAL_CHECK = 'omr_verification_captures_actual_check';

    private const SQLITE_INSERT_TRIGGER = 'omr_verification_captures_actual_insert_check';

    private const SQLITE_UPDATE_TRIGGER = 'omr_verification_captures_actual_update_check';

    private const LEGACY_ACTUALS = [
        'pass',
        'typed_rejection',
        'answer_vector_mismatch',
        'failed',
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->installPostgresConstraint([
                ...self::LEGACY_ACTUALS,
                'false_accept',
            ]);

            return;
        }

        if ($driver === 'sqlite') {
            $this->installSqliteConstraints();

            return;
        }

        throw new RuntimeException("OMR false-accept compatibility requires pgsql or sqlite; got {$driver}.");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException("OMR false-accept compatibility requires pgsql or sqlite; got {$driver}.");
        }

        $this->assertLegacyConstraintCanBeRestored();

        if ($driver === 'pgsql') {
            $this->installPostgresConstraint(self::LEGACY_ACTUALS);

            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS '.self::SQLITE_INSERT_TRIGGER);
        DB::statement('DROP TRIGGER IF EXISTS '.self::SQLITE_UPDATE_TRIGGER);
    }

    /** @param list<string> $actuals */
    private function installPostgresConstraint(array $actuals): void
    {
        $allowed = implode("', '", $actuals);

        DB::statement('ALTER TABLE '.self::CAPTURE_TABLE.' DROP CONSTRAINT IF EXISTS '.self::ACTUAL_CHECK);
        DB::statement('ALTER TABLE '.self::CAPTURE_TABLE.' ADD CONSTRAINT '.self::ACTUAL_CHECK." CHECK (actual IN ('{$allowed}'))");
    }

    private function installSqliteConstraints(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS '.self::SQLITE_INSERT_TRIGGER);
        DB::statement('DROP TRIGGER IF EXISTS '.self::SQLITE_UPDATE_TRIGGER);

        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_verification_captures_actual_insert_check
            BEFORE INSERT ON omr_template_verification_captures
            FOR EACH ROW
            WHEN NEW.actual IS NOT NULL
                AND NEW.actual NOT IN ('pass', 'typed_rejection', 'answer_vector_mismatch', 'false_accept', 'failed')
            BEGIN
                SELECT RAISE(ABORT, 'omr verification capture actual is invalid');
            END
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER omr_verification_captures_actual_update_check
            BEFORE UPDATE OF actual ON omr_template_verification_captures
            FOR EACH ROW
            WHEN NEW.actual IS NOT NULL
                AND NEW.actual NOT IN ('pass', 'typed_rejection', 'answer_vector_mismatch', 'false_accept', 'failed')
            BEGIN
                SELECT RAISE(ABORT, 'omr verification capture actual is invalid');
            END
        SQL);
    }

    private function assertLegacyConstraintCanBeRestored(): void
    {
        $incompatible = DB::table(self::CAPTURE_TABLE)
            ->whereNotNull('actual')
            ->whereNotIn('actual', self::LEGACY_ACTUALS)
            ->exists();

        if ($incompatible) {
            throw new RuntimeException('Cannot roll back OMR false-accept compatibility while incompatible capture actual values exist.');
        }
    }
};
