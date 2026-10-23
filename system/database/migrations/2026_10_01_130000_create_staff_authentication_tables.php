<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasUniqueUserId = collect(Schema::getIndexes('staff_profiles'))->contains(
            fn (array $index): bool => $index['unique'] && $index['columns'] === ['user_id'],
        );

        if (! $hasUniqueUserId) {
            Schema::table('staff_profiles', function (Blueprint $table): void {
                $table->unique('user_id');
            });
        }

        Schema::create('staff_authentication_states', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('staff_profiles', 'user_id')->restrictOnDelete();
            $table->timestampTz('activation_completed_at')->nullable();
            $table->timestampTz('password_set_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->bigInteger('session_version')->default(1);
            $table->boolean('mfa_required')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->uuid('two_factor_generation')->nullable();
            $table->bigInteger('two_factor_last_accepted_step')->nullable();
            $table->uuid('recovery_generation')->nullable();
            $table->text('pending_two_factor_secret')->nullable();
            $table->timestampTz('pending_two_factor_expires_at')->nullable();
            $table->uuid('pending_enrollment_attempt_id')->nullable();
            $table->timestampsTz();
        });

        Schema::create('staff_activation_vouchers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('public_reference', 10)->unique();
            $table->foreignId('user_id')->constrained('staff_authentication_states', 'user_id')->restrictOnDelete();
            $table->string('purpose', 24);
            $table->char('token_digest', 64)->unique();
            $table->string('fallback_hash', 255);
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('redeemed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->smallInteger('failed_attempts')->default(0);
            $table->timestampTz('attempt_window_started_at')->nullable();
            $table->timestampTz('blocked_until')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'created_at'], 'staff_activation_vouchers_user_created_index');
        });

        Schema::create('staff_authentication_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('staff_authentication_states', 'user_id')->restrictOnDelete();
            $table->string('purpose', 24);
            $table->string('primary_method', 16);
            $table->string('phase', 32);
            $table->foreignUuid('voucher_id')->nullable()->unique()
                ->constrained('staff_activation_vouchers')->restrictOnDelete();
            $table->bigInteger('expected_session_version')->default(1);
            $table->uuid('expected_factor_generation')->nullable();
            $table->char('session_binding_digest', 64);
            $table->timestampTz('primary_verified_at');
            $table->timestampTz('mfa_verified_at')->nullable();
            $table->smallInteger('failed_attempts')->default(0);
            $table->timestampTz('blocked_until')->nullable();
            $table->boolean('remember_requested')->default(false);
            $table->text('intended_path')->nullable();
            $table->string('pending_google_subject', 255)->nullable();
            $table->string('pending_google_email', 255)->nullable();
            $table->boolean('pending_google_email_verified')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampsTz();
        });

        Schema::table('staff_authentication_states', function (Blueprint $table): void {
            $table->foreign('pending_enrollment_attempt_id', 'staff_auth_states_pending_enrollment_fk')
                ->references('id')
                ->on('staff_authentication_attempts')
                ->restrictOnDelete();
        });

        Schema::create('staff_provider_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('staff_authentication_states', 'user_id')->restrictOnDelete();
            $table->string('provider', 24);
            $table->string('subject', 255)->unique();
            $table->string('email_at_link', 255);
            $table->boolean('email_verified_at_link');
            $table->timestampTz('linked_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignUuid('linked_via_attempt_id')->nullable()
                ->constrained('staff_authentication_attempts')->restrictOnDelete();
            $table->foreignId('linked_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
        Schema::create('staff_two_factor_recovery_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('staff_authentication_states', 'user_id')->restrictOnDelete();
            $table->uuid('generation');
            $table->char('code_digest', 64)->unique();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampsTz();
            $table->index(['user_id', 'generation'], 'staff_two_factor_recovery_user_generation_index');
        });

        DB::table('staff_profiles')
            ->select('user_id')
            ->orderBy('user_id')
            ->chunkById(1000, function ($profiles): void {
                $now = now();
                $states = $profiles->map(fn (object $profile): array => [
                    'user_id' => $profile->user_id,
                    'activation_completed_at' => $now,
                    'password_set_at' => $now,
                    'must_change_password' => false,
                    'session_version' => 1,
                    'mfa_required' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('staff_authentication_states')->insert($states);
            }, 'user_id', 'user_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_two_factor_recovery_codes');
        Schema::dropIfExists('staff_provider_identities');

        Schema::table('staff_authentication_states', function (Blueprint $table): void {
            $table->dropForeign('staff_auth_states_pending_enrollment_fk');
        });

        Schema::dropIfExists('staff_authentication_attempts');
        Schema::dropIfExists('staff_activation_vouchers');
        Schema::dropIfExists('staff_authentication_states');
    }
};
