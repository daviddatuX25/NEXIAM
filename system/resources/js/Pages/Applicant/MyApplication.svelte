<script>
  /**
   * My Application — read-only own record (2026-08-15-applicant-portal-interface).
   * No edit affordance except the needs_correction-gated fix prompt.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import DocumentRequirementsPanel from '@/Components/Admission/DocumentRequirementsPanel.svelte';

  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { AlertTriangle, CalendarClock, FileText, Lock, Pencil } from 'lucide-svelte';
  import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
  import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
  import { formatBirthdateDisplay } from '@/lib/date-utils.js';
  let { application = null, document_checklist = null } = $props();
  let tourOpen = $state(false);

  const applicationContext = $derived.by(() => {
    const status = application?.status ?? 'pending';
    const needsCorr = Boolean(application?.needs_correction);
    const hasP = Boolean(application?.program_preferences?.length > 0);
    const safetyKey = `${status}:${needsCorr ? 'corr' : 'ok'}:${hasP ? 'p' : 'nop'}`;

    return {
      entityKey: application?.reference_code ?? 'my-application',
      safetyKey,
      freshness: 'current',
      applicationStatus: status,
      needsCorrection: needsCorr,
      correctionNotes: application?.correction_notes ?? null,
      hasPreferences: hasP,
      canAmend: status === 'pending' || needsCorr,
    };
  });

  const rows = $derived(
    application
      ? [
          { label: 'Full name', value: application.personal?.name },
          { label: 'Birthdate', value: formatBirthdateDisplay(application.personal?.birthdate) },
          {
            label: 'Sex',
            value: application.personal?.sex
              ? application.personal.sex === 'female'
                ? 'Female'
                : application.personal.sex === 'male'
                  ? 'Male'
                  : application.personal.sex
              : null,
          },
          { label: 'Contact phone', value: application.personal?.contact_phone },
          { label: 'Contact email', value: application.personal?.contact_email },
          { label: 'Address', value: application.personal?.address },
          { label: 'Last school', value: application.personal?.last_school },
          { label: 'Strand', value: application.personal?.strand },
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
      <div class="sticky top-16 z-20 -mt-2 bg-background/95 pb-3 pt-2 backdrop-blur-md supports-[backdrop-filter]:bg-background/85 border-b border-border/40">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center justify-between gap-3" data-tour="my-application-status">
            <div class="flex items-center gap-3">
              <h1 class="text-2xl font-semibold tracking-tight">My application</h1>
              <TourTriggerGlyph tourKey="applicant-my-application" label="Need help understanding your application? Ask Kuya Xiam!" onclick={() => (tourOpen = true)} />
            </div>
            {#if application.needs_correction && application.correction_href}
              <Link href={application.correction_href}>
                <Button class="min-h-10 gap-1.5 bg-amber-600 text-white hover:bg-amber-500">
                  <Pencil class="size-4" />
                  Fix Application
                </Button>
              </Link>
            {:else if application.can_amend && application.correction_href}
              <Link href={application.correction_href}>
                <Button variant="outline" class="min-h-10 gap-1.5 font-medium">
                  <Pencil class="size-4" />
                  Edit Application
                </Button>
              </Link>
            {:else}
              <div title={application.amend_disabled_reason ?? 'Application details are locked'}>
                <Button variant="outline" disabled class="min-h-10 gap-1.5 opacity-60 cursor-not-allowed">
                  <Lock class="size-3.5" />
                  Edit Application
                </Button>
              </div>
            {/if}
          </div>
          {#if application.reference_code}
            <p class="font-mono text-xs text-muted-foreground">Applicant No. {application.reference_code}</p>
          {/if}
        </div>
      </div>

      {#if application.needs_correction}
        <Card.Root class="border-amber-500/40 bg-amber-500/10" data-tour="my-application-correction">
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
                <Button class="min-h-11 w-full gap-2">
                  <Pencil class="size-4" />
                  Fix application
                </Button>
              </Link>
            {/if}
          </Card.Content>
        </Card.Root>
      {:else if application.can_amend && application.correction_href}
        <Card.Root class="border-primary/20 bg-primary/5">
          <Card.Content class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-0.5">
              <p class="text-sm font-medium text-foreground">Application pending review</p>
              <p class="text-xs text-muted-foreground">
                You can update your personal contact details or preferences before it is accepted.
              </p>
            </div>
            <Link href={application.correction_href} class="shrink-0">
              <Button variant="outline" class="min-h-11 gap-1.5 w-full sm:w-auto">
                <Pencil class="size-4" />
                Update application
              </Button>
            </Link>
          </Card.Content>
        </Card.Root>
      {:else}
        <Card.Root class="border-muted bg-muted/20">
          <Card.Content class="flex flex-col gap-2 p-3.5 sm:flex-row sm:items-center sm:justify-between text-xs text-muted-foreground">
            <span class="flex items-center gap-2">
              <Lock class="size-3.5 shrink-0 text-muted-foreground" />
              <span>
                {application.amend_disabled_reason ?? 'Application details are locked. Once an exam session is assigned or accepted, changes require Registrar assistance.'}
              </span>
            </span>
            <Button variant="ghost" size="sm" disabled class="h-8 gap-1 opacity-50 cursor-not-allowed shrink-0">
              <Pencil class="size-3" /> Locked
            </Button>
          </Card.Content>
        </Card.Root>
      {/if}
      {#if document_checklist}
        <div data-tour="my-application-documents">
          <DocumentRequirementsPanel
            checklist={document_checklist}
            requestHistory={document_checklist.request_history?.requests ?? document_checklist.request_history ?? []}
            readOnly={true}
            columns={2}
          />
        </div>
      {/if}

      <Card.Root data-tour="my-application-details">
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

      <Card.Root data-tour="my-application-preferences">
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
        You can update your application while it is pending review or when the Registrar requests a
        correction. Once accepted, changes require Registrar authorization.
      </p>
    {/if}
  </div>
  <XiamNpcDialog tourKey="applicant-my-application" context={applicationContext} bind:open={tourOpen} />
</ApplicantLayout>
