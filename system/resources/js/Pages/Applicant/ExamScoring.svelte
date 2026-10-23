<script lang="ts">
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { ClipboardCheck, Clock3, UserRound } from 'lucide-svelte';

  type Scoring = {
    status: 'not_started' | 'in_progress' | 'finalized';
    recordings: { recorded_by: string | null; recorded_at: string | null }[];
    finalization: { finalized_by: string | null; finalized_at: string | null } | null;
  };

  let { page_state = 'denied', scoring = { status: 'not_started', recordings: [], finalization: null } } = $props<{
    page_state: 'denied' | 'ready';
    scoring: Scoring;
  }>();

  function formatDateTime(value: string | null): string {
    if (!value) return 'Not recorded';
    return new Date(value).toLocaleString(undefined, {
      year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit',
    });
  }

  const statusLabel = $derived(scoring.status === 'finalized' ? 'Finalized' : scoring.status === 'in_progress' ? 'In progress' : 'Not started');
</script>

<ApplicantLayout title="Exam Scoring" breadcrumbs={[{ label: 'Exam Scoring' }]}>
  <div class="mx-auto max-w-3xl space-y-5">
    {#if page_state === 'denied'}
      <Card.Root class="">
        <Card.Content class="space-y-2 p-6">
          <Card.Title class="text-xl">Scoring details unavailable</Card.Title>
          <Card.Description class="">No application record is linked to this account.</Card.Description>
        </Card.Content>
      </Card.Root>
    {:else}
      <Card.Root class="overflow-hidden shadow-sm">
        <Card.Header class="border-b p-5 sm:p-6">
          <div class="flex items-start gap-3">
            <ClipboardCheck class="mt-1 size-5 shrink-0 text-primary" aria-hidden="true" />
            <div class="space-y-1">
              <Card.Title class="text-xl">Exam Scoring</Card.Title>
              <Card.Description class="">Current processing status and recorded scoring activity for your application.</Card.Description>
            </div>
          </div>
        </Card.Header>
        <Card.Content class="space-y-6 p-5 sm:p-6">
          <section aria-labelledby="scoring-status-heading" class="space-y-2">
            <h2 id="scoring-status-heading" class="text-sm font-medium text-muted-foreground">Processing status</h2>
            <p class="text-lg font-semibold">{statusLabel}</p>
          </section>

          <section aria-labelledby="recordings-heading" class="space-y-3">
            <div>
              <h2 id="recordings-heading" class="text-base font-semibold">Recorded scoring activity</h2>
              <p class="text-sm text-muted-foreground">These entries identify who recorded current scoring information. Legacy score records show the time of their latest update.</p>
            </div>
            {#if scoring.recordings.length > 0}
              <ol class="space-y-3">
                {#each scoring.recordings as recording, index (index)}
                  <li class="rounded-lg border p-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                      <div class="flex items-start gap-2">
                        <UserRound class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <p class="text-sm"><span class="font-medium">Recorded by:</span> {recording.recorded_by ?? 'Attribution not recorded'}</p>
                      </div>
                      <div class="flex items-start gap-2 text-sm text-muted-foreground">
                        <Clock3 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <time datetime={recording.recorded_at ?? undefined}>{formatDateTime(recording.recorded_at)}</time>
                      </div>
                    </div>
                  </li>
                {/each}
              </ol>
            {:else}
              <p class="rounded-lg border p-4 text-sm text-muted-foreground">No current scoring activity has been recorded.</p>
            {/if}
          </section>

          {#if scoring.finalization}
            <section aria-labelledby="finalization-heading" class="space-y-3 border-t pt-5">
              <div>
                <h2 id="finalization-heading" class="text-base font-semibold">Finalization</h2>
                <p class="text-sm text-muted-foreground">Current scoring finalization record.</p>
              </div>
              <div class="rounded-lg border p-4">
                <p class="text-sm"><span class="font-medium">Finalized by:</span> {scoring.finalization.finalized_by ?? 'Attribution not recorded'}</p>
                <time class="mt-2 block text-sm text-muted-foreground" datetime={scoring.finalization.finalized_at ?? undefined}>
                  {formatDateTime(scoring.finalization.finalized_at)}
                </time>
              </div>
            </section>
          {/if}

          <p class="border-t pt-4 text-sm text-muted-foreground">Score details are not shown on this page.</p>
        </Card.Content>
      </Card.Root>
    {/if}
    <Link href="/applicant/portal" class="inline-flex min-h-11 items-center rounded-md border px-4 py-2 text-sm font-medium hover:bg-accent">Back to Home</Link>
  </div>
</ApplicantLayout>
