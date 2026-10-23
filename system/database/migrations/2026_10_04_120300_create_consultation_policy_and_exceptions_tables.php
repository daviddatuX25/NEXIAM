<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consultation_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->string('normal_method', 32)->default('f2f');
            $table->boolean('online_exceptions_enabled')->default(false);
            $table->foreignId('configured_by_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['institution_id', 'academic_cycle_id']);
        });

        Schema::create('consultation_online_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until');
            $table->text('reason');
            $table->unsignedInteger('virtual_capacity')->nullable();
            $table->foreignId('pinned_counselor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('authorized_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 32)->default('active');
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index(['institution_id', 'academic_cycle_id', 'target_type', 'target_id']);
            $table->index(['status', 'effective_from', 'effective_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_online_exceptions');
        Schema::dropIfExists('consultation_policies');
    }
};
