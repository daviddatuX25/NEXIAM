<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_norm_packages', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('code', 64);
            $table->boolean('is_synthetic')->default(false);
            $table->unsignedBigInteger('current_approved_version_id')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
        });

        Schema::create('assessment_norm_package_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('norm_package_id')->constrained('assessment_norm_packages')->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 16)->default('draft');
            $table->string('display_name', 255);
            $table->string('publisher_name', 255)->nullable();
            $table->string('form_code', 64)->nullable();
            $table->string('edition', 128)->nullable();
            $table->string('language', 32)->nullable();
            $table->string('applicable_population', 255)->nullable();
            $table->string('source_reference', 500)->nullable();
            $table->string('permitted_use_reference', 500)->nullable();
            $table->foreignId('source_reviewed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('source_reviewed_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('retired_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->char('content_sha256', 64)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['norm_package_id', 'version_number']);
        });

        Schema::create('assessment_norm_tables', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('norm_package_version_id')->constrained('assessment_norm_package_versions')->restrictOnDelete();
            $table->string('metric_code', 32);
            $table->string('output_scale', 32);
            $table->json('intervals_json');
            $table->timestamps();

            $table->unique(['norm_package_version_id', 'metric_code', 'output_scale']);
        });

        Schema::create('assessment_conversion_revisions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('administration_id')->constrained('assessment_administrations')->restrictOnDelete();
            $table->foreignId('norm_package_version_id')->constrained('assessment_norm_package_versions')->restrictOnDelete();
            $table->unsignedInteger('revision_no')->default(1);
            $table->json('typed_outputs_json');
            $table->string('status', 16)->default('valid');
            $table->foreignId('converted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('converted_at');
            $table->boolean('is_current')->default(true);
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['administration_id', 'revision_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_conversion_revisions');
        Schema::dropIfExists('assessment_norm_tables');
        Schema::dropIfExists('assessment_norm_package_versions');
        Schema::dropIfExists('assessment_norm_packages');
    }
};
