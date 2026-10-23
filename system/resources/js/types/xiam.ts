/**
 * XiamSessionView contract_version 2 — mirrors App\Ai\Http\XiamSessionView.
 * Unknown contract versions must render degraded/manual fallback.
 */

export type XiamAvailability =
    | 'hidden'
    | 'inquiry_only'
    | 'assistant_available'
    | 'assistant_active'
    | 'degraded'
    | 'quota_exhausted'
    | 'suspended';



export type XiamUiIntent = {
    type: string;
    route_key?: string;
    label?: string;
};

export type XiamInteractionMode = 'inquiry' | 'assistant';

export type XiamInteractionModeOption = {
    key: XiamInteractionMode;
    label: string;
};

export type XiamUsageView = {
    state: 'available' | 'near_limit' | 'exhausted';
    used: number;
    limit: number;
    remaining: number | null;
    credit_balance: number;
    unit: string;
    period: string;
    resets_at: string | null;
};

export type XiamModeView = {
    active_mode: XiamInteractionMode;
    interaction_modes: XiamInteractionModeOption[];
    context_epoch: string | null;
    usage: XiamUsageView;
};

export type XiamSessionView = {
    contract_version: string;
    surface: { key: string; version: number } | null;
    context_epoch: string | null;
    page_pack: { key: string; version: number } | null;
    publication_version: number | null;
    availability: XiamAvailability;
    active_mode: XiamInteractionMode;
    interaction_modes: XiamInteractionModeOption[];

    disclosure: { key: string; version: number; text: string };
    conversation: { id: string | null; run_id: string | null; cursor: number };
    usage: XiamUsageView | null;
    proposals: unknown[];
    manual_fallbacks: XiamUiIntent[];
};

/** Transitional Inertia share until XiamDock is the only consumer. */
export type XiamInertiaShare = {
    enabled?: boolean;
    mode?: string;
    disclosure?: string;

    usage?: XiamUsageView;
    session?: XiamSessionView;
};

export const XIAM_CONTRACT_VERSION = '2';

export function isXiamSessionView(value: unknown): value is XiamSessionView {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const v = value as Record<string, unknown>;

    return (
        typeof v.contract_version === 'string' &&
        typeof v.availability === 'string'
    );
}

export function resolveXiamSession(raw: unknown): XiamSessionView | null {
    if (!raw || typeof raw !== 'object') {
        return null;
    }

    const share = raw as XiamInertiaShare & Record<string, unknown>;

    if (isXiamSessionView(share.session)) {
        return share.session;
    }

    if (isXiamSessionView(share)) {
        return share;
    }

    return null;
}

export function xiamShouldMount(
    session: XiamSessionView | null | undefined,
): boolean {
    if (!session) {
        return false;
    }

    return session.availability !== 'hidden';
}

export function xiamAvailabilityLabel(availability: XiamAvailability): string {
    switch (availability) {
        case 'degraded':
            return 'Degraded — provider offline; use the page path.';
        case 'inquiry_only':
            return 'Inquiry only — no Apply actions.';
        case 'quota_exhausted':
            return 'Quota exhausted — try again after reset.';
        case 'suspended':
            return 'Suspended — follow the manual path.';
        case 'assistant_available':
            return 'Assistant available — consent required.';
        case 'assistant_active':
            return 'Assistant active.';
        case 'hidden':
        default:
            return 'Hidden';
    }
}
