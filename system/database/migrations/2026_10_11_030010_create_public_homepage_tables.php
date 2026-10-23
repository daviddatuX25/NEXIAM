<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_homepage_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('storage_disk', 40);
            $table->string('storage_path');
            $table->char('sha256', 64);
            $table->string('mime_type', 80);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('status', 24)->default('staged');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'status']);
        });

        Schema::create('public_homepage_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->enum('key', ['hero', 'campus_stories']);
            $table->string('eyebrow')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('badge_text')->nullable();
            $table->foreignId('image_id')->nullable()->constrained('public_homepage_media')->nullOnDelete();
            $table->string('image_alt')->nullable();
            $table->string('seed_image_src', 2048)->nullable();
            $table->string('image_source')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'key']);
        });

        Schema::create('public_homepage_stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('public_homepage_sections')->cascadeOnDelete();
            $table->string('seed_key')->nullable();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('body');
            $table->foreignId('image_id')->nullable()->constrained('public_homepage_media')->nullOnDelete();
            $table->string('image_alt')->nullable();
            $table->string('seed_image_src', 2048)->nullable();
            $table->string('image_source')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_href', 2048)->nullable();
            $table->enum('align', ['left', 'right'])->default('right');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['institution_id', 'section_id', 'is_active', 'sort_order']);
            $table->unique(['institution_id', 'seed_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_homepage_stories');
        Schema::dropIfExists('public_homepage_sections');
        Schema::dropIfExists('public_homepage_media');
    }
};
