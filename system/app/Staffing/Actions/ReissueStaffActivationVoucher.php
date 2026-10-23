<?php

namespace App\Staffing\Actions;

use App\Models\User;

class ReissueStaffActivationVoucher
{
    public function __construct(private readonly IssueStaffActivationVoucher $issue) {}

    /** @return array{public_reference: string, token: string, fallback: string, mode: string, expires_at: ?string, qr_url: string} */
    public function handle(
        User $actor,
        User $target,
        string $purpose = 'activation',
        string $mode = 'standard_24h',
        ?string $customExpiry = null,
        ?string $timezone = null,
    ): array {
        return $this->issue->handle($actor, $target, $purpose, $mode, $customExpiry, $timezone, true);
    }
}
