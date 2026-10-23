<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutional_setup_delegations', function (Blueprint $table): void {
            $table->json('scopes')->nullable();
        });

        DB::table('institutional_setup_delegations')->update([
            'scopes' => json_encode(['cycles', 'rooms', 'programs'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        Schema::table('institutional_setup_delegations', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
