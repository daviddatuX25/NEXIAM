<script>
import { newIdempotencyKey } from '@/lib/utils';
  /* global route */
  /**
   * Applicant program choice — own-scope portal page.
   * Renders released-result facts, the advised default (acknowledge),
   * alternative pool options (select/transfer), cancel, and the analysis
   * region. The server is the only authorizer — no engine UI, no synthetic
   * recommendation, and every action carries a fresh idempotency key.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { useForm } from '@inertiajs/svelte';
  import { CheckCircle2, Clock3, Info } from 'lucide-svelte';

  let { page_state = 'ready', support = null } = $props();

  const readiness = $derived(support?.readiness ?? {});
  const pool = $derived(support?.program_pool ?? {});
  const analysis = $derived(support?.analysis ?? null);

  const selectedCode = $derived(pool.selection_program ?? readiness.selection?.program_code ?? null);
  const selectionExists = $derived(
    ['pending_acknowledgement', 'active'].includes(readiness.selection?.status),
  );
  const options = $derived((pool.options ?? []).filter((option) => option.program_code !== selectedCode));
  const canStillChoose = $derived(!readiness.enrolling && !readiness.enrolled);

  const acknowledgeUrl =
    typeof route === 'function'
      ? route('applicant.program-choice.acknowledge')
      : '/applicant/program-choice/acknowledge';
  const selectUrl =
    typeof route === 'function' ? route('applicant.program-choice.select') : '/applicant/program-choice/select';
  const transferUrl =
    typeof route === 'function'
      ? route('applicant.program-choice.transfer')
      : '/applicant/program-choice/transfer';
  const cancelUrl =
    typeof route === 'function' ? route('applicant.program-choice.cancel') : '/applicant/program-choice/cancel';

  const acknowledgeForm = useForm({ idempotency_key: '' });
  const selectForm = useForm({ program_code: '', idempotency_key: '' });
  const cancelForm = useForm({ reason: '', idempotency_key: '' });

  let confirmOpen = $state(false);
  let pendingCode = $state(null);
  let cancelOpen = $state(false);

  const pendingProgram = $derived(
    (pool.options ?? []).find((option) => option.program_code === pendingCode) ?? null,
  );
  const confirmTitle = $derived(selectionExists ? 'Transfer program choice' : 'Choose program');
  const confirmDescription = $derived(
    pendingProgram
      ? selectionExists
        ? `This replaces your current selection with ${pendingProgram.program_name} (${pendingProgram.program_code}).`
        : `Record ${pendingProgram.program_name} (${pendingProgram.program_code}) as your program choice.`
      : 'Confirm your program choice.',
  );

  function acceptDefault() {
    acknowledgeForm.idempotency_key = newIdempotencyKey();
    acknowledgeForm.post(acknowledgeUrl, { preserveScroll: true });
  }

  function openSelect(code) {
    pendingCode = code;
    confirmOpen = true;
  }

  function confirmSelect() {
    if (!pendingCode) return;
    selectForm.program_code = pendingCode;
    selectForm.idempotency_key = newIdempotencyKey();
    selectForm.post(selectionExists ? transferUrl : selectUrl, {
      preserveScroll: true,
      onFinish: () => {
        confirmOpen = false;
        pendingCode = null;
      },
    });
  }

  function confirmCancel() {
    cancelForm.idempotency_key = newIdempotencyKey();
    cancelForm.post(cancelUrl, {
      preserveScroll: true,
      onFinish: () => {
        cancelOpen = false;
        cancelForm.reason = '';
      },
    });
  }
</script>

<svelte:head><title>Program choice</title></svelte:head>

<ApplicantLayout title="Program choice">
  <div class="mx-auto max-w-2xl space-y-5">
    {#if page_state !== 'ready'}
      <div class="sr-only"><E2PageState state={page_state} /></div>
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <Clock3 class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">Program choice unavailable</p>
          <p class="text-sm text-muted-foreground">
            {page_state === 'denied'
              ? 'This account cannot access program choice.'
              : 'Program choice is not ready yet.'}
          </p>
        </Card.Content>
      </Card.Root>
    {:else}
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold tracking-tight">Program choice</h1>
        <p class="text-sm text-muted-foreground">
          Pick or confirm your program after your result is released.
        </p>
      </div>

      <Card.Root class="overflow-hidden shadow-sm">
        <Card.Header class="border-b bg-muted/20 pb-3">
          <Card.Title class="text-lg">Your admission status</Card.Title>
          <Card.Description>Result, advice, selection, and enrollment facts</Card.Description>
        </Card.Header>
        <Card.Content class="pt-5">
          <dl class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-1">
              <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Result</dt>
              <dd>
                <Badge variant={readiness.result_released ? 'default' : 'secondary'}>
                  {readiness.result_released ? 'Released' : 'Pending'}
                </Badge>
              </dd>
            </div>
            <div class="space-y-1">
              <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Advised</dt>
              <dd>
                <Badge variant={readiness.advised ? 'default' : 'secondary'}>
                  {readiness.advised ? 'Advised' : 'Not advised'}
                </Badge>
              </dd>
            </div>
            <div class="space-y-1">
              <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Program selection
              </dt>
              <dd class="space-y-1">
                {#if selectedCode}
                  <Badge>{selectedCode}</Badge>
                  {#if readiness.selection?.acknowledged || readiness.selection?.source}
                    <p class="text-xs text-muted-foreground">
                      {#if readiness.selection?.acknowledged}
                        Acknowledged by you
                      {:else if readiness.selection?.source === 'guidance_default'}
                        Guidance default · projection active
                      {:else}
                        Awaiting acknowledgement
                      {/if}
                      {#if readiness.selection?.source === 'student_self'}
                        · your choice
                      {/if}
                    </p>
                  {/if}
                {:else}
                  <Badge variant="secondary">None yet</Badge>
                {/if}
              </dd>
            </div>
            <div class="space-y-1">
              <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Enrollment</dt>
              <dd>
                <Badge variant={readiness.enrolled || readiness.enrolling ? 'default' : 'secondary'}>
                  {readiness.enrolled ? 'Enrolled' : readiness.enrolling ? 'Enrolling' : 'Not started'}
                </Badge>
              </dd>
            </div>
          </dl>
        </Card.Content>
      </Card.Root>

      {#if pool.default && pool.selection_status === 'pending_acknowledgement'}
        <Card.Root class="overflow-hidden border-emerald-600/30 shadow-sm">
          <Card.Header class="border-b bg-emerald-600/5 pb-3">
            <div class="flex items-center justify-between gap-3">
              <div>
                <Card.Title class="text-lg">Guidance default</Card.Title>
                <Card.Description>Your projection is already active — no action needed.</Card.Description>
              </div>
              <Badge variant="outline" class="shrink-0 border-emerald-600/40 text-emerald-700 dark:text-emerald-400">
                ✦ Auto
              </Badge>
            </div>
          </Card.Header>
          <Card.Content class="space-y-3 pt-4">
            <div class="flex items-start gap-3">
              <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-600 dark:text-emerald-400" />
              <div class="space-y-0.5">
                <p class="font-medium">{pool.default}</p>
                <p class="text-sm text-muted-foreground">
                  Set by Guidance after consultation. Quota is reserved and your projection is visible to
                  the admission team. Acknowledgement is optional — it marks this as your explicit current
                  selection.
                </p>
              </div>
            </div>
            {#if acknowledgeForm.errors.selection}
              <p class="text-sm text-destructive">{acknowledgeForm.errors.selection}</p>
            {/if}
            <Button variant="outline" class="min-h-11" disabled={acknowledgeForm.processing} onclick={acceptDefault}>
              {acknowledgeForm.processing ? 'Confirming…' : 'This is my choice'}
            </Button>
          </Card.Content>
        </Card.Root>
      {/if}

      {#if canStillChoose}
        <Card.Root class="overflow-hidden shadow-sm">
          <Card.Header class="border-b bg-muted/20 pb-3">
            <Card.Title class="text-lg">Choose another program</Card.Title>
            <Card.Description>
              {#if selectionExists}
                You already have a selection — picking an option here transfers it.
              {:else}
                Pick any open option; your choice is recorded once confirmed.
              {/if}
            </Card.Description>
          </Card.Header>
          <Card.Content class="space-y-3 pt-4">
            {#if options.length === 0}
              <p class="text-sm text-muted-foreground">No other programs are open for choice right now.</p>
            {:else}
              <ul class="space-y-2">
                {#each options as option (option.program_code)}
                  <li>
                    <button
                      type="button"
                      class="flex w-full items-start gap-3 rounded-lg border border-border px-3 py-3 text-left text-sm transition hover:border-primary/50 hover:bg-muted/20"
                      onclick={() => openSelect(option.program_code)}
                    >
                      <span
                        class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-muted-foreground/50"
                        aria-hidden="true"
                      ></span>
                      <span class="min-w-0 flex-1 space-y-0.5">
                        <span class="block font-medium">{option.program_name}</span>
                        <span class="block font-mono text-xs text-muted-foreground">
                          {option.program_code}
                        </span>
                        {#if option.basis}
                          <span class="block text-xs text-muted-foreground">Basis: {option.basis}</span>
                        {/if}
                      </span>
                    </button>
                  </li>
                {/each}
              </ul>
            {/if}
            {#if selectForm.errors.selection}
              <p class="text-sm text-destructive">{selectForm.errors.selection}</p>
            {/if}
          </Card.Content>
        </Card.Root>

        {#if selectionExists}
          <Card.Root class="border-destructive/30">
            <Card.Content class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-sm text-muted-foreground">
                Changed your mind? Cancel your current selection before enrollment starts.
              </p>
              <Button
                type="button"
                variant="outline"
                class="min-h-11 border-destructive/50 text-destructive hover:bg-destructive/10"
                onclick={() => (cancelOpen = true)}
              >
                Cancel selection
              </Button>
            </Card.Content>
          </Card.Root>
        {/if}
      {/if}

      {#if analysis}
        <Card.Root>
          <Card.Header class="pb-3">
            <Card.Title class="text-lg">Analysis</Card.Title>
            <Card.Description>Consultation evidence summary</Card.Description>
          </Card.Header>
          <Card.Content class="space-y-2 pt-2">
            <p class="text-sm">{analysis.message}</p>
            {#if analysis.status === 'result_not_ready'}
              <p class="flex gap-2 text-xs text-muted-foreground">
                <Info class="mt-0.5 size-3.5 shrink-0" />
                Unlocks after grading is finalized and the result is released.
              </p>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}
    {/if}
  </div>

  <Dialog.Root bind:open={confirmOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>{confirmTitle}</Dialog.Title>
        <Dialog.Description>{confirmDescription}</Dialog.Description>
      </Dialog.Header>
      {#if pendingProgram}
        <dl class="space-y-1 text-sm">
          <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">Program</dt>
            <dd>{pendingProgram.program_name}</dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-muted-foreground">Code</dt>
            <dd class="font-mono">{pendingProgram.program_code}</dd>
          </div>
          {#if pendingProgram.basis}
            <div class="flex justify-between gap-4">
              <dt class="text-muted-foreground">Basis</dt>
              <dd>{pendingProgram.basis}</dd>
            </div>
          {/if}
        </dl>
      {/if}
      {#if selectForm.errors.selection}
        <p class="text-sm text-destructive">{selectForm.errors.selection}</p>
      {/if}
      <Dialog.Footer>
        <Button
          type="button"
          variant="outline"
          class="min-h-11"
          onclick={() => (confirmOpen = false)}
        >
          Back
        </Button>
        <Button
          type="button"
          class="min-h-11"
          disabled={selectForm.processing || !pendingCode}
          onclick={confirmSelect}
        >
          {selectForm.processing
            ? 'Confirming…'
            : selectionExists
              ? 'Transfer choice'
              : 'Confirm choice'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <Dialog.Root bind:open={cancelOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Cancel program selection</Dialog.Title>
        <Dialog.Description>
          Tell us why so guidance can follow up. This cannot be undone.
        </Dialog.Description>
      </Dialog.Header>
      <div class="space-y-2">
        <Label for="cancel-reason">Reason</Label>
        <Textarea
          id="cancel-reason"
          bind:value={cancelForm.reason}
          placeholder="Why are you cancelling your selection?"
          maxlength="500"
        />
      </div>
      {#if cancelForm.errors.selection}
        <p class="text-sm text-destructive">{cancelForm.errors.selection}</p>
      {/if}
      <Dialog.Footer>
        <Button
          type="button"
          variant="outline"
          class="min-h-11"
          onclick={() => (cancelOpen = false)}
        >
          Back
        </Button>
        <Button
          type="button"
          variant="destructive"
          class="min-h-11"
          disabled={cancelForm.processing || !cancelForm.reason.trim()}
          onclick={confirmCancel}
        >
          {cancelForm.processing ? 'Cancelling…' : 'Cancel selection'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</ApplicantLayout>
