<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_session_leases', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id_hash', 64)->unique();
            $table->uuid('inactivity_generation');
            $table->timestamp('authenticated_at');
            $table->timestamp('idle_expires_at');
            $table->timestamp('absolute_expires_at');
            $table->integer('effective_idle_minutes')->default(15);
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_session_leases');
    }
};
