<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateDesignations = DB::table('public_policies')
            ->select('institution_id')
            ->where('is_designated_intake', true)
            ->groupBy('institution_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($hasDuplicateDesignations) {
            throw new RuntimeException('Cannot enforce one applicant-intake policy per institution while duplicate designations exist.');
        }

        Schema::table('public_policy_revisions', function (Blueprint $table): void {
            $table->string('kind', 32)->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX public_policies_one_intake_designation_per_institution_idx ON public_policies (institution_id) WHERE is_designated_intake = TRUE');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS public_policies_one_intake_designation_per_institution_idx');

        Schema::table('public_policy_revisions', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
