<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { ClipboardPaste, Copy, Plus, Save, Trash2 } from 'lucide-svelte';
  import { Link, router, usePage } from '@inertiajs/svelte';

  let { area = {}, conversion_rows = [] } = $props();

  const page = usePage();
  const saveError = $derived(page.props.errors?.scoring ?? '');
  const status = $derived(page.props.flash?.status ?? '');

  let maxItems = $state(area.max_items ?? 100);
  let rows = $state(
    conversion_rows.map((r) => ({
      min_score: r.min_score,
      max_score: r.max_score,
      output_value: r.output_value,
    })),
  );

  let pasteOpen = $state(false);
  let pasteText = $state('');
  let pasteError = $state('');
  let localError = $state('');
  let saving = $state(false);

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Aptitude areas', href: '/setup/aptitude' },
    { label: `${area.code ?? ''} scoring` },
  ];

  function addRow() {
    const nextMin = rows.length === 0 ? 0 : Math.max(rows[rows.length - 1].max_score + 1, 0);
    rows = [
      ...rows,
      { min_score: nextMin, max_score: nextMin, output_value: '' },
    ];
  }

  function removeRow(index) {
    rows = rows.filter((_, i) => i !== index);
  }

  function collapseTable(flatRows) {
    const sorted = [...flatRows]
      .filter((r) => r.min_score !== '' && r.min_score !== null)
      .sort((a, b) => Number(a.min_score) - Number(b.min_score));
    const uiRows = [];
    let currentGroup = null;

    for (const row of sorted) {
      const start = Number(row.min_score);
      const end = Number(row.max_score);
      const out = row.output_value;

      if (!currentGroup) {
        currentGroup = { start, end, percentile: out };
        continue;
      }

      if (start === currentGroup.end + 1 && out === currentGroup.percentile) {
        currentGroup.end = end;
      } else {
        uiRows.push(currentGroup);
        currentGroup = { start, end, percentile: out };
      }
    }
    if (currentGroup) uiRows.push(currentGroup);

    return uiRows;
  }

  function applyPaste() {
    pasteError = '';
    if (!pasteText.trim()) {
      pasteError = 'Paste area is empty.';
      return;
    }
    const lines = pasteText.trim().split(/\r?\n/).filter((l) => l.trim());
    const parsed = [];
    for (const line of lines) {
      const separatorIndex = line.search(/[\t:=]|\s{2,}/);
      let scorePart = '';
      let out = '';

      if (separatorIndex !== -1) {
        scorePart = line.substring(0, separatorIndex).trim();
        const matchedDelim = line.match(/[\t:=]|\s{2,}/);
        const delimLength = matchedDelim ? matchedDelim[0].length : 1;
        out = line.substring(separatorIndex + delimLength).trim();
      } else {
        const spaceIndex = line.indexOf(' ');
        if (spaceIndex === -1) continue;
        scorePart = line.substring(0, spaceIndex).trim();
        out = line.substring(spaceIndex + 1).trim();
      }

      if (!scorePart || !out) continue;
      const rangeMatch = scorePart.match(/^(\d+)\s*(?:-|_|~|\/|to)\s*(\d+)$/i);
      if (rangeMatch) {
        const start = parseInt(rangeMatch[1], 10);
        const end = parseInt(rangeMatch[2], 10);
        if (!isNaN(start) && !isNaN(end) && start <= end) {
          parsed.push({ min_score: start, max_score: end, output_value: out });
        }
      } else {
        const raw = parseInt(scorePart, 10);
        if (!isNaN(raw)) {
          parsed.push({ min_score: raw, max_score: raw, output_value: out });
        }
      }
    }
    if (parsed.length === 0) {
      pasteError = 'No valid rows found. Supported format:\n- 0-10 [tab] 85th\n- 15: 90th';
      return;
    }
    rows = [...rows, ...parsed];
    pasteOpen = false;
    pasteText = '';
  }

  function copyTable() {
    if (rows.length === 0) return;
    const text = collapseTable(rows)
      .map((g) =>
        g.start === g.end
          ? `${g.start}\t${g.percentile}`
          : `${g.start}-${g.end}\t${g.percentile}`,
      )
      .join('\n');
    navigator.clipboard?.writeText(text);
  }

  function validateRows() {
    if (rows.length === 0) return 'A conversion table needs at least one range row.';
    const sorted = [...rows].sort((a, b) => Number(a.min_score) - Number(b.min_score));
    if (sorted[0].min_score !== 0) return 'The conversion table must start at score 0.';
    let previousMax = -1;
    for (let i = 0; i < sorted.length; i++) {
      const min = Number(sorted[i].min_score);
      const max = Number(sorted[i].max_score);
      if (isNaN(min) || isNaN(max) || min < 0 || max < min) return `Row ${i + 1} has an invalid score range.`;
      if (max > Number(maxItems)) return `Row ${i + 1} exceeds the maximum score of ${maxItems}.`;
      if (i > 0) {
        if (min <= previousMax) return 'Conversion table ranges must not overlap.';
        if (min !== previousMax + 1) return `Conversion table has a gap between ${previousMax} and ${min}.`;
      }
      previousMax = max;
    }
    if (previousMax < Number(maxItems)) return `The conversion table must cover scores up to ${maxItems}.`;
    return '';
  }

  function save() {
    if (saving) return;
    localError = validateRows();
    if (localError) return;

    saving = true;
    router.put(`/setup/aptitude/${area.id}/scoring`, {
      scoring_method: 'conversion_table',
      max_items: Number(maxItems),
      conversion_rows: rows.map((r) => ({
        min_score: Number(r.min_score),
        max_score: Number(r.max_score),
        output_value: r.output_value,
      })),
    }, {
      preserveScroll: true,
      onFinish: () => {
        saving = false;
      },
    });
  }
</script>

<svelte:head><title>{area.code ?? 'Area'} scoring · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 max-w-3xl space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Scoring configuration" description={`${area.name ?? ''} (${area.code ?? ''}) · v${area.version ?? 1}`}>
      {#snippet actions()}
        <Link href="/setup/aptitude"><Button variant="outline" size="sm">Back to aptitude areas</Button></Link>
      {/snippet}
    </PageHeaderBar>

    <ModeBanner variant="warning" title="Confidential — Test Administrator only">
      This conversion table decides every grade in the assessment. It is never shown to applicants,
      proctors, or Program Head. Treat edits as versioned changes: already-recorded scores keep the
      table that was live when they were entered.
    </ModeBanner>

    {#if status}
      <p class="text-sm text-emerald-600" data-testid="scoring-status">{status}</p>
    {/if}

    <form
      class="space-y-6 rounded-xl border border-border bg-card p-6"
      onsubmit={(e) => {
        e.preventDefault();
        save();
      }}
    >
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-1.5">
          <Label for="max-items">Maximum raw score</Label>
          <Input id="max-items" type="number" min="1" max="1000" bind:value={maxItems} class="min-h-10 w-32" />
          <p class="text-xs text-muted-foreground">Every score from 0 to this value must be covered by a row.</p>
        </div>
        <div class="flex items-end justify-end gap-2">
          <Button type="button" variant="outline" class="gap-2" onclick={() => (pasteOpen = true)}>
            <ClipboardPaste class="size-4" />
            Paste
          </Button>
          <Button type="button" variant="outline" class="gap-2" onclick={copyTable} disabled={rows.length === 0}>
            <Copy class="size-4" />
            Copy
          </Button>
        </div>
      </div>

      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <Label>Conversion table</Label>
          <Button type="button" variant="outline" size="sm" class="gap-2" onclick={addRow}>
            <Plus class="size-4" />
            Add row
          </Button>
        </div>

        {#if rows.length === 0}
          <div class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
            No rows yet — add a row or paste a table (e.g. <code class="font-mono">0-10 [tab] 85th</code>).
          </div>
        {:else}
          <div class="space-y-2">
            {#each rows as row, i (i)}
              <div class="grid grid-cols-[7rem_7rem_1fr_auto] items-center gap-2">
                <Input
                  aria-label={`Row ${i + 1} minimum score`}
                  type="number"
                  min="0"
                  max="1000"
                  value={row.min_score}
                  oninput={(e) => (row.min_score = e.currentTarget.value)}
                  class="min-h-10"
                />
                <Input
                  aria-label={`Row ${i + 1} maximum score`}
                  type="number"
                  min="0"
                  max="1000"
                  value={row.max_score}
                  oninput={(e) => (row.max_score = e.currentTarget.value)}
                  class="min-h-10"
                />
                <Input
                  aria-label={`Row ${i + 1} output value`}
                  type="text"
                  maxlength="50"
                  value={row.output_value}
                  oninput={(e) => (row.output_value = e.currentTarget.value)}
                  placeholder="e.g. 85th"
                  class="min-h-10"
                />
                <Button type="button" variant="ghost" size="icon" aria-label={`Remove row ${i + 1}`} onclick={() => removeRow(i)}>
                  <Trash2 class="size-4" />
                </Button>
              </div>
            {/each}
          </div>
        {/if}
      </div>

      {#if localError}
        <p class="text-sm text-destructive" data-testid="local-error">{localError}</p>
      {/if}
      {#if saveError}
        <p class="text-sm text-destructive" data-testid="save-error">{saveError}</p>
      {/if}

      <div class="flex items-center gap-3 border-t border-border pt-4">
        <Button type="submit" class="min-h-10 gap-2" disabled={saving || rows.length === 0}>
          <Save class="size-4" />
          {saving ? 'Saving…' : 'Save scoring configuration'}
        </Button>
        <Badge variant="outline">conversion_table</Badge>
      </div>
    </form>
  </div>

  {#if pasteOpen}
    <div class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div class="w-full max-w-lg rounded-xl border border-border bg-card p-5">
        <h2 class="text-lg font-semibold">Paste conversion table</h2>
        <p class="mt-1 text-sm text-muted-foreground">
          One range per line: <code class="font-mono">0-10 [tab] 85th</code>, <code class="font-mono">15: 90th</code>, or
          <code class="font-mono">20 92nd</code>. Ranges may be entered directly.
        </p>
        <Textarea bind:value={pasteText} rows="8" class="mt-3 min-h-40 font-mono text-sm" placeholder={'0-49\tBelow Average\n50-74\tAverage\n75-89\tAbove Average\n90-100\tExcellent'} />
        {#if pasteError}
          <p class="mt-2 text-sm text-destructive">{pasteError}</p>
        {/if}
        <div class="mt-4 flex justify-end gap-2">
          <Button type="button" variant="outline" onclick={() => (pasteOpen = false)}>Cancel</Button>
          <Button type="button" onclick={applyPaste}>Add rows</Button>
        </div>
      </div>
    </div>
  {/if}
</StaffLayout>
