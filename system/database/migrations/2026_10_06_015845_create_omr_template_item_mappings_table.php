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
        Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
            $table->foreignId('test_instrument_version_id')
                ->nullable()
                ->constrained('test_instrument_versions')
                ->restrictOnDelete();
        });

        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->foreignId('test_instrument_version_id')
                ->nullable()
                ->constrained('test_instrument_versions')
                ->restrictOnDelete();
        });

        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->foreignId('test_instrument_version_id')
                ->nullable()
                ->constrained('test_instrument_versions')
                ->restrictOnDelete();
        });

        Schema::create('omr_template_item_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')
                ->constrained('omr_answer_sheet_template_versions')
                ->restrictOnDelete();
            $table->foreignId('test_instrument_version_id')
                ->constrained('test_instrument_versions')
                ->restrictOnDelete();
            $table->foreignId('test_instrument_subtest_id')
                ->constrained('test_instrument_subtests')
                ->restrictOnDelete();
            $table->foreignId('test_instrument_item_id')
                ->constrained('test_instrument_items')
                ->restrictOnDelete();
            $table->string('native_label', 64);
            $table->smallInteger('page_no')->default(1);
            $table->smallInteger('global_position');
            $table->timestamps();

            $table->unique(
                ['template_version_id', 'native_label'],
                'omr_template_item_mappings_version_label_unique'
            );
            $table->unique(
                ['template_version_id', 'test_instrument_item_id'],
                'omr_template_item_mappings_version_item_unique'
            );
            $table->unique(
                ['template_version_id', 'global_position'],
                'omr_template_item_mappings_version_position_unique'
            );
            $table->index(
                ['template_version_id', 'test_instrument_subtest_id'],
                'omr_template_item_mappings_subtest_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('omr_template_item_mappings');

        Schema::table('omr_question_booklet_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('test_instrument_version_id');
        });

        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('test_instrument_version_id');
        });

        Schema::table('omr_template_authoring_drafts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('test_instrument_version_id');
        });
    }
};
