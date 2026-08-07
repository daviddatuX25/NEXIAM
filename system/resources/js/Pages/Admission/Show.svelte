<script>
  /**
   * P-REG-02 — Registrar attempt detail.
   * Legacy feel (Applications/Show): status+action bar, Card dossier sections, Timeline.
   * Nav: StaffLayout breadcrumbs only — no redundant Back link.
   * Action matrix: openspec/changes/admission-attempt-detail/design.md
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import IntakeCorrectionFields from '@/Pages/Admission/IntakeCorrectionFields.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';
  import { router, useForm } from '@inertiajs/svelte';
  import { CheckCircle, RotateCcw, XCircle } from 'lucide-svelte';

  let {
    page_state = 'ready',
    attempt,
    programs = [],
    grade_fields = [],
    reissued_access_key = null,
    capabilities = { accept: false, dismiss: false, correct: false },
  } = $props();

  let dismissOpen = $state(false);
  let requestOpen = $state(false);
  let archiveOpen = $state(false);
  let reopenOpen = $state(false);
  let editOpen = $state(false);
  let amendOpen = $state(false);
  let reconsiderOpen = $state(false);
  let reissueOpen = $state(false);
  let keyVisible = $state(false);

  let dismissReason = $state('');
  let requestReason = $state('');
  let reopenReason = $state('');
  let reissueReason = $state('');
  let reconsiderReason = $state('');
  let amendReason = $state('');

  const initialGrades = Object.fromEntries(
    (grade_fields ?? []).map((f) => [
      f.code,
      attempt.grades?.find((g) => g.code === f.code)?.value ?? '',
    ]),
  );

  const editForm = useForm({
    expected_version: attempt.version,
    applicant_type: attempt.applicant_type ?? 'new',
    contact_email: attempt.contact_email ?? '',
    contact_phone: attempt.contact_phone ?? '',
    last_school_enrolled: attempt.last_school_enrolled ?? '',
    strand: attempt.strand ?? '',
    grades: initialGrades,
    program_preference_1_code: attempt.program_preferences?.[0] ?? attempt.preferred_program_code ?? '',
    program_preference_2_code: attempt.program_preferences?.[1] ?? '',
    program_preference_3_code: attempt.program_preferences?.[2] ?? '',
    address_line: attempt.address?.line ?? '',
    city: attempt.address?.city ?? '',
    province: attempt.address?.province ?? '',
    zip_code: attempt.address?.zip_code ?? '',
    correction_notes: attempt.correction_notes ?? '',
  });

  const amendForm = useForm({
    expected_version: attempt.version,
    reason: '',
    identity_first_name: attempt.identity?.first_name ?? '',
    identity_middle_name: attempt.identity?.middle_name ?? '',
    identity_last_name: attempt.identity?.last_name ?? '',
    identity_suffix: attempt.identity?.suffix ?? '',
    identity_birthdate: attempt.identity?.birthdate ?? '',
    identity_sex: attempt.identity?.sex ?? 'male',
    identity_email: attempt.identity?.email ?? '',
    identity_phone: attempt.identity?.phone ?? '',
    applicant_type: attempt.applicant_type ?? 'new',
    contact_email: attempt.contact_email ?? '',
    contact_phone: attempt.contact_phone ?? '',
    last_school_enrolled: attempt.last_school_enrolled ?? '',
    strand: attempt.strand ?? '',
    grades: { ...initialGrades },
    program_preference_1_code: attempt.program_preferences?.[0] ?? attempt.preferred_program_code ?? '',
    program_preference_2_code: attempt.program_preferences?.[1] ?? '',
    program_preference_3_code: attempt.program_preferences?.[2] ?? '',
    address_line: attempt.address?.line ?? '',
    city: attempt.address?.city ?? '',
    province: attempt.address?.province ?? '',
    zip_code: attempt.address?.zip_code ?? '',
  });

  const canDecide = $derived(
    attempt.status === 'pending' || attempt.status === 'needs_correction',
  );
  const canRequestCorrection = $derived(attempt.status === 'pending' && capabilities.correct);
  const canEditFields = $derived(canDecide && capabilities.correct);
  const isAccepted = $derived(attempt.status === 'accepted');
  /** Authority paths after accept — not quiet Correct. */
  const canAuthorizeAmend = $derived(isAccepted && capabilities.accept);
  const canReconsider = $derived(isAccepted && capabilities.accept);
  const canRevert = $derived(
    (attempt.status === 'dismissed' || attempt.status === 'archived') && capabilities.accept,
  );
  const canArchive = $derived(attempt.status !== 'archived' && capabilities.dismiss);
  const canReissueKey = $derived(attempt.status !== 'archived' && capabilities.correct);

  const addressLine = $derived(
    [attempt.address?.line, attempt.address?.city, attempt.address?.province, attempt.address?.zip_code]
      .filter(Boolean)
      .join(', ') || '—',
  );

  const preferenceList = $derived(
    attempt.program_preferences?.length
      ? attempt.program_preferences
      : attempt.preferred_program_code
        ? [attempt.preferred_program_code]
        : [],
  );

  function statusVariant(status) {
    switch (status) {
      case 'accepted':
        return 'default';
      case 'needs_correction':
        return 'outline';
      case 'dismissed':
        return 'destructive';
      default:
        return 'secondary';
    }
  }

  function statusLabel(status) {
    const labels = {
      pending: 'Pending',
      needs_correction: 'Needs correction',
      accepted: 'Accepted',
      dismissed: 'Dismissed',
      archived: 'Archived',
    };
    return labels[status] ?? status;
  }

  function formatDate(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      });
    } catch {
      return iso;
    }
  }

  function formatDateTime(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return iso;
    }
  }

  function accept() {
    router.post(`/admissions/${attempt.id}/accept`, {
      expected_version: attempt.version,
    });
  }

  function submitDismiss() {
    if (!dismissReason.trim()) return;
    router.post(
      `/admissions/${attempt.id}/dismiss`,
      {
        expected_version: attempt.version,
        reason: dismissReason.trim(),
      },
      { onSuccess: () => { dismissOpen = false; } },
    );
  }

  function submitRequestCorrection() {
    if (!requestReason.trim()) return;
    router.post(
      `/admissions/${attempt.id}/request-correction`,
      {
        expected_version: attempt.version,
        reason: requestReason.trim(),
      },
      { onSuccess: () => { requestOpen = false; } },
    );
  }

  function submitArchive() {
    router.post(
      `/admissions/${attempt.id}/archive`,
      { expected_version: attempt.version },
      { onSuccess: () => { archiveOpen = false; } },
    );
  }

  function submitReopen() {
    if (!reopenReason.trim()) return;
    router.post(
      `/admissions/${attempt.id}/reopen`,
      {
        expected_version: attempt.version,
        reason: reopenReason.trim(),
      },
      { onSuccess: () => { reopenOpen = false; } },
    );
  }

  function submitEdit() {
    editForm.expected_version = attempt.version;
    editForm.post(`/admissions/${attempt.id}/correct`, {
      onSuccess: () => { editOpen = false; },
    });
  }

  function submitAmend() {
    if (!amendReason.trim()) return;
    amendForm.expected_version = attempt.version;
    amendForm.reason = amendReason.trim();
    amendForm.post(`/admissions/${attempt.id}/authorize-amendment`, {
      onSuccess: () => { amendOpen = false; },
    });
  }

  function submitReconsider() {
    if (!reconsiderReason.trim()) return;
    router.post(
      `/admissions/${attempt.id}/reconsider`,
      {
        expected_version: attempt.version,
        reason: reconsiderReason.trim(),
      },
      { onSuccess: () => { reconsiderOpen = false; } },
    );
  }

  function submitReissue() {
    router.post(
      `/admissions/${attempt.id}/reissue-access-key`,
      {
        expected_version: attempt.version,
        reason: reissueReason.trim(),
      },
      { onSuccess: () => { reissueOpen = false; } },
    );
  }

  const breadcrumbs = [
    { label: 'Admissions', href: '/admissions' },
    { label: attempt.reference_code ?? 'Application' },
  ];
</script>

<svelte:head><title>{attempt.reference_code} · Admissions</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title={attempt.applicant_name}
      description={`Reference ${attempt.reference_code} · v${attempt.version} · ${attempt.cycle?.name ?? 'Cycle —'}`}
    >
      {#snippet actions()}
        <Badge variant={statusVariant(attempt.status)} class="capitalize text-sm">
          {statusLabel(attempt.status)}
        </Badge>
      {/snippet}
    </PageHeaderBar>

    <!-- Legacy status + action bar -->
    <div
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/70 bg-card px-4 py-3 shadow-[0_1px_2px_oklch(0_0_0/0.04)]"
    >
      <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
        <span>Status</span>
        <Badge variant={statusVariant(attempt.status)} class="capitalize">
          {statusLabel(attempt.status)}
        </Badge>
        {#if attempt.cycle?.code}
          <span class="text-xs">{attempt.cycle.code}</span>
        {/if}
      </div>
      <div class="flex flex-wrap items-center gap-2">
        {#if capabilities.accept && canDecide}
          <Button size="sm" class="min-h-10" onclick={accept}>
            <CheckCircle class="mr-1.5 size-4" />
            Accept
          </Button>
        {/if}
        {#if capabilities.dismiss && canDecide}
          <Button
            size="sm"
            variant="outline"
            class="min-h-10 border-destructive/30 text-destructive hover:bg-destructive/5"
            onclick={() => {
              dismissReason = '';
              dismissOpen = true;
            }}
          >
            <XCircle class="mr-1.5 size-4" />
            Dismiss
          </Button>
        {/if}
        {#if canRequestCorrection}
          <Button
            size="sm"
            variant="outline"
            class="min-h-10"
            onclick={() => {
              requestReason = '';
              requestOpen = true;
            }}
          >
            Ask applicant to correct
          </Button>
        {/if}
        {#if canEditFields}
          <Button size="sm" variant="ghost" class="min-h-10" onclick={() => (editOpen = true)}>
            Update for applicant
          </Button>
        {/if}
        {#if canAuthorizeAmend}
          <Button
            size="sm"
            variant="outline"
            class="min-h-10"
            onclick={() => {
              amendReason = '';
              amendOpen = true;
            }}
          >
            Authorize amendment
          </Button>
        {/if}
        {#if canReconsider}
          <Button
            size="sm"
            variant="outline"
            class="min-h-10"
            onclick={() => {
              reconsiderReason = '';
              reconsiderOpen = true;
            }}
          >
            <RotateCcw class="mr-1.5 size-4" />
            Reconsider acceptance
          </Button>
        {/if}
        {#if canRevert}
          <Button
            size="sm"
            variant="outline"
            class="min-h-10"
            onclick={() => {
              reopenReason = '';
              reopenOpen = true;
            }}
          >
            <RotateCcw class="mr-1.5 size-4" />
            Revert to pending
          </Button>
        {/if}
        {#if canArchive}
          <Button
            size="sm"
            variant="ghost"
            class="min-h-10 text-muted-foreground"
            onclick={() => (archiveOpen = true)}
          >
            Archive
          </Button>
        {/if}
        {#if canReissueKey}
          <Button
            size="sm"
            variant="ghost"
            class="min-h-10"
            onclick={() => {
              reissueReason = '';
              reissueOpen = true;
            }}
          >
            Reissue access key
          </Button>
        {/if}
      </div>
    </div>

    {#if reissued_access_key}
      <Card.Root class="border-primary/20">
        <Card.Header class="border-b border-border/60 pb-4">
          <Card.Title class="text-base">New access key</Card.Title>
          <Card.Description>Copy now — shown once. Prior keys are revoked.</Card.Description>
        </Card.Header>
        <Card.Content class="space-y-3 pt-4">
          <div class="flex flex-wrap items-center gap-2">
            <Input
              class="max-w-xs font-mono tracking-widest"
              type={keyVisible ? 'text' : 'password'}
              readonly
              value={reissued_access_key}
            />
            <Button size="sm" variant="outline" onclick={() => (keyVisible = !keyVisible)}>
              {keyVisible ? 'Hide' : 'Show'}
            </Button>
            <Button
              size="sm"
              variant="outline"
              onclick={async () => {
                try {
                  await navigator.clipboard.writeText(reissued_access_key);
                } catch {
                  /* ignore */
                }
              }}
            >
              Copy
            </Button>
          </div>
          <p class="text-xs text-muted-foreground">
            Give this key to the applicant with their birthdate for portal login.
          </p>
        </Card.Content>
      </Card.Root>
    {/if}

    {#if isAccepted}
      <p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
        Accepted — exam-eligible. Quiet Update / Ask correction stay off.
        Use <span class="font-medium text-foreground">Authorize amendment</span> for wrong name/details
        (stays accepted, no retake). Use
        <span class="font-medium text-foreground">Reconsider acceptance</span> only if the decision
        itself must return to pending.
      </p>
    {/if}
    {#if attempt.status === 'needs_correction' && attempt.correction_notes}
      <p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
        Waiting on applicant: {attempt.correction_notes}
      </p>
    {/if}
    {#if attempt.status === 'dismissed' && attempt.dismissal_reason}
      <p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
        Dismissal reason: {attempt.dismissal_reason}
      </p>
    {/if}

    <div class="grid gap-6 md:grid-cols-2">
      <Card.Root>
        <Card.Header class="border-b border-border/60 pb-4">
          <Card.Title class="text-base">Personal details</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2.5 pt-4 text-sm">
          <p><span class="text-muted-foreground">Name:</span> {attempt.applicant_name}</p>
          <p>
            <span class="text-muted-foreground">Birthdate:</span>
            {formatDate(attempt.identity?.birthdate)}
          </p>
          <p>
            <span class="text-muted-foreground">Sex:</span>
            <span class="capitalize">{attempt.identity?.sex ?? '—'}</span>
          </p>
          <p>
            <span class="text-muted-foreground">Classification:</span>
            <span class="capitalize">
              {attempt.applicant_type === 'transferee' ? 'Transferee' : attempt.applicant_type ?? 'New student'}
            </span>
          </p>
          {#if attempt.last_school_enrolled}
            <p>
              <span class="text-muted-foreground">Last school enrolled:</span>
              {attempt.last_school_enrolled}
            </p>
          {/if}
          {#if attempt.strand}
            <p>
              <span class="text-muted-foreground">SHS strand / previous course:</span>
              {attempt.strand}
            </p>
          {/if}
          <p><span class="text-muted-foreground">Email:</span> {attempt.contact_email ?? '—'}</p>
          <p><span class="text-muted-foreground">Phone:</span> {attempt.contact_phone ?? '—'}</p>
          <p><span class="text-muted-foreground">Address:</span> {addressLine}</p>
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header class="border-b border-border/60 pb-4">
          <Card.Title class="text-base">Program preferences</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2.5 pt-4 text-sm">
          {#if preferenceList.length}
            {#each preferenceList as code, i}
              <p>
                <span class="text-muted-foreground"
                  >{i === 0 ? '1st' : i === 1 ? '2nd' : `${i + 1}th`}:</span
                >
                <span class="font-medium">{code ?? '—'}</span>
              </p>
            {/each}
          {:else}
            <p class="text-muted-foreground">No preferences recorded.</p>
          {/if}
          <p class="pt-2 text-xs text-muted-foreground">
            Cycle: {attempt.cycle?.name ?? '—'} ({attempt.cycle?.code ?? '—'})
          </p>
        </Card.Content>
      </Card.Root>
    </div>

    <div class="grid gap-6 md:grid-cols-2">
      <Card.Root>
        <Card.Header class="border-b border-border/60 pb-4">
          <Card.Title class="text-base">Grades &amp; consent</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2.5 pt-4 text-sm">
          {#if attempt.grades?.length}
            {#each attempt.grades as g}
              <p>
                <span class="text-muted-foreground">{g.code}:</span>
                <span class="font-mono">{g.value}</span>
              </p>
            {/each}
          {:else}
            <p class="text-muted-foreground">No catalog grades on this attempt.</p>
          {/if}
          {#if attempt.gwa}
            <p>
              <span class="text-muted-foreground">GWA:</span>
              <span class="font-mono">{attempt.gwa}</span>
            </p>
          {/if}
          {#if attempt.privacy_policy_title}
            <p class="pt-1 text-xs text-muted-foreground">
              Consent: {attempt.privacy_policy_title}
            </p>
          {/if}
        </Card.Content>
      </Card.Root>

      <Card.Root>
        <Card.Header class="border-b border-border/60 pb-4">
          <Card.Title class="text-base">Timeline</Card.Title>
        </Card.Header>
        <Card.Content class="space-y-2.5 pt-4 text-sm">
          <p>
            <span class="text-muted-foreground">Submitted:</span>
            {formatDateTime(attempt.submitted_at)}
          </p>
          {#if attempt.accepted_at}
            <p>
              <span class="text-muted-foreground">Accepted:</span>
              {formatDateTime(attempt.accepted_at)}
            </p>
          {/if}
          {#if attempt.dismissed_at}
            <p>
              <span class="text-muted-foreground">Dismissed:</span>
              {formatDateTime(attempt.dismissed_at)}
            </p>
          {/if}
          {#if attempt.archived_at}
            <p>
              <span class="text-muted-foreground">Archived:</span>
              {formatDateTime(attempt.archived_at)}
            </p>
          {/if}
          {#if attempt.status === 'dismissed' && attempt.dismissal_reason}
            <p>
              <span class="text-muted-foreground">Dismissal reason:</span>
              {attempt.dismissal_reason}
            </p>
          {/if}
        </Card.Content>
      </Card.Root>
    </div>
  </div>
</StaffLayout>

<Dialog.Root bind:open={dismissOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Dismiss application</Dialog.Title>
      <Dialog.Description>Requires a reason. History is retained.</Dialog.Description>
    </Dialog.Header>
    <div class="space-y-2 py-2">
      <Label for="dismiss-reason">Reason</Label>
      <Textarea id="dismiss-reason" bind:value={dismissReason} rows={3} class="min-h-24" />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (dismissOpen = false)}>Cancel</Button>
      <Button variant="destructive" disabled={!dismissReason.trim()} onclick={submitDismiss}
        >Dismiss</Button
      >
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={requestOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Ask applicant to correct</Dialog.Title>
      <Dialog.Description>
        Moves status to needs correction and records instructions for the applicant (notice delivery
        later).
      </Dialog.Description>
    </Dialog.Header>
    <div class="space-y-2 py-2">
      <Label for="request-reason">What must be corrected?</Label>
      <Textarea id="request-reason" bind:value={requestReason} rows={4} class="min-h-28" />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (requestOpen = false)}>Cancel</Button>
      <Button disabled={!requestReason.trim()} onclick={submitRequestCorrection}>Send request</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={archiveOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Archive application</Dialog.Title>
      <Dialog.Description>
        Removes it from the active queue. Identity and history are kept.
      </Dialog.Description>
    </Dialog.Header>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (archiveOpen = false)}>Cancel</Button>
      <Button onclick={submitArchive}>Archive</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={reopenOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Revert to pending</Dialog.Title>
      <Dialog.Description>
        Non-destructive reopen — never deletes identity or history.
      </Dialog.Description>
    </Dialog.Header>
    <div class="space-y-2 py-2">
      <Label for="reopen-reason">Reason</Label>
      <Textarea id="reopen-reason" bind:value={reopenReason} rows={3} class="min-h-24" />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (reopenOpen = false)}>Cancel</Button>
      <Button disabled={!reopenReason.trim()} onclick={submitReopen}>Revert</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={editOpen}>
  <Dialog.Content class="flex max-h-[90vh] flex-col sm:max-w-2xl">
    <Dialog.Header>
      <Dialog.Title>Update for applicant</Dialog.Title>
      <Dialog.Description>
        {#if attempt.status === 'needs_correction'}
          Records the office fix from the applicant visit and returns this application to pending
          review (ready for Accept). Audited — does not require portal resubmit.
        {:else}
          Quiet in-place fix while still pending. Stays in the review queue. Same intake fields as
          apply. After accept, use an authorized authority path instead.
        {/if}
      </Dialog.Description>
    </Dialog.Header>
    <div class="max-h-[65vh] overflow-y-auto py-2 pr-1">
      <IntakeCorrectionFields
        form={editForm}
        {programs}
        {grade_fields}
        identity={attempt.identity}
        showInternalNotes={true}
        idPrefix="edit"
      />
      {#if editForm.errors?.version}
        <p class="mt-3 text-sm text-destructive">{editForm.errors.version}</p>
      {/if}
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (editOpen = false)}>Cancel</Button>
      <Button disabled={editForm.processing} onclick={submitEdit}>Save</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={amendOpen}>
  <Dialog.Content class="flex max-h-[90vh] flex-col sm:max-w-2xl">
    <Dialog.Header>
      <Dialog.Title>Authorize amendment</Dialog.Title>
      <Dialog.Description>
        Fix wrong name or details while staying accepted. Exam assignment and Applicant No stay —
        no retake. Requires an authority reason. Distinct from Reconsider (which pulls acceptance
        back to pending).
      </Dialog.Description>
    </Dialog.Header>
    <div class="max-h-[65vh] space-y-4 overflow-y-auto py-2 pr-1">
      <div class="space-y-2">
        <Label for="amend-reason">Authority reason</Label>
        <Textarea id="amend-reason" bind:value={amendReason} rows={2} class="min-h-20" />
      </div>
      <IntakeCorrectionFields
        form={amendForm}
        {programs}
        {grade_fields}
        identity={attempt.identity}
        identityEditable={true}
        idPrefix="amend"
      />
      {#if amendForm.errors?.version}
        <p class="text-sm text-destructive">{amendForm.errors.version}</p>
      {/if}
      {#if amendForm.errors?.reason}
        <p class="text-sm text-destructive">{amendForm.errors.reason}</p>
      {/if}
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (amendOpen = false)}>Cancel</Button>
      <Button
        disabled={amendForm.processing || !amendReason.trim()}
        onclick={submitAmend}
      >
        Record authorized amendment
      </Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={reconsiderOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Reconsider acceptance</Dialog.Title>
      <Dialog.Description>
        Returns status to pending (decision pullback). Existing exam links are preserved for
        reconciliation — new scheduling requires Accept again. Do not use this for a typo; use
        Authorize amendment instead.
      </Dialog.Description>
    </Dialog.Header>
    <div class="space-y-2 py-2">
      <Label for="reconsider-reason">Authority reason</Label>
      <Textarea id="reconsider-reason" bind:value={reconsiderReason} rows={3} class="min-h-24" />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (reconsiderOpen = false)}>Cancel</Button>
      <Button disabled={!reconsiderReason.trim()} onclick={submitReconsider}>Reconsider</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={reissueOpen}>
  <Dialog.Content class="sm:max-w-md">
    <Dialog.Header>
      <Dialog.Title>Reissue access key</Dialog.Title>
      <Dialog.Description>
        Revokes every prior key for this applicant. The new key is shown once on this page.
      </Dialog.Description>
    </Dialog.Header>
    <div class="space-y-2 py-2">
      <Label for="reissue-reason">Reason (optional)</Label>
      <Textarea id="reissue-reason" bind:value={reissueReason} rows={2} class="min-h-20" />
    </div>
    <Dialog.Footer>
      <Button variant="outline" onclick={() => (reissueOpen = false)}>Cancel</Button>
      <Button onclick={submitReissue}>Reissue key</Button>
    </Dialog.Footer>
  </Dialog.Content>
</Dialog.Root>
