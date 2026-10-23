<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_source_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_source_id');
            $table->uuid('knowledge_source_version_id');
            $table->string('disposition');
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->unsignedInteger('target_version')->nullable();
            $table->string('checksum_at_review', 64);
            $table->unsignedBigInteger('reviewed_by');
            $table->timestamp('reviewed_at');
            $table->text('reason');
            $table->timestamps();

            $table->foreign('knowledge_source_id')
                ->references('id')
                ->on('knowledge_sources')
                ->cascadeOnDelete();

            $table->foreign('knowledge_source_version_id')
                ->references('id')
                ->on('knowledge_source_versions')
                ->cascadeOnDelete();

            $table->foreign('reviewed_by')
                ->references('id')
                ->on('users');

            $table->index(['knowledge_source_version_id', 'disposition']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_source_reconciliations');
    }
};
