<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admission_attempts', function (Blueprint $table): void {
            $table->foreignId('public_policy_id')
                ->nullable()
                ->constrained('public_policies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admission_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('public_policy_id');
        });
    }
};
