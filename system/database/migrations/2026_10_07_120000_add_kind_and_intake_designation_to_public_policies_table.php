<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_policies', function (Blueprint $table): void {
            $table->string('kind', 32)->default('policy')->after('program_id');
            $table->boolean('is_designated_intake')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('public_policies', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'is_designated_intake']);
        });
    }
};
