<script>
  import { Check, Clipboard, Search, X, Plus, Clock3, AlertTriangle } from 'lucide-svelte';

  let {
    open = false,
    placeholderGroups = {},
    usedKeys = [],
    recentKeys = [],
    mode = 'copy',
    onclose = () => {},
    oncopy = () => {},
    oninsert = () => {},
    embedded = false,
    missingKeys = [],
  } = $props();

  let query = $state('');
  let category = $state('all');
  let searchInput = $state();

  const fieldDetails = {
    last_school_enrolled: { label: 'Last school — full name', description: 'Original applicant-entered school name.', example: 'Ilocos Sur College' },
    last_school_short: { label: 'Last school — abbreviation', description: 'Known abbreviation, otherwise generated initials.', example: 'Ilocos Sur College → ISC' },
    school_short: { label: 'Last school — abbreviation', description: 'Alias for the same abbreviation value.', example: 'Ilocos Sur College → ISC' },
    strand: { label: 'Strand / previous course — full text', description: 'Existing raw academic-history value.', example: 'Humanities and Social Sciences' },
    strand_or_prev_course: { label: 'Strand / previous course — full text', description: 'Original saved academic-history text.', example: 'Humanities and Social Sciences' },
    strand_or_prev_course_short: { label: 'Strand / previous course — abbreviation', description: 'Known abbreviation, otherwise generated initials.', example: 'Humanities and Social Sciences → HUMSS' },
    history_short: { label: 'Strand / previous course — abbreviation', description: 'Alias for the same abbreviation value.', example: 'Humanities and Social Sciences → HUMSS' },
    course_applied: { label: 'Course applied — full name', description: 'Selected institution program name.' },
    course_applied_name: { label: 'Course applied — full name', description: 'Selected institution program name.' },
    course_applied_code: { label: 'Course applied — program code', description: 'Selected institution program code.', aliases: ['course_code', 'cc', 'prog_code'] },
    course_code: { label: 'Course applied — program code', description: 'Alias for the selected institution program code.' },
    cc: { label: 'Course applied — program code', description: 'Alias for the selected institution program code.' },
    prog_code: { label: 'Course applied — program code', description: 'Alias for the selected institution program code.' },
  };

  const groupKeys = $derived(Object.keys(placeholderGroups).filter((key) => key !== 'dual'));

  const categories = $derived([
    { key: 'all', label: 'All fields' },
    ...groupKeys.map((key) => ({ key, label: key === 'aliases' ? 'Legacy aliases' : labelFor(key) })),
  ]);

  const allItems = $derived(groupKeys.flatMap((key) => placeholderGroups[key] ?? []));
  const missingItems = $derived(missingKeys.map((key) => allItems.find((item) => keyFor(item) === key) ?? { placeholder: key, description: 'Required field detected as missing' }));

  const items = $derived.by(() => {
    const allKeys = groupKeys.filter((key) => key !== 'aliases');
    const source = category === 'all'
      ? allKeys.flatMap((key) => placeholderGroups[key] ?? [])
      : (placeholderGroups[category] ?? []);
    const needle = query.trim().toLowerCase();
    const filtered = source.filter((item) => {
      const details = detailsFor(item);
      const aliasText = Array.isArray(details.aliases) ? details.aliases.join(' ') : '';
      const example = details.example ?? '';
      return `${item.placeholder} ${details.description ?? ''} ${example} ${aliasText} ${labelFor(item)}`.toLowerCase().includes(needle);
    });
    return [...new Map(filtered.map((item) => [keyFor(item), item])).values()];
  });

  $effect(() => {
    if (open) {
      setTimeout(() => searchInput?.focus(), 0);
    }
  });

  function labelFor(value) {
    const key = value && typeof value === 'object' ? keyFor(value) : String(value ?? '').replace(/^\{\{|\}\}$/g, '');
    return (value && typeof value === 'object' ? value.label : null)
      || fieldDetails[key]?.label
      || key
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function detailsFor(item) {
    return { ...fieldDetails[keyFor(item)], ...item };
  }

  function keyFor(item) {
    return String(item.placeholder ?? item.value ?? '').replace(/^\{\{|\}\}$/g, '');
  }

  function macroFor(item) {
    return `{{${keyFor(item)}}}`;
  }

  function isUsed(item) {
    return usedKeys.includes(keyFor(item));
  }

  function isRecent(item) {
    return recentKeys.includes(keyFor(item));
  }

  function closeOnBackdrop(event) {
    if (event.target === event.currentTarget) onclose();
  }
</script>
<svelte:window onkeydown={(event) => { if (event.key === 'Escape' && open) onclose(); }} />

{#if open}
  <div class={embedded ? 'h-auto w-full lg:h-full' : 'fixed inset-0 z-[80] flex items-center justify-center bg-studio-overlay p-4 backdrop-blur-[2px]'} role={embedded ? undefined : 'presentation'} onclick={embedded ? undefined : closeOnBackdrop}>
    <section
      class={`flex flex-col overflow-hidden border border-studio-border bg-studio-panel ${embedded ? 'h-auto min-h-96 w-full max-w-none rounded-xl shadow-none lg:h-full lg:min-h-0 lg:max-h-full' : 'max-h-[min(760px,calc(100vh-2rem))] w-full max-w-4xl rounded-2xl shadow-2xl'}`}
      role="dialog"
      aria-modal={embedded ? undefined : 'true'}
      aria-labelledby="placeholder-picker-title"
    >
      <header class="flex items-start justify-between gap-4 border-b border-studio-border px-5 py-4">
        <div class="min-w-0">
          <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">Template fields</p>
          <h2 id="placeholder-picker-title" class="mt-1 text-lg font-semibold text-foreground">{mode === 'insert' ? 'Insert a field' : 'Copy a placeholder'}</h2>
          <p class="mt-1 text-xs text-muted-foreground">{embedded ? 'Copy exact fields into Word or LibreOffice before uploading the document.' : 'Use human labels first. The exact binding is copied or inserted unchanged.'}</p>
        </div>
        {#if !embedded}<button type="button" class="rounded-lg p-2 text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground" onclick={onclose} aria-label="Close placeholder picker">
          <X class="size-4" />
        </button>{/if}
      </header>

      <div class="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_220px]">
        <div class="flex min-h-80 flex-col border-b border-studio-border lg:min-h-0 lg:border-b-0 lg:border-r">
          <div class="space-y-3 px-5 py-4">
            <label class="relative block">
              <span class="sr-only">Search placeholders</span>
              <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
              <input bind:this={searchInput} bind:value={query} class="min-h-10 w-full rounded-lg border border-studio-border bg-studio-panel-muted pl-9 pr-3 text-sm text-studio-panel-foreground outline-none ring-offset-studio-panel focus-visible:ring-2 focus-visible:ring-studio-focus" placeholder="Search applicant, score, exam date…" />
            </label>
            <div class="flex gap-1.5 overflow-x-auto pb-1" role="tablist" aria-label="Placeholder categories">
              {#each categories as item (item.key)}
                <button type="button" role="tab" aria-selected={category === item.key} class={`shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors motion-reduce:transition-none ${category === item.key ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground' : 'border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground hover:bg-studio-panel'}`} onclick={() => (category = item.key)}>
                  {item.label}
                </button>
              {/each}
            </div>
          </div>

          <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-5">
            {#if missingItems.length}
              <div class="mb-4 space-y-2 rounded-xl border border-studio-warning bg-studio-warning p-3" role="status"><div class="flex items-center gap-2 text-xs font-black text-studio-warning-foreground"><AlertTriangle class="size-3.5" /> Missing required fields</div><p class="text-[10px] leading-relaxed text-studio-warning-foreground/90">Copy these into Word, upload the corrected document, then analyze again.</p>{#each missingItems as item (keyFor(item))}{@const key = keyFor(item)}<div class="flex items-center gap-3 rounded-lg border border-studio-warning/50 bg-studio-panel px-3 py-2"><div class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-foreground">{labelFor(key)}</span><code class="block truncate text-[11px] text-muted-foreground">{macroFor(item)}</code></div><button type="button" class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg bg-studio-warning-foreground px-3 text-xs font-semibold text-studio-warning hover:bg-studio-warning-foreground/90" onclick={() => oncopy({ key, macro: macroFor(item), label: labelFor(key) })}><Clipboard class="size-3.5" /> Copy</button></div>{/each}</div>
            {/if}
            {#if items.length === 0}
              <div class="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">No matching fields.</div>
            {:else}
              <div class="space-y-2">
                {#each items as item (keyFor(item))}
                  {@const key = keyFor(item)}
                  {@const details = detailsFor(item)}
                  <div class={`flex items-center gap-3 rounded-xl border px-3 py-3 transition-colors motion-reduce:transition-none ${isUsed(item) ? 'border-studio-success/80 bg-studio-success/30' : 'border-studio-border/70 bg-studio-panel hover:bg-studio-panel-muted/50'}`}>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-semibold text-foreground">{labelFor(item)}</span>
                        {#if isUsed(item)}<Check class="size-3.5 shrink-0 text-studio-success-foreground" aria-label="Used in template" />{/if}
                        {#if isRecent(item)}<Clock3 class="size-3.5 shrink-0 text-studio-focus" aria-label="Recently copied" />{/if}
                      </div>
                      <p class="mt-0.5 truncate text-xs text-muted-foreground">{details.description || 'Template binding'}</p>
                      {#if details.example}
                        <p class="mt-1 text-xs text-muted-foreground">Example: <span class="font-medium text-foreground">{details.example}</span></p>
                      {/if}
                      <code class="mt-1 block truncate text-[11px] text-muted-foreground/80">{macroFor(item)}</code>
                      {#if details.aliases && details.aliases.length > 0}
                        <div class="mt-1.5 flex flex-wrap items-center gap-1">
                          <span class="text-[10px] text-muted-foreground/80 font-medium">Tag aliases:</span>
                          {#each details.aliases as alias (alias)}
                            <button
                              type="button"
                              class="font-mono text-[10px] bg-studio-panel-muted hover:bg-studio-panel text-studio-panel-foreground px-1.5 py-0.5 rounded border border-studio-border hover:border-studio-focus transition-colors"
                              onclick={(e) => {
                                e.stopPropagation();
                                const selection = { key: alias, macro: `{{${alias}}}`, label: labelFor(alias) };
                                if (mode === 'insert') oninsert(selection);
                                else oncopy(selection);
                              }}
                              title={`${mode === 'insert' ? 'Insert' : 'Copy'} {{${alias}}}`}
                            >
                              {`{{${alias}}}`}
                            </button>
                          {/each}
                        </div>
                      {/if}
                    </div>
                    {#if mode === 'insert'}
                      <button type="button" class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground hover:bg-primary/90" onclick={() => oninsert({ key, macro: macroFor(item), label: labelFor(key) })}>
                        <Plus class="size-3.5" /> Insert
                      </button>
                    {:else}
                      <button type="button" class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-lg border border-studio-border bg-studio-panel px-3 text-xs font-semibold text-studio-panel-foreground hover:bg-studio-panel-muted" onclick={() => oncopy({ key, macro: macroFor(item), label: labelFor(key) })}>
                        <Clipboard class="size-3.5" /> Copy
                      </button>
                    {/if}
                  </div>
                {/each}
              </div>
            {/if}
          </div>
        </div>

        <aside class="min-h-0 overflow-y-auto bg-studio-muted-panel px-5 py-4">
          <div>
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Used in template</h3>
              <span class="rounded-full bg-studio-success px-2 py-0.5 text-[10px] font-semibold text-studio-success-foreground">{usedKeys.length}</span>
            </div>
            {#if usedKeys.length === 0}
              <p class="mt-3 text-xs leading-relaxed text-muted-foreground">No fields detected yet. Insert a field or add one to the template.</p>
            {:else}
              <div class="mt-3 flex flex-wrap gap-1.5">
                {#each usedKeys as key (key)}<span class="rounded-md border border-studio-success/50 bg-studio-panel px-2 py-1 text-[11px] text-studio-panel-foreground">{labelFor(key)}</span>{/each}
              </div>
            {/if}
          </div>
          <div class="mt-6 border-t border-border/70 pt-4">
            <div class="flex items-center justify-between gap-2">
              <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Recently copied</h3>
              <span class="rounded-full bg-info/15 px-2 py-0.5 text-[10px] font-semibold text-info">{recentKeys.length}</span>
            </div>
            {#if recentKeys.length === 0}
              <p class="mt-3 text-xs leading-relaxed text-muted-foreground">Copied fields will appear here during this session.</p>
            {:else}
              <div class="mt-3 space-y-1.5">{#each recentKeys as key (key)}<div class="flex items-center gap-2 rounded-lg border border-studio-border/70 bg-studio-panel px-2.5 py-2 text-xs"><Check class="size-3.5 text-info" />{labelFor(key)}</div>{/each}</div>
            {/if}
          </div>
        </aside>
      </div>
    </section>
  </div>
{/if}
