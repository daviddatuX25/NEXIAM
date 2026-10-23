<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import { formatRelative } from '@/lib/date-utils.js';
  import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
  import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
  import BentoCard from '@/Components/kit/BentoCard.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Link } from '@inertiajs/svelte';
  import { Activity, CheckCircle2, Clock } from 'lucide-svelte';

  let { staff, dashboard } = $props();
  let tourOpen = $state(false);
  let selectedDept = $state('ALL');

  const breadcrumbs = [{ label: 'Dashboard' }];

  const cycleRoadmap = $derived(dashboard?.cycle_roadmap ?? null);
  const setupReadiness = $derived(dashboard?.setup_readiness ?? null);
  const academicScope = $derived(dashboard?.academic_scope ?? null);
  const registrar = $derived(dashboard?.registrar ?? null);
  const guidance = $derived(dashboard?.guidance ?? null);
  const programHead = $derived(dashboard?.program_head ?? null);
  const myActivity = $derived(dashboard?.my_activity ?? []);
  const activeCycle = $derived(dashboard?.active_cycle ?? null);
  const isAcademicLeader = $derived(Boolean(programHead) && !registrar && !guidance && !setupReadiness);
  const isDeanLeader = $derived(Boolean(programHead?.is_dean) || (academicScope?.scope_mode === 'department'));
  const isGuidanceCounselor = $derived(Boolean(guidance) && !registrar && !setupReadiness && !isAcademicLeader);
  const isRegistrarStaff = $derived(Boolean(registrar) && !guidance && !setupReadiness && !isAcademicLeader);
  const canViewAuditLogs = $derived(
    (staff?.capabilities ?? dashboard?.actor?.capabilities ?? []).includes('audit.logs.view') ||
    (staff?.capabilities ?? dashboard?.actor?.capabilities ?? []).includes('audit.logs.view.own')
  );

  const deptOptions = $derived(academicScope?.departments ?? []);
  const canFilterDept = $derived(Boolean(academicScope?.can_filter_department) && deptOptions.length > 1);
  const deptFilteredPrograms = $derived.by(() => {
    const all = academicScope?.programs ?? [];
    if (!canFilterDept || selectedDept === 'ALL') return all;
    return all.filter((program) => program.department_code === selectedDept);
  });
  const scopedPrograms = $derived(deptFilteredPrograms);
  const primaryProgramHref = $derived(
    isDeanLeader || !programHead?.program_code || scopedPrograms.length > 1
      ? '/staff/programs'
      : `/staff/programs/${programHead.program_code}`
  );
  const placementSummary = $derived(dashboard?.course_placement ?? null);

  function selectDepartment(code) {
    selectedDept = code;
  }

  const operationsContext = $derived.by(() => {
    const hasReg = Boolean(registrar);
    const hasGui = Boolean(guidance);
    const hasPh = Boolean(programHead);
    const office = staff?.scope?.office ?? 'Campus Administration';
    const name = staff?.name ?? 'Colleague';
    const safetyKey = `${office}:${hasReg ? 'reg' : 'noreg'}:${hasGui ? 'gui' : 'nogui'}:${hasPh ? 'ph' : 'noph'}:${activeCycle?.name ?? 'nocycle'}`;

    return {
      entityKey: 'staff-operations-hub',
      safetyKey,
      freshness: 'current',
      staffOffice: office,
      staffName: name,
      activeCycleName: activeCycle?.name ?? null,
      hasRegistrar: hasReg,
      hasGuidance: hasGui,
      hasProgramHead: hasPh,
      activityCount: myActivity.length,
    };
  });

  function formatTime(iso) {
    if (!iso) return '';
    try {
      const date = new Date(iso);
      return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } catch {
      return iso;
    }
  }
</script>

<svelte:head>
  <title>Dashboard &bull; NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-5 p-4 sm:p-5 lg:p-6" data-tour="operations-dashboard-summary">
    <!-- Operational Overview Header -->
    <div class="rounded-2xl border border-border/80 bg-card/90 p-5 shadow-xs backdrop-blur-xs" data-tour="operations-welcome-compass">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-bold tracking-tight text-foreground sm:text-2xl">
              Welcome back, {staff?.name ?? dashboard?.actor?.name ?? 'Colleague'}
            </h1>
            {#if activeCycle}
              <span class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-primary/20 bg-primary/10 px-2.5 py-0.5 font-mono text-xs font-semibold text-primary">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span>
                <span class="truncate">{activeCycle.name}</span>
              </span>
            {/if}
          </div>
          <p class="text-xs text-muted-foreground">
            {#if isAcademicLeader}
              {isDeanLeader
                ? `Departmental quota allocations, applicant demand, and referral reviews for ${programHead?.department_name ?? 'your college'}.`
                : `Program quota allocation, applicant demand, and referral reviews for ${programHead?.program_code ?? 'your assigned program'}.`}
            {:else if isGuidanceCounselor}
              Assessment throughput, examinee scheduling, and academic advising for candidate course placement.
            {:else if isRegistrarStaff}
              Admissions verification, document discrepancy handling, and final admission clearance.
            {:else}
              Central operations console across admissions, examinations, and clearance.
            {/if}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <TourTriggerGlyph tourKey="staff-operations-dashboard" label="Open the staff operations guide" onclick={() => (tourOpen = true)} />
          {#if isAcademicLeader}
            <Link href={primaryProgramHref}>
              <Button size="sm" class="h-8 text-xs font-semibold">
                {isDeanLeader ? `Open My Programs (${scopedPrograms.length})` : `Open ${programHead?.program_code ?? 'My Program'}`} &rarr;
              </Button>
            </Link>
          {:else if isGuidanceCounselor}
            <Link href="/consultation">
              <Button size="sm" class="h-8 text-xs font-semibold">
                Open Release Day &rarr;
              </Button>
            </Link>
            <Link href="/exams/sessions">
              <Button size="sm" variant="outline" class="h-8 text-xs font-semibold">
                Exam Sessions
              </Button>
            </Link>
          {:else if isRegistrarStaff}
            <Link href="/admissions">
              <Button size="sm" class="h-8 text-xs font-semibold">
                Review Applications &rarr;
              </Button>
            </Link>
            <Link href="/registrar/enrollment-eligibility">
              <Button size="sm" variant="outline" class="h-8 text-xs font-semibold">
                Clearance Desk
              </Button>
            </Link>
          {/if}
        </div>
      </div>
    </div>
    {#if isAcademicLeader && programHead}
      <section class="space-y-4" data-tour="operations-program-quota">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <BentoCard class="space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                {isDeanLeader ? 'Department Quota' : 'Program Quota'}
              </span>
              <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                {programHead.percentage ?? 0}% Filled
              </span>
            </div>
            <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
              {(programHead.allocated ?? 0).toLocaleString()} <span class="text-base font-normal text-muted-foreground">/ {(programHead.quota ?? 0).toLocaleString()}</span>
            </div>
            <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
              {(programHead.open_slots ?? Math.max(0, (programHead.quota ?? 0) - (programHead.allocated ?? 0))).toLocaleString()} open quota slots remaining
            </div>
          </BentoCard>

          <BentoCard class="space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">1st-Choice Demand</span>
              <span class="rounded-full border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-sky-600 dark:text-sky-400">
                Primary Choice
              </span>
            </div>
            <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
              {(programHead.potential_candidates ?? 0).toLocaleString()}
            </div>
            <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
              {(programHead.all_choice_candidates ?? programHead.potential_candidates ?? 0).toLocaleString()} total 1st–3rd preferences
            </div>
          </BentoCard>

          <BentoCard class="space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Advising &amp; Tested Pool</span>
              <span class="rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-bold text-primary">
                Exam Present
              </span>
            </div>
            <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400 sm:text-3xl">
              {(programHead.advising_pool ?? 0).toLocaleString()}
            </div>
            <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
              {(programHead.cleared_candidates ?? 0).toLocaleString()} confirmed admission clearances
            </div>
          </BentoCard>

          <BentoCard class="space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Open Follow-ups</span>
              <span class="rounded-full border {(programHead.pending_follow_ups ?? 0) > 0 ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'border-border bg-muted text-muted-foreground'} px-2 py-0.5 font-mono text-[10px] font-bold">
                {(programHead.pending_follow_ups ?? 0) > 0 ? 'Action Needed' : 'All Clear'}
              </span>
            </div>
            <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums {(programHead.pending_follow_ups ?? 0) > 0 ? 'text-amber-500' : 'text-foreground'} sm:text-3xl">
              {(programHead.pending_follow_ups ?? 0).toLocaleString()}
            </div>
            <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
              Targeted referrals &amp; interview cases
            </div>
          </BentoCard>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
          <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-8">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="text-sm font-bold text-foreground">
                  {#if isDeanLeader}
                    {programHead.department_code ? `${programHead.department_code} Department Programs — Quota & Placement` : 'My Department Programs — Quota & Placement'}
                  {:else}
                    {programHead.program_code ? `${programHead.program_code} — Program Quota & Placement` : 'My Program — Quota & Placement'}
                  {/if}
                </h2>
                <p class="font-mono text-[10px] text-muted-foreground">
                  {#if isDeanLeader}
                    Showing all {scopedPrograms.length} active programs in {programHead.department_code ?? 'your department'} &middot; Calibrated 0%&ndash;100% quota meters
                  {:else}
                    {programHead.program_name ?? 'Assigned Academic Program'} &middot; Calibrated 0%&ndash;100% quota meter
                  {/if}
                </p>
              </div>
              <div class="flex items-center gap-2 font-mono text-[10px]">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Placed</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full border border-sky-500 bg-sky-500/40"></span> 1st-Choice</span>
              </div>
            </div>

            <div class="space-y-2.5 border-t border-border/50 pt-3">
              {#if scopedPrograms.length > 0}
                {#each scopedPrograms as prog (prog.code)}
                  {@const placedPct = Math.min(100, Math.max(0, prog.utilization_percent ?? 0))}
                  {@const choicePct = Math.min(100, Math.max(0, prog.demand_percent ?? 0))}
                  <div class="space-y-2 rounded-lg border border-border/60 bg-muted/25 p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                      <div class="flex min-w-0 items-center gap-1.5">
                        <span class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{prog.code}</span>
                        <span class="truncate text-xs font-semibold text-foreground">{prog.name}</span>
                      </div>
                      <div class="flex shrink-0 flex-wrap items-center gap-2.5 font-mono text-[10px]">
                        <span class="text-emerald-600 dark:text-emerald-400">Placed {prog.course_placed ?? prog.actual_committed ?? 0}/{prog.planned_quota}</span>
                        <span class="text-muted-foreground">1st-Choice {prog.first_choice_demand}</span>
                        <Link href={`/staff/programs/${prog.code}`} class="rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 font-bold text-primary hover:bg-primary/20">
                          Open &rarr;
                        </Link>
                      </div>
                    </div>
                    <div class="relative h-2.5 w-full overflow-hidden rounded-full border border-border/70 bg-muted/90" aria-label="{prog.code} quota meter">
                      <div class="absolute inset-y-0 left-1/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-2/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-3/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-0 rounded-full border-r border-sky-500 bg-sky-500/35 transition-all duration-300" style="width: {choicePct}%"></div>
                      <div class="relative h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-300" style="width: {placedPct}%"></div>
                    </div>
                    <div class="flex flex-wrap justify-between gap-2 font-mono text-[9px] text-muted-foreground">
                      <span>{prog.open_slots ?? Math.max(0, (prog.planned_quota ?? 0) - (prog.course_placed ?? 0))} of {prog.planned_quota} slots open &middot; Total 1st–3rd choices: {prog.total_choice_demand ?? prog.first_choice_demand}</span>
                      <span>{placedPct}% placed &middot; {choicePct}% 1st-choice</span>
                    </div>
                  </div>
                {/each}
              {:else}
                <p class="rounded-lg bg-muted/30 py-6 text-center text-xs text-muted-foreground">No active programs are assigned to your scope.</p>
              {/if}
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between gap-3 p-4 sm:p-5 lg:col-span-4">
            <div class="space-y-3">
              <div>
                <h2 class="text-sm font-bold text-foreground">
                  {isDeanLeader ? 'Department Governance & Actions' : 'Program Governance & Actions'}
                </h2>
                <p class="font-mono text-[10px] text-muted-foreground">
                  Direct access to your {isDeanLeader ? 'departmental' : 'program'} workspaces
                </p>
              </div>

              <div class="space-y-2 border-t border-border/50 pt-2.5 text-xs">
                <Link href={primaryProgramHref} class="block rounded-lg border border-border/60 bg-muted/30 p-3 transition-colors hover:border-primary/40 hover:bg-muted/50">
                  <div class="flex items-center justify-between font-semibold text-foreground">
                    <span>{isDeanLeader ? 'My Programs Selection' : `${programHead.program_code ?? 'My'} Program Workspace`}</span>
                    <span class="font-mono text-primary">&rarr;</span>
                  </div>
                  <p class="mt-1 text-[11px] text-muted-foreground">
                    {isDeanLeader
                      ? 'Inspect candidate pools, endorse pre-enrollment lists, and resolve follow-ups per program.'
                      : 'Review candidate pools, endorse pre-enrollment lists, and resolve targeted follow-ups.'}
                  </p>
                </Link>

                <Link href="/reports" class="block rounded-lg border border-border/60 bg-muted/30 p-3 transition-colors hover:border-primary/40 hover:bg-muted/50">
                  <div class="flex items-center justify-between font-semibold text-foreground">
                    <span>{isDeanLeader ? 'Department Analytics & Reports' : 'Program Analytics & Reports'}</span>
                    <span class="font-mono text-primary">&rarr;</span>
                  </div>
                  <p class="mt-1 text-[11px] text-muted-foreground">
                    {isDeanLeader
                      ? `Scoped choice demand lenses, demographics, and quota summaries for ${programHead.department_code ?? 'your department'}.`
                      : `Scoped choice demand lenses, demographics, and quota qualifiers for ${programHead.program_code ?? 'your program'}.`}
                  </p>
                </Link>

                <Link href="/setup" class="block rounded-lg border border-border/60 bg-muted/30 p-3 transition-colors hover:border-primary/40 hover:bg-muted/50">
                  <div class="flex items-center justify-between font-semibold text-foreground">
                    <span>Benchmark &amp; Quota Governance</span>
                    <span class="font-mono text-primary">&rarr;</span>
                  </div>
                  <p class="mt-1 text-[11px] text-muted-foreground">
                    Configure admission support benchmarks, advising notes, and quota deliberations.
                  </p>
                </Link>

                <Link href="/staff/inquiries" class="block rounded-lg border border-border/60 bg-muted/30 p-3 transition-colors hover:border-primary/40 hover:bg-muted/50">
                  <div class="flex items-center justify-between font-semibold text-foreground">
                    <span>Case &amp; Office Messages</span>
                    <span class="font-mono text-primary">&rarr;</span>
                  </div>
                  <p class="mt-1 text-[11px] text-muted-foreground">
                    Coordinate with Guidance counselors on referred applicants and case deliberations.
                  </p>
                </Link>
              </div>
            </div>
          </BentoCard>
        </div>
      </section>
    {:else if isGuidanceCounselor && guidance}
      <!-- Guidance Operational Cockpit -->
      <section class="space-y-4" data-tour="operations-guidance-focus">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Release Day Advising</span>
              <span class="rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-bold text-primary">
                Today
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {(guidance.kpis?.today_release_appointments ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                {(guidance.kpis?.pending_advising_qualifiers ?? 0).toLocaleString()} examinees awaiting advising
              </div>
            </div>
            <div class="pt-1">
              <Link href="/consultation" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Enter Board &rarr;
              </Link>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Score Finalization</span>
              <span class="rounded-full border {(guidance.kpis?.pending_scoring ?? 0) > 0 ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'border-border bg-muted text-muted-foreground'} px-2 py-0.5 font-mono text-[10px] font-bold">
                {(guidance.kpis?.pending_scoring ?? 0) > 0 ? 'Review Needed' : 'Complete'}
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {(guidance.kpis?.pending_scoring ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                Sessions awaiting score review and lock
              </div>
            </div>
            <div class="pt-1">
              <Link href="/scoring" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Finalize Scores &rarr;
              </Link>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Academic Referrals</span>
              <span class="rounded-full border {(guidance.kpis?.pending_referrals ?? 0) > 0 ? 'border-amber-500/30 bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'border-border bg-muted text-muted-foreground'} px-2 py-0.5 font-mono text-[10px] font-bold">
                {(guidance.kpis?.pending_referrals ?? 0) > 0 ? 'Follow-ups' : 'Clear'}
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {(guidance.kpis?.pending_referrals ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                Departmental reviews &amp; interview cases
              </div>
            </div>
            <div class="pt-1">
              <Link href="/admissions" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Triage Referrals &rarr;
              </Link>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Applicant Inquiries</span>
              <span class="rounded-full border {(guidance.kpis?.open_inquiries ?? 0) > 0 ? 'border-sky-500/30 bg-sky-500/10 text-sky-600 dark:text-sky-400' : 'border-border bg-muted text-muted-foreground'} px-2 py-0.5 font-mono text-[10px] font-bold">
                {(guidance.kpis?.open_inquiries ?? 0) > 0 ? 'Active' : 'All Clear'}
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {(guidance.kpis?.open_inquiries ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                Direct candidate counseling communications
              </div>
            </div>
            <div class="pt-1">
              <Link href="/staff/inquiries" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Open Inbox &rarr;
              </Link>
            </div>
          </BentoCard>
        </div>

        <!-- Campus Program Quotas & Advising Radar -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
          <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-8">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="text-sm font-bold text-foreground">Campus Program Quotas &amp; Advising Radar</h2>
                <p class="font-mono text-[10px] text-muted-foreground">Live capacity indicators for candidate course placement during Release Day advising</p>
              </div>
              <div class="flex items-center gap-2 font-mono text-[10px]">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Placed</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full border border-sky-500 bg-sky-500/40"></span> 1st-Choice</span>
              </div>
            </div>

            {#if canFilterDept}
              <div class="flex flex-wrap items-center gap-1.5 border-t border-border/50 pt-2" aria-label="Filter by college">
                <span class="mr-1 font-mono text-[10px] font-bold uppercase text-muted-foreground">College</span>
                <Button size="sm" variant={selectedDept === 'ALL' ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment('ALL')}>
                  All
                </Button>
                {#each deptOptions as dept (dept.code)}
                  <Button size="sm" variant={selectedDept === dept.code ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment(dept.code)}>
                    {dept.code}
                  </Button>
                {/each}
              </div>
            {/if}

            <div class="space-y-2 border-t border-border/50 pt-2">
              {#if scopedPrograms.length > 0}
                {#each scopedPrograms.slice(0, 5) as prog (prog.code)}
                  {@const placedPct = Math.min(100, Math.max(0, prog.utilization_percent ?? 0))}
                  {@const choicePct = Math.min(100, Math.max(0, prog.demand_percent ?? 0))}
                  <div class="space-y-1.5 rounded-lg border border-border/60 bg-muted/25 p-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-1.5">
                      <div class="flex min-w-0 items-center gap-1.5">
                        <span class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{prog.code}</span>
                        <span class="truncate text-[11px] font-semibold text-foreground">{prog.name}</span>
                        <Badge variant="outline" class="shrink-0 font-mono text-[9px]">{prog.department_code}</Badge>
                      </div>
                      <div class="flex shrink-0 flex-wrap items-center gap-2 font-mono text-[10px]">
                        <span class="text-emerald-600 dark:text-emerald-400">Placed {prog.course_placed ?? prog.actual_committed ?? 0}/{prog.planned_quota}</span>
                        <span class="text-muted-foreground">1st-Choice {prog.first_choice_demand}</span>
                      </div>
                    </div>
                    <div class="relative h-2 w-full overflow-hidden rounded-full border border-border/70 bg-muted/90" aria-label="{prog.code} quota meter">
                      <div class="absolute inset-y-0 left-1/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-2/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-3/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-0 rounded-full border-r border-sky-500 bg-sky-500/35 transition-all duration-300" style="width: {choicePct}%"></div>
                      <div class="relative h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-300" style="width: {placedPct}%"></div>
                    </div>
                    <div class="flex justify-between font-mono text-[9px] text-muted-foreground">
                      <span>{prog.open_slots ?? Math.max(0, (prog.planned_quota ?? 0) - (prog.course_placed ?? 0))} of {prog.planned_quota} slots open</span>
                      <span>{placedPct}% placed &middot; {choicePct}% 1st-choice</span>
                    </div>
                  </div>
                {/each}
              {:else}
                <p class="rounded-lg bg-muted/30 py-6 text-center text-xs text-muted-foreground">No active programs are available in this scope.</p>
              {/if}
            </div>

            <div class="border-t border-border/50 pt-2 text-right">
              <Link href="/reports" class="font-mono text-[10px] font-bold text-primary hover:underline">
                Open All {scopedPrograms.length} Programs &amp; Comparison Lenses in Analytics &rarr;
              </Link>
            </div>
          </BentoCard>

          <!-- Assessment Progression & Summary -->
          <BentoCard class="flex flex-col justify-between space-y-4 p-4 sm:p-5 lg:col-span-4">
            <div>
              <h2 class="text-sm font-bold text-foreground">Assessment Progression</h2>
              <p class="font-mono text-[10px] text-muted-foreground">Examinee conversion across lifecycle phases</p>
            </div>

            <dl class="space-y-3">
              <div class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-2.5">
                <div>
                  <dt class="font-mono text-[10px] uppercase text-muted-foreground">Registered Applicants</dt>
                  <dd class="text-lg font-bold tabular-nums text-foreground">
                    {(cycleRoadmap?.velocity?.total_applications ?? 0).toLocaleString()}
                  </dd>
                </div>
                <Badge variant="outline" class="font-mono text-[9px]">Cycle Total</Badge>
              </div>

              <div class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-2.5">
                <div>
                  <dt class="font-mono text-[10px] uppercase text-muted-foreground">Examinees Tested</dt>
                  <dd class="text-lg font-bold tabular-nums text-foreground">
                    {(guidance.kpis?.candidates_tested ?? 0).toLocaleString()}
                  </dd>
                </div>
                <Badge variant="outline" class="font-mono text-[9px] text-emerald-600 dark:text-emerald-400">
                  {guidance.kpis?.attendance_percentage ?? 0}% Attendance
                </Badge>
              </div>

              <div class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-2.5">
                <div>
                  <dt class="font-mono text-[10px] uppercase text-muted-foreground">Sessions Completed</dt>
                  <dd class="text-lg font-bold tabular-nums text-foreground">
                    {(guidance.kpis?.completed_sessions ?? 0).toLocaleString()}
                  </dd>
                </div>
                <Badge variant="outline" class="font-mono text-[9px]">
                  {guidance.kpis?.scheduled_sessions ?? 0} Total
                </Badge>
              </div>

              <div class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/20 p-2.5">
                <div>
                  <dt class="font-mono text-[10px] uppercase text-muted-foreground">Reserved program places</dt>
                  <dd class="text-lg font-bold tabular-nums text-foreground">
                    {placementSummary ? placementSummary.placed.toLocaleString() : '—'}
                  </dd>
                </div>
                <Badge variant="outline" class="font-mono text-[9px] text-primary">
                  {placementSummary ? `${placementSummary.percent}% Filled` : 'Pending'}
                </Badge>
              </div>
            </dl>

            <div class="border-t border-border/50 pt-2 text-right">
              <Link href="/exams/sessions" class="font-mono text-[10px] font-bold text-primary hover:underline">
                Manage Exam Schedule &rarr;
              </Link>
            </div>
          </BentoCard>
        </div>
      </section>
    {:else if isRegistrarStaff && registrar}
      <!-- Registrar Operational Cockpit -->
      <section class="space-y-4" data-tour="operations-registrar-focus">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Pending Review</span>
              <span class="rounded-full border border-sky-500/20 bg-sky-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-sky-600 dark:text-sky-400">
                Inbox
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {Math.max(0, (registrar.kpis?.total ?? 0) - (registrar.kpis?.completed ?? 0) - (registrar.kpis?.dismissed ?? 0)).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                Applications awaiting document verification
              </div>
            </div>
            <div class="pt-1">
              <Link href="/admissions" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Start Review &rarr;
              </Link>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">In Review &amp; Deficiencies</span>
              <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-amber-600 dark:text-amber-400">
                Action
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {(registrar.kpis?.in_pipeline ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                In verification pipeline or returning corrections
              </div>
            </div>
            <div class="pt-1">
              <Link href="/admissions" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Review Deficiencies &rarr;
              </Link>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Confirmed Clearances</span>
              <span class="rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-emerald-600 dark:text-emerald-400">
                Confirmed
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-emerald-600 dark:text-emerald-400 sm:text-3xl">
                {(registrar.kpis?.admission_clearances_confirmed ?? registrar.kpis?.completed ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                Matriculation-ready confirmed admission records
              </div>
            </div>
            <div class="pt-1">
              <Link href="/registrar/enrollment-eligibility" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-primary hover:underline">
                Clearance Desk &rarr;
              </Link>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col justify-between space-y-2 p-4 sm:p-5">
            <div class="flex items-center justify-between gap-2">
              <span class="font-mono text-[10px] font-bold uppercase tracking-wider text-muted-foreground">Dismissed / Closed</span>
              <span class="rounded-full border border-border bg-muted px-2 py-0.5 font-mono text-[10px] font-bold text-muted-foreground">
                Terminal
              </span>
            </div>
            <div>
              <div class="font-mono text-2xl font-extrabold tracking-tight tabular-nums text-foreground sm:text-3xl">
                {(registrar.kpis?.dismissed ?? 0).toLocaleString()}
              </div>
              <div class="border-t border-border/50 pt-1.5 font-mono text-[10px] text-muted-foreground">
                Ineligible or withdrawn applicant records
              </div>
            </div>
            <div class="pt-1">
              <Link href="/admissions" class="inline-flex items-center gap-1 font-mono text-xs font-bold text-muted-foreground hover:underline">
                View Archive &rarr;
              </Link>
            </div>
          </BentoCard>
        </div>

        <!-- Admissions Velocity & Quota Telemetry -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
          <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-8">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="text-sm font-bold text-foreground">Program Quota &amp; Admissions Demand</h2>
                <p class="font-mono text-[10px] text-muted-foreground">Active capacity fill and applicant demand across campus programs</p>
              </div>
              <div class="flex items-center gap-2 font-mono text-[10px]">
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Placed</span>
                <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full border border-sky-500 bg-sky-500/40"></span> 1st-Choice</span>
              </div>
            </div>

            {#if canFilterDept}
              <div class="flex flex-wrap items-center gap-1.5 border-t border-border/50 pt-2" aria-label="Filter by college">
                <span class="mr-1 font-mono text-[10px] font-bold uppercase text-muted-foreground">College</span>
                <Button size="sm" variant={selectedDept === 'ALL' ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment('ALL')}>
                  All
                </Button>
                {#each deptOptions as dept (dept.code)}
                  <Button size="sm" variant={selectedDept === dept.code ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment(dept.code)}>
                    {dept.code}
                  </Button>
                {/each}
              </div>
            {/if}

            <div class="space-y-2 border-t border-border/50 pt-2">
              {#if scopedPrograms.length > 0}
                {#each scopedPrograms.slice(0, 5) as prog (prog.code)}
                  {@const placedPct = Math.min(100, Math.max(0, prog.utilization_percent ?? 0))}
                  {@const choicePct = Math.min(100, Math.max(0, prog.demand_percent ?? 0))}
                  <div class="space-y-1.5 rounded-lg border border-border/60 bg-muted/25 p-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-1.5">
                      <div class="flex min-w-0 items-center gap-1.5">
                        <span class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{prog.code}</span>
                        <span class="truncate text-[11px] font-semibold text-foreground">{prog.name}</span>
                        <Badge variant="outline" class="shrink-0 font-mono text-[9px]">{prog.department_code}</Badge>
                      </div>
                      <div class="flex shrink-0 flex-wrap items-center gap-2 font-mono text-[10px]">
                        <span class="text-emerald-600 dark:text-emerald-400">Placed {prog.course_placed ?? prog.actual_committed ?? 0}/{prog.planned_quota}</span>
                        <span class="text-muted-foreground">1st-Choice {prog.first_choice_demand}</span>
                      </div>
                    </div>
                    <div class="relative h-2 w-full overflow-hidden rounded-full border border-border/70 bg-muted/90" aria-label="{prog.code} quota meter">
                      <div class="absolute inset-y-0 left-1/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-2/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-3/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                      <div class="absolute inset-y-0 left-0 rounded-full border-r border-sky-500 bg-sky-500/35 transition-all duration-300" style="width: {choicePct}%"></div>
                      <div class="relative h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-300" style="width: {placedPct}%"></div>
                    </div>
                  </div>
                {/each}
              {:else}
                <p class="rounded-lg bg-muted/30 py-6 text-center text-xs text-muted-foreground">No active programs are available in this scope.</p>
              {/if}
            </div>

            <div class="border-t border-border/50 pt-2 text-right">
              <Link href="/reports" class="font-mono text-[10px] font-bold text-primary hover:underline">
                View All Programs &amp; Quota Qualifiers in Reports &rarr;
              </Link>
            </div>
          </BentoCard>

          <!-- Cycle Admissions Velocity -->
          <BentoCard class="flex flex-col justify-between space-y-4 p-4 sm:p-5 lg:col-span-4">
            <div>
              <h2 class="text-sm font-bold text-foreground">Admissions Intake Pulse</h2>
              <p class="font-mono text-[10px] text-muted-foreground">Current application registration activity</p>
            </div>

            <dl class="space-y-3">
              <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                <dt class="font-mono text-[10px] uppercase text-muted-foreground">Total Ingested</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-foreground">
                  {(registrar.kpis?.total ?? 0).toLocaleString()}
                </dd>
                <p class="mt-1 font-mono text-[10px] text-primary">+{cycleRoadmap?.velocity?.today_count ?? 0} registered today</p>
              </div>

              <div class="rounded-lg border border-border/50 bg-muted/20 p-3">
                <dt class="font-mono text-[10px] uppercase text-muted-foreground">Year-over-Year Delta</dt>
                <dd class="mt-1 text-base font-bold text-foreground">
                  {registrar.kpis?.yoy_growth ?? 'No prior cycle comparison'}
                </dd>
              </div>
            </dl>

            <div class="border-t border-border/50 pt-2 text-right">
              <Link href="/admissions" class="font-mono text-[10px] font-bold text-primary hover:underline">
                Open Admissions Registry &rarr;
              </Link>
            </div>
          </BentoCard>
        </div>
      </section>
    {:else}
    {#if cycleRoadmap}
      {@const pct = Math.min(100, Math.max(0, cycleRoadmap.progress_percent ?? 0))}
      {@const arcRadius = 58}
      {@const arcCircumference = Math.PI * arcRadius}
      {@const arcOffset = arcCircumference - (pct / 100) * arcCircumference}
      {@const testedCount = cycleRoadmap.milestones?.find((stage) => stage.key === 'examination')?.count ?? 0}
      {@const completedSessionCount = cycleRoadmap.milestones?.find((stage) => stage.key === 'scoring')?.count ?? 0}

      <section class="space-y-3" data-tour="operations-cycle-road-meter">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
          <BentoCard class="space-y-4 p-4 sm:p-5 lg:col-span-8">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex min-w-0 items-center gap-4">
                <div class="relative flex shrink-0 flex-col items-center justify-center">
                  <svg viewBox="0 0 140 84" class="h-20 w-32 overflow-visible" aria-hidden="true">
                    <path
                      d="M 12 72 A 58 58 0 0 1 128 72"
                      fill="none"
                      stroke="currentColor"
                      class="text-muted/60"
                      stroke-width="11"
                      stroke-linecap="round"
                    />
                    <path
                      d="M 12 72 A 58 58 0 0 1 128 72"
                      fill="none"
                      stroke="currentColor"
                      class="text-primary transition-all duration-500"
                      stroke-width="11"
                      stroke-linecap="round"
                      stroke-dasharray={arcCircumference}
                      stroke-dashoffset={arcOffset}
                    />
                  </svg>
                  <div class="-mt-7 text-center">
                    <div class="font-mono text-xl font-extrabold tabular-nums text-foreground">{pct}%</div>
                    <div class="font-mono text-[9px] uppercase tracking-wider text-muted-foreground">Cycle Elapsed</div>
                  </div>
                </div>

                <div class="min-w-0 space-y-1">
                  <h2 class="text-base font-bold text-foreground">Academic Cycle Road Meter</h2>
                  <p class="font-mono text-xs text-muted-foreground">{cycleRoadmap.cycle_label}</p>
                  <p class="inline-flex items-center gap-1.5 font-mono text-xs text-foreground">
                    <Clock class="size-3.5 text-primary" />
                    Day {cycleRoadmap.days_elapsed} of {cycleRoadmap.days_total} &middot; {cycleRoadmap.days_remaining}d remaining
                  </p>
                  <p class="font-mono text-[10px] text-muted-foreground">
                    <span>{cycleRoadmap.start_date} &rarr; {cycleRoadmap.end_date}</span>
                    {#if cycleRoadmap.window_label}
                      <span class="text-muted-foreground/75">&middot; Intake: {cycleRoadmap.window_label}</span>
                    {/if}
                  </p>
                </div>
              </div>

            </div>

            <div class="space-y-3 border-t border-border/50 pt-3">

              <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                {#each cycleRoadmap.milestones ?? [] as stage (stage.key)}
                  <div class="min-w-0 rounded-lg border p-2 transition-colors {stage.status === 'completed' ? 'border-emerald-500/30 bg-emerald-500/5' : stage.status === 'current' ? 'border-primary/50 bg-primary/10 ring-1 ring-primary/30' : 'border-border/50 bg-muted/30 opacity-80'}">
                    <div class="flex items-center justify-between gap-1">
                      <span class="font-mono text-[10px] font-bold uppercase {stage.status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : stage.status === 'current' ? 'text-primary' : 'text-muted-foreground'}">
                        {stage.step}. {stage.label}
                      </span>
                      {#if stage.status === 'completed'}
                        <CheckCircle2 class="size-3.5 shrink-0 text-emerald-500" />
                      {:else if stage.status === 'current'}
                        <span class="size-2 shrink-0 animate-pulse rounded-full bg-primary"></span>
                      {/if}
                    </div>
                    <div class="mt-1 truncate font-mono text-[10px] text-muted-foreground" title={stage.metric}>{stage.metric}</div>
                  </div>
                {/each}
              </div>
            </div>
          </BentoCard>

          <BentoCard class="flex flex-col gap-3 p-4 sm:p-5 lg:col-span-4">
            <div class="flex items-start justify-between gap-2">
              <div>
                <h2 class="text-sm font-bold text-foreground">Cycle Summary</h2>
                <p class="text-xs text-muted-foreground">Current academic cycle</p>
              </div>
              <Activity class="size-4 shrink-0 text-primary" />
            </div>

            <dl class="grid grid-cols-2 gap-x-4 gap-y-5">
              <div class="min-w-0">
                <dt class="text-xs text-muted-foreground">Applications</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-foreground">
                  {(cycleRoadmap.velocity?.total_applications ?? 0).toLocaleString()}
                </dd>
              </div>
              <div class="min-w-0">
                <dt class="text-xs text-muted-foreground">Applicants tested</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-foreground">{testedCount.toLocaleString()}</dd>
              </div>
              <div class="min-w-0">
                <dt class="text-xs text-muted-foreground">Exam sessions completed</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-foreground">{completedSessionCount.toLocaleString()}</dd>
              </div>
              <div class="min-w-0">
                <dt class="text-xs text-muted-foreground">Reserved program places</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-foreground">{placementSummary ? placementSummary.placed.toLocaleString() : '—'}</dd>
                <p class="mt-1 text-xs text-muted-foreground">{placementSummary ? 'Current program commitments' : 'Not available in this scope'}</p>
              </div>
            </dl>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border/50 pt-2">
              <span class="rounded-full border border-primary/20 bg-primary/10 px-2.5 py-1 font-mono text-[11px] font-bold text-primary">
                Today: +{cycleRoadmap.velocity?.today_count ?? 0}
              </span>
              <div class="flex items-center gap-3 font-mono text-[11px]">
                {#if registrar}
                  <Link href="/admissions" class="font-semibold text-foreground hover:text-primary hover:underline">Admissions &rarr;</Link>
                {/if}
                <Link href="/reports" class="font-bold text-primary hover:underline">Velocity &amp; Funnel Plots &rarr;</Link>
              </div>
            </div>
          </BentoCard>
        </div>
      </section>
    {/if}

    <section class="space-y-3" data-tour="operations-setup-readiness">
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-12">
        {#if setupReadiness}
          {@const readyPct = Math.min(100, Math.max(0, setupReadiness.overall_percent ?? 0))}
          {@const ringRadius = 36}
          {@const ringCircumference = 2 * Math.PI * ringRadius}
          {@const ringOffset = ringCircumference - (readyPct / 100) * ringCircumference}
          {@const examTrack = setupReadiness.tracks?.find((track) => track.key === 'exam_preparation') ?? null}
          {@const institutionalTrack = setupReadiness.tracks?.find((track) => track.key === 'institutional_setup') ?? null}

          <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-5">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm font-bold text-foreground">Institutional Setup &amp; Governance</h2>
              <Link href="/setup" class="shrink-0 font-mono text-[10px] font-semibold text-primary hover:underline">Open Setup &rarr;</Link>
            </div>

            <div class="flex items-center gap-3">
              <div class="relative flex shrink-0 items-center justify-center">
                <svg viewBox="0 0 88 88" class="size-20 -rotate-90" aria-hidden="true">
                  <circle cx="44" cy="44" r={ringRadius} fill="none" stroke="currentColor" class="text-muted/60" stroke-width="8" />
                  <circle
                    cx="44"
                    cy="44"
                    r={ringRadius}
                    fill="none"
                    stroke="currentColor"
                    class="text-emerald-500 transition-all duration-500"
                    stroke-width="8"
                    stroke-linecap="round"
                    stroke-dasharray={ringCircumference}
                    stroke-dashoffset={ringOffset}
                  />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                  <span class="font-mono text-lg font-extrabold tabular-nums text-foreground">{readyPct}%</span>
                  <span class="font-mono text-[8px] uppercase text-muted-foreground">Ready</span>
                </div>
              </div>
              <div class="min-w-0 space-y-0.5">
                <div class="font-mono text-xs font-bold text-foreground">
                  {setupReadiness.passed} of {setupReadiness.total} Readiness Gates
                </div>
                <p class="text-[10px] text-muted-foreground">Institutional checklist progress</p>
              </div>
            </div>

            <div class="space-y-2 border-t border-border/50 pt-2">
              <div class="space-y-1">
                <div class="flex items-center justify-between gap-2 font-mono text-[10px]">
                  <span class="text-foreground">Exam preparation</span>
                  <span class="text-muted-foreground">{examTrack?.passed ?? 0}/{examTrack?.total ?? 0} ({examTrack?.percent ?? 0}%)</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                  <div class="h-full rounded-full bg-emerald-500" style="width: {examTrack?.percent ?? 0}%"></div>
                </div>
              </div>
              <div class="space-y-1">
                <div class="flex items-center justify-between gap-2 font-mono text-[10px]">
                  <span class="text-foreground">Institutional setup</span>
                  <span class="text-muted-foreground">{institutionalTrack?.passed ?? 0}/{institutionalTrack?.total ?? 0} ({institutionalTrack?.percent ?? 0}%)</span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-muted">
                  <div class="h-full rounded-full bg-sky-500" style="width: {institutionalTrack?.percent ?? 0}%"></div>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-2 border-t border-border/50 pt-2">
              <div class="rounded-lg bg-muted/35 p-2 text-center">
                <div class="font-mono text-[9px] uppercase text-muted-foreground">Exam Rooms</div>
                <div class="font-mono text-base font-bold tabular-nums text-foreground">{setupReadiness.facility_health?.active_exam_rooms ?? 0}</div>
              </div>
              <div class="rounded-lg bg-muted/35 p-2 text-center">
                <div class="font-mono text-[9px] uppercase text-muted-foreground">Delegations</div>
                <div class="font-mono text-base font-bold tabular-nums text-foreground">{setupReadiness.facility_health?.active_setup_delegations?.length ?? 0}</div>
              </div>
              <div class="rounded-lg bg-muted/35 p-2 text-center">
                <div class="font-mono text-[9px] uppercase text-muted-foreground">Audit (24h)</div>
                <div class="font-mono text-base font-bold tabular-nums text-foreground">{setupReadiness.supervisory_audit?.last_24_hours ?? 0}</div>
              </div>
            </div>
          </BentoCard>
        {:else}
          <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-5">
            <div class="flex items-center justify-between gap-2">
              <h2 class="text-sm font-bold text-foreground">Office Operational Queue</h2>
              <Activity class="size-4 shrink-0 text-primary" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div class="rounded-lg border border-border/60 bg-muted/35 p-3">
                <div class="font-mono text-[10px] uppercase text-muted-foreground">Total Applications</div>
                <div class="mt-1 font-mono text-xl font-extrabold tabular-nums text-foreground">
                  {(registrar?.kpis?.total ?? cycleRoadmap?.velocity?.total_applications ?? 0).toLocaleString()}
                </div>
              </div>
              <div class="rounded-lg border border-border/60 bg-muted/35 p-3">
                <div class="font-mono text-[10px] uppercase text-muted-foreground">Verified / Accepted</div>
                <div class="mt-1 font-mono text-xl font-extrabold tabular-nums text-foreground">{registrar?.kpis?.completed ?? '—'}</div>
              </div>
              <div class="rounded-lg border border-border/60 bg-muted/35 p-3">
                <div class="font-mono text-[10px] uppercase text-muted-foreground">Exam Sessions</div>
                <div class="mt-1 font-mono text-xl font-extrabold tabular-nums text-foreground">
                  {guidance?.kpis?.scheduled_sessions ?? cycleRoadmap?.milestones?.find((stage) => stage.key === 'scheduling')?.count ?? '—'}
                </div>
              </div>
              <div class="rounded-lg border border-border/60 bg-muted/35 p-3">
                <div class="font-mono text-[10px] uppercase text-muted-foreground">Pending Scoring</div>
                <div class="mt-1 font-mono text-xl font-extrabold tabular-nums text-foreground">{guidance?.kpis?.pending_scoring ?? '—'}</div>
              </div>
            </div>
          </BentoCard>
        {/if}

        <BentoCard class="space-y-3 p-4 sm:p-5 lg:col-span-7">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <h2 class="text-sm font-bold text-foreground">Top Program Quota &amp; Course Placement Snapshot</h2>
              <p class="font-mono text-[10px] text-muted-foreground">Top 4 Active Programs &middot; Calibrated 0%&ndash;100% quota meters</p>
            </div>
            <div class="flex items-center gap-2 font-mono text-[10px]">
              <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full bg-emerald-500"></span> Placed</span>
              <span class="inline-flex items-center gap-1"><span class="size-2 rounded-full border border-sky-500 bg-sky-500/40"></span> 1st-Choice</span>
            </div>
          </div>

          {#if canFilterDept}
            <div class="flex flex-wrap items-center gap-1.5 border-t border-border/50 pt-2" aria-label="Filter by college">
              <span class="mr-1 font-mono text-[10px] font-bold uppercase text-muted-foreground">College</span>
              <Button size="sm" variant={selectedDept === 'ALL' ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment('ALL')}>
                All
              </Button>
              {#each deptOptions as dept (dept.code)}
                <Button size="sm" variant={selectedDept === dept.code ? 'default' : 'outline'} class="h-7 px-2.5 font-mono text-[10px]" onclick={() => selectDepartment(dept.code)}>
                  {dept.code}
                </Button>
              {/each}
            </div>
          {/if}

          <div class="space-y-2 border-t border-border/50 pt-2">
            {#if scopedPrograms.length > 0}
              {#each scopedPrograms.slice(0, 4) as prog (prog.code)}
                {@const placedPct = Math.min(100, Math.max(0, prog.utilization_percent ?? 0))}
                {@const choicePct = Math.min(100, Math.max(0, prog.demand_percent ?? 0))}
                <div class="space-y-1.5 rounded-lg border border-border/60 bg-muted/25 p-2.5">
                  <div class="flex flex-wrap items-center justify-between gap-1.5">
                    <div class="flex min-w-0 items-center gap-1.5">
                      <span class="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary">{prog.code}</span>
                      <span class="truncate text-[11px] font-semibold text-foreground">{prog.name}</span>
                      <Badge variant="outline" class="shrink-0 font-mono text-[9px]">{prog.department_code}</Badge>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2 font-mono text-[10px]">
                      <span class="text-emerald-600 dark:text-emerald-400">Placed {prog.course_placed ?? prog.actual_committed ?? 0}/{prog.planned_quota}</span>
                      <span class="text-muted-foreground">1st-Choice {prog.first_choice_demand}</span>
                    </div>
                  </div>
                  <div class="relative h-2.5 w-full overflow-hidden rounded-full border border-border/70 bg-muted/90" aria-label="{prog.code} quota meter">
                    <div class="absolute inset-y-0 left-1/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                    <div class="absolute inset-y-0 left-2/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                    <div class="absolute inset-y-0 left-3/4 z-10 w-px bg-border/80" aria-hidden="true"></div>
                    <div class="absolute inset-y-0 left-0 rounded-full border-r border-sky-500 bg-sky-500/35 transition-all duration-300" style="width: {choicePct}%"></div>
                    <div class="relative h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-300" style="width: {placedPct}%"></div>
                  </div>
                  <div class="flex justify-between font-mono text-[9px] text-muted-foreground">
                    <span>{placedPct}% placed &middot; {choicePct}% 1st-choice</span>
                    <span>0% &middot; 25% &middot; 50% &middot; 75% &middot; 100%</span>
                  </div>
                </div>
              {/each}
            {:else}
              <p class="rounded-lg bg-muted/30 py-6 text-center text-xs text-muted-foreground">No active programs are available in this scope.</p>
            {/if}
          </div>

          <div class="border-t border-border/50 pt-2 text-right">
            <Link href="/reports" class="font-mono text-[10px] font-bold text-primary hover:underline">
              Open All {scopedPrograms.length} Programs &amp; Comparison Lenses in Analytics &rarr;
            </Link>
          </div>
        </BentoCard>
      </div>
    </section>
    {/if}

    <section class="space-y-3" data-tour="operations-activity">
      <div class="flex items-center justify-between gap-2">
        <h2 class="text-sm font-bold text-foreground">Recent Activity Stream</h2>
        {#if canViewAuditLogs}
          <Link href="/staff/logs" class="shrink-0 font-mono text-[11px] font-bold text-primary hover:underline">Open Audit Log &rarr;</Link>
        {/if}
      </div>
      <BentoCard class="p-3 sm:p-4">
        {#if myActivity.length > 0}
          <div class="grid grid-cols-1 gap-2.5 md:grid-cols-2 lg:grid-cols-4">
            {#each myActivity.slice(0, 4) as entry (entry.id)}
              <div class="min-w-0 rounded-lg border border-border/60 bg-muted/25 p-3">
                <div class="flex items-start gap-2">
                  <Activity class="mt-0.5 size-3.5 shrink-0 text-primary" />
                  <div class="min-w-0 flex-1">
                    <p class="line-clamp-2 text-xs font-medium text-foreground">{entry.summary}</p>
                  </div>
                </div>
                <p class="mt-2 text-right font-mono text-[9px] text-muted-foreground">{formatRelative(entry.created_at)}</p>
              </div>
            {/each}
          </div>
        {:else}
          <p class="py-4 text-center text-xs text-muted-foreground">No recent activity recorded.</p>
        {/if}
      </BentoCard>
    </section>

    <XiamNpcDialog tourKey="staff-operations-dashboard" context={operationsContext} bind:open={tourOpen} />
  </div>
</StaffLayout>

