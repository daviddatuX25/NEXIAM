<script>
  import { untrack } from 'svelte';
  import { Button } from '@/Components/ui/button';
  import * as Dialog from '@/Components/ui/dialog';
  import { csrfHeaders } from '@/lib/utils.js';

  let { purpose = 'demo', scenario = 'application-review', active = true, can_control = false, initial = null, index_url = '/demo/preparation', onbusychange = () => {} } = $props();
  const uid = $props.id();
  const presets = [
    ['72h-reminder', 'Starts in 72 hours'], ['24h-reminder', 'Starts in 24 hours'],
    ['2h-reminder', 'Starts in 2 hours'], ['1h-reminder', 'Starts in 1 hour'],
    ['soon', 'Starts in 45 minutes'], ['door-open', 'Door check-in window'],
    ['live', 'Live exam'], ['ended', 'Ended exam'],
  ];
  const windows = ['72h', '24h', '2h', '1h'];
  let selectedScenario = $state(scenario);
  let selectedSetId = $state('');
  let bookletVersionId = $state('');
  let sessionId = $state('');
  let preset = $state('1h-reminder');
  let windowKey = $state('1h');
  let preparation = $state(null);
  let pending = $state(null);
  let error = $state('');
  let message = $state('');
  let result = $state(null);
  let preview = $state(null);
  let confirmationOpen = $state(false);
  let reviewDialog = $state(null);
  let confirmationError = $state('');
  let expired = $state(false);
  let retryConfirmation = $state(false);
  let requestEpoch = 0;
  let controller = null;
  let loadedContext = '';
  const selectedSet = $derived(preparation?.selected_set ?? null);
  const current = $derived(preparation?.purpose === purpose && preparation?.scenario === selectedScenario && (preparation?.selected_set?.id ?? '') === selectedSetId && String(preparation?.materials?.selected_booklet_version_id ?? '') === bookletVersionId);
  const busy = $derived(pending !== null);

  $effect(() => { selectedScenario = scenario; selectedSetId = ''; bookletVersionId = ''; });
  $effect(() => { onbusychange(pending === 'confirm'); });
  $effect(() => {
    const context = `${purpose}:${selectedScenario}:${selectedSetId}:${bookletVersionId}`;
    if (!active || !can_control) {
      untrack(() => { invalidate(); preview = null; confirmationOpen = false; });
      return;
    }
    untrack(() => {
      if (initial && loadedContext === '' && initial.purpose === purpose && initial.scenario === selectedScenario) {
        acceptPreparation(initial);
        loadedContext = `${purpose}:${selectedScenario}:${initial.selected_set?.id ?? ''}:${initial.materials?.selected_booklet_version_id ?? ''}`;
      } else if (context !== loadedContext) {
        void loadPreparation();
      }
    });
    return () => { invalidate(); };
  });
  $effect(() => {
    if (!confirmationOpen || !preview?.operation?.expires_at) return;
    const expiry = new Date(preview.operation.expires_at).getTime();
    expired = !Number.isFinite(expiry) || expiry <= Date.now();
    if (!Number.isFinite(expiry)) return;
    const timer = setTimeout(() => { expired = Date.now() >= expiry; }, Math.min(2_147_483_647, Math.max(0, expiry - Date.now())));
    return () => clearTimeout(timer);
  });

  function invalidate() {
    requestEpoch += 1;
    controller?.abort();
    controller = null;
    pending = null;
  }
  function relativeUrl(value) {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || value.includes('\\')) return null;
    if (typeof window === 'undefined') return value;
    const url = new URL(value, window.location.origin);
    return url.origin === window.location.origin ? `${url.pathname}${url.search}${url.hash}` : null;
  }
  function begin(kind) {
    invalidate();
    controller = new AbortController();
    pending = kind;
    return { epoch: requestEpoch, signal: controller.signal };
  }
  async function request(url, signal, body) {
    const safeUrl = relativeUrl(url);
    if (!safeUrl) throw new Error('The server did not return a current-origin preparation link. Refresh the preparation details.');
    const response = await fetch(safeUrl, {
      method: body === undefined ? 'GET' : 'POST', credentials: 'same-origin', redirect: 'error', signal,
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'ngrok-skip-browser-warning': '1', ...(body === undefined ? {} : { 'Content-Type': 'application/json', ...csrfHeaders() }) },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    if (!(response.headers.get('content-type') ?? '').includes('application/json')) {
      throw new Error('Preparation returned a non-JSON response. Your previous details are retained; sign in or refresh before retrying.');
    }
    const data = await response.json();
    if (!response.ok) {
      const fallback = response.status === 409 ? 'Preparation changed or this review expired. Refresh details and explicitly review a new operation.' : response.status === 403 || response.status === 404 ? 'You no longer have access to this preparation.' : response.status === 419 || response.status === 401 ? 'Your session expired. Sign in or refresh before retrying.' : 'This operation is blocked. Review the prerequisites before retrying.';
      const failure = new Error(typeof data.message === 'string' ? data.message : fallback);
      failure.status = response.status;
      throw failure;
    }
    return data;
  }
  function acceptPreparation(data) {
    if (data?.purpose !== purpose || data?.scenario !== selectedScenario || !Array.isArray(data.scenarios) || !Array.isArray(data.actions)) throw new Error('Preparation details do not match the selected purpose and scenario. Refresh to try again.');
    if (data.selected_set && (data.selected_set.purpose !== purpose || data.selected_set.scenario !== selectedScenario)) throw new Error('The returned practice set does not match this scenario.');
    preparation = data;
    selectedSetId = data.selected_set?.id ?? '';
    bookletVersionId = String(data.materials?.selected_booklet_version_id ?? '');
    sessionId = data.selected_set?.sessions?.some((session) => String(session.id) === sessionId) ? sessionId : String(data.selected_set?.sessions?.[0]?.id ?? '');
    loadedContext = `${purpose}:${selectedScenario}:${selectedSetId}:${bookletVersionId}`;
  }
  async function loadPreparation() {
    const { epoch, signal } = begin('load');
    preview = null; confirmationOpen = false; error = ''; message = ''; result = null;
    try {
      const safeIndex = relativeUrl(preparation?.endpoints?.index ?? index_url);
      if (!safeIndex) throw new Error('The preparation index link is not on the current origin.');
      const url = new URL(safeIndex, window.location.origin);
      url.search = new URLSearchParams({ purpose, scenario: selectedScenario, ...(selectedSetId ? { set_id: selectedSetId } : {}), ...(bookletVersionId ? { booklet_version_id: bookletVersionId } : {}) }).toString();
      const data = await request(`${url.pathname}${url.search}`, signal);
      if (epoch === requestEpoch) acceptPreparation(data);
    } catch (failure) {
      if (epoch === requestEpoch && failure.name !== 'AbortError') error = failure instanceof TypeError ? 'Could not reach preparation. Previous details are retained; check your connection and refresh details.' : failure.message;
    } finally { if (epoch === requestEpoch) pending = null; }
  }
  function actionFor(key) { return preparation?.actions?.find((action) => action.key === key); }
  function permitted(key) { return current && !busy && actionFor(key)?.enabled === true; }
  async function review(key) {
    if (!permitted(key) || (['time', 'reminder'].includes(key) && !sessionId)) return;
    const { epoch, signal } = begin('preview');
    error = ''; message = ''; result = null; confirmationError = ''; retryConfirmation = false; preview = null;
    const body = { purpose, scenario: selectedScenario, action: key,
      ...(selectedSetId ? { set_id: selectedSetId } : {}),
      ...(['prepare', 'replace'].includes(key) && bookletVersionId ? { booklet_version_id: Number(bookletVersionId) } : {}),
      ...(key === 'time' ? { session_id: sessionId, preset } : {}),
      ...(key === 'reminder' ? { session_id: sessionId, window: windowKey } : {}),
    };
    try {
      const data = await request(preparation.endpoints.preview, signal, body);
      if (epoch !== requestEpoch) return;
      if (!data.operation?.id || data.operation.action !== key || !relativeUrl(data.operation.confirm_url)) throw new Error('The server did not return an exact confirmation operation. No change was confirmed.');
      preview = data; confirmationOpen = true;
    } catch (failure) { if (epoch === requestEpoch && failure.name !== 'AbortError') error = failure instanceof TypeError ? 'Could not load the impact review. No confirmation was sent; check your connection and try reviewing again.' : failure.message; }
    finally { if (epoch === requestEpoch) pending = null; }
  }
  function cancelReview() {
    if (pending === 'confirm') return;
    invalidate(); preview = null; confirmationOpen = false; confirmationError = ''; retryConfirmation = false;
  }
  async function confirmOperation() {
    if (!preview?.can_confirm || busy || (expired && !retryConfirmation) || !can_control || !active) return;
    const operation = preview.operation;
    const { epoch, signal } = begin('confirm');
    confirmationError = '';
    try {
      const data = await request(operation.confirm_url, signal, {});
      if (epoch !== requestEpoch) return;
      if (!data.preparation) throw new Error('The confirmation response has no resulting preparation details. Retry this same operation to recover its recorded outcome.');
      acceptPreparation(data.preparation);
      message = String(data.message ?? 'The operation returned its recorded outcome.');
      result = data.result; preview = null; confirmationOpen = false; retryConfirmation = false;
    } catch (failure) {
      if (epoch !== requestEpoch || failure.name === 'AbortError') return;
      confirmationError = failure instanceof TypeError ? 'The confirmation outcome is unknown. Check your connection and retry this same operation to recover its recorded outcome.' : failure.message;
      retryConfirmation = ![401, 403, 404, 409, 419, 422].includes(failure.status);
      if (!retryConfirmation) preview = { ...preview, can_confirm: false };
    } finally { if (epoch === requestEpoch) pending = null; }
  }
  function timestamp(value) {
    if (!value) return 'Not set';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
  }
  function displayValue(value) {
    if (value === null || value === undefined) return 'Not reported';
    return typeof value === 'object' ? String(value.label ?? value.name ?? value.title ?? JSON.stringify(value)) : String(value);
  }
</script>

{#if can_control && active}
  <section class="practice-panel min-w-0 text-slate-100" aria-label="Practice preparation" aria-busy={busy}>
    <p class="max-w-prose text-sm leading-6 text-slate-300">Prepare fictional cases for {purpose === 'research' ? 'researcher-led practice' : 'a panel rehearsal'}. Institutional settings and existing records stay protected. Physical outputs and study approval require their own evidence.</p>
    <div class="mt-5 grid gap-4 sm:grid-cols-2">
      <label class="min-w-0 text-sm font-semibold" for={`${uid}-scenario`}>Scenario
        <select id={`${uid}-scenario`} bind:value={selectedScenario} onchange={() => { selectedSetId = ''; bookletVersionId = ''; }} disabled={busy || !preparation} class="mt-2 w-full min-w-0">
          {#each preparation?.scenarios ?? [{ key: selectedScenario, label: selectedScenario }] as item (item.key)}<option value={item.key}>{item.label}</option>{/each}
        </select>
      </label>
      <label class="min-w-0 text-sm font-semibold" for={`${uid}-set`}>Practice set
        <select id={`${uid}-set`} bind:value={selectedSetId} disabled={busy || !preparation} class="mt-2 w-full min-w-0">
          <option value="">New set</option>
          {#each (preparation?.sets ?? []).filter((set) => set.purpose === purpose && set.scenario === selectedScenario) as set (set.id)}<option value={set.id}>{set.label} · {set.status}</option>{/each}
        </select>
      </label>
    </div>
    {#if preparation?.materials?.booklets?.length}
      <div class="mt-4 max-w-xl">
        <label for={`${uid}-booklet`} class="text-sm font-semibold">Exam material binding
          <select id={`${uid}-booklet`} bind:value={bookletVersionId} disabled={busy} class="mt-2 w-full min-w-0">
            <option value="">Choose an eligible sheet and booklet binding</option>
            {#each preparation.materials.booklets as item (item.id)}<option value={String(item.id)}>{item.label}</option>{/each}
          </select>
        </label>
        <p class="mt-2 text-sm leading-6 text-slate-400">Used only for a fresh set or replacement. Existing set material bindings and institutional assets stay unchanged.</p>
      </div>
    {/if}
    <div class="mt-3 flex flex-wrap items-center gap-3">
      <Button type="button" variant="outline" class="min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" disabled={busy} onclick={loadPreparation}>Refresh details</Button>
      {#if pending}<p role="status" class="text-sm text-slate-300">{pending === 'load' ? 'Loading preparation details…' : pending === 'preview' ? 'Reviewing exact impact…' : 'Confirming this operation…'}</p>{/if}
      {#if pending === 'preview'}<Button type="button" variant="outline" onclick={cancelReview}>Cancel review</Button>{/if}
    </div>
    {#if error}<p role="alert" class="mt-4 break-words rounded-lg border border-rose-400/30 bg-rose-400/5 p-3 text-sm leading-6 text-rose-200">{error}</p>{/if}
    {#if message}<div role="status" class="mt-4 rounded-lg border border-blue-400/30 bg-blue-400/5 p-3 text-sm leading-6 text-blue-100"><p>{message}</p><p class="mt-1">{selectedSet ? `${selectedSet.label} · ${selectedSet.status} · revision ${selectedSet.revision}` : 'No practice set selected.'}</p></div>{/if}
    {#if result}<details class="mt-3"><summary>Recorded operation result</summary><dl class="mt-3 space-y-2 text-sm">{#each Object.entries(result) as [key, value] (key)}<div class="min-w-0"><dt class="font-semibold">{key.replaceAll('_', ' ')}</dt><dd class="break-words text-slate-300">{displayValue(value)}</dd></div>{/each}</dl></details>{/if}
    {#if preparation}
      {#if !current}<p role="status" class="mt-4 text-sm text-amber-200">Previous details are retained below. They do not match the current selection; actions are disabled until details refresh.</p>{/if}
      <p class="mt-5 text-sm text-slate-400">{displayValue(preparation.context?.institution)} · {displayValue(preparation.context?.cycle)}</p>
      <h3 class="mt-3 break-words text-lg font-semibold text-white">{selectedSet?.label ?? 'No practice set selected'}</h3>
      {#if selectedSet}<p class="mt-1 text-sm text-slate-300">{selectedSet.purpose} · {selectedSet.status} · revision {selectedSet.revision}</p>{:else}<p class="mt-1 text-sm text-slate-300">Review preparation to create a fresh set. Preview alone changes no cases or sessions.</p>{/if}
      <section class="mt-5 border-t border-slate-800 pt-4" aria-label="Prerequisites">
        <h3 class="text-sm font-semibold">Prerequisites</h3>
        <ul class="mt-2 divide-y divide-slate-800">{#each preparation.prerequisites ?? [] as item (item.key)}<li class="py-3"><p class="text-sm font-medium">{item.label} <span class={item.status === 'available' ? 'text-blue-200' : 'text-amber-200'}>· {item.status}</span></p><p class="mt-1 break-words text-sm leading-6 text-slate-400">{item.reason}</p></li>{:else}<li class="py-3 text-sm text-amber-200">No prerequisite evidence was returned.</li>{/each}</ul>
      </section>
      <details class="mt-3"><summary>Preserved configuration and records</summary><ul class="mt-3 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-300">{#each preparation.preserved ?? [] as item}<li>{item}</li>{/each}</ul></details>
      <section class="mt-5 border-y border-slate-800 py-4" aria-label="Preparation actions">
        <div class="flex flex-wrap gap-2">{#each (preparation.actions ?? []).filter((action) => ['prepare', 'replace', 'retire'].includes(action.key)) as action (action.key)}<Button type="button" variant="outline" class="min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" disabled={!permitted(action.key)} onclick={() => review(action.key)}>{action.label}</Button>{/each}</div>
        {#each (preparation.actions ?? []).filter((action) => ['prepare', 'replace', 'retire'].includes(action.key) && action.reason) as action (action.key)}<p class="mt-2 text-sm leading-6 text-slate-400">{action.label}: {action.reason}</p>{/each}
      </section>
      {#if selectedSet}
        <details class="mt-4"><summary>Assigned accounts and access checks</summary>
          <p class="mt-3 text-sm leading-6 text-slate-400">Sign in normally using separate browser profiles. These links do not switch identity or grant authority. No staff password is inferred.</p>
          <ul class="mt-2 divide-y divide-slate-800">{#each selectedSet.actors ?? [] as actor, index (`${actor.role}-${index}`)}<li class="py-4 text-sm"><h4 class="font-semibold">{actor.name} · {actor.role}</h4><p class="mt-1 break-all text-slate-300">{actor.email}</p>{#if actor.access_key}<p class="mt-2 break-all text-slate-300">Applicant access key: <code>{actor.access_key}</code></p>{/if}{#if actor.birthdate}<p class="mt-1 text-slate-300">Birthdate: {actor.birthdate}</p>{/if}{#if relativeUrl(actor.login_url)}<a class="mt-2 inline-flex min-h-11 items-center" href={relativeUrl(actor.login_url)} target="_blank" rel="noopener noreferrer">Open sign in (new tab)</a>{/if}<ul class="mt-2 space-y-2">{#each actor.checks ?? [] as check, index (`${check.label}-${index}`)}<li class="leading-6"><span class={check.allowed ? 'text-blue-200' : 'text-amber-200'}>{check.label}: {check.allowed ? 'Allowed' : 'Not allowed'}</span>{#if check.reason}<p class="text-slate-400">{check.reason}</p>{/if}</li>{/each}</ul></li>{:else}<li class="py-3 text-sm text-slate-400">No assigned account returned.</li>{/each}</ul>
        </details>
        {#if selectedSet.assignments?.length}
          <details class="mt-3">
            <summary>Assigned task cards and accounts</summary>
            <ul class="mt-2 divide-y divide-slate-800">
              {#each selectedSet.assignments as assignment, index (`${assignment.case_id}-${index}`)}
                <li class="py-3 text-sm">
                  <p class="font-semibold">{assignment.task_ids?.join(', ')} · {assignment.account_name}</p>
                  <p class="mt-1 text-slate-300">{assignment.actor} · {assignment.available ? 'Assigned' : 'Unavailable or not permitted'}</p>
                  {#if assignment.available && relativeUrl(assignment.url)}
                    <a class="inline-flex min-h-11 items-center" href={relativeUrl(assignment.url)} target="_blank" rel="noopener noreferrer">Open assigned task workspace (new tab)</a>
                  {/if}
                  {#if assignment.reason}<p class="mt-2 leading-6 text-slate-400">{assignment.reason}</p>{/if}
                </li>
              {/each}
            </ul>
          </details>
        {/if}
        <details class="mt-3">
          <summary>Cases and actor handoffs</summary>
          <ul class="mt-2 divide-y divide-slate-800">
            {#each selectedSet.cases ?? [] as item (item.id)}
              <li class="py-3 text-sm">
                <p class="font-semibold">{item.name}</p>
                <p class="mt-1 text-slate-300">{item.state} · {item.actor}</p>
                {#if item.task_ids?.length}<p class="mt-2 text-slate-300">Assigned tasks: {item.task_ids.join(', ')}</p>{/if}
                {#if item.reason}<p class="mt-2 leading-6 text-amber-200">{item.reason}</p>{/if}
                {#if relativeUrl(item.url)}
                  <a class="inline-flex min-h-11 items-center" href={relativeUrl(item.url)} target="_blank" rel="noopener noreferrer">Open {item.actor} workspace (new tab)</a>
                {/if}
                {#each item.handoffs ?? [] as handoff, index (`${handoff.actor}-${index}`)}
                  {#if handoff.available && relativeUrl(handoff.url) && handoff.url !== item.url}
                    <a class="mr-4 inline-flex min-h-11 items-center" href={relativeUrl(handoff.url)} target="_blank" rel="noopener noreferrer">{handoff.label} (new tab)</a>
                  {:else if !handoff.available && handoff.reason}
                    <p class="mt-2 leading-6 text-amber-200">{handoff.label}: {handoff.reason}</p>
                  {/if}
                {/each}
              </li>
            {:else}
              <li class="py-3 text-sm text-slate-400">No assigned case returned.</li>
            {/each}
          </ul>
        </details>
        <details class="mt-3"><summary>Documents and physical outputs</summary><ul class="mt-2 divide-y divide-slate-800">{#each selectedSet.documents ?? [] as item, index (`${item.label}-${index}`)}<li class="py-3 text-sm"><p class="font-semibold">{item.label}</p><p class="mt-1 text-amber-200">Physical status: {item.physical_status}</p><div class="flex flex-wrap gap-x-4">{#if relativeUrl(item.url)}<a class="inline-flex min-h-11 items-center" href={relativeUrl(item.url)} target="_blank" rel="noopener noreferrer">Open document (new tab)</a>{/if}{#if relativeUrl(item.print_url)}<a class="inline-flex min-h-11 items-center" href={relativeUrl(item.print_url)} target="_blank" rel="noopener noreferrer">Open normal print workflow (new tab)</a>{/if}</div></li>{:else}<li class="py-3 text-sm text-slate-400">No current document returned. Preparation does not prove printing or handover.</li>{/each}</ul></details>
        <details class="mt-3"><summary>Paired case exchange</summary><p class="mt-3 text-sm">{selectedSet.paired_exchange?.status ?? 'unverified'}</p><p class="mt-1 text-sm leading-6 text-slate-400">{selectedSet.paired_exchange?.reason}</p>{#if relativeUrl(selectedSet.paired_exchange?.url)}<a class="inline-flex min-h-11 items-center text-sm" href={relativeUrl(selectedSet.paired_exchange.url)} target="_blank" rel="noopener noreferrer">Open assigned exchange (new tab)</a>{/if}{#if actionFor('pair')}<Button type="button" variant="outline" class="mt-3 min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" disabled={!permitted('pair')} onclick={() => review('pair')}>{actionFor('pair').label}</Button><p class="mt-2 text-sm leading-6 text-slate-400">{actionFor('pair').reason}</p>{/if}</details>
        <details class="mt-3"><summary>Session timing and reminders</summary>
          <ul class="mt-3 space-y-3 text-sm">{#each selectedSet.sessions ?? [] as session (session.id)}<li><p class="font-semibold">{session.label} · {session.status}</p><p class="mt-1 leading-6 text-slate-300">Starts {timestamp(session.starts_at)}<br />Ends {timestamp(session.ends_at)}</p></li>{:else}<li class="text-slate-400">No session in this practice set.</li>{/each}</ul>
          <label class="mt-4 block text-sm font-semibold" for={`${uid}-session`}>Selected session<select id={`${uid}-session`} class="mt-2 w-full min-w-0" bind:value={sessionId} disabled={busy || !current || !selectedSet.sessions?.length}>{#each selectedSet.sessions ?? [] as session (session.id)}<option value={String(session.id)}>{session.label}</option>{/each}</select></label>
          <div class="mt-4 grid gap-5 sm:grid-cols-2">
            <div class="min-w-0"><label class="text-sm font-semibold" for={`${uid}-preset`}>Timing preset<select id={`${uid}-preset`} class="mt-2 w-full min-w-0" bind:value={preset} disabled={busy}>{#each presets as [key, label] (key)}<option value={key}>{label}</option>{/each}</select></label><p class="mt-2 text-sm leading-6 text-slate-400">Changes this session only. No system-clock change or automatic reminder.</p><Button type="button" variant="outline" class="mt-3 min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" disabled={!permitted('time') || !sessionId} onclick={() => review('time')}>{actionFor('time')?.label ?? 'Review timing change'}</Button><p class="mt-2 text-sm leading-6 text-slate-400">{actionFor('time')?.reason}</p></div>
            <div class="min-w-0"><label class="text-sm font-semibold" for={`${uid}-window`}>Reminder window<select id={`${uid}-window`} class="mt-2 w-full min-w-0" bind:value={windowKey} disabled={busy}>{#each windows as key (key)}<option value={key}>{key}</option>{/each}</select></label><p class="mt-2 text-sm leading-6 text-slate-400">Separate confirmation. Current notification policy and safe practice recipients still apply.</p><Button type="button" variant="outline" class="mt-3 min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" disabled={!permitted('reminder') || !sessionId} onclick={() => review('reminder')}>{actionFor('reminder')?.label ?? 'Review reminder dispatch'}</Button><p class="mt-2 text-sm leading-6 text-slate-400">{actionFor('reminder')?.reason}</p></div>
          </div>
        </details>
        <details class="mt-3"><summary>Actual notification delivery status</summary><ul class="mt-3 divide-y divide-slate-800">{#each selectedSet.deliveries ?? [] as delivery (delivery.id)}<li class="py-3 text-sm"><p>{delivery.window} · {delivery.channel} · {delivery.status}</p><p class="mt-1 leading-6 text-slate-400">{delivery.reason}</p></li>{:else}<li class="py-3 text-sm text-slate-400">No delivery records returned. Queuing is not evidence of delivery.</li>{/each}</ul></details>
      {/if}
    {:else if !busy && !error}<p role="status" class="mt-5 text-sm text-slate-400">Preparation details are not loaded. Refresh to check the current prerequisites.</p>{/if}
  </section>
{/if}

<Dialog.Root bind:open={confirmationOpen}>
  <Dialog.Content bind:ref={reviewDialog} showCloseButton={pending !== 'confirm'} class="max-h-[90dvh] overflow-y-auto border-slate-800 bg-slate-950 text-slate-100 print:hidden sm:max-w-xl" onOpenAutoFocus={(event) => { event.preventDefault(); reviewDialog?.focus({ preventScroll: true }); if (reviewDialog) reviewDialog.scrollTop = 0; }} onEscapeKeydown={(event) => { if (pending === 'confirm') event.preventDefault(); }} onInteractOutside={(event) => { if (pending === 'confirm') event.preventDefault(); }}>
    <Dialog.Header><Dialog.Title>Review {preview?.operation?.action ?? 'operation'} impact</Dialog.Title><Dialog.Description class="text-slate-300">Confirm only this reviewed operation. Cancelling changes no cases or sessions.</Dialog.Description></Dialog.Header>
    {#if preview}
      {#each [['creates', 'Creates'], ['replaces', 'Replaces or retires'], ['preserves', 'Preserves']] as [key, label] (key)}<section class="mt-2"><h3 class="text-sm font-semibold">{label}</h3><ul class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-slate-300">{#each preview.impact?.[key] ?? [] as item}<li>{item}</li>{:else}<li>None reported</li>{/each}</ul></section>{/each}
      <ul class="mt-3 space-y-2 text-sm">{#each preview.prerequisites ?? [] as item (item.key)}<li><span class={item.status === 'available' ? 'text-blue-200' : 'text-amber-200'}>{item.label} · {item.status}</span><p class="mt-1 leading-6 text-slate-400">{item.reason}</p></li>{/each}</ul>
      <p class="mt-3 text-xs leading-5 text-slate-400">Operation {preview.operation.id} · expires {timestamp(preview.operation.expires_at)}</p>
      {#if !preview.can_confirm && preview.reason}<p role="status" class="text-sm leading-6 text-amber-200">{preview.reason}</p>{/if}
      {#if expired}<p role="alert" class="text-sm leading-6 text-amber-200">This review expired. Cancel, refresh details and explicitly review a new operation.</p>{/if}
      {#if confirmationError}<p role="alert" class="break-words text-sm leading-6 text-rose-200">{confirmationError}</p>{/if}
      {#if retryConfirmation}<p class="text-sm leading-6 text-amber-200">The outcome is unknown. Retry sends the same operation ID, not a new preparation.</p>{/if}
      <Dialog.Footer><Button type="button" variant="outline" class="min-h-11 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" disabled={pending === 'confirm'} onclick={cancelReview}>Cancel</Button><Button type="button" class="min-h-11 bg-blue-600 text-white hover:bg-blue-500" disabled={busy || !preview.can_confirm || (expired && !retryConfirmation)} onclick={confirmOperation}>{pending === 'confirm' ? 'Confirming…' : retryConfirmation ? 'Retry same operation' : `Confirm ${preview.operation.action}`}</Button></Dialog.Footer>
    {/if}
  </Dialog.Content>
</Dialog.Root>

<style>
  .practice-panel :global(select) { min-height: 2.75rem; border: 1px solid #334155; border-radius: 0.5rem; background: #0f172a; padding: 0.5rem 0.75rem; color: #f1f5f9; font-size: 0.875rem; font-weight: 400; color-scheme: dark; }
  .practice-panel :global(select:disabled) { opacity: 0.6; }
  .practice-panel :global(summary) { min-height: 2.75rem; cursor: pointer; padding: 0.625rem 0; color: #e2e8f0; font-size: 0.875rem; font-weight: 600; }
  .practice-panel :global(a) { color: #93c5fd; text-decoration: underline; text-underline-offset: 0.25rem; }
  .practice-panel :global(a:hover) { color: #bfdbfe; }
  .practice-panel :global(select:focus-visible), .practice-panel :global(summary:focus-visible), .practice-panel :global(a:focus-visible) { outline: 2px solid #60a5fa; outline-offset: 3px; }
  .practice-panel :global(::selection) { background: #1e40af; color: #fff; }
</style>
