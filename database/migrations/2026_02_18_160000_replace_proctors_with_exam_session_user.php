<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('exam_session_proctor');
        Schema::dropIfExists('proctors');

        Schema::create('exam_session_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['exam_session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_user');

        Schema::create('proctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('user_id');
            $table->index('name');
        });

        Schema::create('exam_session_proctor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proctor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['exam_session_id', 'proctor_id']);
        });
    }
};
