<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { usePage } from '@inertiajs/svelte';
  import * as Select from '@/Components/ui/select';
  import { Button } from '@/Components/ui/button';
  import { router } from '@inertiajs/svelte';
  import {
    FileText, BarChart3, GraduationCap, Users, XCircle,
    Target, Calendar, MessageSquare, Send, Download, Loader2, AlertCircle,
    TrendingUp, ClipboardCheck, Clock,
    FileDown, PieChart,
  } from 'lucide-svelte';
  import DoughnutChart from '@/Components/DoughnutChart.svelte';
  import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';
  import { onMount, onDestroy } from 'svelte';

  Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

  let { academicYears = [], activeAcademicYearId, reports = [], counts = {}, summaryData = {} } = $props();

  const page = usePage();
  const roles = $derived(($page.props.auth?.user?.roles ?? []).map(r => r.name));
  const isSuperAdmin = $derived(roles.includes('super_admin'));
  const isRegistrar = $derived(roles.includes('registrar_administrator'));
  const isTestAdmin = $derived(roles.includes('test_administrator'));

  const breadcrumbs = [{ label: 'Reports' }];

  const iconMap = {
    'file-text': FileText, 'bar-chart-3': BarChart3, 'graduation-cap': GraduationCap,
    'users': Users, 'x-circle': XCircle, 'target': Target,
    'calendar': Calendar, 'message-square': MessageSquare, 'send': Send,
  };

  let selectedAyId = $state(String(activeAcademicYearId ?? ''));
  let exportingType = $state(null);
  let exportError = $state(null);

  // Collapsible states


  function canSeeReport(report) {
    if (isSuperAdmin) return true;
    if (report.domain === 'registrar' && isRegistrar) return true;
    if (report.domain === 'guidance' && isTestAdmin) return true;
    return false;
  }

  const visibleReports = $derived(reports.filter(canSeeReport));
  const registrarReports = $derived(visibleReports.filter(r => r.domain === 'registrar'));
  const guidanceReports = $derived(visibleReports.filter(r => r.domain === 'guidance'));

  function onAcademicYearChange(value) {
    if (!value) return;
    selectedAyId = value;
    router.get('/admin/reports', { academic_year_id: value }, { preserveState: true, preserveScroll: true });
  }

  async function doExport(type, format = 'xlsx') {
    exportError = null;
    exportingType = `${type}-${format}`;
    try {
      const params = new URLSearchParams({ academic_year_id: selectedAyId });
      if (format === 'pdf') params.append('format', 'pdf');
      const res = await fetch(`/admin/reports/export/${type}?${params.toString()}`, { credentials: 'same-origin' });
      if (!res.ok) {
        exportError = res.status === 403 ? 'You do not have permission to access this report.' : `Export failed (${res.status}). Please try again.`;
        return;
      }
      const blob = await res.blob();
      const disposition = res.headers.get('Content-Disposition') ?? '';
      const filenameMatch = disposition.match(/filename="(.+?)"/);
      const ext = format === 'pdf' ? 'pdf' : 'xlsx';
      const filename = filenameMatch ? filenameMatch[1] : `${type}.${ext}`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      exportError = 'Network error. Please check your connection and try again.';
    } finally {
      exportingType = null;
    }
  }

  const selectedAyLabel = $derived(
    academicYears.find(ay => String(ay.id) === selectedAyId)?.label ?? 'Select academic year'
  );

  // Pipeline hex colors for doughnut chart
  const pipelineHexColors = {
    Pending: '#94a3b8', Accepted: '#3b82f6',
    'Draft Scheduled': '#818cf8', Scheduled: '#6366f1',
    Printed: '#8b5cf6', Attended: '#a78bfa',
    Submitted: '#7c3aed', Scored: '#c084fc',
    Graded: '#a855f7', Released: '#22c55e', Dismissed: '#ef4444',
  };

  // Transform pipeline data for DoughnutChart
  const pipelineDoughnutData = $derived(
    (summaryData?.pipeline ?? [])
      .filter(item => item.count > 0)
      .map(item => ({
        label: item.status,
        value: item.count,
        color: pipelineHexColors[item.status] ?? '#94a3b8',
      }))
  );

  // Chart.js for demographics donut
  let sexChartEl = $state(null);
  let ageChartEl = $state(null);
  let sexChart = null;
  let ageChart = null;

  const sexColors = ['#3b82f6', '#ec4899', '#94a3b8'];
  const ageColors = ['#6366f1', '#8b5cf6', '#a78bfa', '#c4b5fd', '#ddd6fe', '#ede9fe'];

  /** High-contrast text color for Chart.js canvas */
  function getTextColor() {
    if (typeof document === 'undefined') return '#334155';
    return document.documentElement.classList.contains('dark') ? '#e2e8f0' : '#334155';
  }

  function buildCharts() {
    if (sexChart) sexChart.destroy();
    if (ageChart) ageChart.destroy();

    const bySex = summaryData?.demographics?.by_sex ?? [];
    const byAge = summaryData?.demographics?.by_age ?? [];
    const tc = getTextColor();

    // Set global default text color for Chart.js canvas
    Chart.defaults.color = tc;

    if (sexChartEl && bySex.length > 0) {
      sexChart = new Chart(sexChartEl, {
        type: 'doughnut',
        data: {
          labels: bySex.map(s => s.label),
          datasets: [{ data: bySex.map(s => s.count), backgroundColor: sexColors.slice(0, bySex.length), borderWidth: 0, borderRadius: 4 }],
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12 }, color: tc } } } },
      });
    }

    if (ageChartEl && byAge.length > 0) {
      ageChart = new Chart(ageChartEl, {
        type: 'doughnut',
        data: {
          labels: byAge.map(a => a.label),
          datasets: [{ data: byAge.map(a => a.count), backgroundColor: ageColors.slice(0, byAge.length), borderWidth: 0, borderRadius: 4 }],
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12 }, color: tc } } } },
      });
    }
  }

  $effect(() => {
    // Rebuild charts whenever summaryData changes
    if (summaryData?.demographics) {
      setTimeout(buildCharts, 50);
    }
  });

  // Watch for theme toggle to rebuild demographic charts
  let themeObserver = null;
  onMount(() => {
    themeObserver = new MutationObserver(() => {
      if (summaryData?.demographics) setTimeout(buildCharts, 50);
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  });

  onDestroy(() => {
    if (sexChart) sexChart.destroy();
    if (ageChart) ageChart.destroy();
    if (themeObserver) themeObserver.disconnect();
  });

</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-8 min-w-0">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <p class="text-sm text-muted-foreground">Reports & analytics for the selected academic year.</p>
      </div>
      <div class="flex items-center gap-3">
        <Select.Root type="single" value={selectedAyId} onValueChange={onAcademicYearChange}>
          <Select.Trigger class="w-[260px] min-h-[44px]">
            {#if selectedAyId}
              {selectedAyLabel}
            {:else}
              <span class="text-muted-foreground">Select academic year</span>
            {/if}
          </Select.Trigger>
          <Select.Content>
            {#each academicYears as ay}
              <Select.Item value={String(ay.id)} label={ay.label}>
                {ay.label}
                {#if ay.is_active}
                  <span class="ml-2 text-xs text-primary font-semibold">(Active)</span>
                {/if}
              </Select.Item>
            {/each}
          </Select.Content>
        </Select.Root>
      </div>
    </div>

    <!-- Error banner -->
    {#if exportError}
      <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive flex items-center gap-2">
        <AlertCircle class="h-4 w-4 shrink-0" />
        {exportError}
        <button type="button" class="ml-auto text-destructive/70 hover:text-destructive" onclick={() => exportError = null}>✕</button>
      </div>
    {/if}

    <!-- ═══ TIER 1: KPI Cards ═══ -->
    {#if counts.total_applications !== undefined}
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Total Apps</p>
          <p class="text-2xl font-bold text-foreground mt-1">{counts.total_applications?.toLocaleString() ?? 0}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Exam Sessions</p>
          <p class="text-2xl font-bold text-foreground mt-1">{counts.exam_sessions?.toLocaleString() ?? 0}</p>
        </div>

        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Pending Review</p>
          <p class="text-2xl font-bold text-violet-600 dark:text-violet-400 mt-1">{counts.pending_review_count?.toLocaleString() ?? 0}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Released</p>
          <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">{counts.released_count?.toLocaleString() ?? 0}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-4">
          <p class="text-xs text-muted-foreground font-medium uppercase tracking-wider">Dismissed</p>
          <p class="text-2xl font-bold text-rose-600 dark:text-rose-400 mt-1">{counts.dismissed_count?.toLocaleString() ?? 0}</p>
        </div>
      </div>
    {/if}

    <!-- ═══ TIER 2: Inline Visual Summaries ═══ -->
    {#if summaryData?.pipeline}
      <div class="space-y-4">
        <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider flex items-center gap-2">
          <PieChart class="h-4 w-4" /> Analytics
        </h2>

        <!-- Pipeline Breakdown (Doughnut) -->
        <div class="rounded-xl border border-border bg-card overflow-hidden">
          <div class="p-4">
            <div class="flex items-center gap-2">
              <PieChart class="h-4 w-4 text-primary" />
              <h3 class="font-semibold text-sm text-foreground">Pipeline Breakdown</h3>
              <span class="text-xs text-muted-foreground ml-1">Status distribution across all stages</span>
            </div>
          </div>
          <div class="px-4 pb-4">
            <DoughnutChart
              data={pipelineDoughnutData}
              height={280}
            />
          </div>
        </div>

        <!-- Course Demand vs Recommendation -->
        {#if summaryData.course_demand?.length > 0}
          <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="p-4">
              <div class="flex items-center gap-2">
                <GraduationCap class="h-4 w-4 text-primary" />
                <h3 class="font-semibold text-sm text-foreground">Course Demand vs Recommendation</h3>
                <span class="text-xs text-muted-foreground ml-1">Preference alignment analysis</span>
              </div>
            </div>
            <div class="px-4 pb-4 overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-border">
                    <th class="text-left py-2 px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Course</th>
                    <th class="text-center py-2 px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">1st</th>
                    <th class="text-center py-2 px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">2nd</th>
                    <th class="text-center py-2 px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">3rd</th>
                    <th class="text-center py-2 px-2 text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Demand</th>
                    <th class="text-center py-2 px-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Recommended</th>
                    <th class="text-center py-2 px-2 text-xs font-semibold text-muted-foreground uppercase tracking-wider">Alignment</th>
                  </tr>
                </thead>
                <tbody>
                  {#each summaryData.course_demand as course, i}
                    <tr class="border-b border-border/50 hover:bg-muted/20 transition-colors {i % 2 === 0 ? 'bg-muted/5' : ''}">
                      <td class="py-2.5 px-2">
                        <div class="font-medium text-foreground">{course.code}</div>
                        <div class="text-xs text-muted-foreground truncate max-w-[200px]">{course.name}</div>
                      </td>
                      <td class="text-center py-2.5 px-2 text-muted-foreground tabular-nums">{course.pref1}</td>
                      <td class="text-center py-2.5 px-2 text-muted-foreground tabular-nums">{course.pref2}</td>
                      <td class="text-center py-2.5 px-2 text-muted-foreground tabular-nums">{course.pref3}</td>
                      <td class="text-center py-2.5 px-2 font-semibold text-blue-600 dark:text-blue-400 tabular-nums">{course.total_demand}</td>
                      <td class="text-center py-2.5 px-2 font-semibold text-emerald-600 dark:text-emerald-400 tabular-nums">{course.recommended}</td>
                      <td class="text-center py-2.5 px-2">
                        {#if course.alignment > 0}
                          <span class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                            {course.alignment >= 60 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' :
                             course.alignment >= 30 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' :
                             'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-400'}">
                            {course.alignment}%
                          </span>
                        {:else}
                          <span class="text-xs text-muted-foreground">—</span>
                        {/if}
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </div>
        {/if}

        <!-- Demographics -->
        {#if summaryData.demographics}
          <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="p-4">
              <div class="flex items-center gap-2">
                <Users class="h-4 w-4 text-primary" />
                <h3 class="font-semibold text-sm text-foreground">Demographics Snapshot</h3>
                <span class="text-xs text-muted-foreground ml-1">Sex & age distribution</span>
              </div>
            </div>
            <div class="px-4 pb-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- By Sex Chart -->
                <div class="space-y-3">
                  <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">By Sex</h4>
                  <div class="h-[200px] flex items-center justify-center">
                    <canvas bind:this={sexChartEl}></canvas>
                  </div>
                </div>
                <!-- By Age Chart -->
                <div class="space-y-3">
                  <h4 class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">By Age Bracket</h4>
                  <div class="h-[200px] flex items-center justify-center">
                    <canvas bind:this={ageChartEl}></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/if}
      </div>
    {/if}

    <!-- ═══ TIER 3: Downloadable Reports ═══ -->
    {#if registrarReports.length > 0}
      <section>
        <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-4 flex items-center gap-2">
          <FileDown class="h-4 w-4" /> Admissions Downloads
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {#each registrarReports as report (report.type)}
            {@const Icon = iconMap[report.icon] ?? FileText}
            <div class="group relative rounded-xl border border-border bg-card hover:border-primary/30 hover:shadow-md hover:shadow-primary/5 transition-all duration-300 flex flex-col">
              <div class="p-5 flex-1">
                <div class="flex items-start gap-3 mb-3">
                  <div class="rounded-lg bg-primary/10 p-2.5 text-primary shrink-0 group-hover:bg-primary/20 transition-colors">
                    <Icon class="h-5 w-5" />
                  </div>
                  <div class="min-w-0">
                    <h3 class="font-semibold text-foreground text-sm leading-tight">{report.title}</h3>
                  </div>
                </div>
                <p class="text-xs text-muted-foreground leading-relaxed">{report.description}</p>
              </div>
              <div class="px-5 pb-4 flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  class="flex-1 min-h-[40px] gap-2 group-hover:border-primary/40 group-hover:text-primary transition-colors"
                  onclick={() => doExport(report.type, 'xlsx')}
                  disabled={exportingType !== null || !selectedAyId}
                >
                  {#if exportingType === `${report.type}-xlsx`}
                    <Loader2 class="h-4 w-4 animate-spin" /> Generating…
                  {:else}
                    <Download class="h-4 w-4" /> .xlsx
                  {/if}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  class="min-h-[40px] gap-2 group-hover:border-primary/40 group-hover:text-primary transition-colors"
                  onclick={() => doExport(report.type, 'pdf')}
                  disabled={exportingType !== null || !selectedAyId}
                >
                  {#if exportingType === `${report.type}-pdf`}
                    <Loader2 class="h-4 w-4 animate-spin" />
                  {:else}
                    <FileText class="h-4 w-4" /> .pdf
                  {/if}
                </Button>
              </div>
            </div>
          {/each}
        </div>
      </section>
    {/if}

    <!-- Assessment Downloads -->
    {#if guidanceReports.length > 0}
      <section>
        <h2 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-4 flex items-center gap-2">
          <FileDown class="h-4 w-4" /> Assessment Downloads
        </h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {#each guidanceReports as report (report.type)}
            {@const Icon = iconMap[report.icon] ?? FileText}
            <div class="group relative rounded-xl border border-border bg-card hover:border-amber-500/30 hover:shadow-md hover:shadow-amber-500/5 transition-all duration-300 flex flex-col">
              <div class="p-5 flex-1">
                <div class="flex items-start gap-3 mb-3">
                  <div class="rounded-lg bg-amber-500/10 p-2.5 text-amber-600 dark:text-amber-400 shrink-0 group-hover:bg-amber-500/20 transition-colors">
                    <Icon class="h-5 w-5" />
                  </div>
                  <div class="min-w-0">
                    <h3 class="font-semibold text-foreground text-sm leading-tight">{report.title}</h3>
                  </div>
                </div>
                <p class="text-xs text-muted-foreground leading-relaxed">{report.description}</p>
              </div>
              <div class="px-5 pb-4 flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  class="flex-1 min-h-[40px] gap-2 group-hover:border-amber-500/40 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors"
                  onclick={() => doExport(report.type, 'xlsx')}
                  disabled={exportingType !== null || !selectedAyId}
                >
                  {#if exportingType === `${report.type}-xlsx`}
                    <Loader2 class="h-4 w-4 animate-spin" /> Generating…
                  {:else}
                    <Download class="h-4 w-4" /> .xlsx
                  {/if}
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  class="min-h-[40px] gap-2 group-hover:border-amber-500/40 group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors"
                  onclick={() => doExport(report.type, 'pdf')}
                  disabled={exportingType !== null || !selectedAyId}
                >
                  {#if exportingType === `${report.type}-pdf`}
                    <Loader2 class="h-4 w-4 animate-spin" />
                  {:else}
                    <FileText class="h-4 w-4" /> .pdf
                  {/if}
                </Button>
              </div>
            </div>
          {/each}
        </div>
      </section>
    {/if}

    <!-- Empty state -->
    {#if visibleReports.length === 0}
      <div class="rounded-xl border border-dashed border-border p-12 text-center">
        <FileText class="h-10 w-10 mx-auto text-muted-foreground/50 mb-3" />
        <p class="text-muted-foreground">No reports available for your role.</p>
      </div>
    {/if}
  </div>
</AuthenticatedLayout>
