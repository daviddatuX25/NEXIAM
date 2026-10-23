<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('document_revision')->default(1);
        });

        Schema::create('notice_document_drafts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('communication_template_id')->unique()->constrained('communication_templates')->cascadeOnDelete();
            $table->unsignedInteger('base_version')->default(0);
            $table->unsignedBigInteger('document_revision')->default(1);
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->unsignedTinyInteger('stage')->default(1);
            $table->jsonb('state');
            $table->timestamps();
            $table->index(['communication_template_id', 'document_revision']);
        });

        Schema::create('notice_document_versions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('communication_template_id')->constrained('communication_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->jsonb('state');
            $table->char('render_sha256', 64);
            $table->char('definition_sha256', 64);
            $table->text('proof_receipt');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['communication_template_id', 'version']);
            $table->index(['communication_template_id', 'render_sha256']);
        });

        Schema::create('notice_document_receipts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 32);
            $table->string('canonical_slug', 128);
            $table->string('operation', 64);
            $table->string('idempotency_key', 128);
            $table->char('payload_sha256', 64);
            $table->jsonb('response_payload');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['institution_id', 'domain', 'canonical_slug', 'operation', 'idempotency_key'], 'notice_document_receipt_scope_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_document_receipts');
        Schema::dropIfExists('notice_document_versions');
        Schema::dropIfExists('notice_document_drafts');
        Schema::table('communication_templates', function (Blueprint $table): void {
            $table->dropColumn('document_revision');
        });
    }
};
