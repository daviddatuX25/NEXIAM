<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_id', 64);
            $table->string('event_type', 100);
            $table->unsignedSmallInteger('event_version')->default(1);
            $table->string('recipient_type', 50);
            $table->unsignedBigInteger('recipient_id');
            $table->string('channel', 16);
            $table->string('template_key', 100);
            $table->unsignedSmallInteger('template_version')->default(1);
            $table->jsonb('payload');
            $table->string('redaction_class', 50)->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('provider_reference', 255)->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['event_id', 'recipient_type', 'recipient_id', 'channel'],
                'notification_deliveries_event_recipient_channel_unique'
            );
            $table->index(['recipient_type', 'recipient_id', 'status'], 'notif_deliveries_recipient_status_idx');
            $table->index(['status', 'next_attempt_at'], 'notif_deliveries_status_retry_idx');
            $table->index(['event_type', 'event_version'], 'notif_deliveries_event_idx');
        });

        Schema::dropIfExists('notification_outbox');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
