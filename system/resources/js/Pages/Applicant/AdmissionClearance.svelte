<script lang="ts">
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { CheckCircle2, CircleAlert, FileCheck2, MessageSquareText, BellOff } from 'lucide-svelte';
  import { getCurrentSubscription, unsubscribeFromPush, subscribeToPush } from '@/lib/push-notifications';

  type Requirement = {
    id: number;
    code: string;
    label: string;
    item_kind: string;
    is_mandatory: boolean;
    latest_status: string | null;
    latest_verified_at: string | null;
    applicant_instruction: string | null;
  };

  type ClearanceStatus = 'not_ready' | 'ready' | 'confirmed' | 'revoked';
  type Clearance = {
    requirements: {
      items: Requirement[];
      all_mandatory_verified: boolean;
      mandatory_pending_count: number;
    };
    correction_requests: {
      recorded_at: string;
      items: { label: string; applicant_instruction: string }[];
    }[];
    status: ClearanceStatus;
    latest_receipt: {
      receipt_number: string;
      status: string;
      confirmed_at: string | null;
      revoked_at: string | null;
    } | null;
    historical_sheet_issue: {
      receipt_number: string;
      issued_at: string | null;
      belongs_to_latest_clearance: boolean;
    } | null;
  };

  type PageProps = {
    page_state: 'no_attempt' | 'ready';
    clearance: Clearance | null;
  };
  let { page_state = 'no_attempt', clearance = null }: PageProps = $props();

  let pushDisabled = $state(false);
  let pushProcessing = $state(false);

  async function disablePush() {
    pushProcessing = true;
    try {
      await unsubscribeFromPush();
      pushDisabled = true;
    } finally {
      pushProcessing = false;
    }
  }

  async function enablePush() {
    pushProcessing = true;
    try {
      const res = await subscribeToPush();
      if (res.success) {
        pushDisabled = false;
      }
    } finally {
      pushProcessing = false;
    }
  }

  const statusCopy: Record<ClearanceStatus, { title: string; detail: string }> = {
    not_ready: {
      title: 'Clearance is not ready yet',
      detail: 'The Registrar will confirm clearance after the required records and checks are complete.',
    },
    ready: {
      title: 'Ready for Registrar review',
      detail: 'The current records meet the documented prerequisites. The Registrar still needs to confirm clearance.',
    },
    confirmed: {
      title: 'Admission clearance confirmed',
      detail: 'Thank you for completing your application. Your admission processing in NEXIAM is complete. No further clearance action is needed here.',
    },
    revoked: {
      title: 'Clearance reopened',
      detail: 'The Registrar has reopened your clearance for correction. Contact the Registrar about the remaining requirements.',
    },
  };

  function formatDate(value: string | null): string {
    if (!value) return 'Date not recorded';
    const date = new Date(value);
    return Number.isNaN(date.getTime())
      ? 'Date not recorded'
      : new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(date);
  }
</script>

<svelte:head><title>Admission Clearance</title></svelte:head>

<ApplicantLayout title="Admission Clearance" breadcrumbs={[{ label: 'Admission Clearance' }]}>
  <div class="space-y-4">
    <h1 class="text-2xl font-semibold tracking-tight">Admission Clearance</h1>
    {#if page_state === 'no_attempt' || clearance === null}
      <Card.Root class="shadow-sm">
        <Card.Header class="">
          <h2 class="text-lg font-semibold tracking-tight">No application found</h2>
          <Card.Description class="">There is no application linked to this account. Contact the Registrar if you need help.</Card.Description>
        </Card.Header>
        <Card.Content class="">
          <Link href="/applicant/admission-support?group=offices" class="inline-flex min-h-11 items-center gap-2 rounded-md text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            <MessageSquareText class="size-4" aria-hidden="true" /> Message the Registrar
          </Link>
        </Card.Content>
      </Card.Root>
    {:else}
      <Card.Root class="shadow-sm">
        <Card.Header class="">
          <div class="flex items-start gap-3">
            {#if clearance.status === 'confirmed'}
              <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-700" aria-hidden="true" />
            {:else if clearance.status === 'revoked'}
              <CircleAlert class="mt-0.5 size-5 shrink-0 text-amber-700" aria-hidden="true" />
            {:else}
              <FileCheck2 class="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
            {/if}
            <div class="space-y-1">
              <h2 class="text-lg font-semibold tracking-tight">{statusCopy[clearance.status].title}</h2>
              <Card.Description class="">{statusCopy[clearance.status].detail}</Card.Description>
            </div>
          </div>
        </Card.Header>
        {#if clearance.latest_receipt}
          <Card.Content class="border-t pt-4">
            <details>
              <summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold">Clearance details</summary>
              <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
              <div><dt class="text-muted-foreground">Receipt reference</dt><dd class="font-medium">{clearance.latest_receipt.receipt_number}</dd></div>
              <div><dt class="text-muted-foreground">Status</dt><dd class="font-medium">{clearance.latest_receipt.status === 'confirmed' ? 'Confirmed' : 'Revoked'}</dd></div>
              {#if clearance.latest_receipt.confirmed_at}<div><dt class="text-muted-foreground">Confirmed</dt><dd>{formatDate(clearance.latest_receipt.confirmed_at)}</dd></div>{/if}
              {#if clearance.latest_receipt.revoked_at}<div><dt class="text-muted-foreground">Reopened</dt><dd>{formatDate(clearance.latest_receipt.revoked_at)}</dd></div>{/if}
              </dl>
            </details>
          </Card.Content>
        {/if}
      </Card.Root>

      {#if clearance.status === 'confirmed'}
        <Card.Root class="shadow-sm">
          <Card.Header class="">
            <div class="flex items-start gap-3">
              <BellOff class="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
              <div class="space-y-1">
                <h2 class="text-lg font-semibold tracking-tight">Notification offboarding</h2>
                <Card.Description>Admission processing is finished. You can disable alerts on this device.</Card.Description>
              </div>
            </div>
          </Card.Header>
          <Card.Content class="pt-0 space-y-3">
            {#if pushDisabled}
              <p class="text-sm text-muted-foreground">Alerts have been disabled for this browser. <button type="button" class="text-primary underline hover:text-primary/80 font-medium" onclick={enablePush}>Enable</button></p>
            {:else}
              <Button type="button" variant="outline" class="min-h-11" disabled={pushProcessing} onclick={disablePush}>
                {pushProcessing ? 'Disabling…' : 'Turn off alerts on this device'}
              </Button>
            {/if}
            <p class="text-xs text-muted-foreground">If installed as an app, you can now remove it from your home screen without affecting your records.</p>
          </Card.Content>
        </Card.Root>
      {/if}

      <Card.Root class="shadow-sm">
        <Card.Header class="">
          <h2 class="text-lg font-semibold tracking-tight">Document checklist</h2>
          <Card.Description class="">Current applicant-visible requirements and verification status.</Card.Description>
        </Card.Header>
        <Card.Content class="">
          {#if clearance.correction_requests.length > 0}
            <div class="mb-4 rounded-lg border border-amber-600/30 bg-amber-600/5 p-3">
              <h3 class="font-medium">Requested document corrections</h3>
              {#each clearance.correction_requests as request (request.recorded_at)}
                <ul class="mt-2 list-disc space-y-2 pl-5 text-sm">
                  {#each request.items as item (`${request.recorded_at}-${item.label}`)}
                    <li><span class="font-medium">{item.label}</span>{#if item.applicant_instruction}<span class="block text-muted-foreground">{item.applicant_instruction}</span>{/if}</li>
                  {/each}
                </ul>
              {/each}
            </div>
          {/if}
          {#if clearance.requirements.items.length > 0}
            <ul class="divide-y">
              {#each clearance.requirements.items as requirement (requirement.id)}
                <li class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                  <div>
                    <p class="font-medium">{requirement.label}{requirement.is_mandatory ? ' · Required' : ' · Optional'}</p>
                    {#if requirement.applicant_instruction}<p class="mt-1 text-sm text-muted-foreground">{requirement.applicant_instruction}</p>{/if}
                  </div>
                  <div class="shrink-0 text-sm sm:text-right">
                    <p>{requirement.latest_status === 'verified' ? 'Verified' : requirement.latest_status === 'needs_correction' ? 'Update requested' : 'Not yet verified'}</p>
                    {#if requirement.latest_verified_at}<p class="text-muted-foreground">{formatDate(requirement.latest_verified_at)}</p>{/if}
                  </div>
                </li>
              {/each}
            </ul>
          {:else}
            <p class="text-sm text-muted-foreground">No document requirements are listed for this application.</p>
          {/if}
        </Card.Content>
      </Card.Root>

      {#if clearance.historical_sheet_issue}
        <details class="rounded-xl border border-border p-4">
          <summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold">Historical sheet handoff</summary>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
              <div><dt class="text-muted-foreground">Issue reference</dt><dd class="font-medium">{clearance.historical_sheet_issue.receipt_number}</dd></div>
              <div><dt class="text-muted-foreground">Issued</dt><dd>{formatDate(clearance.historical_sheet_issue.issued_at)}</dd></div>
              <div class="sm:col-span-2"><dt class="text-muted-foreground">Clearance receipt link</dt><dd>{clearance.historical_sheet_issue.belongs_to_latest_clearance ? 'Linked to the latest clearance receipt' : 'Historical issue; not linked to the latest clearance receipt'}</dd></div>
            </dl>
        </details>
      {/if}

      <p class="text-sm text-muted-foreground">
        Need help with these records? <Link href="/applicant/admission-support?group=offices" class="font-medium text-primary underline underline-offset-4">Message the Registrar</Link>.
      </p>
    {/if}
  </div>
</ApplicantLayout>
