<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import ModeBanner from '@/Components/kit/ModeBanner.svelte';
  import SearchSuggestionDropdown from '@/Components/kit/SearchSuggestionDropdown.svelte';
  import ScoringSetupNavigation from '@/Components/Assessment/ScoringSetupNavigation.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Dialog from '@/Components/ui/dialog';
  import { router, usePage } from '@inertiajs/svelte';
  import { newIdempotencyKey } from '@/lib/utils';
  import { Clock, FileSpreadsheet, Lock, Save, SlidersHorizontal, Trash2, User } from 'lucide-svelte';
  import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';

  let {
    page_state = 'ready',
    exam_session_id = null,
    grading_status = null,
    grading_version = null,
    score_baseline = null,
    can_write = false,
    urls = {},
    finalized = false,
    individual_frozen = false,
    is_profile_bound = false,
    has_scoring_profile = false,
    test_kit_versions = [],
    selected_test_kit_version_id = null,
    test_kit_binding_persisted = false,
    norm_versions = [],
    selected_norm_package_version_id = null,
    instrument_profile = null,
    scoring_mode = 'manual_raw',
    is_final_only = false,
    attempt = {},
    exam = {},
    domains = [],
    constructs = [],
    search_context = {},
    binding_state = null,
    administration_state = null,
    setup_links = null,
    can_manage_catalog = false,
    blockers = [],
  } = $props();
  const page = usePage();
  const saveError = $derived(page.props.errors?.scores ?? '');
  let localInputError = $state('');

  const idempotencyKey = $state(newIdempotencyKey());
  const clearCommandKey = $state(newIdempotencyKey());
  const initialScores = Object.fromEntries(domains.map((metric) => [
    String(metric.metric_id ?? metric.id),
    metric.raw_value ?? metric.score ?? '',
  ]));
  let scores = $state({ ...initialScores });
  let externalOutputs = $state(Object.fromEntries(domains.map((metric) => [
    String(metric.metric_id ?? metric.id),
    Object.fromEntries((metric.required_output_scales ?? []).map((scale) => [
      scale,
      metric.typed_outputs?.[scale] ?? '',
    ])),
  ])));
  const initialExternalOutputs = JSON.stringify(externalOutputs);
  let externalProvenance = $state('');
  let externalSources = $state(Object.fromEntries(domains.map((metric) => [
    String(metric.metric_id ?? metric.id),
    metric.external_provenance ?? '',
  ])));
  const initialExternalSources = JSON.stringify(externalSources);
  let selectedKitVersionId = $state(selected_test_kit_version_id === null ? '' : String(selected_test_kit_version_id));
  let selectedNormVersionId = $state(selected_norm_package_version_id === null ? '' : String(selected_norm_package_version_id));
  let activeMode = $state(is_final_only || scoring_mode === 'manual_converted' ? 'manual_converted' : 'manual_raw');
  let omrOverrides = $state({});
  let overrideReasons = $state({});
  let lookupScope = $state('current_session');
  let searchInput = $state(null);
  let suggestionDropdown = $state(null);
  let quickSearch = $state('');
  let saving = $state(false);
  let clearing = $state(false);
  let clearDialog = $state(false);
  let confirmNavigationOpen = $state(false);
  let pendingNavigationUrl = $state(null);
  let pendingNavigationTarget = $state(null);
  function formIsDirty() {
    return isDirty;
  }
  function scoreKey(metric) {
    return String(metric.metric_id ?? metric.id);
  }


  function isMetricDirty(metric) {
    return String(scores[scoreKey(metric)] ?? '') !== String(initialScores[scoreKey(metric)] ?? '');
  }

  const isDirty = $derived(
    domains.some(isMetricDirty) ||
    JSON.stringify(externalOutputs) !== initialExternalOutputs ||
    JSON.stringify(externalSources) !== initialExternalSources,
  );

  const hasScores = $derived(domains.some(
    (metric) => !metric.is_derived && scores[scoreKey(metric)] !== '' && scores[scoreKey(metric)] !== null,
  ) || Object.values(externalOutputs).some((outputs) => Object.values(outputs).some((value) => value !== '' && value !== null)));

  const selectedKitRecord = $derived(
    test_kit_versions.find((kit) => String(kit.id) === String(selectedKitVersionId)) ?? null,
  );
  function rawValueFor(metric) {
    if (metric.is_derived && metric.input_codes?.length) {
      const componentMetrics = metric.input_codes.map((code) => domains.find(
        (candidate) => (candidate.metric_code ?? candidate.code) === code,
      ));
      const values = componentMetrics.map((component) => component ? scores[scoreKey(component)] : '');
      if (componentMetrics.every(Boolean) && values.every((value) => value !== '' && value !== null && value !== undefined && Number.isFinite(Number(value)))) {
        return values.reduce((sum, value) => sum + Number(value), 0);
      }
      return metric.raw_value ?? '';
    }

    return scores[scoreKey(metric)];
  }

  function convertedFor(metric) {
    const rawValue = rawValueFor(metric);
    const raw = Number(rawValue);
    if (rawValue === '' || rawValue === null || rawValue === undefined || !Number.isFinite(raw)) return null;
    const row = metric.conversion_rows?.find(
      (conversionRow) => raw >= Number(conversionRow.min_score) && raw <= Number(conversionRow.max_score),
    );
    return row ? row.output_value : null;
  }

  function conversionLabel(metric) {
    return `Converted${metric.construct_name ? ` (${metric.construct_name})` : ''}`;
  }

  function convertedMissing(metric) {
    const rawValue = rawValueFor(metric);
    const raw = Number(rawValue);
    return rawValue !== '' && rawValue !== null && rawValue !== undefined &&
      Number.isFinite(raw) && !metric.conversion_rows?.some(
        (conversionRow) => raw >= Number(conversionRow.min_score) && raw <= Number(conversionRow.max_score),
      );
  }

  function sourceLabel(metric) {
    if (metric.is_derived) return 'DERIVED';
    if (metric.source === 'omr' && omrOverrides[scoreKey(metric)]) return 'OVERRIDE';
    if (metric.source === 'omr') return 'OMR';
    if (metric.source === 'import') return 'IMPORT';
    return 'MANUAL';
  }

  function compositeSummary(metric) {
    if (metric.rule_kind !== 'sum_raw_metrics' || !metric.input_codes?.length) return null;

    const components = metric.input_codes.map((code) => domains.find(
      (candidate) => (candidate.metric_code ?? candidate.code) === code,
    ));
    if (components.some((component) => !component)) return null;

    const values = components.map((component) => rawValueFor(component));
    if (values.some((value) => value === '' || value === null || value === undefined || !Number.isFinite(Number(value)))) {
      return null;
    }

    return {
      expression: components.map((component) => component.name).join(' + '),
      score: values.reduce((sum, value) => sum + Number(value), 0),
      max: components.every((component) => component.max_raw_value !== null
        && component.max_raw_value !== undefined
        && Number.isFinite(Number(component.max_raw_value)))
        ? components.reduce((sum, component) => sum + Number(component.max_raw_value), 0)
        : null,
    };
  }

  const breadcrumbs = [
    { label: 'Scoring', href: urls.hub ?? '/scoring' },
    { label: `Session ${exam.code ?? ''}`, href: urls.roster ?? `/scoring/sessions/${exam_session_id}` },
    { label: attempt.reference_code ?? 'Applicant' },
  ];

  function handleSearchActivation(action) {
    if (action?.type !== 'navigate' || !action.url) return;
    if (formIsDirty()) {
      pendingNavigationUrl = action.url;
      pendingNavigationTarget = action.label ?? 'another examinee';
      confirmNavigationOpen = true;
      return;
    }
    router.visit(action.url);
  }

  function handleSetupNavigation({ url, label }) {
    if (!url) return;
    if (formIsDirty()) {
      pendingNavigationUrl = url;
      pendingNavigationTarget = label;
      confirmNavigationOpen = true;
      return;
    }
    router.visit(url);
  }
  function updateScoreSelection(key, value) {
    const query = new URLSearchParams(window.location.search);
    if (value) query.set(key, value);
    else query.delete(key);
    const target = `${window.location.pathname}${query.toString() ? `?${query}` : ''}`;
    if (formIsDirty()) {
      pendingNavigationUrl = target;
      pendingNavigationTarget = 'the selected assessment context';
      confirmNavigationOpen = true;
      return;
    }
    router.get(target, {}, { preserveScroll: true });
  }


  function confirmDiscardAndNavigate() {
    if (pendingNavigationUrl) {
      router.visit(pendingNavigationUrl);
    }
    pendingNavigationUrl = null;
    pendingNavigationTarget = null;
    confirmNavigationOpen = false;
  }
  function handleSearchInput(event) {
    quickSearch = event.currentTarget.value;
    suggestionDropdown?.handleInput(quickSearch);
  }

  function handleSearchFocus() {
    suggestionDropdown?.handleFocus();
    suggestionDropdown?.handleInput(quickSearch);
  }

  function handleSearchKeydown(event) {
    suggestionDropdown?.handleKeydown(event);
  }

  function saveScores() {
    if (!can_write || grading_status === 'finalized' || finalized || saving || !urls.update) return;
    localInputError = '';
    if (!selectedKitVersionId) {
      localInputError = 'Select an exact locked Test Kit version before recording scores.';
      return;
    }

    let payload;
    if (activeMode === 'manual_converted') {
      if (!has_scoring_profile) {
        localInputError = 'Select an exact profile-bound Test Kit before recording external final values.';
        return;
      }
      payload = domains
        .filter((metric) => (metric.required_output_scales ?? []).length > 0)
        .map((metric) => {
          const outputs = externalOutputs[scoreKey(metric)] ?? {};
          const hasAnyValue = metric.required_output_scales.some((scale) => outputs[scale] !== '' && outputs[scale] !== null && outputs[scale] !== undefined);
          if (!metric.is_required && !hasAnyValue) return null;
          if (metric.required_output_scales.some((scale) => outputs[scale] === '' || outputs[scale] === null || outputs[scale] === undefined)) {
            localInputError = `Enter every required output scale for ${metric.name}.`;
            return null;
          }
          const sourceReference = String(externalSources[scoreKey(metric)] ?? '').trim();
          return {
            metric_id: metric.metric_id ?? metric.id,
            typed_outputs: Object.fromEntries(metric.required_output_scales.map((scale) => [scale, String(outputs[scale])])),
            external_provenance: sourceReference,
          };
        })
        .filter(Boolean);
      if (localInputError) return;
      if (payload.length === 0) {
        localInputError = 'Enter a complete typed final for at least one profile metric.';
        return;
      }
    } else {
      if (!has_scoring_profile) {
        localInputError = 'Select an exact Test Kit version, then load its profile before recording scores.';
        return;
      }
      payload = domains
        .map((metric) => {
          const value = scores[scoreKey(metric)];
          if (metric.is_derived || metric.read_only || value === '' || value === null || value === undefined) return null;
          if (is_profile_bound && metric.source === 'omr' && omrOverrides[scoreKey(metric)] !== true) return null;
          return {
            metric_id: metric.metric_id ?? metric.id,
            metric_code: metric.metric_code ?? metric.code,
            score: Number(value),
            override_omr: Boolean(omrOverrides[scoreKey(metric)]),
            override_reason: overrideReasons[scoreKey(metric)] || null,
          };
        })
        .filter(Boolean);
    }

    if (payload.length === 0) {
      localInputError = 'Enter at least one score before saving.';
      return;
    }
    saving = true;
    router.put(urls.update, {
      ...(score_baseline ? { expected_baseline: score_baseline } : {}),
      scoring_mode: activeMode,
      scores: payload,
      test_kit_version_id: Number(selectedKitVersionId),
      ...(activeMode === 'manual_raw' && selectedNormVersionId
        ? { norm_package_version_id: Number(selectedNormVersionId) }
        : {}),
      idempotency_key: idempotencyKey,
    }, {
      preserveScroll: true,
      onFinish: () => {
        saving = false;
      },
    });
  }

  function clearScores() {
    if (!can_write || grading_status === 'finalized' || finalized || clearing || !urls.destroy) return;
    clearing = true;
    router.delete(urls.destroy, {
      data: {
        expected_version: grading_version,
        idempotency_key: clearCommandKey,
      },
      onFinish: () => {
        clearing = false;
        clearDialog = false;
      },
    });
  }
</script>

<svelte:head><title>{attempt.reference_code ?? 'Applicant'} — Scores</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 w-full max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="rounded-xl border border-border bg-card p-6">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-2">
          <User class="size-5 shrink-0 text-muted-foreground" />
          <h1 class="truncate text-xl font-semibold tracking-tight">{attempt.name}</h1>
        </div>
        <div class="flex min-w-0 flex-wrap items-center justify-end gap-2">
          {#if attempt.id}
            <ApplicantActionsMenu attemptId={attempt.id} context={{ exam_session_id }} />
          {/if}
          {#if binding_state === 'awaiting_norm_conversion' || administration_state === 'awaiting_norm_conversion'}
            <Badge variant="outline" class="gap-1 border-amber-500/50 bg-amber-50/50 text-amber-700">
              <Clock class="size-3" />
              Awaiting norm conversion
            </Badge>
          {/if}
          <div class="relative w-48 sm:w-56">
            <label class="sr-only" for="grading-examinee-search">Switch examinee</label>
            <Input
              bind:ref={searchInput}
              id="grading-examinee-search"
              bind:value={quickSearch}
              type="search"
              placeholder="Switch examinee…"
              role="combobox"
              aria-label="Switch examinee"
              aria-autocomplete="list"
              aria-expanded={suggestionDropdown?.isExpanded() ?? false}
              aria-controls={suggestionDropdown?.listboxIdentifier()}
              aria-activedescendant={suggestionDropdown?.activeDescendantId()}
              class="h-9"
              oninput={handleSearchInput}
              onfocus={handleSearchFocus}
              onkeydown={handleSearchKeydown}
            />
            <SearchSuggestionDropdown
              bind:this={suggestionDropdown}
              search={quickSearch}
              academicCycleId={search_context?.academic_cycle_id}
              examSessionId={search_context?.exam_session_id ?? exam_session_id}
              contextKey={search_context?.exam_session_id ?? exam_session_id}
              {lookupScope}
              domainContext="grading"
              destinationKind="score_entry"
              navigationOnly={true}
              inputRef={searchInput}
              onactivate={handleSearchActivation}
            />
          </div>
          <label class="sr-only" for="grading-lookup-scope">Search scope</label>
          <select id="grading-lookup-scope" bind:value={lookupScope} class="h-9 rounded-md border border-input bg-background px-2 text-xs text-foreground">
            <option value="current_session">This session</option>
            <option value="selected_cycle">All sessions in year</option>
          </select>
          <ScoringSetupNavigation {can_manage_catalog} {setup_links} onNavigate={handleSetupNavigation} />
        </div>
      </div>
      <p class="mt-2 text-sm text-muted-foreground">
        Reference: {attempt.reference_code} · {exam.code ?? '—'} · {exam.starts_at ?? '—'} · {exam.room_name ?? '—'}
      </p>
    </div>


    {#if finalized || grading_status === 'finalized'}
      <ModeBanner variant="info" title="Finalized">
        This grading session is finalized — scores are locked and can no longer be edited.
      </ModeBanner>
    {:else if individual_frozen}
      <ModeBanner variant="info" title="Individual result frozen">
        Routine score edits and clearing are locked for this applicant. Other applicants in the batch can remain open.
      </ModeBanner>
    {:else if is_final_only && administration_state !== 'ready_to_finalize' && administration_state !== 'finalized'}
      <ModeBanner variant="info" title="Awaiting final scores">
        Record every required aptitude and its source reference. Raw scoring, local norm conversion and OMR do not apply to this Test Setup.
      </ModeBanner>
    {:else if binding_state === 'awaiting_norm_conversion' || administration_state === 'awaiting_norm_conversion'}
      <ModeBanner variant="info" title="Awaiting norm conversion">
        All required raw results have been accepted. Raw results are preserved and awaiting approved norm conversion.
      </ModeBanner>
    {/if}

    <div class="rounded-xl border border-border bg-card p-6">
      <div class="grid gap-3 sm:grid-cols-2">
        <label class="space-y-1.5 text-xs font-semibold text-foreground">
          <span>Exact locked Test Kit version</span>
          <select
            value={selectedKitVersionId}
            disabled={test_kit_binding_persisted}
            required
            class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
            onchange={(event) => {
              selectedKitVersionId = event.currentTarget.value;
              updateScoreSelection('test_kit_version_id', selectedKitVersionId);
            }}
          >
            <option value="" disabled>Select a Test Kit version</option>
            {#each test_kit_versions as kit (kit.id)}
              <option value={String(kit.id)}>{kit.display_name} ({kit.kit_code} v{kit.version_number}) · {kit.profile_name}</option>
            {/each}
          </select>
        </label>
        {#if has_scoring_profile && !is_final_only}
          <label class="space-y-1.5 text-xs font-semibold text-foreground">
            <span>Norm package version <span class="font-normal text-muted-foreground">(optional)</span></span>
            <select
              value={selectedNormVersionId}
              class="border-input bg-background min-h-10 w-full rounded-md border px-3 py-2 text-sm"
              onchange={(event) => {
                selectedNormVersionId = event.currentTarget.value;
                updateScoreSelection('norm_package_version_id', selectedNormVersionId);
              }}
            >
              <option value="">No norm selected — raw results remain awaiting conversion</option>
              {#each norm_versions as norm (norm.id)}
                <option value={String(norm.id)}>{norm.label}</option>
              {/each}
            </select>
          </label>
        {/if}
      </div>
      {#if !test_kit_binding_persisted && !has_scoring_profile}
        <p class="mt-2 text-xs text-muted-foreground">Select a currently active Test Kit version to load its exact profile. Scores remain disabled until that profile is selected.</p>
      {/if}
      {#if can_manage_catalog}
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/60 bg-muted/30 px-3 py-2 text-xs">
          <span class="text-muted-foreground">
            {is_final_only ? 'Need to adjust required aptitudes, precision, or source requirements?' : 'Need to adjust subtests, raw-score formulas, norm tables, or kit materials?'}
          </span>
          <div class="flex flex-wrap items-center gap-3">
            {#if selectedKitRecord?.test_setup_url || selectedKitRecord?.test_kit_id}
              {@const testSetupHref = selectedKitRecord.test_setup_url || `/setup/exam-materials/test-kits/${selectedKitRecord.test_kit_id}/test-setup?test_kit_id=${selectedKitRecord.test_kit_id}&test_kit_version_id=${selectedKitRecord.id}`}
              {@const kitHref = selectedKitRecord.kit_url || `/setup/exam-materials/test-kits/${selectedKitRecord.test_kit_id}?version=${selectedKitRecord.id}`}
              <a
                href={testSetupHref}
                class="text-primary font-medium underline underline-offset-4"
                onclick={(event) => {
                  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                  event.preventDefault();
                  handleSetupNavigation({ url: testSetupHref, label: 'Test Setup & Norms' });
                }}
              >
                Configure Test Specific Settings ({selectedKitRecord.kit_code}) →
              </a>
              <a
                href={kitHref}
                class="text-primary font-medium underline underline-offset-4"
                onclick={(event) => {
                  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                  event.preventDefault();
                  handleSetupNavigation({ url: kitHref, label: 'Test Kit' });
                }}
              >
                Open Test Kit →
              </a>
            {:else}
              {@const kitsHref = setup_links?.test_kits || '/setup/exam-materials/test-kits'}
              <a
                href={kitsHref}
                class="text-primary font-medium underline underline-offset-4"
                onclick={(event) => {
                  if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                  event.preventDefault();
                  handleSetupNavigation({ url: kitsHref, label: 'Test Kits & Scoring Setup' });
                }}
              >
                Manage Test Kits &amp; Scoring Settings →
              </a>
            {/if}
          </div>
        </div>
      {/if}
      <div class="mt-6 inline-flex rounded-lg border border-border bg-muted/50 p-1" role="group" aria-label="Scoring input mode">
        {#if !is_final_only}
        <button
          type="button"
          class="rounded-md px-3 py-2 text-sm font-medium transition-colors {activeMode === 'manual_raw' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
          aria-pressed={activeMode === 'manual_raw'}
          onclick={() => (activeMode = 'manual_raw')}
        >Subtest Raw Scores</button>
        {/if}
        <button
          type="button"
          class="rounded-md px-3 py-2 text-sm font-medium transition-colors {activeMode === 'manual_converted' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}"
          aria-pressed={activeMode === 'manual_converted'}
          disabled={!has_scoring_profile}
          onclick={() => (activeMode = 'manual_converted')}
        >Aptitude Final Scores</button>
      </div>

      {#if has_scoring_profile && activeMode === 'manual_raw'}
        <p class="mt-2 text-xs text-muted-foreground">
          Scoring for {instrument_profile?.display_name ?? 'selected Test Kit profile'}. Derived composites calculate automatically from component scores.
        </p>
      {:else if !has_scoring_profile}
        <p class="mt-2 text-xs text-muted-foreground">Choose an exact active Test Kit version above to load profile-bound scoring inputs.</p>
      {:else}
        <div class="mt-2.5 space-y-2 text-xs text-muted-foreground">
          <p>Enter the declared final scores for each aptitude. No raw values or conversion history will be created.</p>
        </div>
      {/if}

      {#if activeMode === 'manual_raw' && has_scoring_profile}
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {#each domains as metric (metric.metric_id ?? metric.id)}
            {@const summary = compositeSummary(metric)}
            <div class="rounded-lg border border-border bg-background p-4">
              <div class="flex items-start justify-between gap-2">
                <label class="text-sm font-medium leading-tight" for="score-{scoreKey(metric)}">
                  {metric.name}
                </label>
                <Badge variant="outline" class="shrink-0 text-[10px]">{sourceLabel(metric)}</Badge>
              </div>
              {#if summary}
                <div class="mt-3 rounded-md border border-border/70 bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                  {summary.expression} = {summary.score}{#if summary.max !== null} / {summary.max}{/if}
                </div>
              {/if}
              {#if metric.is_derived}
                <p class="mt-2 text-xs text-muted-foreground">Derived composite · read-only</p>
              {/if}
              <div class="mt-3 flex items-center gap-2">
                <Input
                  id="score-{scoreKey(metric)}"
                  type="number"
                  min={metric.min_raw_value ?? 0}
                  max={metric.max_raw_value ?? 100}
                  step={metric.precision_scale > 0 ? (1 / Math.pow(10, metric.precision_scale)) : 1}
                  value={metric.is_derived ? rawValueFor(metric) : scores[scoreKey(metric)]}
                  disabled={!can_write || finalized || grading_status === 'finalized' || metric.is_derived || metric.read_only || (metric.source === 'omr' && !omrOverrides[scoreKey(metric)])}
                  placeholder="0.00"
                  class="w-28"
                  oninput={(event) => {
                    scores[scoreKey(metric)] = event.currentTarget.value;
                  }}
                />
                <span class="text-xs text-muted-foreground">
                  / {is_profile_bound ? (metric.max_raw_value ?? 100) : (metric.max_items ?? metric.max_raw_value ?? 100)}{#if typeof metric.unit === 'string' && metric.unit.trim()} {metric.unit.trim()}{/if}
                </span>
                {#if can_write && metric.source === 'omr' && !finalized && grading_status !== 'finalized' && !omrOverrides[scoreKey(metric)]}
                  <Button type="button" variant="outline" size="sm" onclick={() => (omrOverrides[scoreKey(metric)] = true)}>Edit</Button>
                {/if}
              </div>
              {#if metric.source === 'omr' && omrOverrides[scoreKey(metric)]}
                <div class="mt-3 flex items-center gap-2">
                  <Input
                    value={overrideReasons[scoreKey(metric)] ?? ''}
                    placeholder="Reason for overriding OMR..."
                    aria-label="Reason for overriding OMR"
                    class="h-9 text-xs"
                    oninput={(event) => {
                      overrideReasons[scoreKey(metric)] = event.currentTarget.value;
                    }}
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={!can_write || finalized || grading_status === 'finalized'}
                    onclick={() => {
                      scores[scoreKey(metric)] = initialScores[scoreKey(metric)];
                      omrOverrides[scoreKey(metric)] = false;
                      overrideReasons[scoreKey(metric)] = '';
                    }}
                  >Cancel</Button>
                </div>
              {/if}
              {#if metric.converted_score !== null && metric.converted_score !== undefined && !isMetricDirty(metric) && convertedFor(metric) === null}
                <p class="mt-3 text-sm font-medium text-emerald-700" data-testid="converted-{metric.id}">
                  {conversionLabel(metric)}: {metric.converted_score}
                </p>
              {:else if convertedFor(metric) !== null}
                <p class="mt-3 text-sm font-medium text-emerald-700" data-testid="converted-preview-{metric.id}">
                  {conversionLabel(metric)}: {convertedFor(metric)}
                </p>
              {:else if selectedNormVersionId && metric.scoring_method === 'conversion_table' && convertedMissing(metric)}
                <p class="mt-3 text-sm text-destructive" data-testid="converted-missing-{metric.id}">Outside selected norm table</p>
              {/if}
            </div>
          {/each}
        </div>
      {:else if !has_scoring_profile}
        <p class="mt-4 rounded-lg border border-border/80 bg-muted/30 p-3 text-xs text-muted-foreground">No profile-bound scoring inputs are loaded. Select an exact active Test Kit version above; score entry remains disabled until its profile is available.</p>
      {:else}
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {#each domains.filter((metric) => (metric.required_output_scales ?? []).length > 0) as metric (metric.metric_id ?? metric.id)}
            <div class="rounded-lg border border-border bg-background p-4">
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-medium leading-tight">{metric.name}</p>
                <Badge variant="outline" class="shrink-0 text-[10px]">FINAL SCORE</Badge>
              </div>
              <div class="mt-3 space-y-3">
                {#each metric.required_output_scales as scale (`${metric.metric_id ?? metric.id}:${scale}`)}
                  <label class="block space-y-1 text-xs font-medium text-muted-foreground" for="external-output-{scoreKey(metric)}-{scale}">
                    <span>{scale}</span>
                    <Input
                      id="external-output-{scoreKey(metric)}-{scale}"
                      type="number"
                      min={scale === 'stanine' ? 1 : 0}
                      max={scale === 'stanine' ? 9 : 100}
                      step={metric.precision_scale > 0 ? (1 / Math.pow(10, metric.precision_scale)) : 1}
                      value={externalOutputs[scoreKey(metric)]?.[scale] ?? ''}
                      disabled={!can_write || finalized || grading_status === 'finalized'}
                      oninput={(event) => {
                        externalOutputs[scoreKey(metric)] = {
                          ...(externalOutputs[scoreKey(metric)] ?? {}),
                          [scale]: event.currentTarget.value,
                        };
                      }}
                    />
                  </label>
                {/each}
              </div>
            </div>
          {/each}
        </div>
      {/if}

      {#if localInputError || saveError}
        <p class="mt-4 text-sm text-destructive" data-testid="score-error">{localInputError || saveError}</p>
      {/if}

      <div class="mt-6 flex flex-wrap gap-3">
        {#if can_write && !finalized && grading_status !== 'finalized'}
          <Button class="min-h-10 gap-2" disabled={saving || clearing || !urls.update || !selectedKitVersionId || !has_scoring_profile} onclick={saveScores}>
            <Save class="size-4" />
            {saving ? 'Saving…' : activeMode === 'manual_converted' ? 'Save final scores' : 'Save scores'}
          </Button>
          {#if hasScores}
            <Button variant="destructive" class="min-h-10 gap-2" disabled={saving || clearing || !urls.destroy} onclick={() => (clearDialog = true)}>
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
          This removes every aptitude area score for {attempt.name} ({attempt.reference_code}) in this grading session. Scores can be re-entered later while the session is open.
        </Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => (clearDialog = false)}>Back</Button>
        <Button type="button" variant="destructive" disabled={clearing} onclick={clearScores}>{clearing ? 'Clearing…' : 'Clear scores'}</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  <Dialog.Root bind:open={confirmNavigationOpen}>
    <Dialog.Content class="sm:max-w-md">
      <Dialog.Header>
        <Dialog.Title>Discard unsaved score changes?</Dialog.Title>
        <Dialog.Description>Your score edits have not been saved. Leave this examinee and go to {pendingNavigationTarget ?? 'the selected page'}?</Dialog.Description>
      </Dialog.Header>
      <Dialog.Footer>
        <Button type="button" variant="outline" onclick={() => {
          confirmNavigationOpen = false;
          pendingNavigationUrl = null;
          pendingNavigationTarget = null;
        }}>Stay here</Button>
        <Button type="button" variant="destructive" onclick={confirmDiscardAndNavigate}>Discard and continue</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>

  {#if finalized || grading_status === 'finalized'}
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
      <Lock class="size-4" />
      Scores are locked for this finalized grading session.
    </div>
  {/if}
</StaffLayout>
