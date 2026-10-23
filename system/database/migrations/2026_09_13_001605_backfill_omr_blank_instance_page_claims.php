<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('omr_blank_instance_pages')
            ->whereNotNull('captured_page_id')
            ->orderBy('id')
            ->each(function (object $page): void {
                $capturedBy = DB::table('omr_capture_sheet_pages')
                    ->join('omr_capture_sheets', 'omr_capture_sheets.id', '=', 'omr_capture_sheet_pages.sheet_id')
                    ->where('omr_capture_sheet_pages.id', $page->captured_page_id)
                    ->value('omr_capture_sheets.captured_by_user_id');

                if (DB::table('omr_blank_instance_page_claims')
                    ->where('capture_sheet_page_id', $page->captured_page_id)
                    ->exists()) {
                    DB::table('omr_blank_instance_pages')
                        ->where('id', $page->id)
                        ->update([
                            'status' => 'void',
                            'captured_page_id' => null,
                            'updated_at' => now(),
                        ]);

                    return;
                }
                DB::table('omr_blank_instance_page_claims')->insert([
                    'blank_instance_page_id' => $page->id,
                    'capture_sheet_page_id' => $page->captured_page_id,
                    'reproduction_authorization_id' => null,
                    'claim_kind' => 'original',
                    'created_by_user_id' => $capturedBy,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('omr_blank_instance_page_claims')->delete();
    }
};
