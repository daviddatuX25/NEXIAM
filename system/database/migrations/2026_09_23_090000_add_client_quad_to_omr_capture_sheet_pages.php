<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->json('client_quad_json')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('omr_capture_sheet_pages', function (Blueprint $table): void {
            $table->dropColumn('client_quad_json');
        });
    }
};
