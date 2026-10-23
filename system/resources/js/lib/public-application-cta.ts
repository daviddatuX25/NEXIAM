export function isPublicIntakeOpen(
    activeSeason: { is_open?: unknown } | null | undefined,
    sharedAvailability: unknown,
): boolean {
    if (typeof activeSeason?.is_open === 'boolean') {
        return activeSeason.is_open;
    }

    return sharedAvailability === true;
}

export function publicApplyCtaLabel(
    isOpen: boolean,
    alertsAvailable: boolean,
    compact = false,
): string {
    if (isOpen) {
        return compact ? 'Apply' : 'Start your application';
    }

    if (!alertsAvailable) {
        return 'Admissions information';
    }

    return compact ? 'Notify me' : 'Notify me when applications open';
}
