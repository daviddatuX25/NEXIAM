<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained('exam_domains')->nullOnDelete();
            $table->decimal('min_score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->text('note');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('course_id');
            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_rules');
    }
};
