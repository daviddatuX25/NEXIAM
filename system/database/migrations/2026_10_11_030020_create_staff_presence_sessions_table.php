<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_presence_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('office', 64);
            $table->string('session_id_hash', 64)->unique();
            $table->unsignedBigInteger('auth_session_version')->default(1);
            $table->string('device_type', 16)->default('Unknown');
            $table->string('browser', 24)->default('Unknown');
            $table->string('os', 24)->default('Unknown');
            $table->text('ip_address')->nullable();
            $table->string('current_path', 255)->nullable();
            $table->string('workspace_area', 64)->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('first_observed_at');
            $table->timestamp('last_active_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'last_active_at']);
            $table->index(['institution_id', 'office', 'last_active_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_presence_sessions');
    }
};
