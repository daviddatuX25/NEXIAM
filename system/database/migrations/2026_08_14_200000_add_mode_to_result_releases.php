<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Release mode metadata (consultation change, I1).
 * f2f/online recorded per release; selects the release notification template.
 * Gate logic is uniform — mode never becomes a prerequisite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->string('mode')->default('f2f')->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
