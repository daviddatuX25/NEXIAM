<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_authentication_attempts', function (Blueprint $table): void {
            $table->uuid('expected_recovery_generation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_authentication_attempts', function (Blueprint $table): void {
            $table->dropColumn('expected_recovery_generation');
        });

    }
};
