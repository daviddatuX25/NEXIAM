<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            if (! Schema::hasColumn('print_jobs', 'stage')) {
                $table->string('stage', 40)->nullable()->after('status');
            }
        });

        Schema::table('print_job_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('admission_attempt_id')->nullable()->change();
            $table->string('item_key', 120)->nullable()->after('admission_attempt_id');
            $table->string('work_unit_type', 40)->default('student')->after('item_key');
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table): void {
            if (Schema::hasColumn('print_jobs', 'stage')) {
                $table->dropColumn('stage');
            }
        });

        Schema::table('print_job_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('admission_attempt_id')->change();
            $table->dropColumn(['item_key', 'work_unit_type']);
        });
    }
};
