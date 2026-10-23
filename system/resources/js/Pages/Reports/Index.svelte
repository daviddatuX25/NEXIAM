<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import BentoCard from '@/Components/kit/BentoCard.svelte';
  import MaterialDocumentPreview from '@/Components/kit/MaterialDocumentPreview.svelte';
  import DoughnutChart from '@/Components/kit/DoughnutChart.svelte';
  import LineChart from '@/Components/kit/LineChart.svelte';
  import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
  import DocumentPrintManagerModal from '@/Components/DocumentTemplate/DocumentPrintManagerModal.svelte';
  import PrintQueueMonitorModal from '@/Components/DocumentTemplate/PrintQueueMonitorModal.svelte';
  import { getAllPrintJobs } from '@/lib/printQueue.js';
  import { onMount, onDestroy } from 'svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Link, router } from '@inertiajs/svelte';
  import { Download, Printer, Layers } from 'lucide-svelte';
  let { report } = $props();

  const breadcrumbs = [{ label: 'Reports & Analytics' }];

  let selectedCycleId = $state(report.selected_cycle_id ? String(report.selected_cycle_id) : '');
  let activeTab = $state(
    report.can_registrar
      ? 'registrar'
      : report.can_guidance
        ? 'guidance'
        : report.can_admin
          ? 'admin'
          : 'program'
  );
  let selectedDept = $state('ALL');
  let selectedProgram = $state('ALL');
  const initialPrograms = report?.analytics?.academic_scope?.programs ?? [];
  const initialPlacedSum = initialPrograms.reduce((sum, p) => sum + (p.course_placed ?? 0), 0);
  const initialFirstChoiceSum = initialPrograms.reduce((sum, p) => sum + (p.first_choice_demand ?? 0), 0);
  let metricLens = $state(initialPlacedSum === 0 && initialFirstChoiceSum > 0 ? 'first' : 'placed');
  let timeGranularity = $state('daily');
  let printModalOpen = $state(false);
  let selectedPrintTemplate = $state(null);
  let printQueueOpen = $state(false);
  let printQueueCount = $state(0);

  async function updateQueueCount() {
    try {
      const jobs = await getAllPrintJobs();
      printQueueCount = jobs.length;
    } catch {
      printQueueCount = 0;
    }
  }

  onMount(() => {
    updateQueueCount();
    if (typeof window !== 'undefined') {
      window.addEventListener('nexiam-print-queue-updated', updateQueueCount);
    }
  });

  onDestroy(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('nexiam-print-queue-updated', updateQueueCount);
    }
  });
  function openPrintModal(template) {
    if (!template) return;
    selectedPrintTemplate = template;
    printModalOpen = true;
  }

  const currentTabReports = $derived.by(() => {
    if (activeTab === 'registrar') return report.registrar_reports ?? [];
    if (activeTab === 'guidance') return report.guidance_reports ?? [];
    if (activeTab === 'admin') return report.admin_reports ?? [];
    if (activeTab === 'program') return report.program_reports ?? [];
    return [];
  });
  const analytics = $derived(report?.analytics ?? null);
  const academicScope = $derived(analytics?.academic_scope ?? null);
  const deptOptions = $derived(academicScope?.departments ?? []);
  const canFilterDept = $derived(Boolean(academicScope?.can_filter_department) && deptOptions.length > 1);
  const effectiveDept = $derived(!canFilterDept && deptOptions.length === 1 ? deptOptions[0].code : selectedDept);
  const deptFilteredPrograms = $derived.by(() => {
    const all = academicScope?.programs ?? [];
    if (effectiveDept === 'ALL') return all;
    return all.filter((program) => program.department_code === effectiveDept);
  });
  const canFilterProgram = $derived(Boolean(academicScope?.can_filter_program) && deptFilteredPrograms.length > 1);
  const effectiveProgram = $derived(!canFilterProgram && deptFilteredPrograms.length === 1 ? deptFilteredPrograms[0].code : selectedProgram);
  const scopedPrograms = $derived.by(() => {
    if (effectiveProgram === 'ALL') return deptFilteredPrograms;
    return deptFilteredPrograms.filter((program) => program.code === effectiveProgram);
  });
  const focusScopeLabel = $derived.by(() => {
    if (!canFilterDept && !canFilterProgram && deptFilteredPrograms.length === 1) {
      return `${deptFilteredPrograms[0].code} — ${deptFilteredPrograms[0].name}`;
    }
    if (!canFilterDept && deptOptions.length === 1) {
      return effectiveProgram === 'ALL'
        ? `${deptOptions[0].code} (${deptOptions[0].name})`
        : `${deptOptions[0].code} · ${effectiveProgram}`;
    }
    if (effectiveDept === 'ALL') {
      return effectiveProgram === 'ALL' ? 'All Colleges' : effectiveProgram;
    }
    return effectiveProgram === 'ALL' ? effectiveDept : `${effectiveDept} · ${effectiveProgram}`;
  });
  const activeSeries = $derived(analytics?.time_series?.[timeGranularity] ?? { labels: [], values: [] });
  const stageFunnel = $derived(analytics?.stage_funnel ?? []);
  const scopedShareData = $derived.by(() => {
    const items = effectiveDept === 'ALL' && effectiveProgram === 'ALL' && deptOptions.length > 1
      ? deptOptions
      : scopedPrograms.slice(0, 8);
    const metricFor = (item) => {
      if (metricLens === 'placed') return item.course_placed ?? 0;
      if (metricLens === 'first') return item.first_choice_demand ?? 0;
      if (metricLens === 'all_choices') return item.total_choice_demand ?? item.first_choice_demand ?? 0;
      return Math.abs(item.net_shift ?? 0);
    };

    return items.map((item) => ({
      label: item.code,
      value: metricFor(item),
      color: item.color,
    }));
  });
  const scopeTotals = $derived.by(() => {
    const quota = scopedPrograms.reduce((total, program) => total + (program.planned_quota ?? 0), 0);
    const firstChoice = scopedPrograms.reduce((total, program) => total + (program.first_choice_demand ?? 0), 0);
    const allChoices = scopedPrograms.reduce((total, program) => total + (program.total_choice_demand ?? program.first_choice_demand ?? 0), 0);
    const placed = scopedPrograms.reduce((total, program) => total + (program.course_placed ?? 0), 0);

    return {
      quota,
      firstChoice,
      allChoices,
      placed,
      fillPercent: quota > 0 ? Math.round((placed / quota) * 100) : 0,
    };
  });

  function selectDepartment(code) {
    selectedDept = code;
    selectedProgram = 'ALL';
  }

  function onCycleChange(event) {
    const value = event.target.value;
    selectedCycleId = value;
    router.get('/reports', { cycle_id: value }, { preserveState: true, preserveScroll: true });
  }

  function downloadReport(type, format = 'csv') {
    const params = new URLSearchParams();
    if (selectedCycleId) params.set('cycle_id', selectedCycleId);
    if (effectiveProgram && effectiveProgram !== 'ALL') params.set('program_code', effectiveProgram);
    if (effectiveDept && effectiveDept !== 'ALL') params.set('department_code', effectiveDept);
    params.set('format', format);
    window.location.href = `/reports/export/${type}?${params.toString()}`;
  }
</script>

<svelte:head>
  <title>Reports & Official Exports &bull; NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-5 lg:p-6">
    <div class="flex flex-col justify-between gap-4 border-b border-border/60 pb-4 sm:flex-row sm:items-center">
      <div>
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Reports & Analytics Hub</h1>
          {#if academicScope && !canFilterDept}
            <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 font-mono text-xs font-semibold text-primary">
              {canFilterProgram ? `My Department: ${focusScopeLabel}` : `My Program: ${focusScopeLabel}`}
            </span>
          {/if}
        </div>
        <p class="mt-1 text-xs text-muted-foreground">Official institutional census, demographic breakdowns, and certified export files.</p>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        <label class="font-mono text-xs font-bold text-muted-foreground">Cycle:</label>
        <select
          value={selectedCycleId}
          onchange={onCycleChange}
          class="rounded-xl border border-border bg-card px-3 py-1.5 font-mono text-xs text-foreground focus:ring-2 focus:ring-primary/20"
        >
          {#each report.cycles as cycle}
            <option value={String(cycle.id)}>
              {cycle.name} {cycle.is_active ? '(Active)' : ''}
            </option>
          {/each}
        </select>

        <Button
          variant="outline"
          size="sm"
          class="gap-1.5 h-8 font-semibold text-xs rounded-xl"
          onclick={() => (printQueueOpen = true)}
        >
          <Layers class="size-3.5 text-primary" />
          <span>Print Queue Monitor</span>
          {#if printQueueCount > 0}
            <Badge variant="default" class="size-4 p-0 text-[10px] flex items-center justify-center rounded-full ml-0.5">
              {printQueueCount}
            </Badge>
          {/if}
        </Button>
      </div>
    </div>

    {#if analytics}
      <section class="space-y-4" data-testid="analytics-velocity-and-funnel">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
          <BentoCard class="space-y-4 p-4 sm:p-5 lg:col-span-6">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
              <div>
                <h2 class="text-sm font-bold text-foreground">Application Submission Velocity</h2>
                <p class="font-mono text-[10px] text-muted-foreground">
                  {timeGranularity === 'daily' ? 'Last 14 days' : timeGranularity === 'weekly' ? 'Last 8 weeks' : 'Last 6 months'}
                </p>
              </div>
              <div class="flex flex-wrap items-center gap-1.5 font-mono text-xs" aria-label="Time Axis">
                <span class="mr-1 text-[10px] font-bold uppercase text-muted-foreground">Time Axis:</span>
                {#each [
                  { key: 'daily', label: 'Daily (14d)' },
                  { key: 'weekly', label: 'Weekly (8w)' },
                  { key: 'monthly', label: 'Monthly (6m)' },
                ] as granularity (granularity.key)}
                  <Button
                    size="sm"
                    variant={timeGranularity === granularity.key ? 'default' : 'outline'}
                    class="h-7 px-2.5 font-mono text-[10px]"
                    onclick={() => (timeGranularity = granularity.key)}
                  >
                    {granularity.label}
                  </Button>
                {/each}
              </div>
            </div>
            <LineChart
              labels={activeSeries.labels ?? []}
              values={activeSeries.values ?? []}
              height={185}
              color="#0ea5e9"
            />
          </BentoCard>

          <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-6">
            <div>
              <h2 class="text-sm font-bold text-foreground">7-Stage Lifecycle Conversion Funnel</h2>
              <p class="font-mono text-[10px] text-muted-foreground">Cycle Setup &rarr; Applications &rarr; Scheduling &rarr; Exam &rarr; Scoring &rarr; Release &rarr; Enrollment Clearance</p>
            </div>
            <div class="space-y-2.5 pt-1">
              {#each stageFunnel as stage (stage.step)}
                <div class="space-y-1">
                  <div class="flex items-center justify-between gap-2 font-mono text-[10px] sm:text-xs">
                    <span class="font-semibold text-foreground">{stage.label}</span>
                    <span class="shrink-0 text-muted-foreground"><strong class="text-foreground">{stage.count}</strong> ({stage.unit}) &bull; {stage.percent}%</span>
                  </div>
                  <div class="relative h-2.5 w-full overflow-hidden rounded-full border border-border/70 bg-muted/90">
                    <div
                      class="h-full rounded-full transition-all duration-300"
                      style="width: {Math.max(4, stage.percent)}%; background-color: {stage.color}"
                    ></div>
                  </div>
                </div>
              {/each}
            </div>
          </BentoCard>
        </div>
      </section>

      <section class="space-y-4" data-testid="program-quota-placement-matrix">
        <BentoCard class="space-y-4 p-4 sm:p-5">
          <div class="space-y-3 border-b border-border/60 pb-4">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="text-sm font-bold text-foreground">Program Quota, Choice Preferences &amp; Course Placement Matrix</h2>
                <p class="font-mono text-[10px] text-muted-foreground">
                  Showing {scopedPrograms.length} program{scopedPrograms.length === 1 ? '' : 's'} ({focusScopeLabel}) &bull; Calibrated 0%&ndash;100% quota meters
                </p>
              </div>
              <div class="flex items-center gap-3 font-mono text-[10px]">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Placed</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full border border-sky-500 bg-sky-500/40"></span> 1st-Choice</span>
              </div>
            </div>

            {#if academicScope}
              {#if canFilterDept}
                <div class="flex flex-wrap items-center gap-1.5" aria-label="Filter by college">
                  <span class="mr-1 font-mono text-[10px] font-bold uppercase text-muted-foreground">College:</span>
                  <Button size="sm" variant={selectedDept === 'ALL' ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment('ALL')}>
                    All Colleges ({deptOptions.length})
                  </Button>
                  {#each deptOptions as dept (dept.code)}
                    <Button size="sm" variant={selectedDept === dept.code ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment(dept.code)}>
                      {dept.code} ({dept.program_count})
                    </Button>
                  {/each}
                </div>
              {/if}

              {#if canFilterProgram}
                <div class="flex flex-wrap items-center gap-1.5 {canFilterDept ? 'border-t border-border/50 pt-2' : ''}" aria-label="Filter by program">
                  <span class="mr-1 font-mono text-[10px] font-bold uppercase text-muted-foreground">Program:</span>
                  <Button size="sm" variant={selectedProgram === 'ALL' ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => (selectedProgram = 'ALL')}>
                    All {effectiveDept !== 'ALL' ? `${effectiveDept} ` : ''}Programs ({deptFilteredPrograms.length})
                  </Button>
                  {#each deptFilteredPrograms as program (program.code)}
                    <Button size="sm" variant={selectedProgram === program.code ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => (selectedProgram = program.code)}>
                      {program.code}
                    </Button>
                  {/each}
                </div>
              {/if}
            {/if}

            <div class="flex flex-wrap items-center gap-1.5 border-t border-border/50 pt-2" aria-label="Program Lens">
              <span class="mr-1 font-mono text-[10px] font-bold uppercase text-muted-foreground">Program Lens:</span>
              {#each [
                { key: 'placed', label: '1. Course Placed & Cleared vs. Quota' },
                { key: 'first', label: '2. 1st-Choice Demand vs. Quota' },
                { key: 'all_choices', label: '3. All Choices (1st–3rd) Breakdown' },
                { key: 'shift', label: '4. Choice → Placement Redirection Shift' },
              ] as lens (lens.key)}
                <Button
                  size="sm"
                  variant={metricLens === lens.key ? 'default' : 'outline'}
                  class="h-7 px-2.5 font-mono text-[10px]"
                  onclick={() => (metricLens = lens.key)}
                >
                  {lens.label}
                </Button>
              {/each}
            </div>
          </div>

          <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
            <div class="space-y-3 lg:col-span-4">
              <div>
                <h3 class="text-xs font-bold text-foreground">Scoped Share</h3>
                <p class="font-mono text-[10px] text-muted-foreground">
                  {metricLens === 'placed'
                    ? 'Course placed and cleared'
                    : metricLens === 'first'
                      ? '1st-choice demand'
                      : metricLens === 'all_choices'
                        ? 'All 1st–3rd choices'
                        : 'Absolute redirection shift'}
                  &middot; {focusScopeLabel}
                </p>
              </div>
              {#if scopedShareData.length > 0}
                <DoughnutChart data={scopedShareData} height={220} />
              {:else}
                <p class="rounded-lg bg-muted/30 py-8 text-center text-xs text-muted-foreground">No program share data in this scope.</p>
              {/if}
              <div class="grid grid-cols-2 gap-2 border-t border-border/60 pt-2 font-mono text-center">
                <div class="rounded-lg bg-muted/35 p-2">
                  <div class="text-[9px] uppercase text-muted-foreground">1st-Choice</div>
                  <div class="text-sm font-bold tabular-nums text-foreground">{scopeTotals.firstChoice.toLocaleString()}</div>
                </div>
                <div class="rounded-lg bg-muted/35 p-2">
                  <div class="text-[9px] uppercase text-muted-foreground">All Choices</div>
                  <div class="text-sm font-bold tabular-nums text-sky-600 dark:text-sky-400">{scopeTotals.allChoices.toLocaleString()}</div>
                </div>
                <div class="rounded-lg bg-muted/35 p-2">
                  <div class="text-[9px] uppercase text-muted-foreground">Placed</div>
                  <div class="text-sm font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{scopeTotals.placed.toLocaleString()}</div>
                </div>
                <div class="rounded-lg bg-muted/35 p-2">
                  <div class="text-[9px] uppercase text-muted-foreground">Quota Fill</div>
                  <div class="text-sm font-bold tabular-nums text-primary">{scopeTotals.fillPercent}%</div>
                </div>
              </div>
            </div>

            <div class="space-y-3 lg:col-span-8">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <h3 class="text-xs font-bold text-foreground">Calibrated 0%&ndash;100% Program Quota Meters</h3>
                  <p class="font-mono text-[10px] text-muted-foreground">
                    {metricLens === 'placed'
                      ? 'Course Placed vs. 1st-Choice, against quota'
                      : metricLens === 'first'
                        ? '1st-Choice demand vs. quota'
                        : metricLens === 'all_choices'
                          ? 'All 1st–3rd choices vs. quota'
                          : 'Placement redirection shift vs. quota'}
                  </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 font-mono text-[10px]">
                  <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span>{metricLens === 'placed' ? 'Placed' : metricLens === 'first' ? '1st-Choice' : metricLens === 'all_choices' ? 'All Choices' : 'Placement Shift'}</span>
                  <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full border border-sky-500 bg-sky-500/40"></span>{metricLens === 'placed' ? '1st-Choice' : metricLens === 'shift' ? 'Placed' : 'All Choices'}</span>
                </div>
              </div>

              <div class="max-h-[460px] space-y-2.5 overflow-y-auto pr-1">
                {#if scopedPrograms.length > 0}
                  {#each scopedPrograms as program (program.code)}
                    {@const rawPrimaryPercent = metricLens === 'placed'
                      ? (program.utilization_percent ?? 0)
                      : metricLens === 'first'
                        ? (program.demand_percent ?? 0)
                        : metricLens === 'all_choices'
                          ? (program.total_choice_percent ?? 0)
                          : Math.round((Math.abs(program.net_shift ?? 0) / Math.max(1, program.planned_quota ?? 0)) * 1000) / 10}
                    {@const primaryPercent = Math.min(100, Math.max(0, rawPrimaryPercent))}
                    {@const rawSecondaryPercent = metricLens === 'placed'
                      ? (program.demand_percent ?? 0)
                      : metricLens === 'shift'
                        ? (program.utilization_percent ?? 0)
                        : (program.total_choice_percent ?? program.demand_percent ?? 0)}
                    {@const secondaryPercent = Math.min(100, Math.max(0, rawSecondaryPercent))}
                    <div class="space-y-2 rounded-lg border border-border/70 bg-muted/25 p-3">
                      <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-1.5">
                          <span class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{program.code}</span>
                          <span class="truncate text-xs font-semibold text-foreground">{program.name}</span>
                          <Badge variant="outline" class="shrink-0 font-mono text-[9px]">{program.department_code}</Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 font-mono text-[10px]">
                          <span class="text-emerald-600 dark:text-emerald-400">Placed: {program.course_placed} / {program.planned_quota}</span>
                          <span class="text-muted-foreground">1st-Choice: {program.first_choice_demand}</span>
                          <span class="rounded-md border border-border bg-card px-1.5 py-0.5 font-bold text-foreground">
                            {metricLens === 'shift'
                              ? `${(program.net_shift ?? 0) > 0 ? '+' : ''}${program.net_shift ?? 0} shift`
                              : `${primaryPercent}%`}
                          </span>
                        </div>
                      </div>

                      <div class="space-y-1">
                        <div class="relative h-3 w-full overflow-hidden rounded-full border border-border/80 bg-muted/90">
                          <div class="absolute inset-y-0 left-1/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                          <div class="absolute inset-y-0 left-2/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                          <div class="absolute inset-y-0 left-3/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                          {#if secondaryPercent > 0}
                            <div class="absolute inset-y-0 left-0 rounded-full border-r border-sky-500 bg-sky-500/35 transition-all duration-300" style="width: {secondaryPercent}%"></div>
                          {/if}
                          <div
                            class="relative h-full rounded-full transition-all duration-300 {primaryPercent > 0 ? 'bg-gradient-to-r from-primary to-emerald-500' : 'bg-muted-foreground/35'}"
                            style="width: {primaryPercent}%"
                          ></div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 font-mono text-[9px] text-muted-foreground">
                          <span>
                            {program.open_slots} of {program.planned_quota} quota slots open &bull; Total 1st–3rd preferences: {program.total_choice_demand}
                          </span>
                          <span>0% &bull; 25% &bull; 50% &bull; 75% &bull; 100%</span>
                        </div>
                      </div>
                    </div>
                  {/each}
                {:else}
                  <p class="rounded-lg bg-muted/30 py-8 text-center text-xs text-muted-foreground">No programs are available in this scope.</p>
                {/if}
              </div>
            </div>
          </div>
        </BentoCard>
      </section>
    {/if}

    <section class="space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-bold text-foreground">Pipeline &amp; Demographic Distributions</h2>
      </div>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <BentoCard class="space-y-3 p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-foreground">Pipeline Breakdown</h3>
          </div>
          <DoughnutChart data={report.pipeline} height={185} />
        </BentoCard>

        <BentoCard class="space-y-3 p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-foreground">Demographics by Sex</h3>
          </div>
          <DoughnutChart data={report.demographics.sex} height={185} />
        </BentoCard>

        <BentoCard class="space-y-3 p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-foreground">Age Distribution</h3>
          </div>
          <DoughnutChart data={report.demographics.age} height={185} />
        </BentoCard>

        <BentoCard class="space-y-3 p-4">
          <div class="flex items-center justify-between gap-2">
            <h3 class="text-xs font-bold text-foreground">SHS Strand Mix</h3>
          </div>
          <DoughnutChart data={report.demographics.strands} height={185} />
        </BentoCard>
      </div>
    </section>

    <!-- Section 4: Registrar and Guidance export catalogs remain below. -->

    <!-- Report Export Catalogs -->
    <section class="space-y-4">
      <!-- Domain Tabs -->
      <div class="flex flex-wrap items-center gap-2 border-b border-border/60 pb-3">
        {#if report.can_registrar}
          <button
            onclick={() => (activeTab = 'registrar')}
            class={`px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ${
              activeTab === 'registrar'
                ? 'bg-primary text-primary-foreground shadow-sm'
                : 'bg-muted text-muted-foreground hover:text-foreground'
            }`}
          >
            Registrar Reports ({report.registrar_reports?.length ?? 0})
          </button>
        {/if}

        {#if report.can_guidance}
          <button
            onclick={() => (activeTab = 'guidance')}
            class={`px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ${
              activeTab === 'guidance'
                ? 'bg-primary text-primary-foreground shadow-sm'
                : 'bg-muted text-muted-foreground hover:text-foreground'
            }`}
          >
            Guidance Testing Reports ({report.guidance_reports?.length ?? 0})
          </button>
        {/if}

        {#if report.can_admin}
          <button
            onclick={() => (activeTab = 'admin')}
            class={`px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ${
              activeTab === 'admin'
                ? 'bg-primary text-primary-foreground shadow-sm'
                : 'bg-muted text-muted-foreground hover:text-foreground'
            }`}
          >
            Institutional Admin Reports ({report.admin_reports?.length ?? 0})
          </button>
        {/if}

        {#if report.can_program_head}
          <button
            onclick={() => (activeTab = 'program')}
            class={`px-3.5 py-1.5 rounded-xl font-bold text-xs transition-all ${
              activeTab === 'program'
                ? 'bg-primary text-primary-foreground shadow-sm'
                : 'bg-muted text-muted-foreground hover:text-foreground'
            }`}
          >
            Academic Leadership Reports ({report.program_reports?.length ?? 0})
          </button>
        {/if}
      </div>

      <!-- Report Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        {#each currentTabReports as r (r.type)}
          <BentoCard class="p-5 sm:p-6 space-y-4 flex flex-col justify-between text-xs hover:border-primary/40 transition-all">
            <div class="flex items-start gap-4">
              {#if r.surface_key}
                <MaterialDocumentPreview
                  src={r.active_template?.thumbnail_url}
                  kind="report"
                  mode={r.active_template?.mode?.toUpperCase() ?? 'HTML'}
                  title={r.active_template?.name ?? r.title}
                  builder_json={r.active_template?.builder_json}
                  orientation={r.active_template?.orientation ?? 'portrait'}
                  alt={`${r.title} template preview`}
                />
              {/if}
              <div class="min-w-0 space-y-1.5 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <h4 class="text-base font-bold text-foreground tracking-tight">{r.title}</h4>
                  {#if r.active_template}
                    <span class="rounded-md bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">{r.active_template.name}</span>
                  {/if}
                </div>
                <p class="text-muted-foreground leading-relaxed text-[11px]">{r.description}</p>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t border-border/60 pt-3">
              <button
                onclick={() => downloadReport(r.type, 'docx')}
                class="rounded-xl border border-border bg-card px-2.5 py-1.5 font-bold text-foreground shadow-xs hover:bg-muted transition-colors flex items-center gap-1"
                title="Download DOCX Word Document"
              >
                <Download class="size-3.5" /> DOCX
              </button>
              <button
                onclick={() => downloadReport(r.type, 'pdf')}
                class="rounded-xl bg-primary px-3 py-1.5 font-bold text-primary-foreground shadow-xs hover:opacity-90 transition-opacity flex items-center gap-1"
                title="Download Compiled PDF"
              >
                <Download class="size-3.5" /> PDF
              </button>
              <button
                onclick={() => downloadReport(r.type, 'csv')}
                class="rounded-xl border border-border bg-card px-2.5 py-1.5 font-bold text-foreground shadow-xs hover:bg-muted transition-colors flex items-center gap-1"
                title="Download CSV Spreadsheet"
              >
                <Download class="size-3.5" /> CSV
              </button>
              {#if r.active_template}
                <button
                  onclick={() => openPrintModal(r.active_template)}
                  class="rounded-xl border border-border bg-card px-2.5 py-1.5 font-bold text-foreground shadow-xs hover:bg-muted transition-colors flex items-center gap-1"
                  title="Open Print Manager"
                >
                  <Printer class="size-3.5" /> Print
                </button>
              {/if}
              {#if r.can_configure_template && r.surface_key}
                <Link
                  href={`/setup/report-templates/${r.surface_key}`}
                  class="ml-auto rounded-xl border border-border px-2.5 py-1.5 font-semibold text-foreground hover:bg-muted transition-colors"
                >
                  Configure Template
                </Link>
              {/if}
            </div>
          </BentoCard>
        {/each}
      </div>
    </section>

    {#if selectedPrintTemplate}
      <DocumentPrintManagerModal
        bind:open={printModalOpen}
        template={selectedPrintTemplate}
        cycleId={selectedCycleId ? Number(selectedCycleId) : null}
        programCode={effectiveProgram !== 'ALL' ? effectiveProgram : null}
        departmentCode={effectiveDept !== 'ALL' ? effectiveDept : null}
      />
    {/if}
    <PrintQueueMonitorModal bind:open={printQueueOpen} />

  </div>
</StaffLayout>
