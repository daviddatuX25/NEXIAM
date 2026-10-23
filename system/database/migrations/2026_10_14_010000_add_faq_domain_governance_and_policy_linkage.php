<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_sources', function (Blueprint $table): void {
            $table->string('category', 64)->default('admissions')->index();
        });

        Schema::create('knowledge_source_consensus_signatures', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_version_id')->index();
            $table->string('office', 32);
            $table->foreignId('signer_id')->constrained('users');
            $table->string('signed_digest', 128);
            $table->unsignedInteger('reviewed_version');
            $table->timestampTz('signed_at');
            $table->timestampsTz();
            $table->foreign('knowledge_source_version_id')
                ->references('id')
                ->on('knowledge_source_versions')
                ->cascadeOnDelete();
            $table->unique(
                ['knowledge_source_version_id', 'office'],
                'uq_ks_consensus_version_office',
            );
            $table->index(
                ['knowledge_source_version_id', 'office', 'signed_digest'],
                'idx_ks_consensus_lookup',
            );
        });

        Schema::create('knowledge_source_policy_dependencies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_version_id')->index();
            $table->foreignId('public_policy_id')->constrained('public_policies')->restrictOnDelete();
            $table->foreignId('public_policy_revision_id')->constrained('public_policy_revisions')->restrictOnDelete();
            $table->string('dependency_type', 32)->default('governing');
            $table->string('policy_digest_at_link', 128);
            $table->foreignId('linked_by')->constrained('users');
            $table->timestampTz('linked_at');
            $table->timestampsTz();
            $table->foreign('knowledge_source_version_id')
                ->references('id')
                ->on('knowledge_source_versions')
                ->cascadeOnDelete();
            $table->unique(
                ['knowledge_source_version_id', 'public_policy_id'],
                'uq_ks_policy_dep_version_policy',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_source_policy_dependencies');
        Schema::dropIfExists('knowledge_source_consensus_signatures');

        Schema::table('knowledge_sources', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
