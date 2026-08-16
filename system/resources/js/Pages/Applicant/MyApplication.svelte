<script>
  /**
   * My Application — read-only own record (2026-08-15-applicant-portal-interface).
   * No edit affordance except the needs_correction-gated fix prompt.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { AlertTriangle, CalendarClock, FileText } from 'lucide-svelte';

  let { application = null } = $props();

  const statusLabel = (status) => {
    const labels = {
      pending: 'Pending review',
      needs_correction: 'Needs your correction',
      accepted: 'Accepted',
      dismissed: 'Dismissed',
      archived: 'Archived',
    };
    return labels[status] ?? status;
  };

  const statusVariant = (status) => {
    if (status === 'needs_correction') return 'destructive';
    if (status === 'accepted') return 'default';
    if (status === 'dismissed') return 'destructive';
    return 'secondary';
  };

  const rows = $derived(
    application
      ? [
          { label: 'Full name', value: application.personal?.name },
          { label: 'Birthdate', value: application.personal?.birthdate },
          { label: 'Contact phone', value: application.personal?.contact_phone },
          { label: 'Contact email', value: application.personal?.contact_email },
          { label: 'Address', value: application.personal?.address },
          { label: 'Applicant type', value: application.personal?.applicant_type },
          { label: 'Last school', value: application.personal?.last_school },
          { label: 'Strand', value: application.personal?.strand },
          { label: 'GWA', value: application.personal?.gwa },
        ].filter((row) => row.value)
      : [],
  );
</script>

<svelte:head><title>My application</title></svelte:head>

<ApplicantLayout title="My application" breadcrumbs={[{ label: 'My application' }]}>
  <div class="space-y-5">
    {#if !application}
      <Card.Root>
        <Card.Content class="p-6 text-center text-sm text-muted-foreground">
          No application record available.
        </Card.Content>
      </Card.Root>
    {:else}
      <div class="space-y-1">
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-2xl font-semibold tracking-tight">My application</h1>
          <Badge variant={statusVariant(application.status)}>{statusLabel(application.status)}</Badge>
        </div>
        <p class="font-mono text-sm text-muted-foreground">Ref {application.reference_code}</p>
      </div>

      {#if application.needs_correction}
        <Card.Root class="border-amber-500/40 bg-amber-500/10">
          <Card.Content class="space-y-3 p-4">
            <p class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">
              <AlertTriangle class="size-4" aria-hidden="true" />
              Registrar needs a correction
            </p>
            {#if application.correction_notes}
              <p class="text-sm text-muted-foreground">{application.correction_notes}</p>
            {/if}
            {#if application.correction_href}
              <Link href={application.correction_href}>
                <Button class="min-h-11 w-full">Fix application</Button>
              </Link>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}

      <Card.Root>
        <Card.Header class="border-b pb-3">
          <Card.Title class="text-lg">Personal information</Card.Title>
        </Card.Header>
        <Card.Content class="pt-4">
          <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
            {#each rows as row (row.label)}
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{row.label}</dt>
                <dd class="mt-0.5 text-sm">{row.value}</dd>
              </div>
            {/each}
          </dl>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header class="border-b pb-3">
          <Card.Title class="text-lg">Program preferences</Card.Title>
        </Card.Header>
        <Card.Content class="pt-4">
          {#if application.preferences?.length}
            <ol class="space-y-2">
              {#each application.preferences as pref, i (pref)}
                <li class="flex items-center gap-3 text-sm">
                  <span
                    class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                  >
                    {i + 1}
                  </span>
                  <span class="font-mono">{pref}</span>
                </li>
              {/each}
            </ol>
          {:else}
            <p class="text-sm text-muted-foreground">No preferences recorded.</p>
          {/if}
        </Card.Content>
      </Card.Root>

      {#if application.grades?.length}
        <Card.Root>
          <Card.Header class="border-b pb-3">
            <Card.Title class="text-lg">Intake grades</Card.Title>
            <Card.Description>Digital grades from your application (D-086).</Card.Description>
          </Card.Header>
          <Card.Content class="pt-4">
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
              {#each application.grades as grade (grade.label)}
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{grade.label}</dt>
                  <dd class="mt-0.5 font-mono text-sm tabular-nums">{grade.value}</dd>
                </div>
              {/each}
            </dl>
          </Card.Content>
        </Card.Root>
      {/if}

      {#if application.submitted_at}
        <Card.Root>
          <Card.Content class="flex items-center gap-3 p-4 text-sm text-muted-foreground">
            <CalendarClock class="size-4 shrink-0" aria-hidden="true" />
            <span>
              Submitted on
              <span class="font-medium text-foreground">
                {new Date(application.submitted_at).toLocaleString(undefined, {
                  month: 'long',
                  day: 'numeric',
                  year: 'numeric',
                  hour: '2-digit',
                  minute: '2-digit',
                })}
              </span>
            </span>
          </Card.Content>
        </Card.Root>
      {/if}

      <p class="flex gap-2 text-xs leading-relaxed text-muted-foreground">
        <FileText class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
        This is a read-only copy of your submitted application. Changes are only possible when the
        Registrar requests a correction.
      </p>
    {/if}
  </div>
</ApplicantLayout>
