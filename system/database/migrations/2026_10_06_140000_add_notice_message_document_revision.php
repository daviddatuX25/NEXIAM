<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['communication_template_drafts', 'communication_template_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('document_revision_id')->nullable()->constrained('notice_document_versions')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['communication_template_drafts', 'communication_template_versions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('document_revision_id');
            });
        }
    }
};
