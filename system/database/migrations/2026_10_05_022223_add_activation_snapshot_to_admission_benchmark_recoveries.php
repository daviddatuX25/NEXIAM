<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('admission_benchmark_recoveries', 'source_activation_snapshot_json')) {
            Schema::table('admission_benchmark_recoveries', function (Blueprint $table): void {
                $table->json('source_activation_snapshot_json')->nullable();
                $table->string('source_activation_sha256', 64)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('admission_benchmark_recoveries', function (Blueprint $table): void {
            $table->dropColumn(['source_activation_snapshot_json', 'source_activation_sha256']);
        });
    }
};
