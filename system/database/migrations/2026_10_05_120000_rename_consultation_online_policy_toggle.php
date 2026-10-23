<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_policies', function (Blueprint $table): void {
            $table->renameColumn('online_exceptions_enabled', 'online_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_policies', function (Blueprint $table): void {
            $table->renameColumn('online_enabled', 'online_exceptions_enabled');
        });
    }
};
