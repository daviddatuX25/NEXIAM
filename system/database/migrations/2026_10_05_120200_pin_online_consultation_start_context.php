<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_encounters', function (Blueprint $table): void {
            $table->foreignId('result_document_id')
                ->nullable()
                ->after('admission_attempt_id')
                ->constrained('result_documents')
                ->restrictOnDelete();
            $table->unsignedInteger('result_document_version')->nullable()->after('result_document_id');
            $table->unsignedInteger('consultation_policy_version')->nullable()->after('result_document_version');
            $table->char('command_fingerprint', 64)->nullable()->after('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_encounters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('result_document_id');
            $table->dropColumn([
                'result_document_version',
                'consultation_policy_version',
                'command_fingerprint',
            ]);
        });
    }
};
