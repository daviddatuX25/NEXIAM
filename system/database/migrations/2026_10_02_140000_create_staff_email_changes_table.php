<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_email_changes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiator_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('old_email');
            $table->string('new_email');
            $table->unsignedBigInteger('expected_session_version')->default(1);
            $table->char('token_digest', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'confirmed_at', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_email_changes');
    }
};
