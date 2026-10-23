<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');
            $table->unsignedBigInteger('institution_id')->nullable();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->index();
            $table->text('p256dh');
            $table->text('auth_token');
            $table->string('vapid_key_id')->nullable();
            $table->string('device_label', 120)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id', 'revoked_at'], 'push_subs_recipient_active_idx');
            $table->unique(['recipient_type', 'recipient_id', 'endpoint_hash'], 'push_subs_recipient_endpoint_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
