<script>
  import { untrack } from 'svelte';
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Textarea } from '@/Components/ui/textarea';
  import { Switch } from '@/Components/ui/switch';
  import { success } from '@/lib/toast';
  import { Calculator, Table, Plus, Trash2, ClipboardPaste, Wand2, X, Copy } from 'lucide-svelte';

  let { enable_normalized_scores = false } = $props();
  const page = usePage();

  const form = useForm({
    name: '',
    code: '',
    description: '',
    max_items: 25,
    formula: '',
    scoring_method: 'formula',
    conversion_table: [],
    display_order: 0,
    is_active: true,
  });

  let showPasteOverlay = $state(false);
  let pasteText = $state('');
  let pasteError = $state('');

  let testScore = $state(10);
  let testResult = $state(null);
  let testError = $state('');

  let uiTable = $state([]);
  let uiTableError = $state('');
  let initialized = $state(false);

  function collapseTable(flatRows) {
    if (!flatRows || flatRows.length === 0) return [];
    const sorted = [...flatRows]
      .filter(r => r.raw_score !== '' && r.raw_score !== null && !isNaN(parseInt(r.raw_score, 10)))
      .sort((a, b) => parseInt(a.raw_score, 10) - parseInt(b.raw_score, 10));
    
    const uiRows = [];
    let currentGroup = null;

    for (const row of sorted) {
      const score = parseInt(row.raw_score, 10);
      const pct = row.percentile_output;
      
      if (!currentGroup) {
        currentGroup = { start: score, end: score, percentile: pct };
        continue;
      }
      
      if (score === currentGroup.end + 1 && pct === currentGroup.percentile) {
        currentGroup.end = score;
      } else {
        uiRows.push(currentGroup);
        currentGroup = { start: score, end: score, percentile: pct };
      }
    }
    if (currentGroup) {
      uiRows.push(currentGroup);
    }
    
    return uiRows.map(g => {
      if (g.start === g.end) {
        return { type: 'single', raw_score: g.start, percentile_output: g.percentile };
      } else {
        return { type: 'range', range_start: g.start, range_end: g.end, percentile_output: g.percentile };
      }
    });
  }

  function flattenTable(uiRows) {
    const flat = [];
    for (const row of uiRows) {
      if (row.type === 'single') {
        const val = parseInt(row.raw_score, 10);
        if (!isNaN(val)) {
          flat.push({ raw_score: val, percentile_output: row.percentile_output });
        }
      } else if (row.type === 'range') {
        const start = parseInt(row.range_start, 10);
        const end = parseInt(row.range_end, 10);
        if (!isNaN(start) && !isNaN(end) && start <= end) {
          for (let s = start; s <= end; s++) {
            flat.push({ raw_score: s, percentile_output: row.percentile_output });
          }
        }
      }
    }
    return flat.sort((a, b) => a.raw_score - b.raw_score);
  }

  function isSameTable(tableA, tableB) {
    if (!tableA || !tableB) return false;
    if (tableA.length !== tableB.length) return false;
    for (let i = 0; i < tableA.length; i++) {
      if (tableA[i].raw_score !== tableB[i].raw_score) return false;
      if (tableA[i].percentile_output !== tableB[i].percentile_output) return false;
    }
    return true;
  }

  $effect(() => {
    if (!initialized) {
      untrack(() => {
        uiTable = collapseTable($form.conversion_table);
        initialized = true;
      });
    }
  });

  $effect(() => {
    const method = $form.scoring_method;
    untrack(() => {
      if (method === 'formula') {
        if (uiTable.length > 0) {
          uiTable = [];
        }
        if ($form.conversion_table.length > 0) {
          $form.conversion_table = [];
        }
      }
    });
  });

  $effect(() => {
    const rows = uiTable;
    uiTableError = '';

    const seenScores = new Set();
    let hasError = false;

    for (let idx = 0; idx < rows.length; idx++) {
      const row = rows[idx];
      const label = `Row ${idx + 1}`;

      if (row.percentile_output === '') {
        uiTableError = `${label}: Percentile output cannot be empty.`;
        hasError = true;
        break;
      }

      if (row.type === 'single') {
        if (row.raw_score === '' || row.raw_score === null || isNaN(parseInt(row.raw_score, 10))) {
          uiTableError = `${label}: Raw score is missing or invalid.`;
          hasError = true;
          break;
        }
        const val = parseInt(row.raw_score, 10);
        if (val < 0) {
          uiTableError = `${label}: Raw score cannot be negative.`;
          hasError = true;
          break;
        }
        if (seenScores.has(val)) {
          uiTableError = `${label}: Raw score ${val} is defined more than once.`;
          hasError = true;
          break;
        }
        seenScores.add(val);
      } else if (row.type === 'range') {
        if (row.range_start === '' || row.range_start === null || isNaN(parseInt(row.range_start, 10))) {
          uiTableError = `${label}: Range start is missing or invalid.`;
          hasError = true;
          break;
        }
        if (row.range_end === '' || row.range_end === null || isNaN(parseInt(row.range_end, 10))) {
          uiTableError = `${label}: Range end is missing or invalid.`;
          hasError = true;
          break;
        }

        const start = parseInt(row.range_start, 10);
        const end = parseInt(row.range_end, 10);
        if (start < 0 || end < 0) {
          uiTableError = `${label}: Range boundaries cannot be negative.`;
          hasError = true;
          break;
        }
        if (start > end) {
          uiTableError = `${label}: Range start (${start}) cannot be greater than range end (${end}).`;
          hasError = true;
          break;
        }
        for (let s = start; s <= end; s++) {
          if (seenScores.has(s)) {
            uiTableError = `${label}: Raw score ${s} in range ${start}-${end} is defined more than once.`;
            hasError = true;
            break;
          }
          seenScores.add(s);
        }
        if (hasError) break;
      }
    }

    untrack(() => {
      const nextTable = !hasError ? flattenTable(rows) : [];
      if (!isSameTable($form.conversion_table, nextTable)) {
        $form.conversion_table = nextTable;
      }
    });
  });

  function addSingleRow() {
    uiTable = [...uiTable, { type: 'single', raw_score: '', percentile_output: '' }];
  }

  function addRangeRow() {
    uiTable = [...uiTable, { type: 'range', range_start: '', range_end: '', percentile_output: '' }];
  }

  function removeRow(index) {
    uiTable = uiTable.filter((_, i) => i !== index);
  }

  function generateRows() {
    const max = parseInt($form.max_items, 10) || 0;
    if (max <= 0) return;
    uiTable = [{ type: 'range', range_start: 0, range_end: max, percentile_output: '' }];
  }

  function openPaste() {
    showPasteOverlay = true;
    pasteText = '';
    pasteError = '';
  }

  // Close Paste Overlay
  function closePaste() {
    showPasteOverlay = false;
    pasteText = '';
    pasteError = '';
  }

  function applyPaste() {
    pasteError = '';
    if (!pasteText.trim()) {
      pasteError = 'Paste area is empty.';
      return;
    }
    const lines = pasteText.trim().split(/\r?\n/).filter(l => l.trim());
    const parsedRows = [];
    for (const line of lines) {
      let separatorIndex = line.search(/[\t:=]|\s{2,}/);
      let scorePart = '';
      let out = '';
      
      if (separatorIndex !== -1) {
        scorePart = line.substring(0, separatorIndex).trim();
        const matchedDelim = line.match(/[\t:=]|\s{2,}/);
        const delimLength = matchedDelim ? matchedDelim[0].length : 1;
        out = line.substring(separatorIndex + delimLength).trim();
      } else {
        const spaceIndex = line.indexOf(' ');
        if (spaceIndex !== -1) {
          scorePart = line.substring(0, spaceIndex).trim();
          out = line.substring(spaceIndex + 1).trim();
        } else {
          continue;
        }
      }

      if (scorePart && out) {
        const rangeMatch = scorePart.match(/^(\d+)\s*(?:-|_|~|\/|to)\s*(\d+)$/i);
        if (rangeMatch) {
          const start = parseInt(rangeMatch[1], 10);
          const end = parseInt(rangeMatch[2], 10);
          if (!isNaN(start) && !isNaN(end) && start <= end) {
            parsedRows.push({ type: 'range', range_start: start, range_end: end, percentile_output: out });
          }
        } else {
          const raw = parseInt(scorePart, 10);
          if (!isNaN(raw)) {
            parsedRows.push({ type: 'single', raw_score: raw, percentile_output: out });
          }
        }
      }
    }
    if (parsedRows.length === 0) {
      pasteError = 'No valid rows found. Supported format:\n- 0-10 [tab] 85th\n- 15: 90th';
      return;
    }
    uiTable = [...uiTable, ...parsedRows];
    closePaste();
  }

  function copyTable() {
    if (uiTable.length === 0) {
      success('The conversion table is empty.');
      return;
    }
    const text = uiTable.map(r => {
      if (r.type === 'single') {
        return `${r.raw_score}\t${r.percentile_output}`;
      } else {
        return `${r.range_start}-${r.range_end}\t${r.percentile_output}`;
      }
    }).join('\n');
    
    navigator.clipboard.writeText(text)
      .then(() => {
        success('Conversion table copied to clipboard.');
      })
      .catch((err) => {
        console.error('Failed to copy text: ', err);
        alert('Failed to copy to clipboard.');
      });
  }

  async function testFormula() {
    testResult = null;
    testError = '';
    if (!$form.formula) {
      testError = 'Enter a formula first';
      return;
    }
    try {
      const res = await fetch('/admin/aptitude-areas/test-formula', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': $page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ formula: $form.formula, sample_raw_score: testScore, max_items: Number($form.max_items) }),
      });
      const data = await res.json();
      if (!res.ok || data.error) {
        testError = data.error || `Request failed (${res.status})`;
      } else {
        testResult = data.result;
      }
    } catch (e) {
      console.error('Test formula error:', e);
      testError = 'Request failed';
    }
  }

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Aptitude area created');
    }
  };

  function submitForm(e) {
    e.preventDefault();
    if (uiTableError) {
      alert('Please resolve the conversion table errors before saving.');
      return;
    }
    if (enable_normalized_scores && $form.scoring_method === 'conversion_table' && uiTable.length === 0) {
      alert('Conversion table cannot be empty.');
      return;
    }
    $form.post('/admin/aptitude-areas');
  }

  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Aptitude Areas', href: '/admin/aptitude-areas' },
    { label: 'Create' },
  ];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-lg space-y-6">
    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="name" class="text-sm font-medium">Name</label>
        <Input id="name" bind:value={$form.name} placeholder="e.g., Spatial Awareness" required maxlength="100" />
        {#if $form.errors?.name}
          <p class="text-sm text-destructive">{$form.errors.name}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="code" class="text-sm font-medium">Code</label>
        <Input id="code" bind:value={$form.code} placeholder="e.g., SA" required maxlength="20" />
        {#if $form.errors?.code}
          <p class="text-sm text-destructive">{$form.errors.code}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="description" class="text-sm font-medium">Description (optional)</label>
        <Textarea
          id="description"
          bind:value={$form.description}
          rows="2"
          class="flex min-h-[60px] w-full"
          placeholder="Brief description"
        />
        {#if $form.errors?.description}
          <p class="text-sm text-destructive">{$form.errors.description}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="max_items" class="text-sm font-medium">Max items (score ceiling)</label>
        <Input id="max_items" type="number" bind:value={$form.max_items} min="1" max="999" required />
        {#if $form.errors?.max_items}
          <p class="text-sm text-destructive">{$form.errors.max_items}</p>
        {/if}
      </div>

      <!-- Scoring Method Toggle -->
      <div class="space-y-2">
        <label class="text-sm font-medium">Scoring Method</label>
        <div class="flex gap-4">
          <label class="flex items-center gap-2 rounded-md border border-border px-3 py-2 cursor-pointer hover:bg-muted/40">
            <input
              type="radio"
              name="scoring_method"
              value="formula"
              bind:group={$form.scoring_method}
              class="h-4 w-4"
            />
            <Calculator class="h-4 w-4 text-muted-foreground" />
            <span class="text-sm">Formula</span>
          </label>
          <label class="flex items-center gap-2 rounded-md border border-border px-3 py-2 cursor-pointer hover:bg-muted/40">
            <input
              type="radio"
              name="scoring_method"
              value="conversion_table"
              bind:group={$form.scoring_method}
              class="h-4 w-4"
            />
            <Table class="h-4 w-4 text-muted-foreground" />
            <span class="text-sm">Conversion Table</span>
          </label>
        </div>
        {#if $form.errors?.scoring_method}
          <p class="text-sm text-destructive">{$form.errors.scoring_method}</p>
        {/if}
      </div>

      {#if $form.scoring_method === 'formula'}
        <div class="space-y-2">
          <label for="formula" class="text-sm font-medium">Formula</label>
          <textarea
            id="formula"
            bind:value={$form.formula}
            rows="2"
            class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
            placeholder="e.g., (x / max_items) * 100"
          ></textarea>
          <p class="text-xs text-muted-foreground">Variables: x (raw score), max_items, pi</p>
          {#if $form.errors?.formula}
            <p class="text-sm text-destructive">{$form.errors.formula}</p>
          {/if}
        </div>

        <div class="space-y-2 rounded-md border border-border bg-muted/30 p-4">
          <p class="text-sm font-medium">Test Formula</p>
          <div class="flex items-center gap-3">
            <div class="flex-1">
              <label class="text-xs text-muted-foreground">Sample raw score</label>
              <Input type="number" bind:value={testScore} min="0" />
            </div>
            <div class="flex-1">
              <label class="text-xs text-muted-foreground">Result</label>
              <div class="h-10 flex items-center text-sm">
                {#if testResult !== null}
                  <span class="font-medium text-green-700">{testResult}</span>
                {:else if testError}
                  <span class="text-red-600">{testError}</span>
                {:else}
                  <span class="text-muted-foreground">—</span>
                {/if}
              </div>
            </div>
          </div>
          <Button type="button" variant="outline" size="sm" onclick={testFormula}>Test</Button>
        </div>
      {:else}
        <!-- Conversion Table Grid -->
        <div class="space-y-3 rounded-md border border-border bg-muted/20 p-4">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium">Conversion Table</p>
            <div class="flex gap-2">
              {#if uiTable.length === 0}
                <Button type="button" variant="outline" size="sm" onclick={generateRows}>
                  <Wand2 class="mr-1.5 h-3.5 w-3.5" />
                  Generate 0–{$form.max_items}
                </Button>
              {/if}
              {#if uiTable.length > 0}
                <Button type="button" variant="outline" size="sm" onclick={copyTable}>
                  <Copy class="mr-1.5 h-3.5 w-3.5" />
                  Copy
                </Button>
              {/if}
              <Button type="button" variant="outline" size="sm" onclick={openPaste}>
                <ClipboardPaste class="mr-1.5 h-3.5 w-3.5" />
                Paste
              </Button>
            </div>
          </div>

          {#if uiTable.length > 0}
            <div class="space-y-2">
              <div class="flex gap-2 text-xs font-medium text-muted-foreground px-2">
                <span class="w-48">Raw Score / Range</span>
                <span class="flex-1">Percentile Output</span>
                <span class="w-8"></span>
              </div>
              <div class="max-h-72 overflow-y-auto space-y-2 pr-1 p-2 bg-background/50 border border-border/50 rounded-md">
                {#each uiTable as row, i}
                  <div class="flex items-center gap-2">
                    {#if row.type === 'single'}
                      <div class="flex items-center gap-1 w-48">
                        <Input
                          type="number"
                          min="0"
                          bind:value={row.raw_score}
                          placeholder="Score"
                          class="w-full font-mono text-center"
                        />
                      </div>
                    {:else}
                      <div class="flex items-center gap-1 w-48">
                        <Input
                          type="number"
                          min="0"
                          bind:value={row.range_start}
                          placeholder="Min"
                          class="w-[45%] font-mono text-center p-1"
                        />
                        <span class="text-muted-foreground font-bold px-0.5">—</span>
                        <Input
                          type="number"
                          min="0"
                          bind:value={row.range_end}
                          placeholder="Max"
                          class="w-[45%] font-mono text-center p-1"
                        />
                      </div>
                    {/if}
                    <Input
                      type="text"
                      maxlength="20"
                      bind:value={row.percentile_output}
                      placeholder="e.g., 85th, 99+, N/A"
                      class="flex-1"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="h-9 w-8 p-0 text-destructive hover:bg-destructive/10"
                      onclick={() => removeRow(i)}
                    >
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                {/each}
              </div>
              
              <div class="flex gap-2">
                <Button type="button" variant="outline" size="sm" onclick={addSingleRow} class="flex-1">
                  <Plus class="mr-1.5 h-3.5 w-3.5" />
                  Add Single Score
                </Button>
                <Button type="button" variant="outline" size="sm" onclick={addRangeRow} class="flex-1">
                  <Plus class="mr-1.5 h-3.5 w-3.5" />
                  Add Range
                </Button>
              </div>
            </div>
          {:else}
            <p class="text-sm text-muted-foreground">No rows yet. Use buttons or Paste to add rows.</p>
            <div class="flex gap-2 mt-2">
              <Button type="button" variant="outline" size="sm" onclick={addSingleRow} class="flex-1">
                <Plus class="mr-1.5 h-3.5 w-3.5" />
                Add Single Score
              </Button>
              <Button type="button" variant="outline" size="sm" onclick={addRangeRow} class="flex-1">
                <Plus class="mr-1.5 h-3.5 w-3.5" />
                Add Range
              </Button>
            </div>
          {/if}

          {#if uiTableError}
            <p class="text-sm text-destructive mt-1">{uiTableError}</p>
          {/if}

          {#if $form.errors?.conversion_table}
            <p class="text-sm text-destructive">{$form.errors.conversion_table}</p>
          {/if}
        </div>
      {/if}

      <div class="space-y-2">
        <label for="display_order" class="text-sm font-medium">Display order</label>
        <Input id="display_order" type="number" bind:value={$form.display_order} min="0" />
        {#if $form.errors?.display_order}
          <p class="text-sm text-destructive">{$form.errors.display_order}</p>
        {/if}
      </div>

      <div class="flex items-center gap-3">
        <Switch
          id="is_active"
          checked={$form.is_active}
          onCheckedChange={(v) => ($form.is_active = v)}
        />
        <label for="is_active" class="text-sm font-medium">Active (included in grading and templates)</label>
      </div>
      {#if $form.errors?.is_active}
        <p class="text-sm text-destructive">{$form.errors.is_active}</p>
      {/if}

      <div class="flex gap-2 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create aptitude area'}
        </Button>
        <Link href="/admin/aptitude-areas">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>

  {#if showPasteOverlay}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="w-full max-w-md space-y-4 rounded-lg border border-border bg-card p-6 shadow-lg">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-medium">Paste from Excel</h3>
          <Button variant="ghost" size="sm" class="h-8 w-8 p-0" onclick={closePaste}>
            <X class="h-4 w-4" />
          </Button>
        </div>
        <p class="text-sm text-muted-foreground">
          Paste tab/colon separated data. Ranges are supported (e.g. 0-10). Each line: raw_score_or_range [tab/colon] percentile_output
        </p>
        <Textarea
          bind:value={pasteText}
          rows="8"
          placeholder={"0-10\t50th\n11-20\t80th\n21: 90th"}
          class="w-full"
        />
        {#if pasteError}
          <p class="text-sm text-destructive">{pasteError}</p>
        {/if}
        <div class="flex justify-end gap-2">
          <Button variant="outline" onclick={closePaste}>Cancel</Button>
          <Button onclick={applyPaste}>Apply</Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>
