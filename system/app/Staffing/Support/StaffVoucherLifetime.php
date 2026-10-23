<?php

namespace App\Staffing\Support;

use Carbon\CarbonImmutable;
use DateTimeZone;
use RuntimeException;
use Throwable;

final class StaffVoucherLifetime
{
    public const ACTIVATION_MODES = [
        'standard_24h',
        'extended_7d',
        'extended_30d',
        'extended_90d',
        'extended_180d',
        'custom',
        'never_expires_single_use',
    ];

    public const RECOVERY_MODES = ['standard_24h', 'extended_7d'];

    public function expiry(
        string $purpose,
        string $mode,
        ?string $customExpiry = null,
        ?string $timezone = null,
        bool $isHr = false,
    ): ?CarbonImmutable {
        $allowed = $purpose === 'activation' ? self::ACTIVATION_MODES : self::RECOVERY_MODES;
        if (! in_array($mode, $allowed, true)) {
            throw new RuntimeException('That voucher lifetime is not available for this purpose.');
        }
        if ($mode === 'never_expires_single_use' && ($purpose !== 'activation' || ! $isHr)) {
            throw new RuntimeException('Only HR may issue non-expiring activation vouchers.');
        }

        if ($mode === 'custom') {
            $timezone ??= (string) config('app.timezone', 'UTC');
            if ($customExpiry === null || ! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                throw new RuntimeException('Choose a valid future expiry date and institution timezone.');
            }
            try {
                $expiry = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $customExpiry, $timezone);
            } catch (Throwable) {
                throw new RuntimeException('The custom expiry must be a canonical future date and time.');
            }
            if ($expiry === false || $expiry->format('Y-m-d\\TH:i') !== $customExpiry || ! $expiry->isFuture()) {
                throw new RuntimeException('The custom expiry must be a canonical future date and time.');
            }

            return $expiry->utc();
        }

        return match ($mode) {
            'never_expires_single_use' => null,
            'extended_7d' => CarbonImmutable::now('UTC')->addDays(7),
            'extended_30d' => CarbonImmutable::now('UTC')->addDays(30),
            'extended_90d' => CarbonImmutable::now('UTC')->addDays(90),
            'extended_180d' => CarbonImmutable::now('UTC')->addDays(180),
            default => CarbonImmutable::now('UTC')->addHours(24),
        };
    }
}
