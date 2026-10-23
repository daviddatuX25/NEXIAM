<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('announcements')
            ->where('publisher_key', 'like', 'program:%')
            ->orderBy('id')
            ->chunkById(200, function ($announcements): void {
                foreach ($announcements as $announcement) {
                    $code = substr($announcement->publisher_key, 8);
                    $programId = DB::table('programs')
                        ->where('institution_id', $announcement->institution_id)
                        ->where('code', $code)
                        ->where('is_active', true)
                        ->value('id');

                    if ($programId === null) {
                        continue;
                    }

                    DB::table('announcements')->where('id', $announcement->id)->update(['program_id' => $programId]);
                    DB::table('announcement_revisions')->where('announcement_id', $announcement->id)->update(['program_id' => $programId]);
                }
            });

        DB::table('announcement_command_receipts')
            ->whereNotNull('announcement_id')
            ->orderBy('id')
            ->chunkById(200, function ($receipts): void {
                foreach ($receipts as $receipt) {
                    $result = json_decode($receipt->result, true);
                    if (! is_array($result) || ! is_array($result['snapshot'] ?? null)) {
                        continue;
                    }

                    $snapshot = $result['snapshot'];
                    if (array_key_exists('program_id', $snapshot)) {
                        continue;
                    }

                    $snapshot['program_id'] = DB::table('announcements')->where('id', $receipt->announcement_id)->value('program_id');
                    $result['snapshot'] = $snapshot;
                    DB::table('announcement_command_receipts')->where('id', $receipt->id)->update([
                        'result' => json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Placement backfill and receipt snapshot reconstruction are intentionally retained; use a forward fix.
    }
};
