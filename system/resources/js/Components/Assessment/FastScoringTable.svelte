<script>
  import { newIdempotencyKey, formatDecimalValue } from '@/lib/utils';
  import { Button } from '@/Components/ui/button';

  let { rows = [], isFinalized = false, ondirtychange = () => {}, onsaved = () => {} } = $props();
  let records = $state([]);
  let globalEntryType = $state('manual_raw');
  let tableContainerEl = $state(null);
  let isSavingAll = $state(false);
  let saveAllMessage = $state('');
  function key(metric) {
    return String(metric.metric_id ?? metric.id ?? metric.aptitude_area_id);
  }

  function createRecord(source) {
    const { applicant, data } = source;
    const metrics = data.domains ?? data.metrics ?? [];
    const values = Object.fromEntries(
      metrics.map((metric) => [
        key(metric),
        formatDecimalValue(metric.raw_value ?? metric.score, metric.precision_scale ?? 0),
      ]),
    );
    const outputs = Object.fromEntries(
      metrics.map((metric) => [
        key(metric),
        Object.fromEntries(
          (metric.required_output_scales ?? []).map((scale) => [
            scale,
            formatDecimalValue(metric.typed_outputs?.[scale], metric.precision_scale ?? 2),
          ]),
        ),
      ]),
    );
    const sources = Object.fromEntries(metrics.map((metric) => [key(metric), metric.external_provenance ?? '']));
    const mode = data.is_final_only || data.scoring_mode === 'manual_converted' ? 'manual_converted' : 'manual_raw';
    const overrides = Object.fromEntries(metrics.map((metric) => [key(metric), Boolean(metric.overridden_from_omr)]));
    const reasons = Object.fromEntries(metrics.map((metric) => [key(metric), metric.override_reason ?? '']));
    const initial = {
      values,
      outputs,
      sources,
      kit: data.selected_test_kit_version_id == null ? '' : String(data.selected_test_kit_version_id),
      norm: data.selected_norm_package_version_id == null ? '' : String(data.selected_norm_package_version_id),
      mode,
      overrides,
      reasons,
    };
    return {
      applicant,
      data,
      metrics,
      values,
      outputs,
      sources,
      overrides,
      reasons,
      initial: JSON.stringify(initial),
      mode,
      kit: initial.kit,
      norm: initial.norm,
      baseline: data.score_baseline,
      state: 'saved',
      error: '',
      errors: {},
      operation: null,
      uncertain: false,
      frozen: Boolean(data.individual_frozen || isFinalized || !data.can_write),
      dirty: false,
    };
  }

  function csrfHeaders() {
    const token = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    return {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(token ? { 'X-XSRF-TOKEN': token } : {}),
    };
  }

  let lastRows = $state(null);
  $effect(() => {
    if (rows === lastRows) return;
    lastRows = rows;
    const current = new Map(records.map((row) => [String(row.applicant.attempt_id), row]));
    records = rows.map((source) => {
      const existing = current.get(String(source.applicant.attempt_id));
      if (existing?.dirty || existing?.uncertain) return existing;
      const rec = createRecord(source);
      if (!rec.data.is_final_only && !isFinalized) {
        rec.mode = globalEntryType;
      }
      return rec;
    });
  });

  function switchGlobalMode(mode) {
    globalEntryType = mode;
    for (const row of records) {
      if (row.data.is_final_only || row.frozen || isFinalized) continue;
      row.mode = mode;
      update(row);
    }
  }

  if (typeof window !== 'undefined') {
    window.addEventListener('beforeunload', (event) => {
      if (!records.some((row) => row.dirty || row.uncertain)) return;
      event.preventDefault();
      event.returnValue = '';
    });
  }

  function refreshDirty() {
    ondirtychange(records.some((row) => row.dirty || row.uncertain));
  }

  function update(row) {
    row.dirty = JSON.stringify({ values: row.values, outputs: row.outputs, sources: row.sources, kit: row.kit, norm: row.norm, mode: row.mode, overrides: row.overrides, reasons: row.reasons }) !== row.initial;
    row.state = row.dirty ? 'dirty' : 'saved';
    row.errors = {};
    refreshDirty();
  }

  function scorePayload(row) {
    const external = row.mode === 'manual_converted';
    const payload = [];
    for (const metric of row.metrics) {
      const metricKey = key(metric);
      if (external) {
        const scales = metric.required_output_scales ?? [];
        const values = row.outputs[metricKey] ?? {};
        const hasValue = scales.some((scale) => values[scale] !== '' && values[scale] !== null && values[scale] !== undefined);
        if (!metric.is_required && !hasValue) continue;
        if (scales.some((scale) => values[scale] === '' || values[scale] === null || values[scale] === undefined)) {
          row.errors[metricKey] = `Enter every required output scale for ${metric.name}.`;
          return null;
        }
        payload.push({
          metric_id: metric.metric_id ?? metric.id,
          typed_outputs: Object.fromEntries(scales.map((scale) => [scale, String(values[scale])])),
        });
      } else {
        if (metric.is_derived || metric.read_only) continue;
        const value = row.values[metricKey];
        if (value === '' || value === null || value === undefined) continue;
        if (metric.source === 'omr' && !row.overrides?.[metricKey]) continue;
        payload.push({ metric_id: metric.metric_id ?? metric.id, metric_code: metric.metric_code ?? metric.code, score: Number(value), override_omr: Boolean(row.overrides?.[metricKey]), override_reason: row.reasons?.[metricKey] || null });
      }
    }
    if (!payload.length) row.error = 'Enter at least one score before saving.';
    return payload.length ? payload : null;
  }

  function savePayload(row) {
    if (row.uncertain && row.operation) return row.operation;
    const scores = scorePayload(row);
    if (!scores) {
      row.state = 'failed';
      row.error ||= Object.values(row.errors)[0] ?? 'Enter at least one score before saving.';
      const metricId = Object.keys(row.errors)[0];
      if (metricId) document.getElementById(`fast-${row.applicant.attempt_id}-${metricId}`)?.focus();
      return null;
    }
    if (!row.kit) {
      row.state = 'failed';
      row.error = 'Select an exact locked Test Kit version before recording scores.';
      return null;
    }
    const body = {
      scoring_mode: row.mode,
      scores,
      test_kit_version_id: Number(row.kit),
      ...(row.mode === 'manual_raw' && row.norm ? { norm_package_version_id: Number(row.norm) } : {}),
      expected_baseline: row.baseline,
      idempotency_key: newIdempotencyKey(),
    };
    return { body };
  }

  async function save(row) {
    if (row.state === 'saving' || row.frozen) return;
    row.error = '';
    row.errors = {};
    const operation = savePayload(row);
    if (!operation) return;
    row.operation = operation;
    row.state = 'saving';
    try {
      const response = await fetch(row.data.urls.update, {
        method: 'PUT',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: csrfHeaders(),
        body: JSON.stringify(operation.body),
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        row.error = result.message ?? 'Unable to save this applicant row.';
        row.errors = result.errors ?? {};
        row.uncertain = response.status >= 500;
        row.state = response.status === 409 ? 'conflict' : 'failed';
        if (row.uncertain) row.operation = operation;
        else row.operation = null;
        return;
      }
      row.uncertain = false;
      row.operation = null;
      row.baseline = result.score_baseline;
      const responseGet = await fetch(row.applicant.score_url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      const canonical = await responseGet.json().catch(() => ({}));
      if (!responseGet.ok) throw new Error(canonical.message ?? 'Saved, but the canonical row could not be refreshed.');
      const fresh = createRecord({ applicant: row.applicant, data: canonical });
      Object.assign(row, fresh);
      row.state = 'saved';
      onsaved();
    } catch (error) {
      row.uncertain = true;
      row.state = 'failed';
      row.error = error?.message ?? 'Connection failed. Retry this exact save to resolve its outcome.';
      row.operation = operation;
    } finally {
      refreshDirty();
    }
  }

  function handleFieldFocus(event) {
    const target = event.currentTarget;
    if (!tableContainerEl || !target) return;
    const containerRect = tableContainerEl.getBoundingClientRect();
    const targetRect = target.getBoundingClientRect();
    const stickyLeftWidth = 192; // 12rem min-w-48 sticky left header
    const stickyRightWidth = 192; // 12rem min-w-48 sticky right action

    // If target is obscured behind sticky left
    if (targetRect.left < containerRect.left + stickyLeftWidth) {
      const diff = (containerRect.left + stickyLeftWidth + 16) - targetRect.left;
      tableContainerEl.scrollBy({ left: -diff, behavior: 'smooth' });
    }
    // If target is obscured behind sticky right
    else if (targetRect.right > containerRect.right - stickyRightWidth) {
      const diff = targetRect.right - (containerRect.right - stickyRightWidth - 16);
      tableContainerEl.scrollBy({ left: diff, behavior: 'smooth' });
    }
  }

  async function saveAllChanges() {
    if (isSavingAll) return;
    const eligibleRows = records.filter((row) => (row.dirty || row.uncertain) && !row.frozen && row.state !== 'saving');
    if (eligibleRows.length === 0) return;

    isSavingAll = true;
    saveAllMessage = `Saving ${eligibleRows.length} ${eligibleRows.length === 1 ? 'applicant' : 'applicants'}…`;
    let successCount = 0;
    let failCount = 0;

    for (const row of eligibleRows) {
      try {
        await save(row);
        if (row.state === 'saved') {
          successCount++;
        } else {
          failCount++;
        }
      } catch {
        failCount++;
      }
    }

    isSavingAll = false;
    if (failCount === 0) {
      saveAllMessage = `Successfully saved ${successCount} ${successCount === 1 ? 'applicant' : 'applicants'}.`;
    } else {
      saveAllMessage = `Saved ${successCount}, but ${failCount} ${failCount === 1 ? 'row' : 'rows'} had validation errors or conflicts.`;
    }
  }

  function moveNext(event) {
    if (event.key !== 'Enter' || event.ctrlKey || event.metaKey || event.shiftKey || event.target.tagName === 'TEXTAREA' || event.target.tagName === 'BUTTON') return;
    event.preventDefault();
    const fields = [...event.currentTarget.querySelectorAll('input:not(:disabled), select:not(:disabled)')];
    const index = fields.indexOf(event.target);
    const next = fields[index + 1];
    if (next) {
      next.focus();
    }
  }

  function handleRowKeydown(event, row) {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
      event.preventDefault();
      save(row);
      return;
    }
    moveNext(event);
  }
</script>

<div class="min-w-0 overflow-hidden">
  {#if !records.length}
    <p class="p-6 text-sm text-muted-foreground">No eligible applicants are available in the current roster.</p>
  {:else}
    <div bind:this={tableContainerEl} class="max-w-full overflow-x-auto overscroll-x-contain">
      {#if records.some((r) => !r.data.is_final_only) && !isFinalized}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-muted/20 px-4 py-2.5">
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-foreground">Scoring entry mode for raw setups:</span>
            <div class="inline-flex rounded-lg border border-border bg-muted/50 p-0.5" role="group" aria-label="Fast table scoring mode">
              <button
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors {globalEntryType === 'manual_raw' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                onclick={() => switchGlobalMode('manual_raw')}
              >
                Subtest Raw Scores
              </button>
              <button
                type="button"
                class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors {globalEntryType === 'manual_converted' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                onclick={() => switchGlobalMode('manual_converted')}
              >
                Aptitude Final Scores (Direct)
              </button>
            </div>
          </div>
          <span class="text-xs text-muted-foreground">
            {globalEntryType === 'manual_raw' ? 'Entering subtest items/counts' : 'Entering externally supplied final percentiles without local norms'}
          </span>
        </div>
      {/if}
      <table class="w-full min-w-[980px] border-collapse text-sm">
        <thead class="bg-muted/50"><tr>
          <th class="sticky left-0 z-10 min-w-48 bg-muted/50 px-3 py-3 text-left">Applicant</th>
          {#each (records[0]?.metrics ?? []) as column (key(column))}
            <th class="min-w-40 px-3 py-3 text-left">
              {column.name ?? column.construct_name ?? column.code}
              {#if column.is_required} *{/if}
            </th>
          {/each}
          <th class="min-w-60 px-3 py-3 text-left">Scoring options</th>
          <th class="sticky right-0 z-10 min-w-48 bg-muted/50 px-3 py-3 text-left">Save row</th>
        </tr></thead>
        <tbody>
          {#each records as row (row.applicant.attempt_id)}
            <tr class="border-t border-border align-top" onkeydown={(event) => handleRowKeydown(event, row)}>
              <th scope="row" class="sticky left-0 z-10 bg-card px-3 py-3 text-left">
                <div class="font-medium">{row.applicant.name}</div><div class="text-xs text-muted-foreground">{row.applicant.reference_code}</div>
                {#if row.frozen}<div class="mt-1 text-xs text-muted-foreground">Locked — read only</div>{/if}
              </th>
              {#each row.metrics as metric (key(metric))}
                {@const metricKey = key(metric)}
                <td class="min-w-40 px-3 py-3">
                  {#if row.mode === 'manual_converted'}
                    {#each metric.required_output_scales ?? [] as scale (scale)}
                      <label class="mb-1 block text-xs">{scale} {metric.is_required ? '*' : ''}</label>
                      <input id={`fast-${row.applicant.attempt_id}-${metricKey}`} type="number" min="0" max="100" step={10 ** -(metric.precision_scale ?? 0)} value={row.outputs[metricKey]?.[scale] ?? ''} disabled={row.frozen || !row.data.can_write} onfocus={handleFieldFocus} oninput={(event) => { row.outputs[metricKey][scale] = event.currentTarget.value; update(row); }} class="h-10 w-full rounded-md border border-input bg-background px-2" />
                    {/each}
                    {#if !metric.required_output_scales?.length}
                      <span class="text-xs text-muted-foreground">No external final for this metric</span>
                    {/if}
                  {:else if metric.is_derived || metric.read_only}
                    <output class="inline-block min-h-10 rounded-md bg-muted px-2 py-2">{formatDecimalValue(metric.raw_value, metric.precision_scale ?? 2) || '—'}</output><span class="ml-1 text-xs text-muted-foreground">Calculated on individual form</span>
                  {:else}
                    <input id={`fast-${row.applicant.attempt_id}-${metricKey}`} type="number" min={metric.min_raw_value ?? metric.min_value} max={metric.max_raw_value ?? metric.max_value} step={10 ** -(metric.precision_scale ?? 0)} required={Boolean(metric.is_required)} value={row.values[metricKey] ?? ''} disabled={row.frozen || !row.data.can_write} onfocus={handleFieldFocus} oninput={(event) => { row.values[metricKey] = event.currentTarget.value; update(row); }} class="h-10 w-full rounded-md border border-input bg-background px-2" />
                    {#if metric.source === 'omr'}
                      <label class="mt-1 flex items-center gap-1 text-xs"><input type="checkbox" checked={Boolean(row.overrides?.[metricKey])} disabled={row.frozen || !row.data.can_write} onchange={(event) => { row.overrides ??= {}; row.overrides[metricKey] = event.currentTarget.checked; update(row); }} />Override OMR value</label>
                      {#if row.overrides?.[metricKey]}<input aria-label="OMR override reason" placeholder="Reason for override" value={row.reasons?.[metricKey] ?? ''} disabled={row.frozen || !row.data.can_write} onfocus={handleFieldFocus} oninput={(event) => { row.reasons ??= {}; row.reasons[metricKey] = event.currentTarget.value; update(row); }} class="mt-1 h-9 w-full rounded-md border border-input bg-background px-2" />{/if}
                    {/if}
                  {/if}
                  {#if row.errors[metricKey]}<p class="mt-1 text-xs text-destructive">{row.errors[metricKey]}</p>{/if}
                </td>
              {/each}
              <td class="px-3 py-3">
                {#if !row.data.is_final_only && !isFinalized}
                  <label class="mb-1 block text-xs">Entry type</label>
                  <select value={row.mode} disabled={row.frozen} onfocus={handleFieldFocus} onchange={(event) => { row.mode = event.currentTarget.value; update(row); }} class="mb-2 h-10 w-full rounded-md border border-input bg-background px-2"><option value="manual_raw">Raw scores</option><option value="manual_converted">Aptitude Final Scores</option></select>
                {:else}<p class="mb-2 text-xs">{row.mode === 'manual_converted' ? 'Aptitude Final Scores' : 'Raw scores'}</p>{/if}
                {#if row.mode === 'manual_raw' && row.data.norm_versions?.length}
                  <label class="mb-1 block text-xs">Norm package version</label><select value={row.norm} disabled={row.frozen} onfocus={handleFieldFocus} onchange={(event) => { row.norm = event.currentTarget.value; update(row); }} class="h-10 w-full rounded-md border border-input bg-background px-2"><option value="">Select norms</option>{#each row.data.norm_versions as norm (norm.id ?? norm.version_id)}<option value={norm.id ?? norm.version_id}>{norm.name ?? `Version ${norm.version_number ?? ''}`}</option>{/each}</select>
                {/if}
              </td>
              <td class="sticky right-0 z-10 bg-card px-3 py-3">
                <Button type="button" size="sm" disabled={row.frozen || row.state === 'saving' || (!row.dirty && !row.uncertain)} onclick={() => save(row)}>{row.state === 'saving' ? 'Saving…' : row.uncertain ? 'Retry exact save' : 'Save applicant row'}</Button>
                <p class="mt-1 text-xs" role={row.state === 'failed' || row.state === 'conflict' ? 'alert' : 'status'}>{row.state === 'saving' ? 'Saving row…' : row.state === 'saved' ? 'Saved' : row.state === 'dirty' ? 'Unsaved changes' : row.state === 'conflict' ? 'Conflict — reload the applicant form' : row.error || (row.state === 'failed' ? 'Save failed' : '')}</p>
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
  {/if}
  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 bg-muted/20">
    <div class="text-xs text-muted-foreground">
      Tab and Shift+Tab follow editable fields and scroll into view. Enter moves to the next field; Ctrl/Cmd+Enter saves row.
      {#if saveAllMessage}
        <span class="ml-2 font-medium text-foreground">{saveAllMessage}</span>
      {/if}
    </div>
    {#if !isFinalized && records.some((r) => !r.frozen && (r.dirty || r.uncertain))}
      {@const dirtyCount = records.filter((r) => !r.frozen && (r.dirty || r.uncertain)).length}
      <Button
        type="button"
        variant="default"
        size="sm"
        class="gap-1.5 shadow-sm font-semibold"
        disabled={isSavingAll}
        onclick={saveAllChanges}
      >
        {isSavingAll ? 'Saving all…' : `Save all changes (${dirtyCount})`}
      </Button>
    {/if}
  </div>
</div>
