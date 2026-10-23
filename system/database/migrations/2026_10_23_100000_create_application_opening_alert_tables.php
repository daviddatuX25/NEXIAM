<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_opening_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('email', 254)->nullable();
            $table->string('normalized_email', 254)->nullable();
            $table->string('state', 24)->default('pending');
            $table->unsignedInteger('generation')->default(1);
            $table->timestampTz('consented_at');
            $table->string('consent_version', 40);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('unsubscribed_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('address_erased_at')->nullable();
            $table->boolean('retention_hold')->default(false);
            $table->timestampTz('last_verification_sent_at')->nullable();
            $table->timestampsTz();
            $table->unique(['institution_id', 'normalized_email'], 'opening_subs_institution_email_unique');
            $table->index(['institution_id', 'state', 'created_at'], 'opening_subs_staff_list_idx');
            $table->index(['state', 'expires_at'], 'opening_subs_expiry_idx');
        });

        Schema::create('application_opening_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('subscription_id')->constrained('application_opening_subscriptions')->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->string('purpose', 24);
            $table->char('token_hash', 64)->unique();
            $table->text('encrypted_token')->nullable();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->timestampsTz();
            $table->index(['subscription_id', 'generation', 'purpose'], 'opening_tokens_generation_purpose_idx');
            $table->index(['expires_at', 'consumed_at'], 'opening_tokens_expiry_idx');
        });

        Schema::create('application_opening_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('academic_cycle_id');
            $table->unsignedBigInteger('application_window_id');
            $table->timestampTz('opened_at');
            $table->timestampTz('closes_at');
            $table->timestampTz('observed_at');
            $table->string('availability_key', 64)->unique();
            $table->unsignedBigInteger('last_subscription_id')->default(0);
            $table->boolean('fanout_complete')->default(false);
            $table->timestampsTz();
            $table->index(['institution_id', 'opened_at'], 'opening_occurrences_institution_time_idx');
        });

        Schema::create('application_opening_alert_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscription_id')->constrained('application_opening_subscriptions')->restrictOnDelete();
            $table->unsignedInteger('generation');
            $table->foreignId('occurrence_id')->constrained('application_opening_occurrences')->restrictOnDelete();
            $table->uuid('delivery_id')->nullable()->constrained('notification_deliveries')->nullOnDelete();
            $table->string('status', 24)->default('claimed');
            $table->timestampTz('claimed_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('address_erased_at')->nullable();
            $table->timestampsTz();
            $table->unique(['subscription_id', 'generation', 'occurrence_id'], 'opening_receipts_generation_occurrence_unique');
            $table->index(['status', 'claimed_at'], 'opening_receipts_status_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_opening_alert_receipts');
        Schema::dropIfExists('application_opening_occurrences');
        Schema::dropIfExists('application_opening_tokens');
        Schema::dropIfExists('application_opening_subscriptions');
    }
};
