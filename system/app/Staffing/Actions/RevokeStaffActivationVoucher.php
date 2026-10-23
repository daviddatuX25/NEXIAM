<?php

declare(strict_types=1);

namespace App\Staffing\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Auth\StaffActivationVoucher;
use App\Models\Auth\StaffAuthenticationAttempt;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\User;
use App\Staffing\Support\StaffRoleCeiling;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RevokeStaffActivationVoucher
{
    public function __construct(
        private readonly StaffRoleCeiling $ceiling,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(User $actor, User $target, StaffActivationVoucher $voucher, ?string $reason = null): StaffActivationVoucher
    {
        if (! $this->ceiling->canManageUser($actor, $target)
            || (! $this->ceiling->isFullHr($actor) && $this->ceiling->rankOf($actor) < 3)) {
            throw new RuntimeException('Actor cannot revoke vouchers for this staff member.');
        }

        if ((int) $voucher->user_id !== (int) $target->id) {
            throw new RuntimeException('Voucher does not belong to this staff member.');
        }

        return DB::transaction(function () use ($actor, $target, $voucher, $reason): StaffActivationVoucher {
            $lockedTarget = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            StaffAuthenticationState::query()
                ->where('user_id', $lockedTarget->id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = StaffActivationVoucher::query()
                ->whereKey($voucher->id)
                ->where('user_id', $lockedTarget->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isRevoked()) {
                return $locked;
            }

            $continuation = StaffAuthenticationAttempt::query()
                ->where('voucher_id', $locked->id)
                ->where('user_id', $lockedTarget->id)
                ->whereNull('completed_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if ($locked->isRedeemed() && $continuation === null) {
                throw new RuntimeException('A completed redeemed voucher cannot be revoked.');
            }

            $locked->forceFill([
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ])->save();

            if ($continuation !== null) {
                $continuation->forceFill(['revoked_at' => now()])->save();
            }

            $this->recordAuditEvent->handle('hr.voucher.revoked', [
                'user_id' => $lockedTarget->id,
                'voucher_id' => $locked->id,
                'purpose' => $locked->purpose,
                'continuation_revoked' => $continuation !== null,
            ], (string) $actor->id);

            return $locked->refresh();
        });
    }
}
