<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceLifecycleConstraints(allowCancelled: true);
    }

    public function down(): void
    {
        $connection = Schema::getConnection();
        if ($connection->getDriverName() !== 'sqlite'
            && $connection->table('omr_diagnostic_runs')->where('status', 'cancelled')->exists()) {
            throw new RuntimeException('Cancelled OMR diagnostic history prevents restoring the prior status constraints.');
        }

        $this->replaceLifecycleConstraints(allowCancelled: false);
    }

    private function replaceLifecycleConstraints(bool $allowCancelled): void
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        if ($driver === 'sqlite') {
            return;
        }

        $dropCheck = match ($driver) {
            'pgsql' => 'DROP CONSTRAINT',
            'mysql' => 'DROP CHECK',
            default => throw new RuntimeException('Unsupported database driver for OMR diagnostic status constraints.'),
        };

        $connection->statement(<<<SQL
            ALTER TABLE omr_diagnostic_runs
                {$dropCheck} omr_diagnostic_runs_status_check,
                {$dropCheck} omr_diagnostic_runs_completed_at_check
        SQL);

        $terminalStatuses = $allowCancelled
            ? "'completed', 'failed', 'cancelled'"
            : "'completed', 'failed'";
        $allStatuses = $allowCancelled
            ? "'open', 'processing', 'needs_review', 'completed', 'failed', 'cancelled'"
            : "'open', 'processing', 'needs_review', 'completed', 'failed'";

        $connection->statement(<<<SQL
            ALTER TABLE omr_diagnostic_runs
                ADD CONSTRAINT omr_diagnostic_runs_status_check
                    CHECK (status IN ({$allStatuses})),
                ADD CONSTRAINT omr_diagnostic_runs_completed_at_check
                    CHECK (
                        (status IN ({$terminalStatuses}) AND completed_at IS NOT NULL)
                        OR (status NOT IN ({$terminalStatuses}) AND completed_at IS NULL)
                    )
        SQL);
    }
};
