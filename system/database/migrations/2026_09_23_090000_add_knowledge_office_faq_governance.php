<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->string('owning_office', 32)->nullable();
            $table->index('owning_office');
            $table->string('audience', 64)->default('staff_only')->change();
            $table->string('classification', 64)->default('office_internal')->change();
            $table->string('source_class', 64)->default('institution_policy')->change();
        });

        Schema::table('knowledge_source_versions', function (Blueprint $table) {
            $table->text('question')->nullable();
            $table->longText('answer')->nullable();
        });

        Schema::create('knowledge_source_scope_backfills', function (Blueprint $table) {
            $table->uuid('knowledge_source_id')->primary();
            $table->string('previous_audience', 64);
            $table->string('previous_classification', 64);
            $table->string('assigned_owning_office', 32);
            $table->timestamp('backfilled_at');
            $table->foreign('knowledge_source_id')
                ->references('id')
                ->on('knowledge_sources')
                ->cascadeOnDelete();
        });

        $identifierCast = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => 'CHAR',
            'pgsql', 'sqlite' => 'TEXT',
            'sqlsrv' => 'VARCHAR(64)',
            default => throw new RuntimeException('Unsupported database driver for knowledge scope backfill.'),
        };

        $verifiedSources = DB::table('knowledge_sources')
            ->join('staff_profiles', function ($join) use ($identifierCast) {
                $join->on('staff_profiles.institution_id', '=', 'knowledge_sources.institution_id')
                    ->whereRaw("CAST(knowledge_sources.owner_user_id AS {$identifierCast}) = CAST(staff_profiles.user_id AS {$identifierCast})");
            })
            ->whereNull('knowledge_sources.owning_office')
            ->whereIn('knowledge_sources.source_class', ['institution_policy', 'public_faq'])
            ->where('staff_profiles.status', 'active')
            ->whereIn('staff_profiles.office', ['registrar', 'guidance'])
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('knowledge_source_versions')
                    ->whereColumn('knowledge_source_versions.knowledge_source_id', 'knowledge_sources.id')
                    ->whereColumn('knowledge_source_versions.created_by', 'knowledge_sources.owner_user_id');
            })
            ->select([
                'knowledge_sources.id as knowledge_source_id',
                'knowledge_sources.audience as previous_audience',
                'knowledge_sources.classification as previous_classification',
                'staff_profiles.office as assigned_owning_office',
            ])
            ->selectRaw('CURRENT_TIMESTAMP AS backfilled_at');

        DB::table('knowledge_source_scope_backfills')->insertUsing([
            'knowledge_source_id',
            'previous_audience',
            'previous_classification',
            'assigned_owning_office',
            'backfilled_at',
        ], $verifiedSources);

        DB::table('knowledge_source_scope_backfills')
            ->orderBy('knowledge_source_id')
            ->each(function (object $snapshot): void {
                DB::table('knowledge_sources')
                    ->where('id', $snapshot->knowledge_source_id)
                    ->whereNull('owning_office')
                    ->update([
                        'owning_office' => $snapshot->assigned_owning_office,
                        'audience' => 'staff_only',
                        'classification' => 'office_internal',
                    ]);
            });

        Schema::create('knowledge_source_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_version_id');
            $table->string('office', 32);
            $table->string('approver_id', 64);
            $table->timestamp('approved_at');
            $table->string('publication_digest', 64);
            $table->timestamps();
            $table->unique(['knowledge_source_version_id', 'office'], 'knowledge_approvals_version_office_unique');
            $table->foreign('knowledge_source_version_id')
                ->references('id')
                ->on('knowledge_source_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_source_approvals');

        DB::table('knowledge_source_scope_backfills')
            ->orderBy('knowledge_source_id')
            ->each(function (object $snapshot): void {
                DB::table('knowledge_sources')
                    ->where('id', $snapshot->knowledge_source_id)
                    ->where('owning_office', $snapshot->assigned_owning_office)
                    ->where('audience', 'staff_only')
                    ->where('classification', 'office_internal')
                    ->update([
                        'audience' => $snapshot->previous_audience,
                        'classification' => $snapshot->previous_classification,
                    ]);
            });
        Schema::dropIfExists('knowledge_source_scope_backfills');

        Schema::table('knowledge_source_versions', function (Blueprint $table) {
            $table->dropColumn(['question', 'answer']);
        });

        Schema::table('knowledge_sources', function (Blueprint $table) {
            $table->string('audience', 64)->default('staff_and_applicant_inquiry')->change();
            $table->string('source_class', 64)->default('institutional_policy')->change();
            $table->string('classification', 64)->default('public_institutional')->change();
            $table->dropIndex(['owning_office']);
            $table->dropColumn('owning_office');
        });
    }
};
