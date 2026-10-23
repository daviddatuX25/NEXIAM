<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_quota_bulk_command_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->json('quota_change_ids');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_user_id', 'idempotency_key'], 'quota_bulk_actor_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_quota_bulk_command_receipts');
    }
};
