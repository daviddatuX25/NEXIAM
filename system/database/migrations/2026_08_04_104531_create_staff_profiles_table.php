<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('office');
            $table->json('program_codes')->nullable();
            $table->string('active_cycle_code')->nullable();
            $table->timestamps();

            $table->index('office');
            $table->index('active_cycle_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
