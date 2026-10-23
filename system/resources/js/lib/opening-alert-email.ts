const STORAGE_KEY = 'nexiam:opening-alert-email:v1';
export const OPENING_ALERT_EMAIL_TTL_MS = 7 * 24 * 60 * 60 * 1000;

interface RememberedEmail {
    email: string;
    expiresAt: number;
}

export function rememberOpeningAlertEmail(email: string, now = Date.now()): void {
    const normalizedEmail = email.trim();
    if (!normalizedEmail) {
        return;
    }

    try {
        window.localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                email: normalizedEmail,
                expiresAt: now + OPENING_ALERT_EMAIL_TTL_MS,
            } satisfies RememberedEmail),
        );
    } catch {
        // Storage may be unavailable; email entry still works for this visit.
    }
}

export function loadRememberedOpeningAlertEmail(now = Date.now()): string {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);
        if (!stored) {
            return '';
        }

        const remembered = JSON.parse(stored) as Partial<RememberedEmail>;
        if (
            typeof remembered.email !== 'string' ||
            !remembered.email.trim() ||
            typeof remembered.expiresAt !== 'number' ||
            remembered.expiresAt <= now
        ) {
            window.localStorage.removeItem(STORAGE_KEY);
            return '';
        }

        return remembered.email;
    } catch {
        return '';
    }
}

export function forgetRememberedOpeningAlertEmail(): void {
    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Forget remains safe when storage is unavailable.
    }
}
