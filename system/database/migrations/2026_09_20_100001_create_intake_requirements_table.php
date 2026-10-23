<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intake_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('label', 255);
            $table->string('applicant_track', 32)->default('all');
            $table->string('item_kind', 32)->default('document');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['institution_id', 'code']);
            $table->index(['institution_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intake_requirements');
    }
};
