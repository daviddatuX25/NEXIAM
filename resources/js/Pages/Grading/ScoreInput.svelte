<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import { Input } from '@/Components/ui/input';
  import { Save, User, Calculator, PenLine } from 'lucide-svelte';

  let { sessionId = '1', applicantId = '1001', workflowStatus = 'in_progress', enableNormalizedScores = false, applicant = {}, domains = [], existing_scores = [] } = $props();
  const sid = $derived(String(sessionId));
  const isReadOnly = $derived(workflowStatus === 'completed');
  const autoCompute = $derived(enableNormalizedScores === true);
  const hasConversionTableDomains = $derived(domains.some((d) => d.scoring_method === 'conversion_table'));
  const hasFormulaDomains = $derived(domains.some((d) => d.scoring_method !== 'conversion_table'));

  const breadcrumbs = $derived([
    { label: 'Grading', href: '/admin/grading' },
    { label: 'Session #' + sid, href: `/admin/grading/sessions/${sid}` },
    { label: applicant?.name ?? 'Applicant' }
  ]);

  const initialScores = $derived.by(() => {
    const arr = [];
    for (const d of domains) {
      const existing = existing_scores.find((s) => s.aptitude_area_id === d.id);
      arr.push({
        aptitude_area_id: d.id,
        raw_score: existing?.raw_score ?? 0,
        max_score: existing?.max_score ?? d.max_items ?? 0,
        normalized_score: existing?.normalized_score ?? null,
        percentile_string: existing?.percentile_string ?? null,
      });
    }
    return arr;
  });

  let scores = $state([]);
  $effect(() => {
    scores = [...initialScores];
  });

  function getScore(domainId) {
    const s = scores.find((x) => x.aptitude_area_id === domainId);
    return s ? s.raw_score : 0;
  }

  function setScore(domainId, value) {
    const num = Math.max(0, parseInt(value, 10) || 0);
    scores = scores.map((s) =>
      s.aptitude_area_id === domainId ? { ...s, raw_score: Math.min(num, s.max_score) } : s
    );
  }

  function getNormalizedScore(domainId) {
    const s = scores.find((x) => x.aptitude_area_id === domainId);
    return s?.normalized_score ?? null;
  }

  function setNormalizedScore(domainId, value) {
    const num = value === '' ? null : Math.max(0, parseFloat(value) || 0);
    scores = scores.map((s) =>
      s.aptitude_area_id === domainId ? { ...s, normalized_score: num } : s
    );
  }

  function getPercentileString(domainId) {
    const s = scores.find((x) => x.aptitude_area_id === domainId);
    return s?.percentile_string ?? null;
  }

  function setPercentileString(domainId, value) {
    const val = value === '' ? null : value;
    scores = scores.map((s) =>
      s.aptitude_area_id === domainId ? { ...s, percentile_string: val } : s
    );
  }

  function getMax(domainId) {
    return scores.find((x) => x.aptitude_area_id === domainId)?.max_score ?? 0;
  }

  function computeNormalized(domainId, raw) {
    const d = domains.find((x) => x.id === domainId);
    if (!d || !d.formula) return null;
    
    // Robust Security Pattern: Prevent XSS by strictly limiting allowed characters
    // We only allow math operators, digits, spaces, and the specific variables 'x' and 'max_items'.
    const stripped = d.formula.replace(/max_items/g, '').replace(/x/g, '');
    if (!/^[\d\s\+\-\*\/\(\)\.]+$/.test(stripped)) {
      // Fallback if formula uses unsupported advanced functions (e.g. max(), min())
      // The backend will calculate it upon saving.
      return null;
    }

    try {
      const fn = new Function('x', 'max_items', `return ${d.formula};`);
      const res = fn(raw, d.max_items || 0);
      return Number.isFinite(res) ? Number(res.toFixed(2)) : null;
    } catch (e) {
      return null;
    }
  }



  let saving = $state(false);
  let saveError = $state('');
  function saveScores() {
    saving = true;
    saveError = '';
    const s = scores.map((sc) => {
      if (autoCompute) {
        return {
          aptitude_area_id: sc.aptitude_area_id,
          raw_score: sc.raw_score,
          max_score: sc.max_score,
        };
      }
      const d = domains.find((x) => x.id === sc.aptitude_area_id);
      if (d?.scoring_method === 'conversion_table') {
        return {
          aptitude_area_id: sc.aptitude_area_id,
          percentile_string: sc.percentile_string,
        };
      }
      return {
        aptitude_area_id: sc.aptitude_area_id,
        normalized_score: sc.normalized_score,
      };
    });
    router.put(`/admin/grading/sessions/${sid}/applicants/${applicantId}/scores`, { scores: s }, {
      onError: (err) => {
        saving = false;
        saveError = Object.values(err).flat().join(', ') || 'Failed to save scores.';
      },
      onFinish: () => (saving = false),
    });
  }

  let clearing = $state(false);
  function clearScores() {
    if (!confirm('Are you sure you want to clear all scores for this applicant?')) return;
    clearing = true;
    saveError = '';
    router.delete(`/admin/grading/sessions/${sid}/applicants/${applicantId}/scores`, {
      onError: (err) => {
        clearing = false;
        saveError = Object.values(err).flat().join(', ') || 'Failed to clear scores.';
      },
      onFinish: () => {
        clearing = false;
      },
    });
  }
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0 max-w-2xl">
    <!-- Mode indicator -->
    {#if autoCompute}
      <div class="flex items-center gap-2 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200">
        <Calculator class="h-4 w-4 shrink-0" />
        <span><strong>Auto-compute mode</strong> — Enter raw scores (items correct). Normalized scores are calculated automatically using aptitude area formulas.</span>
      </div>
    {:else}
      <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
        <PenLine class="h-4 w-4 shrink-0" />
        <span>
          <strong>Manual mode</strong> —
          {#if hasConversionTableDomains && hasFormulaDomains}
            Enter already-converted percentiles for conversion table areas, and normalized scores for formula areas.
          {:else if hasConversionTableDomains}
            Enter already-converted percentiles directly for each aptitude area.
          {:else}
            Enter normalized scores directly for each aptitude area.
          {/if}
        </span>
      </div>
    {/if}

    <Card>
      <CardHeader>
        <CardTitle class="flex items-center gap-2">
          <User class="h-5 w-5" />
          {applicant.name}
        </CardTitle>
        <CardDescription>
          Reference: {applicant.reference}
          {#if autoCompute}
            · Enter raw score per aptitude area (items correct)
          {:else if hasConversionTableDomains && hasFormulaDomains}
            · Enter percentiles or normalized scores per aptitude area
          {:else if hasConversionTableDomains}
            · Enter already-converted percentile per aptitude area
          {:else}
            · Enter normalized score per aptitude area
          {/if}
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-6">
        <!-- Domain score inputs -->
        <div class="grid gap-4 sm:grid-cols-2">
          {#each domains as domain}
            {@const max = domain.max_items ?? domain.max_score ?? 0}
            <div class="rounded-lg border border-border p-4 space-y-2">
              <label for="score-{domain.id}" class="text-sm font-medium leading-none">
                {domain.name} ({domain.code})
              </label>
                {#if autoCompute}
                  {@const raw = getScore(domain.id)}
                  {#if domain.scoring_method === 'conversion_table'}
                    {@const match = domain.conversion_table?.find(r => r.raw_score === raw)}
                    <div class="flex items-center gap-3">
                      <Input
                        id="score-{domain.id}"
                        type="number"
                        min="0"
                        max={max}
                        value={raw}
                        oninput={(e) => !isReadOnly && setScore(domain.id, e.currentTarget.value)}
                        disabled={isReadOnly}
                        class="w-20"
                      />
                      <span class="text-muted-foreground">/ {max}</span>
                      <span class="text-sm font-medium tabular-nums"
                        class:text-green-700={match}
                        class:text-amber-600={!match}>
                        {match ? match.percentile_output : 'Out of Range'}
                      </span>
                    </div>
                  {:else}
                    {@const computedNorm = computeNormalized(domain.id, raw)}
                    <div class="flex items-center gap-3">
                      <Input
                        id="score-{domain.id}"
                        type="number"
                        min="0"
                        max={max}
                        value={raw}
                        oninput={(e) => !isReadOnly && setScore(domain.id, e.currentTarget.value)}
                        disabled={isReadOnly}
                        class="w-20"
                      />
                      <span class="text-muted-foreground">/ {max}</span>
                      {#if computedNorm !== null}
                        <span class="text-sm font-medium tabular-nums text-muted-foreground">
                          ({computedNorm} pts)
                        </span>
                      {/if}
                    </div>
                    {#if !domain.has_formula}
                      <p class="text-xs text-muted-foreground">No formula configured — normalized score will not be computed.</p>
                    {/if}
                  {/if}
                {:else}
                  {#if domain.scoring_method === 'conversion_table'}
                    {@const pctString = getPercentileString(domain.id)}
                    <div class="flex items-center gap-3">
                      <Input
                        id="score-{domain.id}"
                        type="text"
                        value={pctString ?? ''}
                        oninput={(e) => !isReadOnly && setPercentileString(domain.id, e.currentTarget.value)}
                        disabled={isReadOnly}
                        class="w-32"
                        placeholder="e.g. 85th, 99+"
                      />
                      <span class="text-sm text-muted-foreground">percentile</span>
                    </div>
                  {:else}
                    {@const normScore = getNormalizedScore(domain.id)}
                    <div class="flex items-center gap-3">
                      <Input
                        id="score-{domain.id}"
                        type="number"
                        min="0"
                        step="0.01"
                        value={normScore ?? ''}
                        oninput={(e) => !isReadOnly && setNormalizedScore(domain.id, e.currentTarget.value)}
                        disabled={isReadOnly}
                        class="w-24"
                        placeholder="0.00"
                      />
                      <span class="text-sm text-muted-foreground">points</span>
                    </div>
                  {/if}
              {/if}
            </div>
          {/each}
        </div>

        {#if saveError}
          <p class="text-sm text-destructive">{saveError}</p>
        {/if}

        <div class="flex flex-wrap gap-3 pt-2">
          {#if !isReadOnly}
          <Button onclick={saveScores} disabled={saving || clearing} class="min-h-[44px]">
            <Save class="h-4 w-4 mr-2" />
            {saving ? 'Saving…' : 'Save scores'}
          </Button>
          {#if existing_scores.length > 0}
            <Button variant="destructive" onclick={clearScores} disabled={saving || clearing} class="min-h-[44px]">
              {clearing ? 'Clearing…' : 'Clear scores'}
            </Button>
          {/if}
          {/if}

        </div>
      </CardContent>
    </Card>
  </div>
</AuthenticatedLayout>