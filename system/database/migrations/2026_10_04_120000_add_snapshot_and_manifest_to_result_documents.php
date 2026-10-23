<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_documents', function (Blueprint $table): void {
            $table->jsonb('snapshot_payload_json')->nullable();
            $table->jsonb('artifact_manifest')->nullable();
            $table->foreignId('predecessor_id')
                ->nullable()
                ->constrained('result_documents')
                ->nullOnDelete();
        });

        Schema::table('admission_attempts', function (Blueprint $table): void {
            $table->foreignId('current_result_document_id')
                ->nullable()
                ->constrained('result_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('admission_attempts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_result_document_id');
        });

        Schema::table('result_documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('predecessor_id');
            $table->dropColumn(['snapshot_payload_json', 'artifact_manifest']);
        });
    }
};
