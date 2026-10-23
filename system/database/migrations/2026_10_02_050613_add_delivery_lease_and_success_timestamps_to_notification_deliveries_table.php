<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->timestampTz('delivered_at')->nullable();
            $table->uuid('claim_token')->nullable();
            $table->timestampTz('lease_expires_at')->nullable();
            $table->timestampTz('outcome_unknown_at')->nullable();
            $table->index(['status', 'lease_expires_at'], 'notif_deliveries_lease_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropIndex('notif_deliveries_lease_idx');
            $table->dropColumn([
                'delivered_at',
                'claim_token',
                'lease_expires_at',
                'outcome_unknown_at',
            ]);
        });
    }
};
