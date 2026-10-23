<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_public_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->unique()->constrained('programs')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->unsignedInteger('current_revision')->default(0);
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestampsTz();
            $table->index(['institution_id', 'program_id'], 'program_public_profiles_scope_idx');
        });

        Schema::create('program_public_profile_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->restrictOnDelete();
            $table->string('storage_disk', 32)->default('sensitive');
            $table->string('storage_path', 255)->unique();
            $table->char('sha256', 64);
            $table->string('mime_type', 32);
            $table->unsignedInteger('byte_size');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('status', 16)->default('ready');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['program_id', 'status', 'id'], 'program_public_profile_photos_ready_idx');
            $table->index(['institution_id', 'program_id', 'created_at'], 'program_public_profile_photos_scope_idx');
        });

        Schema::create('program_public_profile_photo_stage_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('photo_id')->constrained('program_public_profile_photos')->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('required_capability', 100);
            $table->char('source_sha256', 64);
            $table->char('payload_hash', 64);
            $table->json('result');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['actor_id', 'request_id'], 'program_profile_photo_actor_request_unique');
            $table->index(['program_id', 'id'], 'program_profile_photo_stage_program_idx');
        });

        Schema::create('program_public_profile_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('profile_id')->constrained('program_public_profiles')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('short_description', 240)->default('');
            $table->text('description');
            $table->json('photos');
            $table->boolean('is_published')->default(false);
            $table->string('operation', 16);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name', 160);
            $table->timestampsTz();
            $table->unique(['profile_id', 'revision'], 'program_public_profile_revision_unique');
            $table->index(['profile_id', 'is_published', 'revision'], 'program_public_profile_revision_lookup_idx');
        });

        Schema::table('program_public_profiles', function (Blueprint $table): void {
            $table->foreign('published_revision_id', 'program_public_profiles_published_revision_fk')
                ->references('id')->on('program_public_profile_revisions')->restrictOnDelete();
        });

        Schema::create('program_public_profile_command_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('operation', 16);
            $table->string('required_capability', 100);
            $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
            $table->foreignId('profile_id')->constrained('program_public_profiles')->restrictOnDelete();
            $table->unsignedInteger('expected_revision');
            $table->foreignId('revision_id')->constrained('program_public_profile_revisions')->restrictOnDelete();
            $table->char('payload_hash', 64);
            $table->json('result');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['actor_id', 'request_id'], 'program_public_profile_actor_request_unique');
            $table->index(['program_id', 'operation', 'id'], 'program_public_profile_receipts_program_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_public_profile_command_receipts');
        Schema::table('program_public_profiles', function (Blueprint $table): void {
            $table->dropForeign('program_public_profiles_published_revision_fk');
        });
        Schema::dropIfExists('program_public_profile_revisions');
        Schema::dropIfExists('program_public_profile_photo_stage_receipts');
        Schema::dropIfExists('program_public_profile_photos');
        Schema::dropIfExists('program_public_profiles');
    }
};
