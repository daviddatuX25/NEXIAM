<?php

use App\Foundation\Actions\RecordAuditEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('staff_profiles')
            ->whereIn('office', ['admin', 'registrar', 'guidance'])
            ->where(function ($query): void {
                $query->whereNotNull('department_id')
                    ->orWhereJsonLength('program_codes', '>', 0);
            })
            ->select(['user_id', 'office', 'department_id', 'program_codes'])
            ->orderBy('user_id')
            ->chunkById(500, function ($profiles): void {
                foreach ($profiles as $profile) {
                    app(RecordAuditEvent::class)->handle('staff.central_scope_normalized', [
                        'before' => [
                            'user_id' => $profile->user_id,
                            'office' => $profile->office,
                            'prior_department_id' => $profile->department_id,
                            'prior_program_codes' => $profile->program_codes,
                        ],
                        'after' => [
                            'user_id' => $profile->user_id,
                            'office' => $profile->office,
                            'department_id' => null,
                            'program_codes' => '[]',
                        ],
                    ]);

                    DB::table('staff_profiles')
                        ->where('user_id', $profile->user_id)
                        ->update([
                            'department_id' => null,
                            'program_codes' => '[]',
                        ]);
                }
            }, 'user_id', 'user_id');
    }

    public function down(): void
    {
        // Central staff scope normalization is intentionally forward-only.
    }
};
