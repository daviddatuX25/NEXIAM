<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_authentication_states', function (Blueprint $table): void {
            $table->boolean('protected_from_reset')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('staff_authentication_states', function (Blueprint $table): void {
            $table->dropColumn('protected_from_reset');
        });
    }
};
