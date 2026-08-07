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

  let {
    enabled = true,
    mode = 'degraded',
    disclosure = 'Nexi is an assistant. It cannot grant access or change records without a confirmed typed command.',
    defaultInteraction = 'assistant',
    interactionModes = [
      { key: 'assistant', label: 'Assistant' },
      { key: 'inquiry', label: 'Inquiry' },
    ],
    usage = { used: 0, limit: 50, unit: 'asks', period: 'today' },
  } = $props();

  const STORAGE_KEY = 'nexiam.nexi.expanded';
  const MODE_KEY = 'nexiam.nexi.interaction';

  const page = usePage();
  const grants = $derived(page.props.auth?.user?.capabilities ?? []);
  const context = $derived(resolveNexiContext(page.url ?? '/'));
  const actions = $derived(actionsForContext(context.key, grants).filter((a) => !a.locked));

  const modes = $derived(
    Array.isArray(interactionModes) && interactionModes.length
      ? interactionModes
      : [{ key: 'assistant', label: 'Assistant' }]
  );

  let expanded = $state(false);
  let interaction = $state('assistant');
  let modeMenuOpen = $state(false);
  let input = $state('');
  let frame = $state(0);
  /** @type {{ role: 'user'|'assistant', content: string }[]} */
  let messages = $state([]);
  /** @type {any} */
  let gate = $state(null);
  let hydrated = $state(false);
  /** @type {HTMLDivElement | undefined} */
  let scrollEl = $state();
  /** Ephemeral filler in the wrap island — Thinking… / Analyzing… */
  let statusText = $state(/** @type {string | null} */ (null));
  let busy = $state(false);

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
      const saved = sessionStorage.getItem(MODE_KEY);
      const allowed = (Array.isArray(interactionModes) ? interactionModes : []).map((m) => m.key);
      if (saved && allowed.includes(saved)) {
        interaction = saved;
      } else {
        interaction = defaultInteraction || 'assistant';
      }
    } catch {
      expanded = false;
      interaction = defaultInteraction || 'assistant';
    }
    messages = [
      {
        role: 'assistant',
        content:
          'Hi — I’m Nexi. I’ll stay with you on this page. Ask me anything about what you’re looking at — chat uses the space beside my frame.',
      },
    ];
  });

  $effect(() => {
    if (!hydrated) return;
    try {
      sessionStorage.setItem(STORAGE_KEY, expanded ? '1' : '0');
      sessionStorage.setItem(MODE_KEY, interaction);
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
    modeMenuOpen = false;
    if (interaction === key) return;
    interaction = key;
    gate = null;
    speak(
      key === 'inquiry'
        ? 'Inquiry mode — policy and own-data questions. I won’t propose page Apply commands here.'
        : 'Assistant mode — page help and allowlisted Apply previews when you need them.'
    );
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

  function speak(text) {
    messages = [...messages, { role: 'assistant', content: text }];
    frame = frame === 0 ? 1 : 0;
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

  function confirmGate() {
    if (!gate || busy) return;
    const stub = gate.command_stub;
    gate = null;
    void runStatusThen(
      `Preview acknowledged → ${stub}. Later this becomes the allowlisted command after capability, confirm, idempotency, and audit.`
    );
  }

  function dismissGate() {
    if (busy) return;
    gate = null;
    speak('Okay — staying on the page path. You’re in control.');
  }

  function send() {
    const text = input.trim();
    if (!text || busy) return;
    messages = [...messages, { role: 'user', content: text }];
    input = '';
    frame = frame === 0 ? 1 : 0;

    const reply =
      mode === 'degraded'
        ? interaction === 'inquiry'
          ? `Inquiry on “${context.title}” — citations and own-data stay gated until the provider is live.`
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
</style>
