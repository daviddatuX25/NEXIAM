<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_cycle_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->timestamp('opens_at');
            $table->timestamp('closes_at');
            $table->string('status')->default('draft');
            $table->timestamps();

            $table->unique(['academic_cycle_id', 'code']);
            $table->index(['academic_cycle_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_windows');
    }
};
