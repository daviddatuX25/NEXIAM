<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_conversion_revisions', function (Blueprint $table): void {
            $table->unsignedBigInteger('norm_package_version_id')->nullable()->change();

            if (! Schema::hasColumn('assessment_conversion_revisions', 'source')) {
                $table->string('source', 32)->default('norm_converted')->after('norm_package_version_id');
            }

            if (! Schema::hasColumn('assessment_conversion_revisions', 'provenance_json')) {
                $table->json('provenance_json')->nullable()->after('typed_outputs_json');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_conversion_revisions', function (Blueprint $table): void {
            if (Schema::hasColumn('assessment_conversion_revisions', 'provenance_json')) {
                $table->dropColumn('provenance_json');
            }

            if (Schema::hasColumn('assessment_conversion_revisions', 'source')) {
                $table->dropColumn('source');
            }

            $table->unsignedBigInteger('norm_package_version_id')->nullable(false)->change();
        });
    }
};
