<script lang="ts">
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { CalendarDays, CheckCircle2, Clock3, MapPin, Video } from 'lucide-svelte';
  import { parseDate } from '@/lib/date-utils.js';

  type AccessAction = { available: boolean; href: string | null; unavailable_reason: string | null };
  type FollowUp = {
    status: 'review_pending' | 'appearance_requested';
    program: string | null;
    office: { id: number; name: string; building: string | null; floor: string | null; coordinates_available: boolean } | null;
    instruction_recorded: boolean;
  };
  type ReleaseDay = {
    appointment: { status: 'assigned' | 'consulted'; published?: boolean; scheduled_at: string | null; room: string | null } | null;
    completion: { completed: boolean; completed_at: string | null };
    decision: { state: 'completed' | 'deferred' | 'referred' | 'unrecorded'; recorded_at: string | null };
    follow_ups: FollowUp[];
    result: AccessAction & { review_available: boolean };
    program_choice: AccessAction;
    online_encounter: { status: string; href: string | null; join_available: boolean; unavailable_reason: string | null; access_starts_at: string | null; access_ends_at: string | null } | null;
  };
  let { page_state = 'empty', release_day = null } = $props<{
    page_state: 'ready' | 'empty'; release_day: ReleaseDay | null;
  }>();
  const followUps = $derived(release_day?.follow_ups ?? []);
  const appearanceRequested = $derived(followUps.some((item) => item.status === 'appearance_requested'));
  const outcomeTitle = $derived(appearanceRequested ? 'Visit your department office'
    : followUps.length > 0 ? 'Your program review is underway'
      : release_day?.decision?.state === 'deferred' ? 'Your Release Day is under review'
        : release_day?.completion.completed ? 'Your Release Day is complete'
          : release_day?.decision?.state === 'referred' ? 'Your referral is recorded'
            : release_day?.appointment ? 'Your appointment is recorded' : 'Awaiting your Release Day decision');
  const recordedAt = $derived(release_day?.decision?.recorded_at ?? release_day?.completion.completed_at ?? null);

  function formatDateTime(value: string | null): string {
    if (!value) return 'Not recorded';
    const date = parseDate(value);
    if (!date) return 'Not recorded';
    return new Intl.DateTimeFormat(undefined, { month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }).format(date);
  }
  function openOfficeWayfinder(roomId: number): void {
    window.dispatchEvent(new CustomEvent('nexiam:open-wayfinding', { detail: { roomId } }));
  }
</script>

<ApplicantLayout title="Release Day">
  <div class="mx-auto max-w-3xl space-y-6">
    <h1 class="text-2xl font-semibold tracking-tight">Release Day</h1>
    {#if page_state === 'empty' || release_day === null}
      <p class="rounded-lg border bg-card p-5 text-sm text-muted-foreground">No application is linked to this account. Contact the Registrar if you have already applied.</p>
    {:else}
      <Card.Root>
        <Card.Content>
          <div class="space-y-4">
          <div class="flex items-start gap-3">
            {#if release_day.completion.completed && followUps.length === 0}
              <CheckCircle2 class="mt-0.5 size-6 shrink-0 text-primary" aria-hidden="true" />
            {:else if appearanceRequested}
              <MapPin class="mt-0.5 size-6 shrink-0 text-primary" aria-hidden="true" />
            {:else}
              <Clock3 class="mt-0.5 size-6 shrink-0 text-muted-foreground" aria-hidden="true" />
            {/if}
            <div class="space-y-2">
              <h2 class="text-lg font-semibold tracking-tight">{outcomeTitle}</h2>
              <p class="text-sm leading-relaxed text-muted-foreground">
                {#if appearanceRequested}
                  An office visit has been requested. Check the department details below.
                {:else if followUps.length > 0}
                  No office visit has been requested for these reviews.
                {:else if release_day.decision?.state === 'deferred'}
                  More review is needed. No further instruction has been recorded yet.
                {:else if release_day.completion.completed}
                  Your decision is recorded. No further action is needed for this step.
                {:else if release_day.decision?.state === 'referred'}
                  A referral is recorded. No active department follow-up is currently listed.
                {:else if release_day.appointment}
                  Check the recorded date, time and venue below.
                {:else}
                  A completed decision has not been recorded yet.
                {/if}
              </p>
              {#if recordedAt}
                <time class="block text-xs text-muted-foreground" datetime={recordedAt}>Recorded {formatDateTime(recordedAt)}</time>
              {/if}
            </div>
          </div>
          {#if followUps.length > 0}
            <ul class="divide-y divide-border border-t border-border">
              {#each followUps as followUp (followUp)}
                <li class="space-y-2 py-4 last:pb-0">
                  <p class="text-sm font-medium">{followUp.program ?? 'Program review'}</p>
                  {#if followUp.status === 'appearance_requested'}
                    {#if followUp.office}
                      <p class="text-sm text-muted-foreground">{followUp.office.name}{followUp.office.building ? ` · ${followUp.office.building}` : ''}{followUp.office.floor ? ` · ${followUp.office.floor}` : ''}</p>
                      {#if followUp.office.coordinates_available}
                        <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={() => openOfficeWayfinder(followUp.office!.id)}><MapPin class="size-4" aria-hidden="true" />Find department office</button>
                      {:else}
                        <p class="text-xs text-muted-foreground">No map pin is recorded. Use the office details above.</p>
                      {/if}
                    {:else}
                      <p class="text-sm text-muted-foreground">The office location is not recorded. Contact the department for directions.</p>
                    {/if}
                    {#if !followUp.instruction_recorded}<p class="text-xs text-muted-foreground">No visit time or additional instruction is recorded.</p>{/if}
                  {:else}
                    <p class="text-sm text-muted-foreground">Review pending. No appearance requested.</p>
                  {/if}
                </li>
              {/each}
            </ul>
          {/if}
          </div>
        </Card.Content>
      </Card.Root>

      {#if release_day.appointment}
        <details class="rounded-lg border bg-card p-4" open={!release_day.completion.completed && followUps.length === 0}>
          <summary class="flex min-h-8 cursor-pointer items-center justify-between gap-2 text-sm font-medium">
            <span class="flex items-center gap-2"><CalendarDays class="size-4" aria-hidden="true" />Appointment details</span>
            {#if release_day.appointment.published === false}
              <span class="rounded-full border border-amber-500/40 bg-amber-500/15 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-200">Tentative</span>
            {/if}
          </summary>
          <div class="space-y-1 pt-3 text-sm text-muted-foreground">
            <p>{formatDateTime(release_day.appointment.scheduled_at)}</p>
            {#if release_day.appointment.room}<p>{release_day.appointment.room}</p>{/if}
            <p>{release_day.appointment.status === 'consulted' ? 'Attendance recorded.' : 'Recorded appointment.'}</p>
          </div>
        </details>
      {/if}

      {#if release_day.online_encounter}
        <section class="space-y-2" aria-label="Online encounter">
          {#if release_day.online_encounter.join_available && release_day.online_encounter.href}
            <Link href={release_day.online_encounter.href} class="inline-flex min-h-11 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"><Video class="size-4" aria-hidden="true" />Join online encounter</Link>
          {:else}
            <p class="text-sm text-muted-foreground">{release_day.online_encounter.unavailable_reason ?? 'Joining is not currently available.'}</p>
          {/if}
        </section>
      {/if}

      <div class="divide-y divide-border border-t border-border">
        <section class="space-y-2 py-4" aria-label="My Results">
          {#if release_day.result.review_available && release_day.result.href}
            <Link href={release_day.result.href} class="inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">Review My Results</Link>
          {:else}
            <h2 class="text-sm font-medium">My Results</h2>
          {/if}
          {#if !release_day.result.available && release_day.result.unavailable_reason}<p class="text-sm text-muted-foreground">{release_day.result.unavailable_reason}</p>{/if}
        </section>
        {#if release_day.program_choice.available && release_day.program_choice.href}
          <section class="flex flex-wrap items-center justify-between gap-3 py-4" aria-label="Optional program plans">
            <div class="space-y-1"><h2 class="text-sm font-medium">Your program plans</h2><p class="text-xs text-muted-foreground">Optional. Let us know if your plans changed.</p></div>
            <Link href={release_day.program_choice.href} class="inline-flex min-h-11 items-center rounded-md border px-3 text-sm font-medium hover:bg-muted">Review program plans</Link>
          </section>
        {/if}
        <section class="space-y-2 py-4" aria-label="Registrar verification">
          <Link href="/applicant/admission-clearance" class="inline-flex min-h-10 items-center text-sm font-medium text-primary underline-offset-4 hover:underline">View admission clearance</Link>
          <p class="text-xs leading-relaxed text-muted-foreground">Registrar verification is separate. Check your documents and any recorded instructions there.</p>
        </section>
      </div>
    {/if}
  </div>
</ApplicantLayout>
