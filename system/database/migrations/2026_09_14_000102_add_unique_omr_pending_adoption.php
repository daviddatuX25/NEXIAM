<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_pending_captures', function (Blueprint $table): void {
            $table->unique('adopted_sheet_id', 'omr_pending_captures_adopted_sheet_unique');
        });
    }

    public function down(): void
    {
        Schema::table('omr_pending_captures', function (Blueprint $table): void {
            $table->dropUnique('omr_pending_captures_adopted_sheet_unique');
        });
    }
};
