<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Link } from '@inertiajs/svelte';

  let { dashboard, staff } = $props();
  let collegeFilter = $state('All Colleges');
  const breadcrumbs = [{ label: 'Admin Office' }, { label: 'Executive Dashboard' }];
  const colleges = $derived(['All Colleges', ...new Set((dashboard?.quota_variance?.programs ?? []).map((row) => row.college))]);
  const programs = $derived((dashboard?.quota_variance?.programs ?? []).filter((row) => collegeFilter === 'All Colleges' || row.college === collegeFilter));
  const admissions = $derived(dashboard?.admissions?.kpis ?? {});
  const assessment = $derived(dashboard?.assessment?.kpis ?? {});
  const facilities = $derived(dashboard?.facility_health ?? {});
  const audit = $derived(dashboard?.supervisory_audit ?? {});
</script>

<svelte:head><title>Executive Dashboard - NEXIAM</title></svelte:head>

<StaffLayout {staff} {breadcrumbs}>
  <main class="mx-auto min-w-0 max-w-7xl space-y-6 p-4 sm:p-6">
    <PageHeaderBar title="Executive Command Center" description="Institutional admissions, assessment, capacity, and operational readiness at a glance." />
    <section class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Institutional overview">
      <article class="min-w-0 rounded-xl border bg-card p-5"><p class="text-sm text-muted-foreground">Campus Applications</p><p class="mt-2 text-3xl font-semibold">{admissions.total ?? 0}</p><p class="mt-1 text-sm">{admissions.in_pipeline ?? 0} in pipeline · {admissions.yoy_growth ?? 'No comparison'}</p></article>
      <article class="min-w-0 rounded-xl border bg-card p-5"><p class="text-sm text-muted-foreground">Confirmed Clearances</p><p class="mt-2 text-3xl font-semibold">{admissions.admission_clearances_confirmed ?? 0}</p></article>
      <article class="min-w-0 rounded-xl border bg-card p-5"><p class="text-sm text-muted-foreground">Exam Attendance &amp; Scoring Throughput</p><p class="mt-2 text-3xl font-semibold">{assessment.attendance_rate ?? 0}%</p><p class="mt-1 text-sm">{assessment.completed_sessions ?? 0} completed · {assessment.finalized_grading_sessions ?? 0} finalized</p></article>
      <article class="min-w-0 rounded-xl border bg-card p-5"><p class="text-sm text-muted-foreground">Active Rooms &amp; Seat Capacity</p><p class="mt-2 text-3xl font-semibold">{facilities.active_exam_rooms ?? 0} rooms</p><p class="mt-1 text-sm">{facilities.seat_capacity ?? 0} seats available</p></article>
      <article class="min-w-0 rounded-xl border bg-card p-5 md:col-span-2 xl:col-span-2">
        <h2 class="text-lg font-semibold">Advisory Quota vs. Actual Committed Variance</h2>
        <div class="mt-3 flex min-w-0 flex-wrap gap-2" aria-label="Filter by college">
          {#each colleges as college}<Button variant={collegeFilter === college ? 'default' : 'outline'} size="sm" onclick={() => collegeFilter = college}>{college}</Button>{/each}
        </div>
        <div class="mt-4 min-w-0 overflow-x-auto rounded-lg border"><table class="w-full min-w-[600px] text-left text-sm"><thead class="bg-muted/50"><tr><th class="p-3">Program</th><th class="p-3">College</th><th class="p-3">Planned Quota</th><th class="p-3">Committed</th><th class="p-3">Variance</th><th class="p-3">Status</th></tr></thead><tbody>{#each programs as row}<tr class="border-t"><td class="p-3">{row.program}</td><td class="p-3">{row.college}</td><td class="p-3">{row.planned_quota}</td><td class="p-3">{row.actual_committed}</td><td class="p-3">{row.variance > 0 ? `+${row.variance}` : row.variance}</td><td class="p-3"><Badge variant="outline">{row.badge}</Badge></td></tr>{:else}<tr><td class="p-4 text-muted-foreground" colspan="6">No active programs found.</td></tr>{/each}</tbody></table></div>
      </article>
      <article class="min-w-0 rounded-xl border bg-card p-5">
        <h2 class="text-lg font-semibold">Facility &amp; Setup Readiness</h2><p class="mt-2 text-sm">{facilities.open_application_windows ?? 0} open application windows · {facilities.active_setup_delegations?.length ?? 0} active setup delegations</p>
        <div class="mt-4 flex min-w-0 flex-wrap gap-2"><Button variant="outline" asChild><Link href="/setup">Institutional Setup</Link></Button><Button variant="outline" asChild><Link href="/staff/personnel">Personnel</Link></Button><Button variant="outline" asChild><Link href="/staff/logs">Audit Logs</Link></Button></div>
        <ul class="mt-4 space-y-2 text-sm">{#each facilities.active_setup_delegations ?? [] as delegation}<li class="rounded-lg bg-muted/40 p-3">{delegation.recipient?.name ?? 'Staff'} · delegated by {delegation.actor?.name ?? 'Admin'}</li>{:else}<li class="text-muted-foreground">No active setup delegations.</li>{/each}</ul>
      </article>
      <article class="min-w-0 rounded-xl border bg-card p-5">
        <h2 class="text-lg font-semibold">Supervisory Accomplishment &amp; Audit Summary</h2>
        <dl class="mt-4 grid grid-cols-2 gap-3"><div class="rounded-lg bg-muted/40 p-3"><dt class="text-xs text-muted-foreground">Last 24 hours</dt><dd class="text-2xl font-semibold">{audit.last_24_hours ?? 0}</dd></div><div class="rounded-lg bg-muted/40 p-3"><dt class="text-xs text-muted-foreground">Last 7 days</dt><dd class="text-2xl font-semibold">{audit.last_7_days ?? 0}</dd></div></dl>
        <ul class="mt-4 space-y-2 text-sm">{#each audit.recent_events ?? [] as event}<li class="flex min-w-0 justify-between gap-2"><span class="min-w-0 truncate">{event.event_type}</span><span class="shrink-0 text-muted-foreground">{event.created_at ? new Date(event.created_at).toLocaleDateString() : ''}</span></li>{/each}</ul>
      </article>
    </section>
  </main>
</StaffLayout>
