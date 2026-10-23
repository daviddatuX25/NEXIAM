<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_template_drafts', function (Blueprint $table): void {
            $table->jsonb('builder_json')->nullable();
            $table->string('paper_size', 16)->nullable();
            $table->string('orientation', 16)->nullable();
            $table->jsonb('document_source_drafts')->nullable();
        });

        Schema::table('communication_template_versions', function (Blueprint $table): void {
            $table->jsonb('builder_json')->nullable();
            $table->string('paper_size', 16)->nullable();
            $table->string('orientation', 16)->nullable();
        });

        Schema::create('communication_template_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('communication_template_id')->constrained('communication_templates')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('checksum', 64);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['communication_template_id', 'institution_id']);
        });

        DB::table('communication_template_drafts')
            ->where('document_mode', 'none')
            ->update(['paper_size' => null, 'orientation' => null]);

        DB::table('communication_template_versions')
            ->where('document_mode', 'none')
            ->update(['paper_size' => null, 'orientation' => null]);
        Schema::create('notification_delivery_attempt_artifacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_id')->constrained('notification_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('checksum', 64);
            $table->string('mime_type', 64);
            $table->unsignedBigInteger('file_size_bytes');
            $table->timestamps();

            $table->unique(['delivery_id', 'attempt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempt_artifacts');
        Schema::dropIfExists('communication_template_documents');

        Schema::table('communication_template_versions', function (Blueprint $table): void {
            $table->dropColumn(['builder_json', 'paper_size', 'orientation']);
        });

        Schema::table('communication_template_drafts', function (Blueprint $table): void {
            $table->dropColumn(['builder_json', 'paper_size', 'orientation', 'document_source_drafts']);
        });
    }
};
