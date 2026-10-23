<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_template_documents', function (Blueprint $table): void {
            $table->string('mime_type', 128)->change();
        });
    }

    public function down(): void
    {
        // Canonical DOCX MIME metadata exceeds the old bound and must not be truncated by rollback.
    }
};
