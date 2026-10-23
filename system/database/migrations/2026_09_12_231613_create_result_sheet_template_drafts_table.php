<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_sheet_template_drafts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_template_id')->nullable()->constrained('result_sheet_templates')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage')->default(1);
            $table->json('state');
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(['owner_user_id', 'source_template_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_sheet_template_drafts');
    }
};
