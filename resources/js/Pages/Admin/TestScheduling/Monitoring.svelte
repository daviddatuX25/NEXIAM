<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import * as Table from '@/Components/ui/table';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import ElapsedTime from '@/Components/ElapsedTime.svelte';
  import { ClipboardList, Eye, Pencil, Users, CheckCircle2, UserX, School } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';

  import { formatDate } from '@/lib/date-utils';

  let viewMode = $state('responsive');

  let { sessions = [], isProctorView = false, breadcrumbParent = { label: 'Exam Monitoring', href: '/admin/exam-monitoring' } } = $props();

  const breadcrumbs = $derived([
    breadcrumbParent,
    ...(isProctorView ? [{ label: 'Monitoring' }] : [])
  ]);

  const totalRooms = $derived(sessions.length);
  const activeRooms = $derived(sessions.filter(s => s.status === 'in_progress').length);
  const totalAssignedApplicants = $derived(sessions.reduce((acc, s) => acc + (s.total_count ?? 0), 0));
  const totalPresentApplicants = $derived(sessions.reduce((acc, s) => acc + (s.present_count ?? 0), 0));
  const totalAbsentApplicants = $derived(sessions.reduce((acc, s) => acc + (s.absent_count ?? 0), 0));
  const totalSubmittedApplicants = $derived(sessions.reduce((acc, s) => acc + (s.submitted_count ?? 0), 0));

  const totalProctors = $derived.by(() => {
    const proctorIds = new Set();
    sessions.forEach(s => {
      if (s.proctors) {
        s.proctors.forEach(p => proctorIds.add(p.id));
      }
    });
    return proctorIds.size;
  });

  function rosterHref(sessionId) {
    return isProctorView ? `/proctor/sessions/${sessionId}` : `/admin/test-admin/sessions/${sessionId}/roster`;
  }

  function isOvertime(session) {
    if (session.status !== 'in_progress') return false;
    const endTime = session.extended_end_time || session.end_time;
    if (!endTime) return false;
    
    const parts = String(endTime).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins = parseInt(parts[1], 10) || 0;
    
    const end = new Date();
    end.setHours(hours, mins, 0, 0);
    return new Date() > end;
  }

  $effect(() => {
    const interval = setInterval(() => {
      router.reload({ only: ['sessions'] });
    }, 15000);
    return () => clearInterval(interval);
  });



  function formatTime(value) {
    if (value == null || value === '') return '—';
    const parts = String(value).split(':');
    const hours = parseInt(parts[0], 10) || 0;
    const mins = parseInt(parts[1], 10) || 0;
    const h = hours % 12 || 12;
    const ampm = hours < 12 ? 'AM' : 'PM';
    return `${h}:${String(mins).padStart(2, '0')} ${ampm}`;
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <!-- Premium Live Header Banner -->
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-border/60 pb-5">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight text-foreground flex items-center gap-3">
          Exam Monitoring
          {#if sessions.some(s => s.status === 'in_progress')}
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-500 animate-pulse border border-emerald-500/20">
              <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
              LIVE
            </span>
          {:else}
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-600 border border-amber-500/20">
              <span class="h-2 w-2 rounded-full bg-amber-500/80"></span>
              STANDBY
            </span>
          {/if}
        </h1>
        <p class="text-sm text-muted-foreground mt-1">
          Real-time insights and attendance statistics for active exam rooms. Data refreshes automatically every 15 seconds.
        </p>
      </div>
    </div>

    {#if sessions.length === 0}
      <div class="relative overflow-hidden rounded-2xl border border-border/60 bg-card/45 backdrop-blur-md p-12 text-center shadow-lg max-w-2xl mx-auto my-8 transition-all duration-300 hover:shadow-xl hover:border-border/80">
        <!-- Abstract Glassmorphic Decorative Rings / Signal Waves -->
        <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-32 h-32 bg-primary/10 rounded-full blur-3xl -ml-16 -mb-16 pointer-events-none"></div>

        <div class="flex flex-col items-center max-w-md mx-auto">
          <!-- Animated live signal sensor visual -->
          <div class="relative flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-500 border border-amber-500/20 mb-6 shadow-inner">
            <span class="absolute inline-flex h-full w-full rounded-2xl bg-amber-500/5 animate-pulse opacity-75"></span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
            </svg>
          </div>

          <h2 class="text-xl font-bold tracking-tight text-foreground mb-2">No Active Testing Sessions</h2>
          <p class="text-sm text-muted-foreground leading-relaxed mb-6">
            Real-time exam rooms and candidate attendance flows will appear here once proctors initiate session schedules and start student testing.
          </p>

          {#if !isProctorView}
            <Link href="/admin/test-admin/sessions">
              <Button size="default" class="font-bold shadow-md hover:shadow-lg transition-all group">
                Go to Scheduled Sessions
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 ml-1.5 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
              </Button>
            </Link>
          {/if}
        </div>
      </div>
    {:else}
      <!-- Top-Level Live KPI Stats Grid -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
        <!-- 1. Active Rooms -->
        <div class="relative overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">Active Session Rooms</p>
            <div class="rounded-lg bg-emerald-500/10 p-2 text-emerald-500 border border-emerald-500/10">
              <School class="h-5 w-5" />
            </div>
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold tracking-tight">{activeRooms}</span>
            <span class="text-sm text-muted-foreground">/ {totalRooms} rooms active</span>
          </div>
          <p class="mt-2 text-xs text-muted-foreground">
            {totalRooms - activeRooms} sessions scheduled or on standby
          </p>
        </div>

        <!-- 2. Present Students -->
        <div class="relative overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">Total Checked-In</p>
            <div class="rounded-lg bg-blue-500/10 p-2 text-blue-500 border border-blue-500/10">
              <Users class="h-5 w-5" />
            </div>
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold tracking-tight">{totalPresentApplicants}</span>
            <span class="text-sm text-muted-foreground">/ {totalAssignedApplicants} candidates</span>
          </div>
          <p class="mt-2 text-xs text-muted-foreground">
            {totalAssignedApplicants > 0 ? Math.round((totalPresentApplicants / totalAssignedApplicants) * 100) : 0}% check-in attendance rate
          </p>
        </div>

        <!-- 3. Submitted Exams -->
        <div class="relative overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">Exams Submitted</p>
            <div class="rounded-lg bg-violet-500/10 p-2 text-violet-500 border border-violet-500/10">
              <CheckCircle2 class="h-5 w-5" />
            </div>
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold tracking-tight">{totalSubmittedApplicants}</span>
            <span class="text-sm text-muted-foreground">submitted</span>
          </div>
          <p class="mt-2 text-xs text-muted-foreground">
            {totalPresentApplicants > 0 ? Math.round((totalSubmittedApplicants / totalPresentApplicants) * 100) : 0}% submission completion rate
          </p>
        </div>

        <!-- 4. Absent Students -->
        <div class="relative overflow-hidden rounded-xl border border-border bg-card p-5 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-muted-foreground">Absent / Unaccounted</p>
            <div class="rounded-lg bg-amber-500/10 p-2 text-amber-500 border border-amber-500/10">
              <UserX class="h-5 w-5" />
            </div>
          </div>
          <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold tracking-tight">{totalAbsentApplicants}</span>
            <span class="text-sm text-muted-foreground">candidates absent</span>
          </div>
          <p class="mt-2 text-xs text-muted-foreground">
            {totalAssignedApplicants - totalPresentApplicants - totalAbsentApplicants} candidates pending arrival
          </p>
        </div>
      </div>

      <div class="min-w-0 max-w-full">
        <SwitchableListView bind:viewMode class="sm:space-y-3">
          {#snippet table()}
            <Table.Root class="w-full min-w-[640px] text-sm">
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head class="px-4 py-3">Room & Capacity</Table.Head>
                  <Table.Head class="px-4 py-3">Proctors</Table.Head>
                  <Table.Head class="px-4 py-3">Schedule / Elapsed</Table.Head>
                  <Table.Head class="px-4 py-3">Check-in Progress</Table.Head>
                  <Table.Head class="px-4 py-3">Submission Progress</Table.Head>
                  <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each sessions as session (session.id)}
                  <Table.Row class="hover:bg-muted/30 transition-colors">
                    <!-- Room & Capacity -->
                    <Table.Cell class="px-4 py-3.5">
                      <div class="flex flex-col">
                        <span class="font-semibold text-foreground text-sm flex items-center gap-1.5">
                          {session.room?.name ?? '—'}
                          {#if session.room?.building}
                            <span class="text-xs text-muted-foreground font-normal">({session.room.building})</span>
                          {/if}
                        </span>
                        {#if session.room?.capacity}
                          <span class="text-[11px] text-muted-foreground/90 mt-0.5">
                            Seat Util: <span class="font-medium text-foreground">{session.total_count ?? 0}</span> / {session.room.capacity} ({session.room.capacity > 0 ? Math.round(((session.total_count ?? 0) / session.room.capacity) * 100) : 0}%)
                          </span>
                        {:else}
                          <span class="text-[11px] text-muted-foreground/90 mt-0.5">Capacity unknown</span>
                        {/if}
                      </div>
                    </Table.Cell>

                    <!-- Proctors -->
                    <Table.Cell class="px-4 py-3.5">
                      <div class="flex flex-wrap gap-1 max-w-[180px]">
                        {#if session.proctors && session.proctors.length > 0}
                          {#each session.proctors as proctor}
                            <Badge variant="outline" class="text-[10px] py-0 px-1.5 bg-muted/40 text-muted-foreground border-border/80 truncate">
                              {proctor.name}
                            </Badge>
                          {/each}
                        {:else}
                          <span class="text-[11px] text-muted-foreground italic">No proctors assigned</span>
                        {/if}
                      </div>
                    </Table.Cell>

                    <!-- Schedule / Elapsed -->
                    <Table.Cell class="px-4 py-3.5">
                      <div class="flex flex-col gap-0.5">
                        <span class="text-xs font-medium text-foreground">{formatDate(session.date)}</span>
                        <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                          <span>{formatTime(session.start_time)}</span>
                          <span>•</span>
                          <span class="font-mono bg-muted px-1 rounded flex items-center gap-1">
                            {#if session.status === 'in_progress'}
                              <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                            {/if}
                            <ElapsedTime startedAt={session.started_at} />
                          </span>
                        </div>
                        {#if isOvertime(session)}
                          <div class="mt-1">
                            <Badge variant="destructive" class="animate-pulse text-[9px] py-0 px-1.5 font-bold uppercase tracking-wider scale-95 origin-left">
                              Overtime
                            </Badge>
                          </div>
                        {/if}
                      </div>
                    </Table.Cell>

                    <!-- Check-in Progress -->
                    <Table.Cell class="px-4 py-3.5">
                      <div class="space-y-1.5 w-32">
                        <div class="flex items-center justify-between text-[11px]">
                          <span class="text-muted-foreground">Present: {session.present_count ?? 0}/{session.total_count ?? 0}</span>
                          <span class="font-bold text-foreground">{session.total_count > 0 ? Math.round(((session.present_count ?? 0) / session.total_count) * 100) : 0}%</span>
                        </div>
                        <div class="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                          <div class="h-full bg-blue-500 rounded-full transition-all duration-500" style="width: {session.total_count > 0 ? ((session.present_count ?? 0) / session.total_count) * 100 : 0}%"></div>
                        </div>
                      </div>
                    </Table.Cell>

                    <!-- Submission Progress -->
                    <Table.Cell class="px-4 py-3.5">
                      <div class="space-y-1.5 w-32">
                        <div class="flex items-center justify-between text-[11px]">
                          <span class="text-muted-foreground">Done: {session.submitted_count ?? 0}/{session.present_count ?? 0}</span>
                          <span class="font-bold text-foreground">{session.present_count > 0 ? Math.round(((session.submitted_count ?? 0) / session.present_count) * 100) : 0}%</span>
                        </div>
                        <div class="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                          <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" style="width: {session.present_count > 0 ? ((session.submitted_count ?? 0) / session.present_count) * 100 : 0}%"></div>
                        </div>
                      </div>
                    </Table.Cell>

                    <!-- Actions -->
                    <Table.Cell class="px-4 py-3.5 text-center">
                      <Link href={rosterHref(session.id)}>
                        <Button variant="outline" size="sm" class="h-8 px-3 text-xs font-semibold shadow-sm hover:bg-accent hover:text-accent-foreground">
                          <ClipboardList class="mr-1.5 h-3.5 w-3.5" />
                          View Session
                        </Button>
                      </Link>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          {/snippet}

          {#snippet cards()}
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {#each sessions as session (session.id)}
                <div class="group relative rounded-xl border border-border bg-card shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col justify-between">
                  <!-- Overtime glow top border if overtime -->
                  {#if isOvertime(session)}
                    <div class="absolute top-0 left-0 right-0 h-1 bg-destructive animate-pulse"></div>
                  {/if}

                  <div class="p-5 space-y-4 flex-grow">
                    <!-- Top section: Room title and status -->
                    <div class="flex justify-between items-start gap-2">
                      <div>
                        <h3 class="font-bold tracking-tight text-foreground text-base leading-snug group-hover:text-primary transition-colors">
                          {session.room?.name ?? '—'}
                          {#if session.room?.building}
                            <span class="text-xs text-muted-foreground font-normal block mt-0.5">{session.room.building}</span>
                          {/if}
                        </h3>
                        <p class="text-xs text-muted-foreground mt-1">
                          {formatDate(session.date)} • {formatTime(session.start_time)}
                        </p>
                      </div>

                      <div class="flex flex-col items-end gap-1.5">
                        <span class="font-mono text-xs bg-muted px-2 py-0.5 rounded font-semibold text-foreground flex items-center gap-1.5 border border-border/50">
                          {#if session.status === 'in_progress'}
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                          {/if}
                          <ElapsedTime startedAt={session.started_at} />
                        </span>
                        {#if isOvertime(session)}
                          <Badge variant="destructive" class="animate-pulse text-[9px] py-0 px-1.5 font-bold uppercase tracking-wider">
                            Overtime
                          </Badge>
                        {/if}
                      </div>
                    </div>

                    <!-- Seat Capacity Detail -->
                    {#if session.room?.capacity}
                      <div class="bg-muted/30 rounded-lg p-2.5 flex items-center justify-between text-xs border border-border/40">
                        <span class="text-muted-foreground font-medium">Room Capacity</span>
                        <div class="text-right">
                          <span class="font-bold text-foreground">{session.total_count ?? 0}</span>
                          <span class="text-muted-foreground">/ {session.room.capacity} seats</span>
                          <span class="text-[10px] bg-muted px-1 py-0.5 rounded ml-1 font-semibold text-muted-foreground/80">
                            {session.room.capacity > 0 ? Math.round(((session.total_count ?? 0) / session.room.capacity) * 100) : 0}%
                          </span>
                        </div>
                      </div>
                    {/if}

                    <!-- Proctors Section -->
                    <div class="space-y-1.5">
                      <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Assigned Proctors</p>
                      <div class="flex flex-wrap gap-1">
                        {#if session.proctors && session.proctors.length > 0}
                          {#each session.proctors as proctor}
                            <Badge variant="outline" class="text-[10px] py-0 px-1.5 bg-muted/40 text-muted-foreground border-border/80 truncate">
                              {proctor.name}
                            </Badge>
                          {/each}
                        {:else}
                          <span class="text-xs text-muted-foreground italic">No proctors assigned</span>
                        {/if}
                      </div>
                    </div>

                    <!-- Reactive Attendance Statistics (Dual Progress Bars) -->
                    <div class="space-y-3 pt-2 border-t border-border/50">
                      <!-- Check-in Progress -->
                      <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                          <span class="text-muted-foreground font-medium flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                            Checked In Attendance
                          </span>
                          <span class="font-bold text-foreground">{session.present_count ?? 0} / {session.total_count ?? 0} ({session.total_count > 0 ? Math.round(((session.present_count ?? 0) / session.total_count) * 100) : 0}%)</span>
                        </div>
                        <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                          <div class="h-full bg-blue-500 rounded-full transition-all duration-500" style="width: {session.total_count > 0 ? ((session.present_count ?? 0) / session.total_count) * 100 : 0}%"></div>
                        </div>
                      </div>

                      <!-- Submissions Progress -->
                      <div class="space-y-1">
                        <div class="flex items-center justify-between text-xs">
                          <span class="text-muted-foreground font-medium flex items-center gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Exams Submitted
                          </span>
                          <span class="font-bold text-foreground">{session.submitted_count ?? 0} / {session.present_count ?? 0} ({session.present_count > 0 ? Math.round(((session.submitted_count ?? 0) / session.present_count) * 100) : 0}%)</span>
                        </div>
                        <div class="h-2 w-full bg-muted rounded-full overflow-hidden">
                          <div class="h-full bg-emerald-500 rounded-full transition-all duration-500" style="width: {session.present_count > 0 ? ((session.submitted_count ?? 0) / session.present_count) * 100 : 0}%"></div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Action button footer -->
                  <div class="p-5 pt-0 mt-auto bg-muted/10 group-hover:bg-muted/20 transition-colors">
                    <Link href={rosterHref(session.id)} class="w-full mt-4 block">
                      <Button variant="outline" size="sm" class="w-full min-h-[40px] font-bold shadow-sm group-hover:border-primary/50 group-hover:bg-primary/5 transition-all">
                        <ClipboardList class="mr-1.5 h-3.5 w-3.5" />
                        View Full Session Monitor
                      </Button>
                    </Link>
                  </div>
                </div>
              {/each}
            </div>
          {/snippet}
        </SwitchableListView>
      </div>
    {/if}
  </div>
</AuthenticatedLayout>
