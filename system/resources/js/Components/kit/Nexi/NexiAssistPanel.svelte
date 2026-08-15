<script>
  /**
   * Universal Nexi assist — wireframe-aligned companion chrome.
   *
   * Layout:
   * - History stream scrolls above (older user + agent turns)
   * - Wrap island at bottom: floated frame + latest agent line (+ Thinking/Analyzing)
   *   so the current reply always wraps beside Nexi (screenshot-1 behavior)
   *
   * GF-22 / §8.4–8.5: disclosure is sr-only; typed actions only; no authz bypass.
   */
  import { tick } from 'svelte';
  import { usePage } from '@inertiajs/svelte';
  import {
    Minus,
    Send,
    Sparkles,
    ShieldAlert,
    ChevronDown,
    MessageCircle,
    Bot,
    BookOpenText,
  } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';
  import { resolveNexiContext } from '@/lib/nexi-context.js';
  import { actionsForContext } from '@/lib/nexi-actions.js';
  import {
    cancelNexiProposal,
    clearNexiConversation,
    confirmNexiProposal,
    fetchNexiSnapshot,
    startNexiSkill,
    submitNexiTurn,
  } from '@/lib/nexi-transport';

  let {
    enabled = true,
    mode = 'degraded',
    disclosure = 'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.',
    defaultInteraction = 'inquiry',
    interactionModes = [
      { key: 'inquiry', label: 'Inquiry' },
    ],
    usage = { used: 0, limit: 50, unit: 'asks', period: 'today' },
    /** @type {import('@/types/nexi').NexiSessionView | null} */
    session = null,
  } = $props();

  const STORAGE_KEY = 'nexiam.nexi.expanded';
  const MODE_KEY = 'nexiam.nexi.interaction';

  const page = usePage();
  const grants = $derived(page.props.auth?.user?.capabilities ?? []);
  /** Demo/fixture context only — never authority when session is set. */
  const fixtureContext = $derived(resolveNexiContext(page.url ?? '/'));
  const context = $derived.by(() => {
    if (session?.page_pack) {
      return {
        key: session.page_pack.key,
        title: session.page_pack.key,
        kind: 'page_help',
        summary:
          session.availability === 'degraded'
            ? 'Provider is offline. I can orient you to this page; use ordinary controls to act.'
            : 'Page-aware help from the server pack. Mutations need a later OpenSpec.',
        assistive_hint: 'Use the page path for changes. Apply tools are not live in this foundation cut.',
      };
    }
    return fixtureContext;
  });
  const actions = $derived.by(() => {
    // Server session: no client typed-action catalog as authority.
    if (session) {
      return [];
    }
    return actionsForContext(fixtureContext.key, grants).filter((a) => !a.locked);
  });

  const modes = $derived(
    Array.isArray(interactionModes) && interactionModes.length
      ? interactionModes
      : [{ key: 'assistant', label: 'Assistant' }]
  );

  let expanded = $state(false);
  let interaction = $state('inquiry');
  let modeMenuOpen = $state(false);
  let input = $state('');
  let frame = $state(0);
  /** @type {{ role: 'user'|'assistant', content: string, citations?: Array<{ source_title?: string, excerpt?: string, version_id?: string }>, corpusNote?: string | null }[]} */
  let messages = $state([]);
  /** @type {any} */
  let gate = $state(null);
  let hydrated = $state(false);
  /** @type {HTMLElement | undefined} */
  let dockRoot = $state();
  let loadedConversationId = $state(/** @type {string | null} */ (null));
  /** @type {HTMLDivElement | undefined} */
  let scrollEl = $state();
  /** Ephemeral filler in the wrap island — Thinking… / Analyzing… */
  let statusText = $state(/** @type {string | null} */ (null));
  let busy = $state(false);
  /** @type {string | null} */
  let skillGrantId = $state(null);
  /** @type {string | null} */
  let skillConversationId = $state(null);

  const currentMode = $derived(modes.find((m) => m.key === interaction) ?? modes[0]);

  /** Latest assistant turn stays in the wrap island; everything else is history. */
  const latestAssistantIndex = $derived.by(() => {
    for (let i = messages.length - 1; i >= 0; i -= 1) {
      if (messages[i].role === 'assistant') return i;
    }
    return -1;
  });

  const history = $derived.by(() => {
    if (latestAssistantIndex < 0) return messages;
    return messages.filter((_, i) => i !== latestAssistantIndex);
  });

  const spoken = $derived(
    latestAssistantIndex >= 0 ? messages[latestAssistantIndex] : null
  );

  const suggestions = $derived.by(() => {
    const pool = actions.filter((a) => {
      if (interaction === 'inquiry') {
        return (
          a.kind === 'guide' &&
          ['explain_page', 'cite_policy', 'escalate_human'].includes(a.action_key)
        );
      }
      return a.kind === 'guide' || a.kind === 'apply';
    });
    return pool.slice(0, 3);
  });

  const usagePct = $derived.by(() => {
    const limit = Number(usage?.limit ?? 0);
    const used = Number(usage?.used ?? 0);
    if (!limit) return 0;
    return Math.min(100, Math.round((used / limit) * 100));
  });

  const usageTitle = $derived.by(() => {
    const unit = usage?.unit ?? 'asks';
    const period = usage?.period ?? 'today';
    const limit = Number(usage?.limit ?? 0);
    const used = Number(usage?.used ?? 0);
    if (!limit) return 'Usage limits not configured';
    return `${used} of ${limit} ${unit} ${period}`;
  });

  const ringC = 2 * Math.PI * 9;
  const ringOffset = $derived(ringC * (1 - usagePct / 100));

  async function scrollToBottom() {
    await tick();
    requestAnimationFrame(() => {
      if (scrollEl) {
        scrollEl.scrollTop = scrollEl.scrollHeight;
      }
    });
  }

  $effect(() => {
    if (hydrated) return;
    hydrated = true;
    try {
      expanded = sessionStorage.getItem(STORAGE_KEY) === '1';
      const allowed = (Array.isArray(interactionModes) ? interactionModes : []).map((m) => m.key);
      // Server session owns mode. Local MODE_KEY cannot reactivate Assistant after pack/epoch changes.
      if (session) {
        interaction = session.active_mode || defaultInteraction || 'inquiry';
      } else {
        const saved = sessionStorage.getItem(MODE_KEY);
        if (saved && allowed.includes(saved)) {
          interaction = saved;
        } else {
          interaction = defaultInteraction || 'inquiry';
        }
      }
      if (!allowed.includes(interaction)) {
        interaction = allowed[0] || 'inquiry';
      }
    } catch {
      expanded = false;
      interaction = defaultInteraction || 'inquiry';
    }
    void bootstrapMessages();
  });

  function welcomeMessage() {
    const packLabel = session?.page_pack?.key ?? context.title;
    const availabilityNote =
      session?.availability === 'degraded'
        ? ' I’m in degraded mode — the provider is not live; use the page for real actions.'
        : '';
    return {
      role: /** @type {'assistant'} */ ('assistant'),
      content: `Hi — I’m Nexi on “${packLabel}”.${availabilityNote} Ask about what you’re looking at; chat uses the space beside my frame.`,
    };
  }

  async function bootstrapMessages() {
    const conversationId = session?.conversation?.id ?? null;
    if (conversationId) {
      await loadServerHistory(conversationId);
      return;
    }
    messages = [welcomeMessage()];
    loadedConversationId = null;
  }

  // Inertia page changes remount the dock — reload durable history whenever conversation id is present/changes.
  $effect(() => {
    const conversationId = session?.conversation?.id ?? null;
    if (!hydrated) return;
    if (!conversationId) return;
    if (conversationId === loadedConversationId && messages.length > 0) return;
    void loadServerHistory(conversationId);
  });

  $effect(() => {
    if (!hydrated) return;
    try {
      sessionStorage.setItem(STORAGE_KEY, expanded ? '1' : '0');
      // Do not persist interaction when server session owns mode.
      if (!session) {
        sessionStorage.setItem(MODE_KEY, interaction);
      }
    } catch {
      /* ignore */
    }
  });

  $effect(() => {
    messages;
    statusText;
    expanded;
    if (!expanded) return;
    void scrollToBottom();
  });


  function onDocumentPointerDown(event) {
    if (!expanded || busy) return;
    const target = event.target;
    if (!(target instanceof Node)) return;
    if (dockRoot && dockRoot.contains(target)) return;
    // Floating collapsed launcher also counts as inside when not expanded — handled by expanded check.
    minimize();
  }

  $effect(() => {
    if (!expanded) return;
    // Defer so the opening click does not immediately minimize.
    const timer = window.setTimeout(() => {
      document.addEventListener('pointerdown', onDocumentPointerDown);
    }, 0);
    return () => {
      window.clearTimeout(timer);
      document.removeEventListener('pointerdown', onDocumentPointerDown);
    };
  });

  function open() {
    expanded = true;
  }

  function minimize() {
    expanded = false;
    modeMenuOpen = false;
  }

  function toggleModeMenu() {
    if (modes.length < 2) return;
    modeMenuOpen = !modeMenuOpen;
  }

  function setInteraction(key) {
    if (!modes.some((m) => m.key === key)) return;
    // Server modes list is authority; allow Assistant when pack/surface include it.
    if (session && key === 'assistant' && !(session.modes ?? []).includes('assistant')) return;
    modeMenuOpen = false;
    if (interaction === key) return;
    interaction = key;
    gate = null;
    if (key === 'assistant') {
      void ensureExamSkillStarted();
    }
    speak(
      key === 'inquiry'
        ? 'Inquiry mode — policy and own-data questions. I won’t propose page Apply commands here.'
        : 'Assistant mode — allowlisted Apply previews only. Confirm still runs the same page Action.'
    );
  }

  function examSessionIdFromUrl() {
    const path = String(page.url ?? '');
    const m = path.match(/\/exams\/sessions\/(\d+)/);
    return m ? Number(m[1]) : null;
  }

  async function ensureExamSkillStarted() {
    if (!session?.page_pack?.key?.startsWith('staff.exams.sessions.')) return;
    if (skillGrantId) return;
    try {
      const started = await startNexiSkill({
        skillKey: 'schedule.exam_session@1',
        pagePackKey: session.page_pack.key,
        pagePackVersion: session.page_pack.version,
        sessionId: examSessionIdFromUrl(),
      });
      skillGrantId = started.grant_id;
      skillConversationId = started.conversation_id;
      loadedConversationId = started.conversation_id;
    } catch {
      speak('I could not start the exam scheduling skill. Use the ordinary page controls.');
    }
  }

  /** @param {Array<{ type: string, payload?: Record<string, unknown> }>} events */
  function gateFromSkillEvents(events) {
    const proposalEvent = [...events].reverse().find((e) => e.type === 'skill.proposal');
    if (!proposalEvent?.payload?.proposal_id) return null;
    const capability = String(proposalEvent.payload.capability_id ?? 'proposed command');
    return {
      kind: 'apply',
      label: `Apply: ${capability}`,
      description: 'Confirm runs the same Examination Action as the page button.',
      command_stub: capability,
      capability,
      needs_confirm: true,
      proposal_id: String(proposalEvent.payload.proposal_id),
      equivalent_ui: 'Exam session page control',
    };
  }

  function wait(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Demo filler beats in the wrap island before the real reply.
   * Presentation-only — no provider call.
   */
  async function runStatusThen(reply) {
    if (busy) return;
    busy = true;
    statusText = 'Thinking…';
    await scrollToBottom();
    await wait(360);
    statusText = 'Analyzing…';
    await scrollToBottom();
    await wait(420);
    statusText = null;
    busy = false;
    speak(reply);
  }

  function speak(text, citations = [], corpusNote = null) {
    messages = [...messages, { role: 'assistant', content: text, citations, corpusNote }];
    frame = frame === 0 ? 1 : 0;
  }

  /** @param {Array<{ type: string, payload?: Record<string, unknown> }>} events */
  function corpusNoteFromEvents(events) {
    const retrieval = [...events].reverse().find((e) => e.type === 'knowledge.retrieval');
    if (!retrieval?.payload) {
      return null;
    }
    const outcome = String(retrieval.payload.outcome ?? '');
    const hits = Number(retrieval.payload.hit_count ?? 0);
    if (outcome === 'support') {
      return `Corpus match: ${hits} source${hits === 1 ? '' : 's'} (see citations).`;
    }
    if (outcome === 'no_support') {
      return 'Corpus: no lexical match. Ask using words from the policy title or body, or publish richer policy text in Knowledge.';
    }
    return `Corpus outcome: ${outcome || 'unknown'}.`;
  }

  async function clearChat() {
    if (busy) return;
    busy = true;
    statusText = 'Clearing…';
    try {
      await clearNexiConversation();
      messages = [];
      loadedConversationId = null;
      statusText = null;
      speak('Conversation cleared. Ask a new question when you are ready.');
    } catch {
      statusText = null;
      speak('I could not clear the conversation. Use the ordinary page controls, or try again.');
    } finally {
      busy = false;
    }
  }

  function proposeAction(action) {
    if (busy) return;
    if (action.kind === 'apply') {
      gate = action;
      void runStatusThen(
        action.needs_confirm
          ? `I can propose “${action.label.replace(/^Apply:\s*/, '')}” — same as ${action.equivalent_ui}. Review the gate when you’re ready; I won’t run it myself.`
          : `Here’s a note on “${action.label}”. No records change from this.`
      );
      return;
    }
    gate = null;
    void runStatusThen(action.description);
  }

  async function confirmGate() {
    if (!gate || busy) return;
    const proposalId = gate.proposal_id ?? null;
    const stub = gate.command_stub;
    const current = gate;
    if (!proposalId) {
      gate = null;
      void runStatusThen(
        `Preview acknowledged → ${stub}. No durable proposal id was attached; use the page button if you need the change applied.`
      );
      return;
    }
    busy = true;
    statusText = 'Confirming…';
    try {
      const confirmed = await confirmNexiProposal(proposalId);
      gate = null;
      statusText = null;
      speak(
        `Applied via confirm → ${confirmed.proposal.capability_id ?? current.capability ?? stub}. Same Action as the page path.`
      );
    } catch (err) {
      statusText = null;
      const msg = err instanceof Error ? err.message : 'confirm_failed';
      speak(`Confirm failed (${msg}). Proposal may be stale — refresh the page and try again, or use the ordinary control.`);
    } finally {
      busy = false;
    }
  }

  async function dismissGate() {
    if (busy) return;
    const proposalId = gate?.proposal_id ?? null;
    gate = null;
    if (proposalId) {
      try {
        await cancelNexiProposal(proposalId);
      } catch {
        // cancel best-effort
      }
    }
    speak('Okay — staying on the page path. You’re in control.');
  }

  /** Hydrate confirm gate from server proposals (OpenSpec 8). */
  $effect(() => {
    const list = session?.proposals;
    if (!Array.isArray(list) || list.length === 0 || gate) return;
    const open = list.find((p) => p?.status === 'proposed');
    if (!open) return;
    const impact = open.summary?.impact ?? open.summary ?? {};
    const action = impact.action ?? open.capability_id;
    gate = {
      kind: 'apply',
      label: `Apply: ${action}`,
      description: 'Confirm runs the same Examination Action as the page button.',
      command_stub: open.capability_id,
      capability: open.capability_id,
      needs_confirm: true,
      proposal_id: open.id,
      equivalent_ui: 'Exam session page control',
    };
  });

  async function loadServerHistory(conversationId = session?.conversation?.id ?? null) {
    if (!conversationId) return;
    try {
      const snap = await fetchNexiSnapshot(conversationId, 0);
      loadedConversationId = conversationId;
      if (Array.isArray(snap.messages) && snap.messages.length) {
        messages = snap.messages.map((m) => ({
          role: m.role,
          content: m.content,
          citations: Array.isArray(m.citations) ? m.citations : [],
          corpusNote: null,
        }));
      } else if (messages.length === 0) {
        messages = [welcomeMessage()];
      }
    } catch {
      // Snapshot may 403/404 — keep local welcome if empty.
      if (messages.length === 0) {
        messages = [welcomeMessage()];
      }
    }
  }

  async function send() {
    const text = input.trim();
    if (!text || busy) return;
    messages = [...messages, { role: 'user', content: text }];
    input = '';
    frame = frame === 0 ? 1 : 0;

    if (session) {
      busy = true;
      statusText = 'Thinking…';
      try {
        if (interaction === 'assistant') {
          await ensureExamSkillStarted();
        }
        const accepted = await submitNexiTurn({
          message: text,
          pagePackKey: session.page_pack?.key ?? null,
          pagePackVersion: session.page_pack?.version ?? null,
          mode: interaction === 'assistant' ? 'assistant' : 'inquiry',
        });
        loadedConversationId = accepted.conversation_id;
        statusText = 'Analyzing…';
        // Sync queue completes in tests; poll snapshot briefly for assistant message.
        let assistant = null;
        let citations = [];
        let corpusNote = null;
        /** @type {Array<{ type: string, payload?: Record<string, unknown> }>} */
        let lastEvents = [];
        for (let i = 0; i < 20; i += 1) {
          const snap = await fetchNexiSnapshot(accepted.conversation_id, 0);
          lastEvents = Array.isArray(snap.events) ? snap.events : [];
          const last = [...snap.messages].reverse().find((m) => m.role === 'assistant');
          if (last?.content) {
            assistant = last.content;
            citations = Array.isArray(last.citations) ? last.citations : [];
            corpusNote = corpusNoteFromEvents(lastEvents);
            break;
          }
          if (snap.run?.status === 'failed' || snap.run?.status === 'cancelled') {
            assistant = 'That run did not complete. Use the ordinary page controls, or try again.';
            break;
          }
          await wait(100);
        }
        statusText = null;
        busy = false;
        const nextGate = gateFromSkillEvents(lastEvents);
        if (nextGate) {
          gate = nextGate;
        }
        speak(assistant ?? 'I could not fetch a reply yet. Refresh or try again.', citations, corpusNote);
      } catch (e) {
        statusText = null;
        busy = false;
        const code = e instanceof Error ? e.message : 'turn_failed';
        if (code === 'quota_exhausted') {
          speak('You have reached today’s Nexi ask limit. Use the ordinary page controls, or try again later.');
        } else if (code === 'emergency_stop') {
          speak('Nexi is suspended by emergency stop. Use the ordinary page path.');
        } else {
          speak('I could not send that turn. Use the ordinary page controls, or try again.');
        }
      }
      return;
    }

    const reply =
      mode === 'degraded'
        ? interaction === 'inquiry'
          ? `Inquiry on “${context.title}” — I cite approved Knowledge sources when they support the answer.`
          : `I’m with you on “${context.title}”. Talk here, or use the page controls when you’re ready to act.`
        : context.summary;

    void runStatusThen(reply);
  }

  function onKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  }
</script>

{#if enabled && mode !== 'disabled'}
  {#if expanded}
    <div
      bind:this={dockRoot}
      class="nexi-dock fixed bottom-3 right-3 z-50 flex max-h-[min(740px,calc(100dvh-1rem))] w-[min(420px,calc(100vw-1rem))] flex-col overflow-hidden rounded-2xl bg-card shadow-2xl ring-1 ring-border/50 sm:bottom-6 sm:right-6 sm:w-[min(480px,calc(100vw-2rem))]"
      role="dialog"
      aria-label="Nexi assistant"
      aria-describedby="nexi-disclosure"
    >
      <p id="nexi-disclosure" class="sr-only">{disclosure}</p>

      <div class="relative flex items-center gap-2 border-b border-border/50 px-3 py-2.5 sm:px-3.5">
        <Sparkles class="h-3.5 w-3.5 shrink-0 text-primary/80" aria-hidden="true" />

        {#if modes.length > 0}
          <div class="relative min-w-0">
            <button
              type="button"
              class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-border/70 bg-background px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:bg-muted/50 min-h-[32px]"
              aria-haspopup="listbox"
              aria-expanded={modeMenuOpen}
              aria-label="Interaction mode"
              onclick={toggleModeMenu}
              disabled={modes.length < 2}
            >
              {#if interaction === 'inquiry'}
                <BookOpenText class="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden="true" />
              {:else}
                <Bot class="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden="true" />
              {/if}
              <span class="truncate">{currentMode?.label ?? 'Assistant'} mode</span>
              {#if modes.length > 1}
                <ChevronDown
                  class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform {modeMenuOpen
                    ? 'rotate-180'
                    : ''}"
                  aria-hidden="true"
                />
              {/if}
            </button>

            {#if modeMenuOpen && modes.length > 1}
              <ul
                class="absolute left-0 top-[calc(100%+4px)] z-20 min-w-[10rem] overflow-hidden rounded-xl border border-border/60 bg-card py-1 shadow-lg"
                role="listbox"
                aria-label="Choose interaction mode"
              >
                {#each modes as m}
                  <li role="option" aria-selected={interaction === m.key}>
                    <button
                      type="button"
                      class="flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium transition-colors hover:bg-muted/60 {interaction ===
                      m.key
                        ? 'text-foreground'
                        : 'text-muted-foreground'}"
                      onclick={() => setInteraction(m.key)}
                    >
                      {#if m.key === 'inquiry'}
                        <BookOpenText class="h-3.5 w-3.5" aria-hidden="true" />
                      {:else}
                        <Bot class="h-3.5 w-3.5" aria-hidden="true" />
                      {/if}
                      {m.label} mode
                    </button>
                  </li>
                {/each}
              </ul>
            {/if}
          </div>
        {/if}

        <div class="ml-auto flex items-center gap-2">
          {#if Number(usage?.limit ?? 0) > 0}
            <div
              class="flex items-center gap-1.5 text-muted-foreground"
              title={usageTitle}
              aria-label={usageTitle}
            >
              <svg class="size-5 shrink-0 -rotate-90" viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="12" r="9" fill="none" class="stroke-muted" stroke-width="2.5" />
                <circle
                  cx="12"
                  cy="12"
                  r="9"
                  fill="none"
                  class="stroke-primary"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-dasharray={ringC}
                  stroke-dashoffset={ringOffset}
                />
              </svg>
              <span class="text-[11px] font-medium tabular-nums text-foreground"
                >{usagePct}% used</span
              >
            </div>
          {/if}

          <Button
            variant="ghost"
            size="icon-sm"
            class="min-h-[36px] min-w-[36px]"
            onclick={() => void clearChat()}
            aria-label="Clear conversation"
            disabled={busy}
          >
            <span class="text-[10px] font-semibold tracking-wide">CLR</span>
          </Button>

          <Button
            variant="ghost"
            size="icon-sm"
            class="min-h-[36px] min-w-[36px]"
            onclick={minimize}
            aria-label="Minimize Nexi"
          >
            <Minus class="h-4 w-4" />
          </Button>
        </div>
      </div>

      <div class="nexi-stage relative flex min-h-0 flex-1 flex-col overflow-hidden">
        <div
          class="nexi-scroll"
          bind:this={scrollEl}
          role="presentation"
          onclick={() => {
            if (modeMenuOpen) modeMenuOpen = false;
          }}
          onkeydown={(e) => {
            if (e.key === 'Escape' && modeMenuOpen) modeMenuOpen = false;
          }}
        >
          <!-- Older turns: full-width history above the wrap island -->
          {#if history.length}
            <div class="nexi-history">
              {#each history as msg}
                <p class="nexi-line {msg.role === 'user' ? 'is-user' : 'is-assistant'}">
                  {msg.content}
                </p>
                {#if msg.role === 'assistant' && msg.corpusNote}
                  <p class="nexi-corpus-note">{msg.corpusNote}</p>
                {/if}
                {#if msg.role === 'assistant' && msg.citations?.length}
                  <ul class="nexi-citations" aria-label="Policy citations">
                    {#each msg.citations as cite}
                      <li>
                        <span class="font-medium">{cite.source_title ?? 'Policy source'}</span>
                        {#if cite.excerpt}
                          <span class="text-muted-foreground"> — {cite.excerpt}</span>
                        {/if}
                      </li>
                    {/each}
                  </ul>
                {/if}
              {/each}
            </div>
          {/if}

          <!--
            Wrap island (screenshot-1): frame floats left; latest agent + status wrap beside it.
          -->
          <div class="nexi-wrapflow">
            <aside class="nexi-frame" aria-hidden="true" data-frame={frame}>
              <div class="nexi-frame-inner">
                <div
                  class="nexi-body-shape transition-transform duration-300 {frame === 0
                    ? 'translate-y-0'
                    : '-translate-y-1'}"
                ></div>
                <div
                  class="nexi-head transition-transform duration-300 {frame === 1
                    ? 'scale-105'
                    : 'scale-100'}"
                ></div>
                <div class="nexi-shadow"></div>
              </div>
            </aside>

            {#if spoken}
              <p class="nexi-line is-assistant nexi-spoken">{spoken.content}</p>
              {#if spoken.corpusNote}
                <p class="nexi-corpus-note">{spoken.corpusNote}</p>
              {/if}
              {#if spoken.citations?.length}
                <ul class="nexi-citations" aria-label="Policy citations">
                  {#each spoken.citations as cite}
                    <li>
                      <span class="font-medium">{cite.source_title ?? 'Policy source'}</span>
                      {#if cite.excerpt}
                        <span class="text-muted-foreground"> — {cite.excerpt}</span>
                      {/if}
                    </li>
                  {/each}
                </ul>
              {/if}
            {/if}

            {#if statusText}
              <p class="nexi-line is-status" aria-live="polite">{statusText}</p>
            {/if}
          </div>

          {#if !gate && !busy && suggestions.length}
            <div class="nexi-suggestions">
              {#each suggestions as action}
                <button type="button" class="nexi-chip" onclick={() => proposeAction(action)}>
                  {action.kind === 'apply'
                    ? action.label.replace(/^Apply:\s*/, '')
                    : action.label}
                </button>
              {/each}
            </div>
          {/if}
        </div>

        {#if gate}
          <div
            class="nexi-gate absolute inset-x-3 bottom-[4.75rem] z-10 rounded-xl border border-primary/25 bg-card p-4 shadow-lg ring-1 ring-border/40 sm:inset-x-4"
            role="region"
            aria-label="Typed action confirmation"
          >
            <div class="flex items-start gap-3">
              <span
                class="mt-0.5 inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/15 text-amber-800 dark:text-amber-200"
              >
                <ShieldAlert class="h-4 w-4" />
              </span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-foreground">
                  {gate.label.replace(/^Apply:\s*/, '')}
                </p>
                <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{gate.description}</p>
                <p class="mt-2 font-mono text-[11px] text-muted-foreground">
                  {gate.command_stub}
                  {#if gate.capability}
                    · {gate.capability}
                  {/if}
                </p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <Button size="sm" class="min-h-[36px]" onclick={confirmGate}>
                    {gate.needs_confirm ? 'Acknowledge preview' : 'Got it'}
                  </Button>
                  <Button size="sm" variant="outline" class="min-h-[36px]" onclick={dismissGate}
                    >Not now</Button
                  >
                </div>
              </div>
            </div>
          </div>
        {/if}

        <div class="flex items-center gap-2 border-t border-border/50 bg-card/90 px-3 py-3 sm:px-4">
          <label class="sr-only" for="nexi-universal-input">Message Nexi</label>
          <input
            id="nexi-universal-input"
            type="text"
            bind:value={input}
            onkeydown={onKeydown}
            maxlength="2000"
            disabled={busy}
            placeholder={interaction === 'inquiry' ? 'Ask an inquiry…' : 'Talk to Nexi…'}
            class="h-11 min-h-[44px] flex-1 rounded-xl border border-border/70 bg-background px-3.5 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-60"
          />
          <Button
            type="button"
            class="min-h-[44px] min-w-[44px] rounded-xl"
            size="icon"
            onclick={send}
            disabled={busy || !input.trim()}
            aria-label="Send message"
          >
            <Send class="h-4 w-4" />
          </Button>
        </div>
      </div>
    </div>
  {:else}
    <button
      bind:this={dockRoot}
      type="button"
      class="nexi-fab fixed bottom-4 right-4 z-50 flex min-h-[56px] items-center gap-2 rounded-full bg-primary px-4 py-3 text-primary-foreground shadow-lg transition-transform hover:scale-[1.03] sm:bottom-6 sm:right-6"
      onclick={open}
      aria-label="Open Nexi assistant"
      aria-expanded="false"
    >
      <MessageCircle class="h-5 w-5 shrink-0" />
      <span class="hidden pr-1 text-sm font-semibold sm:inline">Nexi</span>
    </button>
  {/if}
{/if}

<style>
  .nexi-dock,
  .nexi-fab {
    animation: nexi-rise 180ms ease-out;
  }

  .nexi-stage {
    background-image:
      radial-gradient(ellipse 65% 45% at 12% 100%, oklch(0.88 0.06 145 / 0.32), transparent 55%),
      linear-gradient(180deg, oklch(0.985 0.01 90), var(--background));
    min-height: min(420px, calc(100dvh - 7rem));
  }

  :global(.dark) .nexi-stage {
    background-image:
      radial-gradient(ellipse 65% 45% at 12% 100%, oklch(0.35 0.05 145 / 0.35), transparent 55%),
      linear-gradient(180deg, oklch(0.22 0.02 145), var(--background));
  }

  .nexi-scroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow-x: hidden;
    overflow-y: auto;
    padding: 0.75rem 0.85rem 0.5rem;
  }

  @media (min-width: 640px) {
    .nexi-scroll {
      padding: 1rem 1rem 0.65rem;
    }
  }

  .nexi-history {
    margin-bottom: 0.75rem;
  }

  /* Newspaper wrap island — latest agent reply always beside the frame */
  .nexi-wrapflow {
    display: flow-root;
    min-height: 11.5rem;
  }

  .nexi-frame {
    float: left;
    width: 130px;
    height: 170px;
    margin: 0 16px 12px 0;
    shape-outside: inset(0 round 18px);
    -webkit-shape-outside: inset(0 round 18px);
    position: relative;
    z-index: 1;
  }

  @media (min-width: 640px) {
    .nexi-frame {
      width: 140px;
      height: 180px;
      margin-right: 18px;
    }

    .nexi-wrapflow {
      min-height: 12.5rem;
    }
  }

  .nexi-frame-inner {
    position: absolute;
    inset: 0;
    overflow: hidden;
    border-radius: 18px;
    border: 2px solid color-mix(in oklch, var(--border) 85%, transparent);
    background:
      linear-gradient(180deg, color-mix(in oklch, var(--card) 88%, transparent), var(--card)),
      repeating-conic-gradient(#d4d4d8 0% 25%, #f4f4f5 0% 50%) 0 0 / 12px 12px;
    box-shadow: 0 8px 20px -12px rgb(0 0 0 / 0.35);
  }

  :global(.dark) .nexi-frame-inner {
    background:
      linear-gradient(180deg, color-mix(in oklch, var(--card) 80%, transparent), var(--card)),
      repeating-conic-gradient(#3f3f46 0% 25%, #27272a 0% 50%) 0 0 / 12px 12px;
  }

  .nexi-body-shape {
    position: absolute;
    inset: 0.85rem 1rem 0.35rem;
    border-radius: 40% 40% 28% 28%;
    background: color-mix(in oklch, var(--primary) 18%, transparent);
    box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--primary) 22%, transparent);
  }

  .nexi-head {
    position: absolute;
    bottom: 3.25rem;
    left: 50%;
    width: 2.6rem;
    height: 2.6rem;
    translate: -50% 0;
    border-radius: 9999px;
    background: color-mix(in oklch, var(--primary) 28%, transparent);
    box-shadow: inset 0 0 0 1px color-mix(in oklch, var(--primary) 28%, transparent);
  }

  .nexi-shadow {
    position: absolute;
    bottom: 0.35rem;
    left: 50%;
    width: 4rem;
    height: 0.5rem;
    translate: -50% 0;
    border-radius: 9999px;
    background: color-mix(in oklch, var(--foreground) 12%, transparent);
    filter: blur(2px);
  }

  .nexi-line {
    margin: 0 0 0.75rem;
    font-size: 0.9375rem;
    line-height: 1.6;
    color: var(--foreground);
  }

  .nexi-line.is-user {
    margin-left: auto;
    width: fit-content;
    max-width: min(92%, 16rem);
    border-radius: 1rem;
    background: var(--primary);
    color: var(--primary-foreground);
    padding: 0.45rem 0.75rem;
    font-size: 0.875rem;
  }

  .nexi-line.is-assistant {
    max-width: none;
  }

  .nexi-spoken {
    margin-top: 0;
  }

  .nexi-line.is-status {
    font-size: 0.875rem;
    font-style: italic;
    color: var(--muted-foreground);
  }

  .nexi-suggestions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0.5rem 0 0.25rem;
    clear: both;
  }

  .nexi-chip {
    border-radius: 9999px;
    border: 1px solid color-mix(in oklch, var(--border) 70%, transparent);
    background: color-mix(in oklch, var(--background) 90%, transparent);
    padding: 0.35rem 0.75rem;
    font-size: 0.75rem;
    color: var(--foreground);
    transition:
      border-color 120ms,
      background-color 120ms;
  }

  .nexi-chip:hover {
    border-color: color-mix(in oklch, var(--primary) 40%, transparent);
    background: color-mix(in oklch, var(--primary) 6%, transparent);
  }

  .nexi-gate {
    animation: nexi-rise 160ms ease-out;
  }

  @keyframes nexi-rise {
    from {
      opacity: 0;
      transform: translateY(8px) scale(0.98);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .nexi-dock,
    .nexi-fab,
    .nexi-gate {
      animation: none;
    }
  }

  .nexi-citations {
    margin: 0.25rem 0 0.75rem;
    padding-left: 1rem;
    font-size: 0.7rem;
    line-height: 1.35;
    color: var(--muted-foreground, #64748b);
  }

  .nexi-corpus-note {
    margin: 0.15rem 0 0.5rem;
    font-size: 0.68rem;
    line-height: 1.3;
    color: var(--muted-foreground, #64748b);
  }
</style>
