import { router } from '@inertiajs/svelte';

type RealtimePulseEvent = {
  domain: string;
  revision: string;
  workspaceId: string;
};

type RealtimePulseListener = (event: RealtimePulseEvent) => void;

type ProjectionRevisions = {
  notifications?: string;
  navigation_badges?: string;
};

type Observation = {
  principal: string;
  contextKey: string;
  revisions: ProjectionRevisions;
  workspaceId: string;
};

const listeners = new Map<string, Set<RealtimePulseListener>>();
let previousObservation: Observation | null = null;
let reloadInFlight = false;

function dispatch(domain: string, revision: string, workspaceId: string): void {
  for (const listener of listeners.get(domain) ?? []) {
    listener({ domain, revision, workspaceId });
  }
}

export function subscribeRealtimePulse(domain: string, listener: RealtimePulseListener): () => void {
  const domainListeners = listeners.get(domain) ?? new Set<RealtimePulseListener>();
  domainListeners.add(listener);
  listeners.set(domain, domainListeners);

  return () => {
    domainListeners.delete(listener);
    if (domainListeners.size === 0) {
      listeners.delete(domain);
    }
  };
}

export function notifyRealtimePulseObservation(props: Record<string, any>): void {
  const notifications = props.notifications;
  const navigationBadges = props.navigation_badges;
  const principal = String(props.auth?.user?.id ?? 'anonymous');
  const contextKey = String(navigationBadges?.context_key ?? '');
  const workspaceId = String(
    notifications?.workspace_id ?? navigationBadges?.workspace_id ?? contextKey,
  );
  const current: Observation = {
    principal,
    contextKey,
    revisions: {
      notifications: typeof notifications?.revision === 'string' ? notifications.revision : undefined,
      navigation_badges: typeof navigationBadges?.revision === 'string' ? navigationBadges.revision : undefined,
    },
    workspaceId,
  };
  const previous = previousObservation;
  previousObservation = current;

  if (!previous || previous.principal !== current.principal || previous.contextKey !== current.contextKey) {
    return;
  }

  for (const domain of ['notifications', 'navigation_badges'] as const) {
    const revision = current.revisions[domain];
    if (revision && revision !== previous.revisions[domain]) {
      dispatch('notifications', revision, workspaceId);
      dispatch('messaging', revision, workspaceId);
    }
  }
}

export function handleRealtimeWakeup(
  payload: { domains?: string[]; workspace_id?: string; revision?: string },
  currentWorkspaceId?: string,
): void {
  if (
    !payload.workspace_id
    || !currentWorkspaceId
    || payload.workspace_id !== currentWorkspaceId
    || reloadInFlight
  ) {
    return;
  }

  reloadInFlight = true;
  router.reload({
    only: ['notifications', 'navigation_badges'],
    onFinish: () => {
      reloadInFlight = false;
    },
  });
}
