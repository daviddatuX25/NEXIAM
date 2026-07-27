<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_companion_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->text('content');
            $table->timestamp('created_at');

            $table->index(['applicant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_companion_messages');
    }
};
