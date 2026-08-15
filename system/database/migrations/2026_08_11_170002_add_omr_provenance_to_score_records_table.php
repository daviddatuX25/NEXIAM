<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_records', function (Blueprint $table) {
            $table->string('source', 16)->default('manual')->after('converted_score');
            $table->foreignId('capture_sheet_id')->nullable()->after('source')->constrained('omr_capture_sheets')->nullOnDelete();
            $table->decimal('confidence', 4, 3)->nullable()->after('capture_sheet_id');
        });
    }

    public function down(): void
    {
        Schema::table('score_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('capture_sheet_id');
            $table->dropColumn(['source', 'confidence']);
        });
    }
};
