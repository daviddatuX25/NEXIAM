<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_physical_challenges', function (Blueprint $table): void {
            $table->json('guide_json')->nullable()->after('guide_asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('omr_physical_challenges', function (Blueprint $table): void {
            $table->dropColumn('guide_json');
        });
    }
};
