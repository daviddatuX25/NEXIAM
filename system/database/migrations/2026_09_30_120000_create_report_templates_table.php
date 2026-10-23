<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('surface_key', 64)->index();
            $table->string('name');
            $table->string('mode')->default('docx');
            $table->string('paper_size')->default('a4');
            $table->string('orientation')->default('portrait');
            $table->string('document_path')->nullable();
            $table->string('document_checksum', 64)->nullable();
            $table->string('document_extension', 8)->nullable();
            $table->longText('content')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
