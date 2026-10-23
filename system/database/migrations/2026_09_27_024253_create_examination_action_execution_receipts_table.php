<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('examination_action_execution_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('execution_id')->unique();
            $table->string('action_key', 128);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('target_json');
            $table->string('canonical_digest', 128);
            $table->char('request_fingerprint', 64);
            $table->json('safe_result_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('examination_action_execution_receipts');
    }
};
