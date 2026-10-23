<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batch_rows', function (Blueprint $table): void {
            $table->string('guidance_status', 24)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('import_batch_rows', function (Blueprint $table): void {
            $table->dropColumn('guidance_status');
        });
    }
};
