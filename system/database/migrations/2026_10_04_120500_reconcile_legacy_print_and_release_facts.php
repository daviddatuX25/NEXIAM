<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admission_attempts')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('result_documents')
                    ->whereColumn('result_documents.admission_attempt_id', 'admission_attempts.id')
                    ->where('result_documents.status', 'ready');
            })
            ->update([
                'current_result_document_id' => DB::raw("(
                    SELECT result_documents.id
                    FROM result_documents
                    WHERE result_documents.admission_attempt_id = admission_attempts.id
                      AND result_documents.status = 'ready'
                    ORDER BY result_documents.id DESC
                    LIMIT 1
                )"),
            ]);

        DB::table('result_releases')
            ->whereNull('channel')
            ->where('mode', 'online')
            ->update(['channel' => 'portal']);

        DB::table('result_releases')
            ->whereNull('channel')
            ->where('mode', 'f2f')
            ->update(['channel' => 'physical']);
    }

    public function down(): void
    {
        // Reconciliation is intentionally irreversible; do not erase confirmed channels or pointers.
    }
};
