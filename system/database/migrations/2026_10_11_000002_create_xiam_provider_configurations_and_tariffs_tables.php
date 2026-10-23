<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xiam_provider_configurations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->unique()->constrained('institutions')->cascadeOnDelete();
            $table->string('driver', 32);
            $table->string('base_url', 255)->nullable();
            $table->string('key_mode', 16)->default('env');
            $table->text('custom_api_key')->nullable();
            $table->string('active_model_id', 128);
            $table->string('fallback_model_id', 128)->nullable();
            $table->integer('timeout_seconds')->default(30);
            $table->integer('max_tokens')->nullable();
            $table->decimal('temperature', 3, 2)->default(0.20);
            $table->boolean('is_on_premises')->default(false);
            $table->unsignedInteger('config_version')->default(1);
            $table->json('discovered_models')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('xiam_model_tariffs', function (Blueprint $table): void {
            $table->id();
            $table->string('model_id', 128);
            $table->string('provider_driver', 32);
            $table->string('name', 128);
            $table->boolean('supports_tools')->default(true);
            $table->integer('context_window')->default(128000);
            $table->decimal('prompt_micro_per_token', 14, 6)->default(0);
            $table->decimal('completion_micro_per_token', 14, 6)->default(0);
            $table->decimal('cache_read_micro_per_token', 14, 6)->default(0);
            $table->decimal('cache_write_micro_per_token', 14, 6)->default(0);
            $table->decimal('reasoning_micro_per_token', 14, 6)->default(0);
            $table->string('pricing_source', 32)->default('manual_catalog');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['model_id', 'provider_driver'], 'uq_tariff_model_driver');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xiam_model_tariffs');
        Schema::dropIfExists('xiam_provider_configurations');
    }
};
