<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['communication_template_drafts', 'communication_template_versions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('notice_document_version_id')->nullable()
                    ->constrained('notice_document_versions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['communication_template_drafts', 'communication_template_versions'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('notice_document_version_id');
            });
        }
    }
};
