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
        Schema::table('result_sheet_templates', function (Blueprint $table) {
            $table->foreignId('rating_scale_id')->nullable()->constrained('rating_scales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('result_sheet_templates', function (Blueprint $table) {
            $table->dropForeign(['rating_scale_id']);
            $table->dropColumn('rating_scale_id');
        });
    }
};
