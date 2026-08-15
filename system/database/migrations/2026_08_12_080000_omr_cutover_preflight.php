<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $batchCount = Schema::hasTable('omr_capture_batches')
            ? DB::table('omr_capture_batches')->count()
            : 0;
        $sheetCount = Schema::hasTable('omr_capture_sheets')
            ? DB::table('omr_capture_sheets')->count()
            : 0;

        if ($batchCount > 0 || $sheetCount > 0) {
            throw new RuntimeException(
                'OMR cutover blocked: existing skeleton rows were found in omr_capture_batches or omr_capture_sheets. '
                .'Take a verified database backup, export or reconcile those rows, then reset the demo database and rerun migrations.'
            );
        }
    }

    public function down(): void
    {
        // This migration has no schema effect.
    }
};
