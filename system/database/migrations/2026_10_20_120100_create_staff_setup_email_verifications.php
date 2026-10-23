<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_authentication_attempts', function (Blueprint $table): void {
            $table->timestampTz('recipient_email_chosen_at')->nullable();
        });

        Schema::create('staff_setup_email_verifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('attempt_id')->constrained('staff_authentication_attempts')->cascadeOnDelete();
            $table->string('email');
            $table->char('token_digest', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'attempt_id', 'confirmed_at', 'cancelled_at'], 'setup_email_verifications_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_setup_email_verifications');
        Schema::table('staff_authentication_attempts', function (Blueprint $table): void {
            $table->dropColumn('recipient_email_chosen_at');
        });
    }
};
