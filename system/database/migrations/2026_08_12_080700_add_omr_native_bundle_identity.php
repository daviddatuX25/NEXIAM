<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->string('native_bundle_version', 64)->nullable()->after('config_hash');
            $table->json('native_bundle_json')->nullable()->after('native_bundle_version');
            $table->char('native_bundle_hash', 64)->nullable()->after('native_bundle_json');
            $table->string('engine_name', 100)->nullable()->after('native_bundle_hash');
            $table->string('engine_commit', 100)->nullable()->after('engine_name');
            $table->string('patch_identity', 100)->nullable()->after('engine_commit');
            $table->json('review_manifest_json')->nullable()->after('patch_identity');
            $table->char('review_manifest_hash', 64)->nullable()->after('review_manifest_json');
        });

        foreach ([
            'omr_template_areas',
            'omr_template_questions',
            'omr_template_question_options',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->char('projection_manifest_hash', 64)->nullable()->after('updated_at');
                $table->string('projection_source', 40)->default('activation_manifest')->after('projection_manifest_hash');
            });
        }
    }

    public function down(): void
    {
        Schema::table('omr_template_question_options', function (Blueprint $table): void {
            $table->dropColumn(['projection_manifest_hash', 'projection_source']);
        });
        Schema::table('omr_template_questions', function (Blueprint $table): void {
            $table->dropColumn(['projection_manifest_hash', 'projection_source']);
        });
        Schema::table('omr_template_areas', function (Blueprint $table): void {
            $table->dropColumn(['projection_manifest_hash', 'projection_source']);
        });
        Schema::table('omr_answer_sheet_template_versions', function (Blueprint $table): void {
            $table->dropColumn([
                'native_bundle_version',
                'native_bundle_json',
                'native_bundle_hash',
                'engine_name',
                'engine_commit',
                'patch_identity',
                'review_manifest_json',
                'review_manifest_hash',
            ]);
        });
    }
};
