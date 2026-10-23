<script>
  /**
   * ImportEnginePreview.svelte
   *
   * Interactive UI Lab prototype demonstrating the next-generation NEXIAM Ingestion Engine:
   * 1. Multi-Sheet & Multi-Session Workbook Inspector
   * 2. Pre-Upload Structured Validation Checklist (pass / warn / fail)
   * 3. Real-Time Column Mapping with Fuzzy Aliasing (Demographics + Scores)
   * 4. Staged Preview with Row Error Expansion & Selective Checkboxes
   * 5. Single-Pass Pipeline Simulation (Zero Cross-Page Trips)
   */
  import { reveal } from '@/lib/motion.js';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Checkbox } from '@/Components/ui/checkbox';
  import * as Table from '@/Components/ui/table';
  import {
    FileSpreadsheet,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    ArrowRight,
    Upload,
    Layers,
    Sparkles,
    Check,
    RefreshCw,
    Download,
    ChevronDown,
    ChevronUp,
    Calendar,
    Users,
    FileCheck,
    SlidersHorizontal,
  } from 'lucide-svelte';

  // Mode Selection: All-in-One vs Multi-Session vs Classic
  let pipelineMode = $state('all-in-one'); // 'all-in-one' | 'multi-session' | 'domain-only'

  // Workbook Sheets mock
  let sheets = $state([
    { id: 'sheet1', name: 'Batch 1 - Morning (Room 101)', rows: 142, enabled: true, sessionMapping: 'EX-2026-001 (08:00 AM)' },
    { id: 'sheet2', name: 'Batch 2 - Afternoon (Room 102)', rows: 118, enabled: true, sessionMapping: 'EX-2026-002 (01:00 PM)' },
    { id: 'sheet3', name: 'Vendor Meta & Legend', rows: 0, enabled: false, sessionMapping: 'Skip' },
  ]);

  const activeSheetsCount = $derived(sheets.filter(s => s.enabled).length);
  const totalRowsCount = $derived(sheets.filter(s => s.enabled).reduce((acc, s) => acc + s.rows, 0));

  // Pre-upload checklist
  const checks = $derived([
    {
      id: 'format',
      label: 'File format & encoding',
      status: 'pass',
      detail: 'XLSX (Microsoft Excel OpenXML) · UTF-8 safe',
    },
    {
      id: 'sheets',
      label: 'Multi-sheet structure',
      status: 'pass',
      detail: `${sheets.length} sheets detected (${activeSheetsCount} active, ${totalRowsCount} total examinee rows)`,
    },
    {
      id: 'identity',
      label: 'Applicant identity columns',
      status: 'pass',
      detail: 'first_name, last_name, birthdate, sex, strand, course_preference_1 detected',
    },
    {
      id: 'scores',
      label: 'Aptitude score columns',
      status: pipelineMode === 'domain-only' ? 'info' : 'pass',
      detail: pipelineMode === 'domain-only'
        ? 'Ignored in "Applicants Only" preset'
        : '4 aptitude areas detected: MATH, SCIENCE, ENGLISH, ABSTRACT',
    },
    {
      id: 'fuzzy',
      label: 'Fuzzy alias mappings',
      status: 'warn',
      detail: '"Family Name" → last_name; "Gender" → sex; "Math Raw" → MATH',
    },
    {
      id: 'sessions',
      label: 'Session allocation',
      status: 'pass',
      detail: pipelineMode === 'all-in-one'
        ? 'Direct Assessment intake context (auto-binds exam assignment & grading)'
        : 'Mapped to 2 target exam sessions',
    },
  ]);

  // Column mapping definitions
  const columnMappings = $derived([
    { raw: 'Applicant No / LRN', mapped: 'applicant_number', type: 'Applicant', status: 'Required' },
    { raw: 'First Name', mapped: 'first_name', type: 'Applicant', status: 'Required' },
    { raw: 'Family Name', mapped: 'last_name', type: 'Applicant', status: 'Fuzzy Match' },
    { raw: 'Date of Birth', mapped: 'birthdate', type: 'Applicant', status: 'Optional' },
    { raw: 'Gender', mapped: 'sex', type: 'Applicant', status: 'Fuzzy Match' },
    { raw: 'SHS Strand', mapped: 'strand', type: 'Applicant', status: 'Optional' },
    { raw: 'Course Choice 1', mapped: 'course_preference_1', type: 'Applicant', status: 'Required' },
    ...(pipelineMode !== 'domain-only' ? [
      { raw: 'Math Raw Score', mapped: 'score.MATH', type: 'Scoring', status: 'Aptitude' },
      { raw: 'Science Raw Score', mapped: 'score.SCIENCE', type: 'Scoring', status: 'Aptitude' },
      { raw: 'English Raw Score', mapped: 'score.ENGLISH', type: 'Scoring', status: 'Aptitude' },
      { raw: 'Abstract Raw Score', mapped: 'score.ABSTRACT', type: 'Scoring', status: 'Aptitude' },
    ] : []),
  ]);

  // Staged Preview Rows
  let filterOnlyErrors = $state(false);
  let selectedRowIds = $state(new Set([1, 2, 4]));
  let expandedRowId = $state(3);

  const sampleRows = [
    {
      id: 1,
      sheet: 'Batch 1 - Morning',
      row: 2,
      ref: '2026-0041',
      name: 'Maria Clara Santos',
      strand: 'STEM',
      course: 'BSN',
      scores: { MATH: 88, SCIENCE: 92, ENGLISH: 85, ABSTRACT: 90 },
      isValid: true,
      error: null,
    },
    {
      id: 2,
      sheet: 'Batch 1 - Morning',
      row: 3,
      ref: '2026-0042',
      name: 'Juan Dela Cruz',
      strand: 'ABM',
      course: 'BSBA',
      scores: { MATH: 74, SCIENCE: 80, ENGLISH: 89, ABSTRACT: 82 },
      isValid: true,
      error: null,
    },
    {
      id: 3,
      sheet: 'Batch 1 - Morning',
      row: 4,
      ref: '2026-0043',
      name: 'Roberto Alvarez Jr.',
      strand: 'HUMSS',
      course: 'BSPSY',
      scores: { MATH: 'N/A', SCIENCE: 70, ENGLISH: 88, ABSTRACT: 75 },
      isValid: false,
      error: 'Score for MATH must be a valid numeric score (got "N/A"). Correct or exclude row.',
    },
    {
      id: 4,
      sheet: 'Batch 2 - Afternoon',
      row: 2,
      ref: '2026-0044',
      name: 'Alyssa Nicole Tan',
      strand: 'STEM',
      course: 'BSCS',
      scores: { MATH: 95, SCIENCE: 94, ENGLISH: 91, ABSTRACT: 96 },
      isValid: true,
      error: null,
    },
  ];

  const displayedRows = $derived(
    filterOnlyErrors ? sampleRows.filter(r => !r.isValid) : sampleRows
  );

  function toggleRow(id) {
    const next = new Set(selectedRowIds);
    if (next.has(id)) next.delete(id);
    else next.add(id);
    selectedRowIds = next;
  }

  function toggleAll() {
    if (selectedRowIds.size === sampleRows.filter(r => r.isValid).length) {
      selectedRowIds = new Set();
    } else {
      selectedRowIds = new Set(sampleRows.filter(r => r.isValid).map(r => r.id));
    }
  }

  // Simulation state
  let simulating = $state(false);
  let simulatedSuccess = $state(false);

  function runSimulation() {
    simulating = true;
    simulatedSuccess = false;
    setTimeout(() => {
      simulating = false;
      simulatedSuccess = true;
    }, 1200);
  }
</script>

<div class="space-y-6 rounded-2xl border border-border bg-card p-6 shadow-sm" use:reveal={{ y: 12 }}>
  <!-- Header & Mode Switcher -->
  <div class="flex flex-col gap-4 border-b border-border/60 pb-5 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <div class="flex items-center gap-2">
        <span class="inline-flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
          <FileSpreadsheet class="size-5" />
        </span>
        <div>
          <h2 class="text-xl font-bold tracking-tight text-foreground">Next-Gen Ingestion Pipeline</h2>
          <p class="text-xs text-muted-foreground">Interactive UI Lab prototype · multi-sheet, multi-session, all-in-one</p>
        </div>
      </div>
    </div>

    <!-- Mode Selector Pills -->
    <div class="flex flex-wrap items-center gap-1.5 rounded-xl border border-border/80 bg-muted/40 p-1">
      <button
        type="button"
        onclick={() => { pipelineMode = 'all-in-one'; simulatedSuccess = false; }}
        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {pipelineMode === 'all-in-one'
          ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
          : 'text-muted-foreground hover:text-foreground'}"
      >
        <Sparkles class="size-3.5 text-primary" />
        All-in-One Intake
      </button>
      <button
        type="button"
        onclick={() => { pipelineMode = 'multi-session'; simulatedSuccess = false; }}
        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {pipelineMode === 'multi-session'
          ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
          : 'text-muted-foreground hover:text-foreground'}"
      >
        <Layers class="size-3.5 text-info" />
        Multi-Session Sheets
      </button>
      <button
        type="button"
        onclick={() => { pipelineMode = 'domain-only'; simulatedSuccess = false; }}
        class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-all {pipelineMode === 'domain-only'
          ? 'bg-background text-foreground shadow-sm ring-1 ring-border/50'
          : 'text-muted-foreground hover:text-foreground'}"
      >
        <Users class="size-3.5 text-muted-foreground" />
        Contextual (Applicants Only)
      </button>
    </div>
  </div>

  <!-- Pipeline Explanation Box -->
  <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
    <div class="flex items-start gap-3">
      <Sparkles class="mt-0.5 size-5 shrink-0 text-primary" />
      <div class="space-y-1 text-xs">
        <p class="font-semibold text-foreground">
          {#if pipelineMode === 'all-in-one'}
            All-in-One Pipeline Active: Single-Pass Ingestion
          {:else if pipelineMode === 'multi-session'}
            Multi-Session Workbook Active: Automated Session Batching
          {:else}
            Contextual Preset Active: Traditional Domain Scoped Mode
          {/if}
        </p>
        <p class="text-muted-foreground leading-relaxed">
          {#if pipelineMode === 'all-in-one'}
            Applicant demographics, course preferences, and raw exam scores are ingested in a <strong>single database transaction</strong>. No manual stops at scheduling, monitoring, or grading session creation required.
          {:else if pipelineMode === 'multi-session'}
            Worksheets within the same Excel file are mapped to separate exam session timeslots. Eliminates repeated uploads and manual splitting of testing sheets.
          {:else}
            Mimics the single-domain flow from <code class="font-mono text-[11px] bg-muted px-1 py-0.5 rounded">/admissions</code>. Score columns are safely ignored.
          {/if}
        </p>
      </div>
    </div>
  </div>

  <!-- Section 1: Multi-Sheet Inspector -->
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
        <Layers class="size-3.5" />
        Step 1: Workbook & Sheet Detection
      </h3>
      <span class="text-xs font-medium text-foreground">
        Active payload: <strong class="font-bold text-primary">{totalRowsCount}</strong> examinee rows
      </span>
    </div>

    <div class="grid gap-2.5 sm:grid-cols-3">
      {#each sheets as sheet}
        <div
          class="flex flex-col justify-between rounded-xl border p-3.5 transition-all {sheet.enabled
            ? 'border-primary/40 bg-card shadow-sm ring-1 ring-primary/20'
            : 'border-border/60 bg-muted/20 opacity-60'}"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="space-y-0.5">
              <p class="text-xs font-bold text-foreground truncate max-w-[170px]" title={sheet.name}>{sheet.name}</p>
              <p class="text-[11px] text-muted-foreground">{sheet.rows} rows detected</p>
            </div>
            <Checkbox
              checked={sheet.enabled}
              onCheckedChange={(v) => { sheet.enabled = Boolean(v); }}
            />
          </div>

          <div class="mt-3 pt-2 border-t border-border/40 text-[10px]">
            <span class="text-muted-foreground block">Session target:</span>
            <span class="font-medium font-mono text-foreground">{sheet.sessionMapping}</span>
          </div>
        </div>
      {/each}
    </div>
  </div>

  <!-- Section 2: Pre-Upload Checklist (The Proven Good Component) -->
  <div class="space-y-3">
    <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
      <CheckCircle2 class="size-3.5" />
      Step 2: Pre-Upload Health Checklist
    </h3>

    <div class="grid gap-2 sm:grid-cols-2">
      {#each checks as check}
        <div class="flex items-start gap-2.5 rounded-xl border border-border/60 bg-muted/30 p-3">
          {#if check.status === 'pass'}
            <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
          {:else if check.status === 'warn'}
            <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
          {:else}
            <Sparkles class="mt-0.5 size-4 shrink-0 text-primary" />
          {/if}
          <div class="min-w-0 flex-1 space-y-0.5">
            <p class="text-xs font-semibold text-foreground">{check.label}</p>
            <p class="text-[11px] text-muted-foreground leading-tight">{check.detail}</p>
          </div>
        </div>
      {/each}
    </div>
  </div>

  <!-- Section 3: Interactive Column Mapping -->
  <div class="space-y-3">
    <div class="flex items-center justify-between">
      <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
        <SlidersHorizontal class="size-3.5" />
        Step 3: Column Mapping & Aliases
      </h3>
      <Badge variant="outline" class="text-[10px] font-mono">
        {columnMappings.length} columns recognized
      </Badge>
    </div>

    <div class="overflow-hidden rounded-xl border border-border bg-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-border bg-muted/50 text-muted-foreground">
            <tr>
              <th class="px-3.5 py-2 font-semibold">Your Excel Header</th>
              <th class="px-2 py-2 text-center"></th>
              <th class="px-3.5 py-2 font-semibold">Mapped System Property</th>
              <th class="px-3.5 py-2 font-semibold">Scope</th>
              <th class="px-3.5 py-2 text-right font-semibold">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/60 font-sans">
            {#each columnMappings as col}
              <tr class="hover:bg-muted/20">
                <td class="px-3.5 py-2 font-mono font-medium text-foreground">
                  {col.raw}
                </td>
                <td class="px-2 py-2 text-center text-muted-foreground">
                  <ArrowRight class="inline size-3" />
                </td>
                <td class="px-3.5 py-2 font-mono font-semibold text-primary">
                  {col.mapped}
                </td>
                <td class="px-3.5 py-2">
                  <span class="inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium {col.type === 'Scoring' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300' : 'bg-primary/10 text-primary'}">
                    {col.type}
                  </span>
                </td>
                <td class="px-3.5 py-2 text-right">
                  <span class="text-[11px] {col.status === 'Required' ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'}">
                    {col.status}
                  </span>
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Section 4: Staged Preview Table & Selective Confirm -->
  <div class="space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-1.5">
        <FileCheck class="size-3.5" />
        Step 4: Staged Batch Preview & Validation
      </h3>

      <div class="flex items-center gap-2">
        <button
          type="button"
          onclick={() => (filterOnlyErrors = !filterOnlyErrors)}
          class="rounded-lg border border-border px-2.5 py-1 text-[11px] font-medium text-foreground hover:bg-accent"
        >
          {filterOnlyErrors ? 'Show All Rows' : 'Show Only Invalid (1)'}
        </button>
        <span class="text-xs text-muted-foreground">
          Selected: <strong class="text-foreground">{selectedRowIds.size}</strong> of {sampleRows.filter(r => r.isValid).length} valid
        </span>
      </div>
    </div>

    <!-- Rows Table -->
    <div class="overflow-hidden rounded-xl border border-border bg-card">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-border bg-muted/50 text-muted-foreground">
            <tr>
              <th class="w-8 px-3 py-2 text-center">
                <Checkbox
                  checked={selectedRowIds.size > 0 && selectedRowIds.size === sampleRows.filter(r => r.isValid).length}
                  onCheckedChange={toggleAll}
                />
              </th>
              <th class="px-3 py-2 font-semibold">Ref Code</th>
              <th class="px-3 py-2 font-semibold">Examinee Name</th>
              <th class="px-3 py-2 font-semibold">Strand</th>
              <th class="px-3 py-2 font-semibold">Target Course</th>
              {#if pipelineMode !== 'domain-only'}
                <th class="px-3 py-2 font-semibold">Raw Scores</th>
              {/if}
              <th class="px-3 py-2 text-right font-semibold">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/60">
            {#each displayedRows as row}
              <tr class={row.isValid ? 'hover:bg-muted/20' : 'bg-destructive/5 hover:bg-destructive/10'}>
                <td class="px-3 py-2 text-center">
                  <Checkbox
                    checked={selectedRowIds.has(row.id)}
                    disabled={!row.isValid}
                    onCheckedChange={() => toggleRow(row.id)}
                  />
                </td>
                <td class="px-3 py-2 font-mono font-semibold text-foreground">
                  {row.ref}
                </td>
                <td class="px-3 py-2 font-medium text-foreground">
                  {row.name}
                  <span class="block text-[10px] text-muted-foreground">{row.sheet} · Row {row.row}</span>
                </td>
                <td class="px-3 py-2 text-muted-foreground">{row.strand}</td>
                <td class="px-3 py-2 font-semibold text-foreground">{row.course}</td>
                {#if pipelineMode !== 'domain-only'}
                  <td class="px-3 py-2 font-mono text-[11px]">
                    <span class="text-foreground">M:{row.scores.MATH}</span>
                    <span class="text-muted-foreground">·</span>
                    <span class="text-foreground">S:{row.scores.SCIENCE}</span>
                    <span class="text-muted-foreground">·</span>
                    <span class="text-foreground">E:{row.scores.ENGLISH}</span>
                    <span class="text-muted-foreground">·</span>
                    <span class="text-foreground">A:{row.scores.ABSTRACT}</span>
                  </td>
                {/if}
                <td class="px-3 py-2 text-right">
                  {#if row.isValid}
                    <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
                      <Check class="size-3" /> Valid
                    </span>
                  {:else}
                    <button
                      type="button"
                      onclick={() => (expandedRowId = expandedRowId === row.id ? null : row.id)}
                      class="inline-flex items-center gap-1 font-semibold text-destructive underline"
                    >
                      <XCircle class="size-3" /> Fix Issue
                    </button>
                  {/if}
                </td>
              </tr>
              {#if !row.isValid && expandedRowId === row.id}
                <tr class="bg-destructive/10">
                  <td colspan={pipelineMode !== 'domain-only' ? 7 : 6} class="px-4 py-2 text-destructive">
                    <div class="flex items-center justify-between">
                      <span class="font-medium text-[11px]">{row.error}</span>
                      <span class="text-[10px] uppercase font-bold tracking-wider">Blocked from import</span>
                    </div>
                  </td>
                </tr>
              {/if}
            {/each}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Section 5: Simulation & Confirmation Trigger -->
  <div class="flex flex-col gap-3 pt-3 border-t border-border sm:flex-row sm:items-center sm:justify-between">
    <div class="text-xs text-muted-foreground">
      Ready to commit <strong>{selectedRowIds.size}</strong> examinee record{selectedRowIds.size === 1 ? '' : 's'}.
      <span class="block text-[11px] text-muted-foreground/80">Idempotency guaranteed via deterministic batch hashing.</span>
    </div>

    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        class="h-9 gap-1.5"
        onclick={() => {
          selectedRowIds = new Set([1, 2, 4]);
          simulatedSuccess = false;
        }}
      >
        <RefreshCw class="size-3.5" />
        Reset Demo
      </Button>

      <Button
        size="sm"
        class="h-9 gap-2 font-semibold shadow-md shadow-primary/20"
        disabled={simulating || selectedRowIds.size === 0}
        onclick={runSimulation}
      >
        {#if simulating}
          <RefreshCw class="size-3.5 animate-spin" />
          Committing Single-Pass Transaction...
        {:else}
          <Upload class="size-3.5" />
          Execute Single-Pass Import ({selectedRowIds.size})
        {/if}
      </Button>
    </div>
  </div>

  <!-- Success Notification Box -->
  {#if simulatedSuccess}
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300" use:reveal>
      <div class="flex items-start gap-3">
        <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
        <div class="space-y-1 text-xs">
          <p class="font-bold text-emerald-950 dark:text-emerald-100">
            Single-Pass Ingestion Successful (Batch #ING-2026-X9)
          </p>
          <p class="leading-relaxed">
            {selectedRowIds.size} examinees created and bound to Academic Cycle 2025-2026.
            Exam assignments, direct assessment session records, and encrypted score records committed atomically with zero manual page trips.
          </p>
        </div>
      </div>
    </div>
  {/if}
</div>
