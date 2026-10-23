<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->boolean('is_preview')->default(false)->after('artifact_type');
        });

        Schema::create('print_job_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->constrained('print_jobs')->cascadeOnDelete();
            $table->foreignId('admission_attempt_id')->constrained('admission_attempts')->cascadeOnDelete();
            $table->foreignId('result_release_id')->nullable()->constrained('result_releases')->nullOnDelete();
            $table->unsignedInteger('item_index')->default(0);
            $table->string('status', 32)->default('queued');
            $table->string('file_path')->nullable();
            $table->string('file_checksum', 64)->nullable();
            $table->string('safe_error')->nullable();
            $table->timestamps();

            $table->unique(['print_job_id', 'item_index']);
            $table->unique(['print_job_id', 'result_release_id']);
            $table->index(['print_job_id', 'status']);
        });

        if (Schema::hasTable('result_sheet_templates')) {
            DB::table('result_sheet_templates')
                ->where('logical_unit', '!=', 'full')
                ->update(['logical_unit' => 'full']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('print_job_items');

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropColumn('is_preview');
        });
    }
};
