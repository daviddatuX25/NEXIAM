<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffEmailChange;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelStaffEmailChange
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $record = StaffEmailChange::query()
                ->where('user_id', $user->id)
                ->whereNull('confirmed_at')
                ->whereNull('cancelled_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->latest()
                ->first();

            if ($record === null) {
                return;
            }

            $record->update(['cancelled_at' => now()]);
            $this->recordAuditEvent->handle('auth.staff_email.change_cancelled', [
                'user_id' => $user->id,
                'email_change_id' => $record->id,
                'new_email' => $record->new_email,
            ], (string) $user->id);
        });
    }
}
