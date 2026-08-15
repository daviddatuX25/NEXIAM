/**
 * NexiSessionView contract_version 1 — mirrors App\Ai\Http\NexiSessionView.
 * Unknown contract versions must render degraded/manual fallback.
 */

export type NexiAvailability =
  | 'hidden'
  | 'inquiry_only'
  | 'assistant_available'
  | 'assistant_active'
  | 'degraded'
  | 'quota_exhausted'
  | 'suspended';

export type NexiMode = 'inquiry' | 'assistant';

export type NexiUiIntent = {
  type: string;
  route_key?: string;
  label?: string;
};

export type NexiSessionView = {
  contract_version: string;
  surface: { key: string; version: number } | null;
  context_epoch: string | null;
  page_pack: { key: string; version: number } | null;
  publication_version: number | null;
  availability: NexiAvailability;
  modes: NexiMode[];
  active_mode: NexiMode;
  disclosure: { key: string; version: number; text: string };
  conversation: { id: string | null; run_id: string | null; cursor: number };
  usage: {
    state: 'available' | 'near_limit' | 'exhausted' | 'unknown';
    used: number | null;
    limit: number | null;
    unit: string;
    resets_at: string | null;
  };
  proposals: unknown[];
  manual_fallbacks: NexiUiIntent[];
};

/** Transitional Inertia share until NexiDock is the only consumer. */
export type NexiInertiaShare = {
  enabled?: boolean;
  mode?: string;
  disclosure?: string;
  default_interaction?: string;
  interaction_modes?: Array<{ key: string; label: string }>;
  usage?: { used?: number; limit?: number; unit?: string; period?: string };
  session?: NexiSessionView;
};

export const NEXI_CONTRACT_VERSION = '1';

export function isNexiSessionView(value: unknown): value is NexiSessionView {
  if (!value || typeof value !== 'object') {
    return false;
  }

  const v = value as Record<string, unknown>;

  return typeof v.contract_version === 'string' && typeof v.availability === 'string';
}

export function resolveNexiSession(raw: unknown): NexiSessionView | null {
  if (!raw || typeof raw !== 'object') {
    return null;
  }

  const share = raw as NexiInertiaShare & Record<string, unknown>;

  if (isNexiSessionView(share.session)) {
    return share.session;
  }

  if (isNexiSessionView(share)) {
    return share;
  }

  return null;
}

export function nexiShouldMount(session: NexiSessionView | null | undefined): boolean {
  if (!session) {
    return false;
  }

  return session.availability !== 'hidden';
}

export function nexiAvailabilityLabel(availability: NexiAvailability): string {
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
