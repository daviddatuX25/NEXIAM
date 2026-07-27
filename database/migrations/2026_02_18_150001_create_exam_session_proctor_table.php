<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_proctor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proctor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_session_id', 'proctor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_proctor');
    }
};
