/**
 * Client transport for OpenSpec 3 Nexi runs — never treats responses as authz.
 */
import { newIdempotencyKey } from '@/lib/utils.js';

export type NexiTurnAccepted = {
  conversation_id: string;
  turn_id: string;
  run_id: string;
  context_epoch: string;
};

export type NexiSnapshot = {
  conversation_id: string;
  context_epoch: string;
  cursor: number;
  run: { id: string; status: string } | null;
  messages: Array<{
    role: 'user' | 'assistant';
    content: string;
    citations?: Array<{
      version_id?: string;
      source_title?: string | null;
      excerpt?: string;
    }>;
    finish_reason?: string | null;
  }>;
  events: Array<{ seq: number; type: string; payload: Record<string, unknown> }>;
};

function csrfToken(): string {
  const el = document.querySelector('meta[name="csrf-token"]');
  return el instanceof HTMLMetaElement ? el.content : '';
}

export async function submitNexiTurn(input: {
  message: string;
  pagePackKey?: string | null;
  pagePackVersion?: number | null;
  idempotencyKey?: string;
  mode?: 'inquiry' | 'assistant';
}): Promise<NexiTurnAccepted> {
  const res = await fetch('/nexi/turns', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      message: input.message,
      page_pack_key: input.pagePackKey ?? null,
      page_pack_version: input.pagePackVersion ?? null,
      idempotency_key: input.idempotencyKey ?? newIdempotencyKey(),
      mode: input.mode ?? 'inquiry',
    }),
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    const err = new Error(typeof body.error === 'string' ? body.error : 'turn_failed');
    throw err;
  }

  return (await res.json()) as NexiTurnAccepted;
}

export async function fetchNexiSnapshot(
  conversationId: string,
  after = 0,
): Promise<NexiSnapshot> {
  const res = await fetch(
    `/nexi/conversations/${conversationId}/snapshot?after=${after}`,
    {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    },
  );

  if (!res.ok) {
    throw new Error('snapshot_denied');
  }

  return (await res.json()) as NexiSnapshot;
}

export async function clearNexiConversation(): Promise<{
  cleared: boolean;
  conversation_id: string | null;
  context_epoch?: string;
}> {
  const res = await fetch('/nexi/conversations/clear', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: '{}',
  });
  if (!res.ok) {
    throw new Error('clear_denied');
  }
  return (await res.json()) as {
    cleared: boolean;
    conversation_id: string | null;
    context_epoch?: string;
  };
}

export async function startNexiSkill(input: {
  skillKey: string;
  pagePackKey?: string | null;
  pagePackVersion?: number | null;
  sessionId?: number | null;
}): Promise<{
  ok: boolean;
  grant_id: string;
  conversation_id: string;
  skill_key: string;
  cursor: string;
}> {
  const res = await fetch('/nexi/skills/start', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      skill_key: input.skillKey,
      page_pack_key: input.pagePackKey ?? null,
      page_pack_version: input.pagePackVersion ?? null,
      session_id: input.sessionId ?? null,
    }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(typeof body.error === 'string' ? body.error : 'skill_start_failed');
  }
  return (await res.json()) as {
    ok: boolean;
    grant_id: string;
    conversation_id: string;
    skill_key: string;
    cursor: string;
  };
}

export async function invokeNexiSkill(input: {
  grantId: string;
  capabilityId: string;
  input?: Record<string, unknown>;
  conversationId?: string | null;
}): Promise<{
  ok: boolean;
  result: Record<string, unknown>;
  proposal: {
    id: string;
    status: string;
    capability_id: string;
    summary?: Record<string, unknown>;
  } | null;
}> {
  const res = await fetch('/nexi/skills/invoke', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      grant_id: input.grantId,
      capability_id: input.capabilityId,
      input: input.input ?? {},
      conversation_id: input.conversationId ?? null,
    }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(typeof body.error === 'string' ? body.error : 'skill_invoke_failed');
  }
  return (await res.json()) as {
    ok: boolean;
    result: Record<string, unknown>;
    proposal: {
      id: string;
      status: string;
      capability_id: string;
      summary?: Record<string, unknown>;
    } | null;
  };
}

export async function confirmNexiProposal(
  proposalId: string,
  idempotencyKey?: string,
): Promise<{ ok: boolean; proposal: { id: string; status: string }; result: Record<string, unknown> }> {
  const res = await fetch(`/nexi/proposals/${proposalId}/confirm`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: JSON.stringify({
      idempotency_key: idempotencyKey ?? newIdempotencyKey(),
    }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(typeof body.error === 'string' ? body.error : 'confirm_failed');
  }
  return (await res.json()) as {
    ok: boolean;
    proposal: { id: string; status: string };
    result: Record<string, unknown>;
  };
}

export async function cancelNexiProposal(
  proposalId: string,
): Promise<{ ok: boolean; proposal: { id: string; status: string } }> {
  const res = await fetch(`/nexi/proposals/${proposalId}/cancel`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
    credentials: 'same-origin',
    body: '{}',
  });
  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new Error(typeof body.error === 'string' ? body.error : 'cancel_failed');
  }
  return (await res.json()) as {
    ok: boolean;
    proposal: { id: string; status: string };
  };
}
