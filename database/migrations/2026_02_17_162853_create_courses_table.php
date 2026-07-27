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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name')->comment('e.g., "Bachelor of Science in Information Technology"');
            $table->string('code', 20)->unique()->comment('Unique course code: e.g., "BSIT"');
            $table->unsignedInteger('quota')->nullable()->comment('Max enrollees (NULL = unlimited)');
            $table->decimal('score_cutoff', 5, 2)->nullable()->comment('Minimum score threshold');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
