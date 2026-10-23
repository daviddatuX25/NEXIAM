<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_quotas', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0);
        });

        Schema::table('program_quota_changes', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(0);
            $table->string('reason', 1000)->nullable();
            $table->uuid('idempotency_key')->nullable();
            $table->char('payload_hash', 64)->nullable();
            $table->unique(['actor_user_id', 'idempotency_key'], 'program_quota_change_actor_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('program_quota_changes', function (Blueprint $table): void {
            $table->dropUnique('program_quota_change_actor_key_unique');
            $table->dropColumn(['version', 'reason', 'idempotency_key', 'payload_hash']);
        });

        Schema::table('program_quotas', function (Blueprint $table): void {
            $table->dropColumn('version');
        });
    }
};
