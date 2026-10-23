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
        Schema::create('walkthrough_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->string('workspace_key', 80);
            $table->string('surface', 16);
            $table->string('page_key', 160);
            $table->unsignedInteger('revision');
            $table->string('cursor', 160)->nullable();
            $table->string('disposition', 16)->default('in_progress');
            $table->timestamps();

            $table->unique(
                ['user_id', 'institution_id', 'workspace_key', 'surface', 'page_key', 'revision'],
                'walkthrough_progress_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walkthrough_progress');
    }
};
