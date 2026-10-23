<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 32);
            $table->string('event_key', 64);
            $table->string('slug', 64);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('active_version')->default(1);
            $table->timestamps();

            $table->unique(['institution_id', 'event_key']);
            $table->unique(['institution_id', 'domain', 'slug']);
            $table->index(['institution_id', 'domain']);
        });

        Schema::create('communication_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_template_id')->constrained('communication_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('email_subject', 255);
            $table->text('email_body');
            $table->string('in_app_title', 255);
            $table->string('in_app_body', 500);
            $table->string('action_label', 64)->nullable();
            $table->string('action_route', 128)->nullable();
            $table->string('document_mode', 32)->default('none');
            $table->uuid('document_asset_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['communication_template_id', 'version']);
        });

        Schema::create('communication_template_drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_template_id')->unique()->constrained('communication_templates')->cascadeOnDelete();
            $table->unsignedInteger('base_version');
            $table->string('email_subject', 255);
            $table->text('email_body');
            $table->string('in_app_title', 255);
            $table->string('in_app_body', 500);
            $table->string('action_label', 64)->nullable();
            $table->string('action_route', 128)->nullable();
            $table->string('document_mode', 32)->default('none');
            $table->uuid('document_asset_id')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_template_drafts');
        Schema::dropIfExists('communication_template_versions');
        Schema::dropIfExists('communication_templates');
    }
};
