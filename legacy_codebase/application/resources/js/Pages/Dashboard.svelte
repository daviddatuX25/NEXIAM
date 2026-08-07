<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, usePage } from '@inertiajs/svelte';
  import KpiCard from '@/Components/KpiCard.svelte';
  import LineChart from '@/Components/LineChart.svelte';
  import DoughnutChart from '@/Components/DoughnutChart.svelte';
  import { formatDateTime } from '@/lib/date-utils';
  import {
    Activity, Clock, CheckCircle, XCircle,
    Users, GraduationCap, Calendar,
  } from 'lucide-svelte';

  let { user, applicationStats, pipelineDistribution, sessionStats, gradingStats, analytics, myActivity } = $props();

  const breadcrumbs = [{ label: 'Dashboard' }];
  const page = usePage();
  const authUser = $derived($page.props.auth?.user ?? null);
  const roles = $derived(authUser?.roles?.map((r) => r.name) ?? user?.roles?.map((r) => r.name) ?? []);

  function hasRole(r) {
    return roles.includes(r);
  }

  const safeApplicationStats = $derived(Array.isArray(applicationStats) ? applicationStats : []);
  const safeSessionStats = $derived(Array.isArray(sessionStats) ? sessionStats : []);
  const safeGradingStats = $derived(Array.isArray(gradingStats) ? gradingStats : []);


  // Analytics data (guarded)
  const appAnalytics = $derived(analytics?.applications ?? null);
  const sessionAnalytics = $derived(analytics?.sessions ?? null);
  const gradingAnalytics = $derived(analytics?.grading ?? null);


  // ─── Role visibility flags ─────────────────────────────────────────────────

  const showAppSection = $derived(
    hasRole('super_admin') || hasRole('admin') || hasRole('registrar_administrator')
  );
  const showSessionSection = $derived(
    hasRole('super_admin') || hasRole('proctor') || hasRole('registrar_administrator') || hasRole('test_administrator')
  );
  const showGradingSection = $derived(
    hasRole('super_admin') || hasRole('registrar_administrator') || hasRole('test_administrator')
  );




  // ─── KPI icon map ─────────────────────────────────────────────────────────

  const kpiIcons = {
    total_applications: Activity,
    in_pipeline: Clock,
    completed: CheckCircle,
    dismissed: XCircle,
  };
  const kpiAccents = {
    total_applications: 'ok',
    in_pipeline: 'warn',
    completed: 'ok',
    dismissed: 'critical',
  };

  // ─── Session trends dual-series ────────────────────────────────────────────

  const sessionTrendDatasets = $derived(() => {
    const t = sessionAnalytics?.trends;
    if (!t?.labels?.length) return null;
    return {
      labels: t.labels,
      datasets: [
        { label: 'Scheduled', data: t.scheduled ?? [], color: '#3b82f6', fill: false },
        { label: 'Completed', data: t.completed ?? [], color: '#22c55e' },
      ],
    };
  });

  const attendanceDatasets = $derived(() => {
    const a = sessionAnalytics?.attendance;
    if (!a?.labels?.length) return null;
    return {
      labels: a.labels,
      datasets: [
        { label: 'Present', data: a.present ?? [], color: '#22c55e' },
        { label: 'Absent', data: a.absent ?? [], color: '#ef4444', fill: false },
      ],
    };
  });

  function pieNotEmpty(pieData) {
    return Array.isArray(pieData) && pieData.length > 0;
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-8 min-w-0">
    <!-- ─── Welcome Header ────────────────────────────────────────────── -->
    <div>
      <p class="text-muted-foreground text-lg">Welcome back, {user?.name ?? 'User'}.</p>
    </div>

    <!-- ═══ TOP: KPI Summary Row ═══ -->
    {#if safeApplicationStats.length > 0}
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {#each safeApplicationStats as stat (stat.key)}
          <KpiCard
            label={stat.label}
            value={stat.value}
            href={stat.href}
            status={kpiAccents[stat.key] ?? 'ok'}
          />
        {/each}
      </div>
    {/if}

    <!-- ═══ MIDDLE: Application Growth ═══ -->
    {#if showAppSection}
      {#if appAnalytics?.trends?.labels?.length}
        <div class="rounded-2xl border border-border bg-card p-5">
          <LineChart
            title="Application Growth"
            subtitle="New applications per month"
            labels={appAnalytics.trends.labels}
            values={appAnalytics.trends.values}
            height={240}
            color="#14b8a6"
          />
        </div>
      {/if}

      <!-- Application distributions -->
      {#if appAnalytics}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          {#if pieNotEmpty(appAnalytics.statusDistribution)}
            <div class="rounded-2xl border border-border bg-card p-5">
              <DoughnutChart
                title="Pipeline Breakdown"
                data={appAnalytics.statusDistribution}
                height={240}
              />
            </div>
          {/if}

          {#if pieNotEmpty(appAnalytics.coursePreferences)}
            <div class="rounded-2xl border border-border bg-card p-5">
              <DoughnutChart
                title="Top Course Preferences"
                data={appAnalytics.coursePreferences}
                height={240}
              />
            </div>
          {/if}
        </div>
      {/if}
    {/if}

    <!-- ═══ BOTTOM: Role-specific Operational Cards ═══ -->
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">

      <!-- Sessions Card -->
      {#if showSessionSection}
        <div class="rounded-2xl border border-border bg-card p-5 space-y-5">
          <div class="flex items-center gap-2">
            <Calendar class="h-4 w-4 text-primary" />
            <h2 class="font-semibold text-sm text-foreground">Exam Sessions</h2>
          </div>

          {#if safeSessionStats.length > 0}
            <div class="grid grid-cols-3 gap-3">
              {#each safeSessionStats as stat (stat.key)}
                <div class="text-center">
                  <p class="text-2xl font-bold tabular-nums text-foreground">{stat.value}</p>
                  <p class="text-xs text-muted-foreground mt-0.5">{stat.label}</p>
                </div>
              {/each}
            </div>
          {/if}

          {#if sessionAnalytics}
            {@const trendData = sessionTrendDatasets()}
            {#if trendData}
              <LineChart
                title="Session Trends"
                subtitle="Scheduled vs completed per week"
                labels={trendData.labels}
                datasets={trendData.datasets}
                height={160}
              />
            {/if}

            {#if pieNotEmpty(sessionAnalytics.statusDistribution)}
              <DoughnutChart
                title="Session Status"
                data={sessionAnalytics.statusDistribution}
                height={180}
              />
            {/if}
          {/if}
        </div>
      {/if}

      <!-- Grading Card -->
      {#if showGradingSection}
        <div class="rounded-2xl border border-border bg-card p-5 space-y-5">
          <div class="flex items-center gap-2">
            <GraduationCap class="h-4 w-4 text-primary" />
            <h2 class="font-semibold text-sm text-foreground">Grading & Release</h2>
          </div>

          {#if safeGradingStats.length > 0}
            <div class="grid grid-cols-2 gap-3">
              {#each safeGradingStats as stat (stat.key)}
                <div class="text-center">
                  <p class="text-2xl font-bold tabular-nums text-foreground">{stat.value}</p>
                  <p class="text-xs text-muted-foreground mt-0.5">{stat.label}</p>
                </div>
              {/each}
            </div>
          {/if}

          {#if gradingAnalytics}
            {#if gradingAnalytics.turnaround?.labels?.length}
              <LineChart
                title="Grading Turnaround"
                subtitle="Avg. days to finalize per week"
                labels={gradingAnalytics.turnaround.labels}
                values={gradingAnalytics.turnaround.values}
                height={160}
                color="#a855f7"
              />
            {/if}

            {#if pieNotEmpty(gradingAnalytics.statusDistribution)}
              <DoughnutChart
                title="Grading Status"
                data={gradingAnalytics.statusDistribution}
                height={180}
              />
            {/if}
          {/if}
        </div>
      {/if}

      <!-- My Activity Card -->
      <div class="rounded-2xl border border-border bg-card overflow-hidden">
        <div class="px-5 py-4 border-b border-border">
          <div class="flex items-center gap-2">
            <Users class="h-4 w-4 text-primary" />
            <h2 class="font-semibold text-sm text-foreground">My Activity</h2>
          </div>
          <p class="text-xs text-muted-foreground mt-0.5">Your recent actions in the system.</p>
        </div>
        {#if Array.isArray(myActivity) && myActivity.length > 0}
          <ul class="divide-y divide-border max-h-[400px] overflow-y-auto">
            {#each myActivity as entry (entry.id)}
              <li class="flex items-start gap-3 px-5 py-3">
                <div class="mt-1.5 shrink-0">
                  <div class="h-2 w-2 rounded-full bg-primary"></div>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm text-foreground">{entry.summary || entry.event}</p>
                  <p class="text-xs text-muted-foreground mt-0.5 capitalize">{entry.category?.replace('_', ' ')} &middot; {formatDateTime(entry.created_at)}</p>
                </div>
              </li>
            {/each}
          </ul>
        {:else}
          <div class="px-5 py-10 text-center text-sm text-muted-foreground">
            No activity recorded yet.
          </div>
        {/if}
      </div>
    </div>



    {#if safeApplicationStats.length === 0 && safeSessionStats.length === 0 && safeGradingStats.length === 0}
      <p class="text-muted-foreground">Use the sidebar to navigate.</p>
    {/if}
  </div>
</AuthenticatedLayout>
