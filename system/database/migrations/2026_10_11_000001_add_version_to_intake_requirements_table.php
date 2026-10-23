<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('intake_requirements', 'version')) {
            Schema::table('intake_requirements', function (Blueprint $table): void {
                $table->unsignedInteger('version')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('intake_requirements', 'version')) {
            Schema::table('intake_requirements', function (Blueprint $table): void {
                $table->dropColumn('version');
            });
        }
    }
};
