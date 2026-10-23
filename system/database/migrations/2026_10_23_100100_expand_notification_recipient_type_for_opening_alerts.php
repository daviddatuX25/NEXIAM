<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->string('recipient_type', 80)->change();
        });
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->string('recipient_type', 50)->change();
        });
    }
};
