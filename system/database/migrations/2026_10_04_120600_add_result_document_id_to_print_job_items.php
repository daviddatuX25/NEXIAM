<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_job_items', function (Blueprint $table): void {
            $table->foreignId('result_document_id')
                ->nullable()
                ->after('result_release_id')
                ->constrained('result_documents')
                ->nullOnDelete();
            $table->unique(['print_job_id', 'result_document_id'], 'print_job_items_job_document_unique');
        });

        Schema::table('result_releases', function (Blueprint $table): void {
            $table->foreignId('consultation_encounter_id')
                ->nullable()
                ->after('consultation_decision_id')
                ->constrained('consultation_encounters')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('result_releases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('consultation_encounter_id');
        });

        Schema::table('print_job_items', function (Blueprint $table): void {
            $table->dropUnique('print_job_items_job_document_unique');
            $table->dropConstrainedForeignId('result_document_id');
        });
    }
};
