<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->timestamp('superseded_at')->nullable()->after('unreleased_at');
        });
    }

    public function down(): void
    {
        Schema::table('result_releases', function (Blueprint $table) {
            $table->dropColumn('superseded_at');
        });
    }
};
