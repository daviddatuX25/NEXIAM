<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('family_revision')->default(1)->after('active_version');
        });

        Schema::table('communication_template_drafts', function (Blueprint $table): void {
            $table->unsignedBigInteger('lock_version')->default(1)->after('base_version');
            $table->unsignedTinyInteger('stage')->default(1)->after('lock_version');
            $table->string('content_schema_version', 32)->default('notice-content-2')->after('stage');
            $table->string('subject', 255)->nullable()->after('content_schema_version');
            $table->string('body', 500)->nullable()->after('subject');
            $table->jsonb('email_blueprint')->nullable()->after('document_source_drafts');
            $table->string('email_renderer_version', 32)->default('notice-email-1')->after('email_blueprint');
            $table->jsonb('import_provenance')->nullable()->after('email_renderer_version');
            $table->string('content_sha256', 64)->nullable()->after('import_provenance');
        });

        Schema::table('communication_template_versions', function (Blueprint $table): void {
            $table->string('content_schema_version', 32)->default('notice-content-2')->after('version');
            $table->string('subject', 255)->nullable()->after('content_schema_version');
            $table->string('body', 500)->nullable()->after('subject');
            $table->jsonb('email_blueprint')->nullable()->after('orientation');
            $table->string('email_renderer_version', 32)->default('notice-email-1')->after('email_blueprint');
            $table->jsonb('import_provenance')->nullable()->after('email_renderer_version');
            $table->string('content_sha256', 64)->nullable()->after('import_provenance');
        });

        Schema::create('communication_template_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('domain', 32);
            $table->string('canonical_slug', 64);
            $table->string('operation', 64);
            $table->string('idempotency_key', 128);
            $table->string('payload_sha256', 64);
            $table->jsonb('response_payload');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['institution_id', 'domain', 'canonical_slug', 'operation', 'idempotency_key'],
                'comm_tmpl_receipts_unique'
            );
            $table->index(
                ['institution_id', 'domain', 'canonical_slug'],
                'comm_tmpl_receipts_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_template_receipts');

        Schema::table('communication_template_versions', function (Blueprint $table): void {
            $table->dropColumn([
                'content_schema_version',
                'subject',
                'body',
                'email_blueprint',
                'email_renderer_version',
                'import_provenance',
                'content_sha256',
            ]);
        });

        Schema::table('communication_template_drafts', function (Blueprint $table): void {
            $table->dropColumn([
                'lock_version',
                'stage',
                'content_schema_version',
                'subject',
                'body',
                'email_blueprint',
                'email_renderer_version',
                'import_provenance',
                'content_sha256',
            ]);
        });

        Schema::table('communication_templates', function (Blueprint $table): void {
            $table->dropColumn(['family_revision']);
        });
    }
};
