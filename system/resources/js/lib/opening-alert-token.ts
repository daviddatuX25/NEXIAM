export type OpeningAlertTokenState =
    | 'verify'
    | 'unsubscribe'
    | 'invalid'
    | 'confirmed'
    | 'unsubscribed'
    | 'open';

export type OpeningAlertPurpose = 'verify' | 'unsubscribe';

interface RouterPost {
    post(
        url: string,
        data: { purpose: OpeningAlertPurpose },
        options: { preserveScroll: true; onFinish: () => void },
    ): void;
}

export function canConfirmOpeningAlertToken(
    state: OpeningAlertTokenState | string,
    purpose: string,
    token: string,
): boolean {
    return Boolean(token) &&
        ((state === 'verify' && purpose === 'verify') ||
            (state === 'unsubscribe' && purpose === 'unsubscribe'));
}

export function postOpeningAlertTokenAction(
    router: RouterPost,
    state: OpeningAlertTokenState | string,
    purpose: string,
    token: string,
    onFinish: () => void,
): boolean {
    if (!canConfirmOpeningAlertToken(state, purpose, token)) {
        return false;
    }

    router.post(
        `/apply/opening-alerts/${encodeURIComponent(token)}`,
        { purpose },
        { preserveScroll: true, onFinish },
    );

    return true;
}
