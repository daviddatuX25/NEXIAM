<script>
  import { Link } from '@inertiajs/svelte';
  import { Badge } from '@/Components/ui/badge';

  let {
    groups = [],
    sources = [],
    stats = {
      groups: 0,
      terms: 0,
      sources: 0,
      dispositions: {
        canonical: 0,
        reconcile: 0,
        'to-remove': 0,
        contextual: 0,
        'legacy-only': 0,
      },
    },
  } = $props();

  let query = $state('');
  let activeGroup = $state('all');
  let dispositionFilter = $state('all');
  let surfaceFilter = $state('all');
  let viewMode = $state('cards'); // 'cards' | 'table'
  let copiedExport = $state(false);

  const sourceMap = $derived(Object.fromEntries(sources.map((source) => [source.id, source])));

  const filteredGroups = $derived.by(() => {
    const needle = query.trim().toLowerCase();

    return groups
      .filter((group) => activeGroup === 'all' || group.key === activeGroup)
      .map((group) => ({
        ...group,
        terms: group.terms.filter((term) => {
          const matchesDisposition = dispositionFilter === 'all' || term.disposition === dispositionFilter;

          const matchesSurface =
            surfaceFilter === 'all' ||
            (term.surfaces ?? []).some((s) => {
              if (surfaceFilter === 'ui') return (s.surface ?? '').toLowerCase().includes('ui') || (s.surface ?? '').toLowerCase().includes('svelte');
              if (surfaceFilter === 'docs') return (s.surface ?? '').toLowerCase().includes('doc') || (s.surface ?? '').toLowerCase().includes('contract') || (s.surface ?? '').toLowerCase().includes('spec');
              if (surfaceFilter === 'schema') return (s.surface ?? '').toLowerCase().includes('database') || (s.surface ?? '').toLowerCase().includes('schema') || (s.surface ?? '').toLowerCase().includes('migration') || (s.surface ?? '').toLowerCase().includes('model');
              if (surfaceFilter === 'diagram') return (s.surface ?? '').toLowerCase().includes('dfd') || (s.surface ?? '').toLowerCase().includes('erd') || (s.surface ?? '').toLowerCase().includes('use case');
              if (surfaceFilter === 'evidence') return (s.surface ?? '').toLowerCase().includes('ground truth') || (s.surface ?? '').toLowerCase().includes('matrix') || (s.surface ?? '').toLowerCase().includes('survey');
              return true;
            });

          const searchable = [
            term.term,
            term.definition,
            term.disposition,
            term.category ?? '',
            term.target_term ?? '',
            term.reconciliation_action ?? '',
            ...(term.aliases ?? []),
            ...(term.surfaces ?? []).flatMap((s) => [s.surface, s.wording, s.role]),
          ]
            .join(' ')
            .toLowerCase();

          return matchesDisposition && matchesSurface && (!needle || searchable.includes(needle));
        }),
      }))
      .filter((group) => group.terms.length > 0);
  });

  const visibleTerms = $derived(filteredGroups.flatMap((group) => group.terms));
  const visibleTermCount = $derived(visibleTerms.length);

  function dispositionLabel(disp) {
    return {
      canonical: 'Preferred Standard',
      reconcile: 'Needs Reconciliation',
      'to-remove': 'Slated for Removal',
      contextual: 'Context-Specific',
      'legacy-only': 'Legacy Only',
    }[disp] ?? disp;
  }

  function dispositionBadgeClass(disp) {
    return {
      canonical: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300',
      reconcile: 'border-amber-500/40 bg-amber-500/10 text-amber-300',
      'to-remove': 'border-rose-500/40 bg-rose-500/10 text-rose-300',
      contextual: 'border-sky-500/40 bg-sky-500/10 text-sky-300',
      'legacy-only': 'border-slate-600 bg-slate-800 text-slate-400',
    }[disp] ?? 'border-slate-700 bg-slate-800 text-slate-300';
  }

  function roleBadgeClass(role) {
    return {
      target: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
      schema: 'border-blue-500/30 bg-blue-500/10 text-blue-300',
      observed_drift: 'border-amber-500/30 bg-amber-500/10 text-amber-300',
      evidence: 'border-purple-500/30 bg-purple-500/10 text-purple-300',
      legacy: 'border-rose-500/30 bg-rose-500/10 text-rose-400',
    }[role] ?? 'border-slate-700 bg-slate-800 text-slate-400';
  }

  function sourceFor(id) {
    return sourceMap[id] ?? { label: id, kind: 'Unclassified source', ref: id };
  }

  function resetFilters() {
    query = '';
    activeGroup = 'all';
    dispositionFilter = 'all';
    surfaceFilter = 'all';
  }

  async function copyMarkdownSummary() {
    const lines = [
      `# NEXIAM Vocabulary & Reconciliation Matrix (${visibleTermCount} terms)`,
      '',
      '| Term | Category | Disposition | Target Term | Key Surfaces | Reconciliation Directive |',
      '|---|---|---|---|---|---|',
    ];

    for (const term of visibleTerms) {
      const surfacesSummary = (term.surfaces ?? [])
        .map((s) => `${s.surface}: "${s.wording}"`)
        .join('; ');
      lines.push(
        `| **${term.term}** | ${term.category ?? ''} | \`${term.disposition}\` | ${term.target_term ?? '-'} | ${surfacesSummary} | ${term.reconciliation_action ?? term.definition} |`
      );
    }

    try {
      await navigator.clipboard.writeText(lines.join('\n'));
      copiedExport = true;
      setTimeout(() => (copiedExport = false), 2500);
    } catch (e) {
      console.error('Failed to copy to clipboard', e);
    }
  }
</script>

<svelte:head>
  <title>Vocabulary &amp; Reconciliation Registry · NEXIAM Capstone</title>
  <meta
    name="description"
    content="Complete source-backed vocabulary registry, reconciliation matrix, and UI terminology triage for NEXIAM."
  />
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <main class="mx-auto max-w-7xl px-5 py-10 sm:px-8 lg:px-10">
    <!-- Header -->
    <header class="flex flex-col gap-6 border-b border-slate-800 pb-8 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold tracking-[0.18em] uppercase">
          <Link href="/capstone/status" class="text-slate-400 transition hover:text-white">Feature Roadmap</Link>
          <span class="text-slate-600" aria-hidden="true">/</span>
          <span class="text-blue-300">Master Vocabulary Registry</span>
        </div>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-white sm:text-5xl">Vocabulary &amp; Term Crosswalk</h1>
        <p class="mt-4 max-w-4xl text-base leading-7 text-slate-300">
          The single source of truth for all terminology across NEXIAM. Classifies every term as
          <span class="text-emerald-300 font-medium">Preferred Standard</span>,
          <span class="text-amber-300 font-medium">Needs Reconciliation</span>, or
          <span class="text-rose-300 font-medium">Slated for Removal</span> so the team can systematically audit and harmonize
          UI copy, capstone documentation, DFDs, and schema.
        </p>
      </div>

      <nav class="flex flex-wrap gap-2" aria-label="Capstone workspace">
        <Link
          href="/capstone/demo-guide"
          class="rounded-lg border border-slate-700 bg-slate-900 px-3.5 py-2 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white"
        >
          Demo guide
        </Link>
        <Link
          href="/capstone/glossary"
          class="rounded-lg border border-blue-400/40 bg-blue-400/10 px-3.5 py-2 text-xs font-semibold text-blue-200"
          aria-current="page"
        >
          Glossary &amp; Registry
        </Link>
      </nav>
    </header>

    <!-- Triage KPI Summary Cards -->
    <section class="mt-8 grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-6" aria-label="Vocabulary triage overview">
      <button
        type="button"
        class={`text-left rounded-2xl border p-4 transition ${
          dispositionFilter === 'all'
            ? 'border-blue-400 bg-blue-500/10 ring-2 ring-blue-500/20'
            : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'
        }`}
        onclick={() => (dispositionFilter = 'all')}
      >
        <p class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">Total Terms</p>
        <p class="mt-1 text-2xl font-bold text-white">{stats.terms}</p>
        <p class="mt-0.5 text-xs text-slate-400">All registered concepts</p>
      </button>

      <button
        type="button"
        class={`text-left rounded-2xl border p-4 transition ${
          dispositionFilter === 'canonical'
            ? 'border-emerald-400 bg-emerald-500/10 ring-2 ring-emerald-500/20'
            : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'
        }`}
        onclick={() => (dispositionFilter = 'canonical')}
      >
        <p class="text-[10px] font-semibold tracking-[0.16em] text-emerald-400 uppercase">Preferred Standard</p>
        <p class="mt-1 text-2xl font-bold text-emerald-300">{stats.dispositions?.canonical ?? 0}</p>
        <p class="mt-0.5 text-xs text-slate-400">Target terminology to keep</p>
      </button>

      <button
        type="button"
        class={`text-left rounded-2xl border p-4 transition ${
          dispositionFilter === 'reconcile'
            ? 'border-amber-400 bg-amber-500/10 ring-2 ring-amber-500/20'
            : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'
        }`}
        onclick={() => (dispositionFilter = 'reconcile')}
      >
        <p class="text-[10px] font-semibold tracking-[0.16em] text-amber-400 uppercase">Reconcile Across UI/Docs</p>
        <p class="mt-1 text-2xl font-bold text-amber-300">{stats.dispositions?.reconcile ?? 0}</p>
        <p class="mt-0.5 text-xs text-slate-400">Inconsistent usage to align</p>
      </button>

      <button
        type="button"
        class={`text-left rounded-2xl border p-4 transition ${
          dispositionFilter === 'to-remove'
            ? 'border-rose-400 bg-rose-500/10 ring-2 ring-rose-500/20'
            : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'
        }`}
        onclick={() => (dispositionFilter = 'to-remove')}
      >
        <p class="text-[10px] font-semibold tracking-[0.16em] text-rose-400 uppercase">Slated for Removal</p>
        <p class="mt-1 text-2xl font-bold text-rose-300">{stats.dispositions?.['to-remove'] ?? 0}</p>
        <p class="mt-0.5 text-xs text-slate-400">Jargon to drop from UI</p>
      </button>

      <button
        type="button"
        class={`text-left rounded-2xl border p-4 transition ${
          dispositionFilter === 'contextual'
            ? 'border-sky-400 bg-sky-500/10 ring-2 ring-sky-500/20'
            : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'
        }`}
        onclick={() => (dispositionFilter = 'contextual')}
      >
        <p class="text-[10px] font-semibold tracking-[0.16em] text-sky-400 uppercase">Context-Specific</p>
        <p class="mt-1 text-2xl font-bold text-sky-300">{stats.dispositions?.contextual ?? 0}</p>
        <p class="mt-0.5 text-xs text-slate-400">Valid in bounded phase only</p>
      </button>

      <button
        type="button"
        class={`text-left rounded-2xl border p-4 transition ${
          dispositionFilter === 'legacy-only'
            ? 'border-slate-400 bg-slate-500/10 ring-2 ring-slate-500/20'
            : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'
        }`}
        onclick={() => (dispositionFilter = 'legacy-only')}
      >
        <p class="text-[10px] font-semibold tracking-[0.16em] text-slate-400 uppercase">Legacy Only</p>
        <p class="mt-1 text-2xl font-bold text-slate-300">{stats.dispositions?.['legacy-only'] ?? 0}</p>
        <p class="mt-0.5 text-xs text-slate-400">Forbidden in greenfield</p>
      </button>
    </section>

    <!-- Filter & Triage Bar -->
    <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-2xl shadow-slate-950/20 sm:p-6" aria-labelledby="filters-title">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
        <div class="min-w-0 flex-1">
          <label for="glossary-search" id="filters-title" class="text-xs font-semibold tracking-[0.18em] text-slate-400 uppercase">
            Search terms, surfaces &amp; actions
          </label>
          <input
            id="glossary-search"
            type="search"
            bind:value={query}
            placeholder="Search: intake, dossier, canonical, NOA, examinee, quota, slot, OMR..."
            class="mt-2 min-h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-4 text-sm text-white outline-none placeholder:text-slate-600 focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20"
          />
        </div>

        <div class="w-full lg:w-56">
          <label for="surface-filter" class="text-xs font-semibold tracking-[0.18em] text-slate-400 uppercase">Surface Filter</label>
          <select
            id="surface-filter"
            bind:value={surfaceFilter}
            class="mt-2 min-h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-white outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20"
          >
            <option value="all">All Surfaces</option>
            <option value="ui">System UI (Svelte / Views)</option>
            <option value="docs">Documentation &amp; Contracts</option>
            <option value="schema">Database Schema &amp; Migrations</option>
            <option value="diagram">Diagrams (DFDs &amp; ERD)</option>
            <option value="evidence">Institutional Ground Truth</option>
          </select>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div class="inline-flex rounded-xl border border-slate-700 bg-slate-950 p-1">
            <button
              type="button"
              class={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                viewMode === 'cards' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'
              }`}
              onclick={() => (viewMode = 'cards')}
              aria-pressed={viewMode === 'cards'}
            >
              Card View
            </button>
            <button
              type="button"
              class={`rounded-lg px-3 py-1.5 text-xs font-semibold transition ${
                viewMode === 'table' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-white'
              }`}
              onclick={() => (viewMode = 'table')}
              aria-pressed={viewMode === 'table'}
            >
              Triage Matrix
            </button>
          </div>

          <button
            type="button"
            class="min-h-11 rounded-xl border border-slate-700 bg-slate-950 px-3.5 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white"
            onclick={copyMarkdownSummary}
          >
            {copiedExport ? '✓ Copied Markdown' : 'Copy Matrix'}
          </button>

          <button
            type="button"
            class="min-h-11 rounded-xl border border-slate-700 px-3.5 text-xs font-semibold text-slate-300 transition hover:border-slate-500 hover:text-white"
            onclick={resetFilters}
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Domain filter tabs -->
      <div class="mt-5 flex gap-2 overflow-x-auto pb-1" aria-label="Vocabulary Domains">
        <button
          type="button"
          class={`shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition ${
            activeGroup === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 hover:text-slate-200'
          }`}
          onclick={() => (activeGroup = 'all')}
          aria-pressed={activeGroup === 'all'}
        >
          All Domains ({stats.terms})
        </button>
        {#each groups as group (group.key)}
          <button
            type="button"
            class={`shrink-0 rounded-lg px-3 py-2 text-xs font-semibold transition ${
              activeGroup === group.key ? 'bg-blue-600 text-white' : 'bg-slate-950 text-slate-400 hover:bg-slate-800 hover:text-slate-200'
            }`}
            onclick={() => (activeGroup = group.key)}
            aria-pressed={activeGroup === group.key}
          >
            {group.title} ({group.terms.length})
          </button>
        {/each}
      </div>

      <p class="mt-4 text-sm text-slate-400" aria-live="polite">
        Showing <span class="font-semibold text-slate-200">{visibleTermCount}</span> of {stats.terms} terms
        {#if dispositionFilter !== 'all'} matching <span class="font-semibold text-slate-200">{dispositionLabel(dispositionFilter)}</span>{/if}
        {#if surfaceFilter !== 'all'} on <span class="font-semibold text-slate-200">{surfaceFilter}</span>{/if}
        {#if query.trim()} for “{query.trim()}”{/if}.
      </p>
    </section>

    {#if filteredGroups.length === 0}
      <section class="mt-8 rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-6 py-16 text-center" aria-live="polite">
        <h2 class="text-lg font-semibold text-white">No matching terms found</h2>
        <p class="mt-2 text-sm text-slate-400">Try broadening your search term or clearing the disposition/surface filters.</p>
        <button
          type="button"
          class="mt-4 rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700"
          onclick={resetFilters}
        >
          Clear all filters
        </button>
      </section>
    {:else if viewMode === 'table'}
      <!-- DENSE TRIAGE MATRIX VIEW -->
      <div class="mt-8 rounded-xl border border-border/80 bg-card/60 backdrop-blur-md overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
              <tr>
                <th scope="col" class="py-3.5 pl-4 pr-3 sm:pl-6">Term &amp; Key</th>
                <th scope="col" class="px-3 py-3.5">Category</th>
                <th scope="col" class="px-3 py-3.5">Disposition</th>
                <th scope="col" class="px-3 py-3.5">Target Term</th>
                <th scope="col" class="px-3 py-3.5">Observed Surfaces</th>
                <th scope="col" class="py-3.5 pl-3 pr-4 sm:pr-6">Reconciliation Directive</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/60 bg-transparent">
              {#each visibleTerms as term (term.key)}
                <tr class="transition hover:bg-slate-800/40">
                  <td class="py-3.5 pl-4 pr-3 sm:pl-6">
                    <p class="font-semibold text-white">{term.term}</p>
                    <code class="mt-0.5 block font-mono text-[10px] text-slate-500">{term.key}</code>
                    {#if term.aliases?.length}
                      <p class="mt-1 text-[11px] text-slate-400 italic">
                        Aliases: {term.aliases.join(', ')}
                      </p>
                    {/if}
                  </td>
                  <td class="px-3 py-3.5 whitespace-nowrap text-slate-300">
                    {term.category ?? '-'}
                  </td>
                  <td class="px-3 py-3.5 whitespace-nowrap">
                    <span class={`inline-block rounded-full border px-2.5 py-0.5 text-[10px] font-semibold ${dispositionBadgeClass(term.disposition)}`}>
                      {dispositionLabel(term.disposition)}
                    </span>
                  </td>
                  <td class="px-3 py-3.5 text-slate-200">
                    {#if term.target_term}
                      <span class="font-mono text-emerald-300 font-semibold">{term.target_term}</span>
                    {:else}
                      <span class="text-slate-600">—</span>
                    {/if}
                  </td>
                  <td class="px-3 py-3.5 max-w-xs text-slate-300">
                    <ul class="space-y-1">
                      {#each (term.surfaces ?? []).slice(0, 3) as s}
                        <li class="truncate text-[11px]">
                          <span class={`rounded px-1.5 py-0.2 text-[9px] font-mono ${roleBadgeClass(s.role)}`}>{s.role}</span>
                          <span class="font-medium text-slate-300">{s.surface}:</span>
                          <code class="text-slate-400">"{s.wording}"</code>
                        </li>
                      {/each}
                      {#if (term.surfaces?.length ?? 0) > 3}
                        <li class="text-[10px] text-slate-500 italic">+{(term.surfaces?.length ?? 0) - 3} more surfaces</li>
                      {/if}
                    </ul>
                  </td>
                  <td class="py-3.5 pl-3 pr-4 sm:pr-6 max-w-sm text-slate-300 leading-5">
                    <p class="text-amber-200/90 font-medium text-[11px]">{term.reconciliation_action}</p>
                    <p class="mt-1 text-slate-400 text-[11px]">{term.definition}</p>
                  </td>
                </tr>
              {/each}
            </tbody>
          </table>
        </div>
      </div>
    {:else}
      <!-- CARD VIEW -->
      <div class="mt-8 space-y-10">
        {#each filteredGroups as group (group.key)}
          <section aria-labelledby={`${group.key}-heading`}>
            <div class="mb-4 flex flex-col gap-2 border-b border-slate-800 pb-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <h2 id={`${group.key}-heading`} class="text-2xl font-semibold tracking-tight text-white">{group.title}</h2>
                <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-400">{group.summary}</p>
              </div>
              <Badge class="w-fit border-slate-700 bg-slate-900 text-slate-300">{group.terms.length} terms</Badge>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
              {#each group.terms as term (term.key)}
                <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xl shadow-slate-950/10 sm:p-6 flex flex-col justify-between">
                  <div>
                    <!-- Header -->
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                          <h3 class="text-lg font-semibold text-white">{term.term}</h3>
                          {#if term.category}
                            <span class="rounded bg-slate-800 px-2 py-0.5 text-[10px] font-mono text-slate-400">{term.category}</span>
                          {/if}
                        </div>
                        <div class="mt-2.5 flex flex-wrap items-center gap-2">
                          <span class={`rounded-full border px-2.5 py-1 text-[11px] font-semibold ${dispositionBadgeClass(term.disposition)}`}>
                            {dispositionLabel(term.disposition)}
                          </span>
                          {#if term.target_term}
                            <span class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] text-emerald-300">
                              <span>Target:</span>
                              <strong class="font-semibold">{term.target_term}</strong>
                            </span>
                          {/if}
                          {#each term.aliases ?? [] as alias (alias)}
                            <span class="rounded-full border border-slate-700 bg-slate-950 px-2 py-0.5 text-[10px] text-slate-400">{alias}</span>
                          {/each}
                        </div>
                      </div>
                      <span class="font-mono text-[10px] text-slate-600">{term.key}</span>
                    </div>

                    <!-- Definition -->
                    <p class="mt-4 text-sm leading-6 text-slate-300">{term.definition}</p>

                    <!-- Reconciliation Directive -->
                    {#if term.reconciliation_action}
                      <div class="mt-4 rounded-xl border border-amber-500/20 bg-amber-500/5 p-3.5 text-xs leading-5">
                        <p class="font-semibold tracking-wide uppercase text-amber-300">Reconciliation Directive</p>
                        <p class="mt-1 text-amber-200/90">{term.reconciliation_action}</p>
                      </div>
                    {/if}

                    <!-- Surfaces where found -->
                    {#if term.surfaces?.length}
                      <div class="mt-5 border-t border-slate-800/80 pt-4">
                        <h4 class="text-xs font-semibold tracking-[0.16em] text-slate-400 uppercase">
                          Observed Surfaces &amp; Current Wording ({term.surfaces.length})
                        </h4>
                        <div class="mt-3 space-y-2">
                          {#each term.surfaces as s}
                            <div class="rounded-lg border border-slate-800/80 bg-slate-950/60 p-2.5 text-xs">
                              <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-medium text-slate-200">{s.surface}</span>
                                <span class={`rounded px-1.5 py-0.5 text-[9px] font-mono uppercase ${roleBadgeClass(s.role)}`}>
                                  {s.role}
                                </span>
                              </div>
                              <div class="mt-1 flex items-baseline gap-1.5">
                                <span class="text-slate-500 text-[11px]">Wording:</span>
                                <code class="text-blue-300 font-mono text-[11px]">{s.wording}</code>
                              </div>
                            </div>
                          {/each}
                        </div>
                      </div>
                    {/if}
                  </div>

                  <!-- Source Evidence details -->
                  {#if term.sources?.length}
                    <details class="mt-5 rounded-lg border border-slate-800 bg-slate-950/40 px-3 py-2">
                      <summary class="cursor-pointer text-xs font-semibold text-slate-400 hover:text-slate-200">
                        View source evidence references ({term.sources.length})
                      </summary>
                      <ul class="mt-3 space-y-2 border-t border-slate-800 pt-3">
                        {#each term.sources as sourceId (sourceId)}
                          {@const source = sourceFor(sourceId)}
                          <li class="text-xs">
                            <span class="font-semibold text-slate-300">{source.label}</span>
                            <span class="ml-1 text-[10px] text-slate-500 font-mono">({source.kind})</span>
                            <span class="mt-0.5 block break-words font-mono text-[10px] leading-4 text-slate-500">{source.ref}</span>
                          </li>
                        {/each}
                      </ul>
                    </details>
                  {/if}
                </article>
              {/each}
            </div>
          </section>
        {/each}
      </div>
    {/if}

    <footer class="mt-12 border-t border-slate-800 pt-6 text-xs leading-5 text-slate-500 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <p>
        <span class="text-emerald-300 font-medium">Preferred Standard</span> marks approved greenfield terms.
        <span class="text-amber-300 font-medium">Needs Reconciliation</span> marks cross-surface inconsistencies.
        <span class="text-rose-300 font-medium">Slated for Removal</span> marks jargon scheduled for elimination.
      </p>
      <p class="font-mono text-slate-600">
        NEXIAM Capstone Master Terminology Registry &middot; 131 Terms &middot; 57 Sources
      </p>
    </footer>
  </main>
</div>
