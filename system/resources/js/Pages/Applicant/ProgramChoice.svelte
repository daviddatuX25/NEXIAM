<script>
import { newIdempotencyKey } from '@/lib/utils';
  /* global route */
  /**
   * Applicant program choice — own-scope portal page.
   * Renders the current selection, alternative pool options (acknowledge,
   * select/transfer), cancel, and the analysis region. The server is the only
   * authorizer — no engine UI, no synthetic recommendation, and every action
   * carries a fresh idempotency key.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
  import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
  import E2PageState from '@/Pages/Admission/E2PageState.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { Link, useForm } from '@inertiajs/svelte';
  import ReasonSuggestionChips from '@/Components/kit/ReasonSuggestionChips.svelte';
  import { formatEnumLabel } from '@/lib/date-utils.js';
  import { APPLICANT_PROGRAM_CANCEL_SUGGESTIONS } from '@/lib/reason-suggestions.js';
  import { Clock3, Info } from 'lucide-svelte';

  let { page_state = 'ready', support = null } = $props();

  const readiness = $derived(support?.readiness ?? {});
  const pool = $derived(support?.program_pool ?? {});
  const actions = $derived(support?.actions ?? {});
  const analysis = $derived(support?.analysis ?? null);
  const tabfm = $derived(support?.tabfm ?? {});

  const selectedCode = $derived(readiness.selection?.program_code ?? null);
  const selectionExists = $derived(['pending_acknowledgement', 'active'].includes(readiness.selection?.status));
  const options = $derived(pool.options ?? []);
  const canStillChoose = $derived(Boolean(actions.can_choose));

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
  let tourOpen = $state(false);

  const choiceContext = $derived.by(() => {
    const isEnrolled = Boolean(readiness.enrolled || readiness.enrolling);
    const isRel = Boolean(readiness.result_released);
    const isAdv = Boolean(readiness.advised);
    const isAck = Boolean(readiness.selection?.acknowledged);
    const code = selectedCode;
    const safetyKey = `${page_state}:${isRel ? 'rel' : 'unrel'}:${isAdv ? 'adv' : 'unadv'}:${code ?? 'none'}:${isAck ? 'ack' : 'unack'}:${isEnrolled ? 'locked' : 'open'}`;

    return {
      entityKey: code ?? 'program-choice',
      safetyKey,
      freshness: 'current',
      pageState: page_state,
      isResultReleased: isRel,
      isAdvised: isAdv,
      selectedProgramCode: code,
      isAcknowledged: isAck,
      selectionSource: readiness.selection?.source ?? 'none',
      isEnrollingOrEnrolled: isEnrolled,
      hasDefaultProgram: Boolean(pool.default),
      defaultProgramName: pool.default?.program_name ?? null,
      alternativeOptionsCount: options.length,
      canStillChoose,
    };
  });
  const pendingProgram = $derived(options.find((option) => option.program_code === pendingCode) ?? null);
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

<svelte:head><title>Your program plans</title></svelte:head>

<ApplicantLayout title="Your program plans">
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
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-2xl font-semibold tracking-tight">Your program plans</h1>
          <TourTriggerGlyph tourKey="applicant-program-choice" label="Need help choosing a program? Ask Kuya Xiam!" onclick={() => (tourOpen = true)} />
        </div>
        <p class="text-sm text-muted-foreground">Your recorded program and optional updates.</p>
      </div>

      {#if tabfm.status === 'available' && tabfm.matches?.length > 0}
        <Card.Root class="border-sky-600/30 bg-sky-50/70 shadow-sm dark:bg-sky-950/20" data-tour="program-choice-tabfm">
          <Card.Header class="border-b border-sky-600/20 pb-3">
            <Card.Title class="text-lg">TabPFN Decision-Support Notice</Card.Title>
            <Card.Description>{tabfm.disclaimer}</Card.Description>
          </Card.Header>
          <Card.Content class="space-y-3 pt-4">
            <h2 class="font-medium">Program compatibility</h2>
            <ul class="space-y-3">
              {#each tabfm.matches as match (match.program_code)}
                <li class="rounded-lg border bg-background/80 p-3">
                  <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                      <p class="font-medium">{match.program_name}</p>
                      <p class="font-mono text-xs text-muted-foreground">{match.program_code}</p>
                    </div>
                    <Badge variant="outline">{match.fit_band}</Badge>
                  </div>
                  <div class="mt-3 flex items-center gap-3">
                    <div
                      class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                      role="progressbar"
                      aria-label={`${match.program_name} compatibility index`}
                      aria-valuemin="0"
                      aria-valuemax="100"
                      aria-valuenow={match.compatibility_index}
                    >
                      <div class="h-full rounded-full bg-primary" style={`width: ${match.compatibility_index}%`}></div>
                    </div>
                    <span class="min-w-12 text-right text-sm font-semibold">{match.compatibility_index}/100</span>
                  </div>
                  {#if match.drivers?.length}
                    <p class="mt-2 text-xs text-muted-foreground">Drivers: {match.drivers.join(', ')}</p>
                  {/if}
                </li>
              {/each}
            </ul>
          </Card.Content>
        </Card.Root>
      {/if}
      <Card.Root class="overflow-hidden shadow-sm" data-tour="program-choice-status">
        <Card.Header class="border-b bg-muted/20 pb-3">
          <Card.Title class="text-lg">Your recorded program</Card.Title>
        </Card.Header>
        <Card.Content class="pt-5">
          {#if selectedCode}
            <div class="space-y-1">
              <Badge>{selectedCode}</Badge>
              {#if readiness.selection?.acknowledged}
                <p class="text-xs text-muted-foreground">Acknowledged by you</p>
              {:else if readiness.selection?.source === 'guidance_default'}
                <p class="text-xs text-muted-foreground">Set by Guidance</p>
              {:else if readiness.selection?.source === 'student_self'}
                <p class="text-xs text-muted-foreground">Your recorded choice</p>
              {/if}
              {#if readiness.selection?.quota?.configured}
                <p class="text-xs text-muted-foreground">Planned capacity: {readiness.selection.quota.committed} / {readiness.selection.quota.capacity} reserved ({readiness.selection.quota.remaining} remaining)</p>
              {:else if readiness.selection?.quota && !readiness.selection.quota.configured}
                <p class="text-xs text-muted-foreground">Planned capacity: not configured</p>
              {/if}
            </div>
          {:else if pool.default}
            <p class="text-sm text-muted-foreground">Guidance recommends {pool.default.program_name} ({pool.default.program_code}). Your own selection is not yet recorded.</p>
            {#if pool.default?.quota?.configured}
              <p class="mt-1 text-xs text-muted-foreground">Planned capacity: {pool.default.quota.committed} / {pool.default.quota.capacity} reserved ({pool.default.quota.remaining} remaining)</p>
            {:else if pool.default?.quota && !pool.default.quota.configured}
              <p class="mt-1 text-xs text-muted-foreground">Planned capacity: not configured</p>
            {/if}
          {:else}
            <p class="text-sm text-muted-foreground">No current program selection.</p>
          {/if}
          {#if actions.standing_selection_differs_from_pool}
            <p class="mt-3 text-sm text-muted-foreground">Your current selection is not among the latest Guidance options. It remains unchanged; choose an available option only if you want to transfer it.</p>
          {/if}
        </Card.Content>
      </Card.Root>
      <section class="space-y-3 rounded-lg border bg-card p-4" data-tour="program-choice-plans">
        <p class="text-sm text-muted-foreground">Updating your plans helps staff keep your record accurate. This is optional—not required for clearance.</p>
        <div class="flex flex-wrap items-start gap-3">
          {#if actions.can_acknowledge}
            <Button data-tour="program-choice-acknowledge" variant="outline" class="min-h-11" disabled={acknowledgeForm.processing} onclick={acceptDefault}>
              {acknowledgeForm.processing ? 'Saving…' : 'Still my plan'}
            </Button>
          {/if}
          <details class="min-w-0 flex-1" data-tour="program-choice-default">
            <summary class="inline-flex min-h-11 cursor-pointer items-center rounded-md border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Something changed</summary>
            <div class="mt-3 flex flex-col items-start gap-2">
              {#if canStillChoose && options.length > 0}
                <Link href="#program-choice-options" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline">Choose another available program</Link>
              {:else}
                <Link href="/applicant/admission-support?group=offices&amp;draft_reason=program_changed" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline">I chose another program</Link>
              {/if}
              <Link href="/applicant/admission-support?group=offices&amp;draft_reason=program_considering" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline">I am considering another program</Link>
              <Link href="/applicant/admission-support?group=offices&amp;draft_reason=program_enrolled_elsewhere" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline">I enrolled in another program</Link>
              <Link href="/applicant/admission-support?group=offices&amp;draft_reason=program_help" class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline">I need help deciding</Link>
              <p class="text-xs text-muted-foreground">Reports open an editable draft. Nothing is sent until you choose Send, and a report does not change your official program.</p>
            </div>
          </details>
        </div>
        {#if actions.clearance_confirmed}
          <p class="text-xs text-muted-foreground">Your clearance is confirmed. Request a correction before an official program change.</p>
        {/if}
        {#if acknowledgeForm.errors.selection}<p class="text-sm text-destructive">{acknowledgeForm.errors.selection}</p>{/if}
        {#if pool.default?.quota || readiness.selection?.quota || options.some(o => o.quota)}
          <p class="text-xs text-muted-foreground">Capacity snapshot only; numbers may change. Viewing this page does not reserve a place or change your recorded program.</p>
        {/if}
      </section>

      {#if actions.state === 'awaiting_recommendation'}
        <Card.Root class="border-dashed">
          <Card.Content class="p-4 text-sm text-muted-foreground">Awaiting Guidance’s current program recommendation. {selectedCode ? 'Your existing selection remains recorded above.' : 'No program choice has been recorded.'}</Card.Content>
        </Card.Root>
      {/if}

      {#if canStillChoose && options.length > 0}
        <Card.Root id="program-choice-options" class="overflow-hidden shadow-sm" data-tour="program-choice-options">
          <Card.Header class="border-b bg-muted/20 pb-3">
            <Card.Title class="text-lg">{selectionExists ? 'Other Guidance options' : 'Available Guidance options'}</Card.Title>
            <Card.Description>
              {#if selectionExists}
                Choosing an option transfers your current selection after confirmation.
              {:else}
                Confirm an available option to record your program choice.
              {/if}
            </Card.Description>
          </Card.Header>
          <Card.Content class="space-y-3 pt-4">
            <ul class="space-y-2">
              {#each options as option (option.program_code)}
                <li>
                  <button
                    type="button"
                    class="flex w-full items-start gap-3 rounded-lg border border-border px-3 py-3 text-left text-sm transition hover:border-primary/50 hover:bg-muted/20"
                    onclick={() => openSelect(option.program_code)}
                  >
                    <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border border-muted-foreground/50" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1 space-y-0.5">
                      <span class="block font-medium">{option.program_name}</span>
                      <span class="block font-mono text-xs text-muted-foreground">{option.program_code}</span>
                      {#if option.quota?.configured}
                        <span class="block text-xs text-muted-foreground">Planned capacity: {option.quota.committed} / {option.quota.capacity} reserved ({option.quota.remaining} remaining)</span>
                      {:else if option.quota && !option.quota.configured}
                        <span class="block text-xs text-muted-foreground">Planned capacity: not configured</span>
                      {/if}
                      {#if option.basis}
                        <span class="block text-xs text-muted-foreground">Basis: {formatEnumLabel(option.basis)}</span>
                      {/if}
                    </span>
                  </button>
                </li>
              {/each}
            </ul>
            {#if selectForm.errors.selection}
              <p class="text-sm text-destructive">{selectForm.errors.selection}</p>
            {/if}
          </Card.Content>
        </Card.Root>

      {/if}
        {#if actions.can_cancel}
          <Card.Root class="border-destructive/30">
            <Card.Content class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
              <p class="text-sm text-muted-foreground">Cancel your current selection only if you no longer want to keep it.</p>
              <Button type="button" variant="outline" class="min-h-11 border-destructive/50 text-destructive hover:bg-destructive/10" onclick={() => (cancelOpen = true)}>
                Cancel selection
              </Button>
            </Card.Content>
          </Card.Root>
        {/if}

      {#if analysis}
        <Card.Root data-tour="program-choice-analysis">
          <Card.Header class="pb-3">
            <Card.Title class="text-lg">Analysis</Card.Title>
            <Card.Description>Program compatibility evidence summary</Card.Description>
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
              <dd>{formatEnumLabel(pendingProgram.basis)}</dd>
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
        <ReasonSuggestionChips
          suggestions={APPLICANT_PROGRAM_CANCEL_SUGGESTIONS}
          bind:value={cancelForm.reason}
          maxlength={500}
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
  <XiamNpcDialog tourKey="applicant-program-choice" context={choiceContext} bind:open={tourOpen} />
</ApplicantLayout>
