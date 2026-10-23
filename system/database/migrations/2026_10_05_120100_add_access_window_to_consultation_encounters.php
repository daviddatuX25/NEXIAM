<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_encounters', function (Blueprint $table): void {
            $table->timestamp('access_starts_at')->nullable()->after('room_name');
            $table->timestamp('access_ends_at')->nullable()->after('access_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_encounters', function (Blueprint $table): void {
            $table->dropColumn(['access_starts_at', 'access_ends_at']);
        });
    }
};
