<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'record_thread_messages_thread_seq_unique';

    private const MESSAGE_CHECK = 'record_thread_messages_sequence_positive_check';

    private const THREAD_CHECK = 'record_threads_latest_message_sequence_nonnegative_check';

    private const READ_CHECK = 'record_thread_reads_last_read_sequence_nonnegative_check';

    public function up(): void
    {
        if (Schema::hasTable('record_threads') && ! Schema::hasColumn('record_threads', 'latest_message_sequence')) {
            Schema::table('record_threads', function (Blueprint $table): void {
                $table->unsignedBigInteger('latest_message_sequence')->default(0);
            });
        }

        if (Schema::hasTable('record_thread_messages') && ! Schema::hasColumn('record_thread_messages', 'sequence')) {
            Schema::table('record_thread_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('sequence')->nullable();
            });
        }

        if (Schema::hasTable('record_thread_reads') && ! Schema::hasColumn('record_thread_reads', 'last_read_sequence')) {
            Schema::table('record_thread_reads', function (Blueprint $table): void {
                $table->unsignedBigInteger('last_read_sequence')->default(0);
            });
        }

        if (Schema::hasTable('record_threads') && Schema::hasTable('record_thread_messages')) {
            $this->backfillOnlyUninitializedSequences();
            $this->assertSequenceStateIsConsistent();

            Schema::table('record_thread_messages', function (Blueprint $table): void {
                $table->unsignedBigInteger('sequence')->nullable(false)->change();

                if (! Schema::hasIndex('record_thread_messages', self::UNIQUE_INDEX)) {
                    $table->unique(['record_thread_id', 'sequence'], self::UNIQUE_INDEX);
                }

                // The unique index also serves queries by thread and sequence.
                if (Schema::hasIndex('record_thread_messages', 'record_thread_messages_thread_seq_index')) {
                    $table->dropIndex('record_thread_messages_thread_seq_index');
                }
            });
        }

        $this->installNonnegativeConstraints();
    }

    /**
     * Maintenance procedure: pause all message writers, take a restorable database backup, then
     * apply this migration. To reverse, pause writers again and run the migration rollback; that
     * intentionally removes sequence/read-marker columns, so take a fresh backup first if those
     * values must be retained. Restore the backup to preserve them; never renumber live messages
     * as a rollback shortcut. Downstream deployments should otherwise use a forward-fix migration.
     */
    private function backfillOnlyUninitializedSequences(): void
    {
        $hasAnySequences = DB::table('record_thread_messages')->whereNotNull('sequence')->exists();
        $hasNullSequences = DB::table('record_thread_messages')->whereNull('sequence')->exists();

        if ($hasAnySequences && $hasNullSequences) {
            throw new RuntimeException('Cannot backfill record thread messages: sequence data is partially populated.');
        }

        if (! $hasAnySequences) {
            DB::table('record_threads')->orderBy('id')->chunkById(100, function ($threads): void {
                foreach ($threads as $thread) {
                    $sequence = 0;
                    $lastCreatedAt = null;
                    $lastId = null;

                    do {
                        $query = DB::table('record_thread_messages')
                            ->where('record_thread_id', $thread->id)
                            ->orderBy('created_at')
                            ->orderBy('id')
                            ->limit(500);

                        if ($lastId !== null) {
                            $query->where(function ($query) use ($lastCreatedAt, $lastId): void {
                                $query->where('created_at', '>', $lastCreatedAt)
                                    ->orWhere(function ($query) use ($lastCreatedAt, $lastId): void {
                                        $query->where('created_at', '=', $lastCreatedAt)
                                            ->where('id', '>', $lastId);
                                    });
                            });
                        }

                        $messages = $query->get(['id', 'created_at']);

                        foreach ($messages as $message) {
                            $sequence++;
                            DB::table('record_thread_messages')
                                ->where('id', $message->id)
                                ->update(['sequence' => $sequence]);
                            $lastCreatedAt = $message->created_at;
                            $lastId = $message->id;
                        }
                    } while ($messages->count() === 500);

                    DB::table('record_threads')
                        ->where('id', $thread->id)
                        ->update(['latest_message_sequence' => $sequence]);
                }
            });
        }
    }

    private function assertSequenceStateIsConsistent(): void
    {
        if (DB::table('record_thread_messages')->whereNull('sequence')->exists()) {
            throw new RuntimeException('Cannot enforce record thread sequence constraints while message sequences are null.');
        }

        $duplicate = DB::table('record_thread_messages')
            ->select('record_thread_id', 'sequence')
            ->groupBy('record_thread_id', 'sequence')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Cannot enforce record thread sequence constraints: duplicate message sequence within a thread.');
        }

        $mismatch = DB::table('record_threads as threads')
            ->select('threads.id')
            ->leftJoin('record_thread_messages as messages', 'messages.record_thread_id', '=', 'threads.id')
            ->groupBy('threads.id', 'threads.latest_message_sequence')
            ->havingRaw('threads.latest_message_sequence <> COALESCE(MAX(messages.sequence), 0)')
            ->exists();

        if ($mismatch) {
            throw new RuntimeException('Cannot enforce record thread sequence constraints: thread counter does not match its messages.');
        }

        if (DB::table('record_thread_messages')->where('sequence', '<=', 0)->exists()) {
            throw new RuntimeException('Cannot enforce record thread sequence constraints: message sequences must be positive.');
        }

        if (Schema::hasTable('record_thread_reads') && DB::table('record_thread_reads')->where('last_read_sequence', '<', 0)->exists()) {
            throw new RuntimeException('Cannot enforce record thread read constraints: read sequences must be nonnegative.');
        }
    }

    private function installNonnegativeConstraints(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->addPostgresCheck('record_thread_messages', self::MESSAGE_CHECK, 'sequence > 0');
            $this->addPostgresCheck('record_threads', self::THREAD_CHECK, 'latest_message_sequence >= 0');

            if (Schema::hasTable('record_thread_reads')) {
                $this->addPostgresCheck('record_thread_reads', self::READ_CHECK, 'last_read_sequence >= 0');
            }

            return;
        }

        if ($driver === 'sqlite') {
            $this->installSqliteCheckTriggers();
        }
    }

    private function addPostgresCheck(string $table, string $constraint, string $expression): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM pg_constraint WHERE conname = ? AND conrelid = ?::regclass',
            [$constraint, $table],
        );

        if ($exists === null) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$expression})");
        }
    }

    private function installSqliteCheckTriggers(): void
    {
        $checks = [
            ['record_thread_messages', self::MESSAGE_CHECK, 'sequence > 0'],
            ['record_threads', self::THREAD_CHECK, 'latest_message_sequence >= 0'],
        ];

        if (Schema::hasTable('record_thread_reads')) {
            $checks[] = ['record_thread_reads', self::READ_CHECK, 'last_read_sequence >= 0'];
        }

        foreach ($checks as [$table, $name, $expression]) {
            foreach (['INSERT', 'UPDATE'] as $event) {
                $trigger = "{$name}_".strtolower($event);
                $exists = DB::table('sqlite_master')
                    ->where('type', 'trigger')
                    ->where('name', $trigger)
                    ->exists();

                if (! $exists) {
                    $column = $this->columnForConstraint($name);
                    $operator = $this->operatorForConstraint($name);
                    DB::statement(
                        "CREATE TRIGGER {$trigger} BEFORE {$event} ON {$table} "
                        ."WHEN NOT (NEW.{$column} {$operator}) "
                        ."BEGIN SELECT RAISE(ABORT, '{$name}'); END",
                    );
                }
            }
        }
    }

    private function columnForConstraint(string $name): string
    {
        return match ($name) {
            self::MESSAGE_CHECK => 'sequence',
            self::THREAD_CHECK => 'latest_message_sequence',
            self::READ_CHECK => 'last_read_sequence',
        };
    }

    private function operatorForConstraint(string $name): string
    {
        return $name === self::MESSAGE_CHECK ? '> 0' : '>= 0';
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            foreach ([
                self::MESSAGE_CHECK,
                self::THREAD_CHECK,
                self::READ_CHECK,
            ] as $name) {
                foreach (['insert', 'update'] as $event) {
                    DB::statement("DROP TRIGGER IF EXISTS {$name}_{$event}");
                }
            }
        } elseif ($driver === 'pgsql') {
            if (Schema::hasTable('record_thread_messages')) {
                DB::statement('ALTER TABLE record_thread_messages DROP CONSTRAINT IF EXISTS '.self::MESSAGE_CHECK);
            }
            if (Schema::hasTable('record_threads')) {
                DB::statement('ALTER TABLE record_threads DROP CONSTRAINT IF EXISTS '.self::THREAD_CHECK);
            }
            if (Schema::hasTable('record_thread_reads')) {
                DB::statement('ALTER TABLE record_thread_reads DROP CONSTRAINT IF EXISTS '.self::READ_CHECK);
            }
        }

        if (Schema::hasTable('record_thread_messages')) {
            Schema::table('record_thread_messages', function (Blueprint $table): void {
                if (Schema::hasIndex('record_thread_messages', self::UNIQUE_INDEX)) {
                    $table->dropUnique(self::UNIQUE_INDEX);
                }
                if (Schema::hasColumn('record_thread_messages', 'sequence')) {
                    $table->dropColumn('sequence');
                }
            });
        }

        if (Schema::hasTable('record_thread_reads') && Schema::hasColumn('record_thread_reads', 'last_read_sequence')) {
            Schema::table('record_thread_reads', function (Blueprint $table): void {
                $table->dropColumn('last_read_sequence');
            });
        }

        if (Schema::hasTable('record_threads') && Schema::hasColumn('record_threads', 'latest_message_sequence')) {
            Schema::table('record_threads', function (Blueprint $table): void {
                $table->dropColumn('latest_message_sequence');
            });
        }
    }
};
