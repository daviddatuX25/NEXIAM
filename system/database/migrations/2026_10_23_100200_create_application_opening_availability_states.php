<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_opening_availability_states', function (Blueprint $table): void {
            $table->foreignId('institution_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(false);
            $table->unsignedInteger('generation')->default(0);
            $table->unsignedBigInteger('academic_cycle_id')->nullable();
            $table->unsignedBigInteger('application_window_id')->nullable();
            $table->timestampTz('opens_at')->nullable();
            $table->timestampTz('available_since')->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->timestampsTz();
        });
        Schema::table('application_opening_occurrences', function (Blueprint $table): void {
            $table->unsignedInteger('availability_generation')->default(0)->after('application_window_id');
            $table->index(['institution_id', 'availability_generation'], 'opening_occurrences_generation_idx');
        });
    }

    public function down(): void
    {
        Schema::table('application_opening_occurrences', function (Blueprint $table): void {
            $table->dropIndex('opening_occurrences_generation_idx');
            $table->dropColumn('availability_generation');
        });
        Schema::dropIfExists('application_opening_availability_states');
    }
};
