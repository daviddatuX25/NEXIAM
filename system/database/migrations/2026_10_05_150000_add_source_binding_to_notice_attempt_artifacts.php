<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_delivery_attempt_artifacts', function (Blueprint $table): void {
            $table->char('source_sha256', 64)->nullable();
            $table->string('source_kind', 32)->nullable();
            $table->jsonb('canonical_binding')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('notification_delivery_attempt_artifacts', function (Blueprint $table): void {
            $table->dropColumn(['source_sha256', 'source_kind', 'canonical_binding']);
        });
    }
};
