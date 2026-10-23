<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['communication_template_drafts', 'communication_template_versions'] as $table) {
            DB::table($table)
                ->whereNull('document_revision_id')
                ->whereNotNull('notice_document_version_id')
                ->whereExists(function ($query) use ($table): void {
                    $query->selectRaw('1')->from('notice_document_versions')
                        ->whereColumn('notice_document_versions.id', $table.'.notice_document_version_id')
                        ->whereColumn('notice_document_versions.communication_template_id', $table.'.communication_template_id');
                })
                ->update(['document_revision_id' => DB::raw('notice_document_version_id')]);
        }
    }

    /**
     * Keeping the copied canonical references is safer than clearing values written after this migration.
     */
    public function down(): void {}
};
