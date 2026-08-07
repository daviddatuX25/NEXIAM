<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type');
            $table->string('actor')->default('system');
            $table->json('payload');
            $table->string('prev_hash', 64)->nullable();
            $table->string('event_hash', 64);
            $table->timestamps();

            $table->unique('event_hash');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
