<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Dialog from '@/Components/ui/dialog';
  import { router, usePage } from '@inertiajs/svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import { Lock, PenLine, Save, Trash2, User } from 'lucide-svelte';

  let {
    page_state = 'ready',
    degraded = false,
    session_id = null,
    session_status = 'open',
    finalized = false,
    attempt = {},
    exam = {},
    domains = [],
  } = $props();

  const page = usePage();
  const saveError = $derived(page.props.errors?.scores ?? '');

  const idempotencyKey = $state(newIdempotencyKey());
  let scores = $state(Object.fromEntries(domains.map((d) => [String(d.id), d.score ?? ''])));

  let saving = $state(false);
  let clearing = $state(false);
  let clearDialog = $state(false);

  const hasScores = $derived(domains.some((d) => scores[String(d.id)] !== '' && scores[String(d.id)] !== null));

  function convertedFor(domain) {
    const raw = Number(scores[String(domain.id)]);
    if (scores[String(domain.id)] === '' || scores[String(domain.id)] === null || isNaN(raw)) return null;
    if (domain.scoring_method !== 'conversion_table') return null;
    const row = domain.conversion_rows?.find(
      (r) => raw >= Number(r.min_score) && raw <= Number(r.max_score),
    );
    return row ? row.output_value : null;
  }

  function domainMax(domain) {
    return domain.max_items ?? 100;
  }

  const breadcrumbs = [
    { label: 'Scoring', href: '/grading' },
    { label: `Session ${exam.code ?? ''}`, href: `/grading/sessions/${session_id}` },
    { label: attempt.reference_code ?? 'Applicant' },
  ];

  function saveScores() {
    if (finalized || saving) return;
    const payload = domains
      .map((d) => {
        const value = scores[String(d.id)];
        if (value === '' || value === null || value === undefined) return null;
        return { aptitude_area_id: d.id, score: Number(value) };
      })
      .filter((entry) => entry !== null);

    if (payload.length === 0) return;

    saving = true;
    router.put(`/grading/sessions/${session_id}/attempts/${attempt.id}/scores`, {
      scores: payload,
      idempotency_key: idempotencyKey,
    }, {
      preserveScroll: true,
      onFinish: () => {
        saving = false;
      },
    });
  }

  function clearScores() {
    if (finalized || clearing) return;
    clearing = true;
    router.delete(`/grading/sessions/${session_id}/attempts/${attempt.id}/scores`, {
      onFinish: () => {
        clearing = false;
        clearDialog = false;
      },
    });
  }
</script>

<svelte:head><title>{attempt.reference_code ?? 'Applicant'} — Scores</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 max-w-3xl space-y-6 p-4 sm:p-6">
    {#if degraded}
      <ModeBanner variant="warning" title="Manual grading mode">
        Inference provider unavailable; manual entry remains authoritative.
      </ModeBanner>
    {/if}

    {#if finalized}
      <ModeBanner variant="info" title="Finalized">
        This grading session is finalized — scores are locked and can no longer be edited.
      </ModeBanner>
    {:else}
      <ModeBanner variant="warning" title="Manual mode">
        Enter the raw score for each aptitude area. The converted value previews live from the area's
        confidential conversion table.
      </ModeBanner>
    {/if}

    <div class="rounded-xl border border-border bg-card p-6">
      <div class="flex items-center gap-2">
        <User class="size-5 text-muted-foreground" />
        <h1 class="text-xl font-semibold tracking-tight">{attempt.name}</h1>
      </div>
      <p class="mt-1 text-sm text-muted-foreground">
        Reference: {attempt.reference_code} · {exam.code ?? '—'} · {exam.starts_at ?? '—'} ·{' '}
        {exam.room_name ?? '—'}
      </p>

      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        {#each domains as domain}
          <div class="rounded-lg border border-border p-4">
            <label class="text-sm font-medium leading-none" for="score-{domain.id}">
              {domain.name} ({domain.code})
            </label>
            <div class="mt-2 flex items-center gap-3">
              <Input
                id="score-{domain.id}"
                type="number"
                min="0"
                max={domainMax(domain)}
                step="0.01"
                value={scores[String(domain.id)]}
                disabled={finalized}
                placeholder="0.00"
                class="w-24"
                oninput={(e) => {
                  scores[String(domain.id)] = e.currentTarget.value;
                }}
              />
              <span class="text-sm text-muted-foreground">/ {domainMax(domain)} points</span>
            </div>
            {#if domain.scoring_method === 'conversion_table'}
              {#if finalized}
                <p class="mt-2 text-sm font-medium text-emerald-700" data-testid="converted-{domain.id}">
                  Converted: {domain.converted_score ?? '—'}
                </p>
              {:else if convertedFor(domain)}
                <p class="mt-2 text-sm font-medium text-emerald-700" data-testid="converted-preview-{domain.id}">
                  Converted: {convertedFor(domain)}
                </p>
              {:else if scores[String(domain.id)] !== '' && scores[String(domain.id)] !== null}
                <p class="mt-2 text-sm text-destructive" data-testid="converted-missing-{domain.id}">
                  Outside conversion table
                </p>
              {/if}
            {/if}
          </div>
        {/each}
      </div>

      {#if saveError}
        <p class="mt-4 text-sm text-destructive" data-testid="score-error">{saveError}</p>
      {/if}

      <div class="mt-6 flex flex-wrap gap-3">
        {#if !finalized}
          <Button
            class="min-h-10 gap-2"
            disabled={saving || clearing}
            onclick={saveScores}
          >
            <Save class="size-4" />
            {saving ? 'Saving…' : 'Save scores'}
          </Button>
          {#if hasScores}
            <Button
              variant="destructive"
              class="min-h-10 gap-2"
              disabled={saving || clearing}
              onclick={() => (clearDialog = true)}
            >
              <Trash2 class="size-4" />
              {clearing ? 'Clearing…' : 'Clear scores'}
            </Button>
          {/if}
        {/if}
      </div>
    </div>
  </div>

  <Dialog.Root bind:open={clearDialog}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Clear all scores</Dialog.Title>
        <Dialog.Description>
          This removes every aptitude area score for {attempt.name} ({attempt.reference_code}) in
          this grading session. Scores can be re-entered later while the session is open.
        </Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (clearDialog = false)}>Back</Button>
        <Button type="button" variant="destructive" disabled={clearing} onclick={clearScores}>
          {clearing ? 'Clearing…' : 'Clear scores'}
        </Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  {#if finalized}
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
      <Lock class="size-4" />
      Scores are locked for this finalized grading session.
    </div>
  {/if}
</StaffLayout>
