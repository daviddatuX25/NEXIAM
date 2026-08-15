<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OpenSpec 4 — nexi-knowledge-governance (AD-25). No soft is_active authority.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('title');
            $table->string('source_class', 64)->default('institutional_policy');
            $table->string('audience', 64)->default('staff_and_applicant_inquiry');
            $table->string('purpose', 64)->default('policy_inquiry');
            $table->string('classification', 64)->default('public_institutional');
            $table->unsignedBigInteger('institution_id')->nullable()->index();
            $table->string('owner_user_id', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('knowledge_source_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_id')->index();
            $table->unsignedInteger('version');
            $table->string('status', 32)->default('draft');
            $table->string('title');
            $table->longText('body_text')->nullable();
            $table->string('content_checksum', 64)->nullable();
            $table->string('quarantine_path')->nullable();
            $table->string('extraction_digest', 64)->nullable();
            $table->longText('extraction_text')->nullable();
            $table->string('publication_digest', 64)->nullable();
            $table->string('created_by', 64);
            $table->string('approved_by', 64)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['knowledge_source_id', 'version']);
            $table->foreign('knowledge_source_id')->references('id')->on('knowledge_sources')->cascadeOnDelete();
        });

        Schema::create('knowledge_citation_fixtures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_version_id')->index();
            $table->string('kind', 32); // citation | refusal
            $table->string('query_text');
            $table->text('expected_excerpt')->nullable();
            $table->string('expected_outcome', 32)->default('cite'); // cite | refuse
            $table->timestamps();
            $table->foreign('knowledge_source_version_id')->references('id')->on('knowledge_source_versions')->cascadeOnDelete();
        });

        Schema::create('knowledge_index_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_version_id')->unique();
            $table->uuid('knowledge_source_id')->index();
            $table->string('status', 32)->default('active');
            $table->longText('search_text');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->foreign('knowledge_source_version_id')->references('id')->on('knowledge_source_versions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_index_entries');
        Schema::dropIfExists('knowledge_citation_fixtures');
        Schema::dropIfExists('knowledge_source_versions');
        Schema::dropIfExists('knowledge_sources');
    }
};
