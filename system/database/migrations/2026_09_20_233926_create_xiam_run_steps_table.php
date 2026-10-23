<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xiam_run_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('run_id')->index();
            $table->uuid('conversation_id')->index();
            $table->unsignedInteger('seq');
            $table->string('step_kind', 32);
            $table->string('status', 32)->default('queued');
            $table->string('provider', 64)->nullable();
            $table->string('provider_request_id', 128)->nullable();
            $table->string('tool_id')->nullable();
            $table->string('skill_key')->nullable();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'seq']);
            $table->foreign('run_id')->references('id')->on('xiam_runs')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('xiam_conversations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xiam_run_steps');
    }
};
