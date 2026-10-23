<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_voucher_batch_row_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('operation_id');
            $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->char('payload_hash', 64);
            $table->string('public_reference', 10);
            $table->string('outcome', 32);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['actor_user_id', 'operation_id', 'target_user_id'], 'staff_voucher_batch_row_unique');
            $table->index(['target_user_id', 'created_at'], 'staff_voucher_batch_target_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_voucher_batch_row_receipts');
    }
};
