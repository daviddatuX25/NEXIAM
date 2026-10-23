<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->integer('exam_feature_policy_version')->default(1);
            $table->boolean('enable_seating_arrangements')->nullable(false)->default(false)->change();
            $table->boolean('enable_multi_set_booklets')->nullable(false)->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->boolean('enable_seating_arrangements')->nullable(false)->default(true)->change();
            $table->boolean('enable_multi_set_booklets')->nullable(false)->default(true)->change();
            $table->dropColumn('exam_feature_policy_version');
        });
    }
};
