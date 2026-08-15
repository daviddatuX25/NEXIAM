<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('omr_answer_sheet_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->boolean('is_synthetic')->default(false);
            $table->timestamps();
        });

        Schema::create('omr_question_booklets', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 255);
            $table->timestamps();
        });

        Schema::create('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('omr_answer_sheet_templates')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('page_width');
            $table->unsignedInteger('page_height');
            $table->json('identity_regions_json');
            $table->json('provider_config_json');
            $table->char('config_hash', 64);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'version']);
            $table->index(['template_id', 'status']);
        });

        Schema::create('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booklet_id')->constrained('omr_question_booklets')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('draft');
            $table->char('answer_key_hash', 64);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['booklet_id', 'version']);
            $table->index(['booklet_id', 'status']);
        });

        Schema::create('omr_template_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->cascadeOnDelete();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->restrictOnDelete();
            $table->unsignedSmallInteger('display_order');
            $table->unsignedSmallInteger('max_items');
            $table->decimal('region_x', 8, 6);
            $table->decimal('region_y', 8, 6);
            $table->decimal('region_w', 8, 6);
            $table->decimal('region_h', 8, 6);
            $table->timestamps();

            $table->unique(['template_version_id', 'aptitude_area_id']);
            $table->unique(['template_version_id', 'display_order']);
            $table->index(['template_version_id', 'display_order']);
        });

        Schema::create('omr_template_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_area_id')->constrained('omr_template_areas')->cascadeOnDelete();
            $table->unsignedSmallInteger('question_no');
            $table->decimal('region_x', 8, 6);
            $table->decimal('region_y', 8, 6);
            $table->decimal('region_w', 8, 6);
            $table->decimal('region_h', 8, 6);
            $table->timestamps();

            $table->unique(['template_area_id', 'question_no']);
            $table->index(['template_area_id', 'question_no']);
        });

        Schema::create('omr_template_question_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_question_id')->constrained('omr_template_questions')->cascadeOnDelete();
            $table->string('code', 32);
            $table->unsignedSmallInteger('display_order');
            $table->decimal('region_x', 8, 6);
            $table->decimal('region_y', 8, 6);
            $table->decimal('region_w', 8, 6);
            $table->decimal('region_h', 8, 6);
            $table->timestamps();

            $table->unique(['template_question_id', 'code']);
            $table->unique(['template_question_id', 'display_order']);
        });

        Schema::create('omr_question_booklet_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booklet_version_id')->constrained('omr_question_booklet_versions')->cascadeOnDelete();
            $table->foreignId('template_question_id')->constrained('omr_template_questions')->restrictOnDelete();
            $table->foreignId('aptitude_area_id')->constrained('aptitude_areas')->restrictOnDelete();
            $table->unsignedSmallInteger('question_position');
            $table->string('correct_option_code', 32);
            $table->timestamps();

            $table->unique(['booklet_version_id', 'template_question_id']);
            $table->unique(['booklet_version_id', 'question_position']);
            $table->index(['booklet_version_id', 'aptitude_area_id']);
        });

        Schema::create('omr_template_booklet_pairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained('omr_answer_sheet_template_versions')->restrictOnDelete();
            $table->foreignId('booklet_version_id')->constrained('omr_question_booklet_versions')->restrictOnDelete();
            $table->string('status', 16)->default('active');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['template_version_id', 'booklet_version_id']);
            $table->index(['status', 'template_version_id']);
            $table->index(['status', 'booklet_version_id']);
        });

        $this->addChecks();
    }

    private function addChecks(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::getConnection()->statement("ALTER TABLE omr_answer_sheet_template_versions ADD CONSTRAINT omr_template_versions_status_check CHECK (status IN ('draft', 'active', 'retired')), ADD CONSTRAINT omr_template_versions_dimensions_check CHECK (page_width > 0 AND page_height > 0), ADD CONSTRAINT omr_template_versions_activation_check CHECK ((status = 'draft' AND activated_at IS NULL) OR (status IN ('active', 'retired') AND activated_at IS NOT NULL))");
        Schema::getConnection()->statement("ALTER TABLE omr_question_booklet_versions ADD CONSTRAINT omr_booklet_versions_status_check CHECK (status IN ('draft', 'active', 'retired')), ADD CONSTRAINT omr_booklet_versions_activation_check CHECK ((status = 'draft' AND activated_at IS NULL) OR (status IN ('active', 'retired') AND activated_at IS NOT NULL))");
        Schema::getConnection()->statement("ALTER TABLE omr_template_areas ADD CONSTRAINT omr_template_areas_region_check CHECK (max_items > 0 AND region_x >= 0 AND region_y >= 0 AND region_w > 0 AND region_h > 0 AND region_x + region_w <= 1 AND region_y + region_h <= 1)");
        Schema::getConnection()->statement("ALTER TABLE omr_template_questions ADD CONSTRAINT omr_template_questions_region_check CHECK (question_no > 0 AND region_x >= 0 AND region_y >= 0 AND region_w > 0 AND region_h > 0 AND region_x + region_w <= 1 AND region_y + region_h <= 1)");
        Schema::getConnection()->statement("ALTER TABLE omr_template_question_options ADD CONSTRAINT omr_template_options_region_check CHECK (region_x >= 0 AND region_y >= 0 AND region_w > 0 AND region_h > 0 AND region_x + region_w <= 1 AND region_y + region_h <= 1)");
        Schema::getConnection()->statement("ALTER TABLE omr_question_booklet_questions ADD CONSTRAINT omr_booklet_questions_position_check CHECK (question_position > 0), ADD CONSTRAINT omr_booklet_questions_code_check CHECK (char_length(trim(correct_option_code)) > 0)");
        Schema::getConnection()->statement("ALTER TABLE omr_template_booklet_pairs ADD CONSTRAINT omr_template_booklet_pairs_status_check CHECK (status IN ('active', 'retired')), ADD CONSTRAINT omr_template_booklet_pairs_approval_check CHECK ((status = 'active' AND approved_at IS NOT NULL) OR status = 'retired')");
    }

    public function down(): void
    {
        Schema::dropIfExists('omr_template_booklet_pairs');
        Schema::dropIfExists('omr_question_booklet_questions');
        Schema::dropIfExists('omr_template_question_options');
        Schema::dropIfExists('omr_template_questions');
        Schema::dropIfExists('omr_template_areas');
        Schema::dropIfExists('omr_question_booklet_versions');
        Schema::dropIfExists('omr_answer_sheet_template_versions');
        Schema::dropIfExists('omr_question_booklets');
        Schema::dropIfExists('omr_answer_sheet_templates');
    }
};
