<script>
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';

  let { page_state = 'ready', attempts = [], released = [] } = $props();

  function statusLabel(status) {
    const labels = {
      pending: 'Pending review',
      needs_correction: 'Needs your correction',
      accepted: 'Accepted',
      dismissed: 'Dismissed',
      archived: 'Archived',
    };
    return labels[status] ?? status;
  }

  function statusVariant(status) {
    if (status === 'needs_correction') return 'outline';
    if (status === 'accepted') return 'default';
    if (status === 'dismissed') return 'destructive';
    return 'secondary';
  }
</script>

<svelte:head><title>Applicant portal</title></svelte:head>

<ApplicantLayout title="Applicant portal">
  <div class="space-y-6">
    <div class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight">Applicant portal</h1>
      <p class="text-sm text-muted-foreground">
        Your applications and released results. Use Sign out when you finish on a shared device.
      </p>
    </div>

    <E2PageState state={page_state} />

    <Card.Root>
      <Card.Header>
        <Card.Title>Your applications</Card.Title>
      </Card.Header>
      <Card.Content>
        {#if attempts.length === 0}
          <p class="text-sm text-muted-foreground">No applications on file for this account.</p>
        {:else}
          <ul class="space-y-3 text-sm">
            {#each attempts as a (a.id)}
              <li class="space-y-2 border-b border-border/50 py-3 last:border-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                  <span class="font-mono font-medium">{a.reference_code}</span>
                  <Badge variant={statusVariant(a.status)} class="capitalize">
                    {statusLabel(a.status)}
                  </Badge>
                </div>
                {#if a.preferred_program_code}
                  <p class="text-muted-foreground">Preference: {a.preferred_program_code}</p>
                {/if}
                {#if a.needs_correction}
                  <div class="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
                    <p class="text-sm">
                      Registrar asked you to correct this application
                      {#if a.correction_notes}
                        : <span class="text-foreground">{a.correction_notes}</span>
                      {/if}
                    </p>
                    <Link href={`/applicant/applications/${a.id}/correct`}>
                      <Button size="sm">Fix and resubmit</Button>
                    </Link>
                  </div>
                {:else if a.can_quiet_amend}
                  <div class="flex flex-wrap items-center gap-2">
                    <Link href={`/applicant/applications/${a.id}/correct`}>
                      <Button size="sm" variant="outline">Update my details</Button>
                    </Link>
                    <span class="text-xs text-muted-foreground">
                      Quiet fix while still pending review
                    </span>
                  </div>
                {:else if a.frozen_after_accept}
                  <div class="space-y-2">
                    <p class="text-xs text-muted-foreground">
                      Accepted and in the exam pipeline — portal edits are frozen. Contact admissions
                      if something must change; that needs visible authority approval.
                    </p>
                    <Link href="/applicant/schedule">
                      <Button size="sm" variant="outline">View exam schedule</Button>
                    </Link>
                  </div>
                {/if}
              </li>
            {/each}
          </ul>
        {/if}
      </Card.Content>
    </Card.Root>

    <Card.Root>
      <Card.Header>
        <Card.Title>Released results</Card.Title>
      </Card.Header>
      <Card.Content>
        {#if released.length === 0}
          <p class="text-sm text-muted-foreground">No released results yet.</p>
        {:else}
          <ul class="space-y-2 text-sm">
            {#each released as r (r.id)}
              <li class="border-b border-border/50 py-2 last:border-0">
                Attempt #{r.admission_attempt_id} · released {r.released_at}
              </li>
            {/each}
          </ul>
        {/if}
      </Card.Content>
    </Card.Root>
  </div>
</ApplicantLayout>
