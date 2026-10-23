<script>
  import { router } from '@inertiajs/svelte';
  import { Badge } from '@/Components/ui/badge';

  let {
    foundation = {},
    roadmap = [],
  } = $props();

  let refreshing = $state(false);

  function refresh() {
    refreshing = true;
    router.reload({
      only: ['foundation', 'roadmap'],
      onFinish: () => (refreshing = false),
    });
  }

  let mermaidPromise = null;
  const renderedSequences = new Set();

  function sequenceId(name) {
    return `mmd-${name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')}`;
  }

  async function ensureMermaid() {
    if (!mermaidPromise) {
      mermaidPromise = import('mermaid').then(({ default: mermaid }) => {
        mermaid.initialize({
          startOnLoad: false,
          theme: 'dark',
          fontFamily: 'IBM Plex Sans, ui-sans-serif, system-ui, sans-serif',
          sequence: { useMaxWidth: true },
        });
        return mermaid;
      });
    }
    return mermaidPromise;
  }

  async function renderSequence(detailsEl, name, code) {
    const slot = detailsEl.querySelector('.mermaid-slot');
    if (!slot || slot.dataset.state === 'done' || slot.dataset.state === 'rendering') return;
    slot.dataset.state = 'rendering';
    try {
      const mermaid = await ensureMermaid();
      const { svg } = await mermaid.render(sequenceId(name), code);
      slot.innerHTML = svg;
      slot.dataset.state = 'done';
      renderedSequences.add(name);
    } catch (error) {
      console.error(`Mermaid render failed for ${name}`, error);
      slot.dataset.state = 'error';
      slot.innerHTML = '<p class="text-xs text-amber-300">Diagram could not be rendered; showing the source below.</p>';
      const raw = slot.parentElement.querySelector('.mermaid-raw');
      if (raw) raw.classList.remove('hidden');
    }
  }

  function handleToggle(event, item) {
    if (event.currentTarget.open && item.sequence) {
      renderSequence(event.currentTarget, item.name, item.sequence);
    }
  }

  $effect(() => {
    roadmap;
    queueMicrotask(() => {
      document.querySelectorAll('details[open] .mermaid-slot').forEach((slot) => {
        const detailsEl = slot.closest('details');
        if (!detailsEl) return;
        const { name, sequence } = slot.dataset;
        if (sequence) renderSequence(detailsEl, name, sequence);
      });
    });
  });

  function statusLabel(status) {
    return {
      complete: 'Complete',
      in_progress: 'In progress',
      next: 'Next',
      planned: 'Planned',
      verify: 'To verify',
    }[status] ?? status;
  }

  function statusClasses(status) {
    return {
      complete: 'border-emerald-400/30 bg-emerald-400/10 text-emerald-300',
      in_progress: 'border-amber-400/30 bg-amber-400/10 text-amber-300',
      next: 'border-blue-400/30 bg-blue-400/10 text-blue-300',
      planned: 'border-slate-700 bg-slate-800/70 text-slate-300',
      verify: 'border-slate-700 bg-slate-950/60 text-slate-400',
    }[status] ?? 'border-slate-700 bg-slate-800/70 text-slate-300';
  }

  function statusMark(status) {
    return status === 'complete' ? '✓' : status === 'next' ? '→' : '';
  }

  function checkMark(status) {
    return status === 'complete' ? '✓' : '□';
  }

  function completedCount(group) {
    return group.items.filter((item) => item.status === 'complete').length;
  }
</script>

<svelte:head>
  <title>Feature roadmap · NEXIAM</title>
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <main class="mx-auto max-w-6xl px-5 py-10 sm:px-8 lg:px-10">
    <header class="flex flex-col gap-5 border-b border-slate-800 pb-8 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-semibold tracking-[0.24em] text-blue-300 uppercase">NEXIAM system</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-5xl">Feature roadmap</h1>
        <p class="mt-4 max-w-3xl text-base leading-7 text-slate-300">
          A project-management checklist: what is being re-adopted from legacy, what is new flagship work, and what is next for the capstone.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <Badge class="border-blue-400/30 bg-blue-400/10 px-3 py-1.5 text-blue-300">{foundation.phase}</Badge>
        <a
          href="/capstone/prototype"
          class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sky-500/40 bg-sky-500/10 px-3.5 py-2 text-sm font-semibold text-sky-300 transition hover:border-sky-400 hover:bg-sky-500/20"
        >
          <span>Interactive Prototype</span>
          <span>→</span>
        </a>
        <a
          href="/capstone/office-guide"
          class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3.5 py-2 text-sm font-semibold text-emerald-300 transition hover:border-emerald-400 hover:bg-emerald-500/20"
        >
          <span>Office Field Guide</span>
          <span>→</span>
        </a>
        <a
          href="/capstone/demo-guide"
          class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-blue-500/40 bg-blue-500/10 px-3.5 py-2 text-sm font-semibold text-blue-300 transition hover:border-blue-400 hover:bg-blue-500/20"
        >
          <span>Master Demo Guide</span>
          <span>→</span>
        </a>
        <button
          type="button"
          class="min-h-10 rounded-lg border border-slate-700 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800 disabled:cursor-wait disabled:opacity-60"
          disabled={refreshing}
          onclick={refresh}
        >
          {refreshing ? 'Refreshing…' : 'Refresh'}
        </button>
      </div>
    </header>

    <section class="mt-8 grid gap-4 md:grid-cols-3" aria-label="Roadmap summary">
      <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase">System boundary</p>
        <p class="mt-2 font-medium text-white">{foundation.runtime}</p>
        <p class="mt-1 text-sm text-slate-400">{foundation.database} · greenfield code in system/</p>
      </div>
      <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase">Checklist groups</p>
        <p class="mt-2 font-medium text-white">{roadmap.length} macro areas</p>
        <p class="mt-1 text-sm text-slate-400">Legacy parity, flagship capabilities, and capstone gates.</p>
      </div>
      <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-5">
        <p class="text-xs font-semibold tracking-[0.18em] text-blue-300 uppercase">Working rule</p>
        <p class="mt-2 font-medium text-white">Macro first, details on click</p>
        <p class="mt-1 text-sm text-slate-400">Expand a feature to see checklists, subfeatures, interfaces, and behavior.</p>
      </div>
    </section>

    <section class="mt-8 space-y-5" aria-labelledby="roadmap-checklist">
      <div>
        <p class="text-xs font-semibold tracking-[0.2em] text-blue-300 uppercase">Project management</p>
        <h2 id="roadmap-checklist" class="mt-2 text-2xl font-semibold text-white">Feature checklist</h2>
      </div>

      {#each roadmap as group (group.key)}
        <section class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-2xl shadow-slate-950/20 sm:p-7" aria-labelledby={`${group.key}-title`}>
          <div class="flex flex-col gap-2 border-b border-slate-800 pb-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h3 id={`${group.key}-title`} class="text-xl font-semibold text-white">{group.title}</h3>
              <p class="mt-1 text-sm text-slate-400">{group.description}</p>
            </div>
            <p class="text-sm text-slate-400">{completedCount(group)} of {group.items.length} complete</p>
          </div>

          <ol class="mt-4 space-y-3">
            {#each group.items as item, index (item.name)}
              <li>
                <details class="group/feature rounded-xl border border-slate-800 bg-slate-950/40 open:border-slate-600" ontoggle={(event) => handleToggle(event, item)}>
                  <summary class="flex cursor-pointer list-none items-start gap-3 p-4 [&::-webkit-details-marker]:hidden">
                    <span
                      class={`mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-md border text-sm font-bold ${statusClasses(item.status)}`}
                      role="checkbox"
                      aria-checked={item.status === 'complete'}
                      aria-label={`${item.name}: ${statusLabel(item.status)}`}
                    >
                      {statusMark(item.status) || index + 1}
                    </span>
                    <span class="min-w-0 flex-1">
                      <span class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-white">{item.name}</span>
                        <Badge class={statusClasses(item.status)}>{statusLabel(item.status)}</Badge>
                      </span>
                      <span class="mt-1 block text-sm leading-6 text-slate-300">{item.detail}</span>
                    </span>
                    <span class="mt-1 text-slate-500 transition group-open/feature:rotate-180" aria-hidden="true">⌄</span>
                  </summary>

                  <div class="border-t border-slate-800 px-4 pb-5 pt-4 sm:pl-[4.25rem]">
                    {#if item.interface_checks?.length}
                      <p class="text-xs font-semibold tracking-[0.16em] text-blue-300 uppercase">Interface checklist</p>
                      <ul class="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
                        {#each item.interface_checks as check (check.label)}
                          <li class="flex items-center gap-2 rounded-lg border border-slate-800 px-3 py-2">
                            <span class="font-mono text-base text-slate-400" aria-hidden="true">{checkMark(check.status)}</span>
                            <span class="flex-1">{check.label}</span>
                            <span class="text-xs text-slate-500">{statusLabel(check.status)}</span>
                          </li>
                        {/each}
                      </ul>
                    {/if}

                    {#if item.subfeature_checks?.length}
                      <p class="mt-5 text-xs font-semibold tracking-[0.16em] text-blue-300 uppercase">Subfeature checklist</p>
                      <ul class="mt-3 grid gap-2 text-sm text-slate-300 sm:grid-cols-2">
                        {#each item.subfeature_checks as check (check.label)}
                          <li class="flex items-center gap-2 rounded-lg border border-slate-800 px-3 py-2">
                            <span class="font-mono text-base text-slate-400" aria-hidden="true">{checkMark(check.status)}</span>
                            <span class="flex-1">{check.label}</span>
                            <span class="text-xs text-slate-500">{statusLabel(check.status)}</span>
                          </li>
                        {/each}
                      </ul>
                    {/if}

                    {#if item.sequence}
                      <p class="mt-5 text-xs font-semibold tracking-[0.16em] text-blue-300 uppercase">Behavior sequence</p>
                      <div
                        class="mermaid-slot mt-3 overflow-x-auto rounded-lg border border-slate-800 bg-slate-950/60 p-4"
                        data-name={item.name}
                        data-sequence={item.sequence}
                      ></div>
                      <pre class="mermaid-raw mt-3 hidden overflow-x-auto rounded-lg border border-slate-800 bg-slate-950 p-4 text-xs leading-5 text-slate-300"><code>{item.sequence}</code></pre>
                    {/if}
                  </div>
                </details>
              </li>
            {/each}
          </ol>
        </section>
      {/each}
    </section>

    <footer class="mt-8 flex flex-col gap-3 border-t border-slate-800 pt-5 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between">
      <p>{foundation.legacyReference}</p>
      <div class="flex flex-wrap gap-2">
        <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone-interview-guide.html">Interview guide</a>
        <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone/client-brief">Client brief</a>
        <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone-manuscript-brief.html">Manuscript brief</a>
        <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/capstone-wireframe-review.html">Wireframe review</a>
        <a class="rounded-lg border border-slate-700 px-3 py-2 text-slate-200 hover:bg-slate-800" href="/ui-kit">UI kit</a>
      </div>
    </footer>
  </main>
</div>
