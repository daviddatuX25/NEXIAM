<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->string('source', 32)->default('manual');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('source');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
