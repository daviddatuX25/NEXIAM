<?php

declare(strict_types=1);

namespace App\Auth\Support;

final class UserAgentDeviceParser
{
    /** @return array{device_type: string, browser: string, os: string} */
    public function parse(?string $userAgent): array
    {
        $agent = trim(substr($userAgent ?? '', 0, 1024));
        if ($agent === '') {
            return ['device_type' => 'Unknown', 'browser' => 'Unknown', 'os' => 'Unknown'];
        }

        $browser = match (true) {
            preg_match('/(?:EdgA|EdgiOS|Edg)\//i', $agent) === 1 => 'Edge',
            preg_match('/(?:Chrome|CriOS)\//i', $agent) === 1 => 'Chrome',
            preg_match('/(?:Firefox|FxiOS)\//i', $agent) === 1 => 'Firefox',
            preg_match('/Safari\//i', $agent) === 1 && preg_match('/(?:Chrome|CriOS|Edg|OPR)\//i', $agent) !== 1 => 'Safari',
            preg_match('/(?:OPR|Opera)\//i', $agent) === 1 => 'Other',
            default => 'Unknown',
        };

        $os = match (true) {
            preg_match('/iPad|iPhone|iPod/i', $agent) === 1 => 'iOS',
            preg_match('/Android/i', $agent) === 1 => 'Android',
            preg_match('/Windows NT|Windows Phone/i', $agent) === 1 => 'Windows',
            preg_match('/Macintosh|Mac OS X/i', $agent) === 1 => 'macOS',
            preg_match('/Linux|X11/i', $agent) === 1 => 'Linux',
            preg_match('/\b(?:CrOS|FreeBSD)\b/i', $agent) === 1 => 'Other',
            default => 'Unknown',
        };

        $device = match (true) {
            preg_match('/iPad|Tablet|PlayBook|Silk|Android(?!.*Mobile)/i', $agent) === 1 => 'Tablet',
            preg_match('/iPhone|iPod|Windows Phone|Android.*Mobile|Mobile/i', $agent) === 1 => 'Mobile',
            preg_match('/Windows NT|Macintosh|Mac OS X|X11|Linux x86_64|Linux i[0-9]86/i', $agent) === 1 => 'Desktop',
            default => 'Unknown',
        };

        return ['device_type' => $device, 'browser' => $browser, 'os' => $os];
    }
}
