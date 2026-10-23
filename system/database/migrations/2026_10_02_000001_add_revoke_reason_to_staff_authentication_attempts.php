<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_authentication_attempts', function (Blueprint $table): void {
            $table->text('revoke_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_authentication_attempts', function (Blueprint $table): void {
            $table->dropColumn('revoke_reason');
        });
    }
};
