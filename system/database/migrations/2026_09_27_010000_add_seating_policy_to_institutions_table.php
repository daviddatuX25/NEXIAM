<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->boolean('enable_seating_arrangements')->default(true);
            $table->boolean('enable_multi_set_booklets')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->dropColumn(['enable_seating_arrangements', 'enable_multi_set_booklets']);
        });
    }
};
