<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateCurrentRequirementSets();

        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['pgsql', 'sqlite'], true)) {
            throw new RuntimeException('Admission checklist current-set constraints require PostgreSQL or SQLite.');
        }

        Schema::table('admission_attempt_document_verifications', function (Blueprint $table): void {
            $table->text('applicant_instruction')->nullable();
            $table->text('internal_remarks')->nullable();
        });

        DB::table('admission_attempt_document_verifications')
            ->whereNotNull('remarks')
            ->update(['internal_remarks' => DB::raw('remarks')]);

        $predicate = $driver === 'sqlite' ? 'is_current = 1' : 'is_current = true';
        DB::statement("CREATE UNIQUE INDEX admission_attempt_requirement_sets_single_current ON admission_attempt_requirement_sets (admission_attempt_id) WHERE {$predicate}");
    }

    public function assertNoDuplicateCurrentRequirementSets(): void
    {
        $duplicateAttemptIds = DB::table('admission_attempt_requirement_sets')
            ->select('admission_attempt_id')
            ->where('is_current', true)
            ->groupBy('admission_attempt_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('admission_attempt_id')
            ->pluck('admission_attempt_id');

        if ($duplicateAttemptIds->isEmpty()) {
            return;
        }

        $duplicates = [];

        foreach ($duplicateAttemptIds as $attemptId) {
            $setIds = DB::table('admission_attempt_requirement_sets')
                ->where('admission_attempt_id', $attemptId)
                ->where('is_current', true)
                ->orderBy('id')
                ->pluck('id')
                ->implode(', ');

            $duplicates[] = "attempt {$attemptId} (current set IDs: {$setIds})";
        }

        throw new RuntimeException(
            'Admission checklist migration found duplicate current requirement sets; resolve them through an approved disposition before migration. Conflicts: '.implode('; ', $duplicates)
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX admission_attempt_requirement_sets_single_current');

        Schema::table('admission_attempt_document_verifications', function (Blueprint $table): void {
            $table->dropColumn(['applicant_instruction', 'internal_remarks']);
        });
    }
};
