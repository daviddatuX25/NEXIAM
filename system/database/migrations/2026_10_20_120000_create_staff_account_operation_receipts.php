<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_account_operation_receipts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('operation_id')->unique();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('purpose', 40);
            $table->char('payload_digest', 64);
            $table->string('outcome', 32);
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestampsTz();
            $table->index(['actor_id', 'institution_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_account_operation_receipts');
    }
};
