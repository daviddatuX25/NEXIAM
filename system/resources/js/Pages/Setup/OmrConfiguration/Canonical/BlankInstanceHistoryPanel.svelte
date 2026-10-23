<script>
    import { onMount } from 'svelte';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils';

    let { templateVersionId = null } = $props();
    let instances = $state([]);
    let qrDisabledIssuances = $state([]);
    let qrMode = $state('qr_signed');
    let loading = $state(false);
    let error = $state('');
    let displayCode = $state('');
    let recoveryState = $state('undecodable');
    let recovery = $state(null);

    async function load() {
        if (!templateVersionId) return;
        loading = true;
        error = '';
        try {
            const response = await fetch(
                `/setup/omr/templates/${templateVersionId}/blank/instances`,
                {
                    headers: { Accept: 'application/json' },
                },
            );
            const body = await response.json();
            if (!response.ok)
                throw new Error(
                    body?.message ?? 'Instance history unavailable.',
                );
            instances = body.instances ?? [];
            qrDisabledIssuances = body.qr_disabled_issuances ?? [];
            qrMode = body.qr_mode ?? 'qr_signed';
        } catch (exception) {
            error = exception.message;
        } finally {
            loading = false;
        }
    }

    async function voidInstance(instance) {
        const response = await fetch(
            `/setup/omr/templates/${templateVersionId}/blank/instances/${instance.id}/void`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({ idempotency_key: newIdempotencyKey() }),
            },
        );
        const body = await response.json();
        if (!response.ok) error = body?.message ?? 'Void failed.';
        await load();
    }

    async function resolveManual() {
        error = '';
        recovery = null;
        const response = await fetch(
            '/grading/omr/blank-instance/manual-resolve',
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...csrfHeaders(),
                },
                body: JSON.stringify({
                    qr_state: recoveryState,
                    display_code: displayCode,
                    confirmed: true,
                    idempotency_key: newIdempotencyKey(),
                }),
            },
        );
        const body = await response.json();
        if (!response.ok) error = body?.message ?? 'Manual recovery failed.';
        else recovery = body;
    }

    onMount(load);
</script>

<section
    class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
    aria-labelledby="blank-history-title"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 id="blank-history-title" class="font-semibold text-slate-950">
                Answer Sheet print history
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Copy policy and page claims for signed sheets, plus explicit
                QR-disabled issuance history.
            </p>
        </div>
        <button
            type="button"
            class="rounded border border-slate-300 px-3 py-1.5 text-xs font-medium"
            onclick={load}
            disabled={loading}
        >
            {loading ? 'Loading…' : 'Refresh'}
        </button>
    </div>

    {#if error}<div
            class="mt-3 rounded bg-red-50 p-3 text-xs text-red-800"
            role="alert"
        >
            {error}
        </div>{/if}

    <div class="mt-4 space-y-2">
        {#if instances.length === 0 && qrDisabledIssuances.length === 0}
            <p class="rounded border border-dashed border-slate-300 p-4 text-sm text-slate-600">
                No Answer Sheets printed from this version.
            </p>
        {/if}
        {#each instances as instance (instance.id)}
            <div class="rounded-lg border border-slate-200 p-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-mono text-xs font-semibold">
                            {instance.display_code}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            {instance.copy_policy === 'reusable_master'
                                ? 'Reusable master'
                                : 'Unique sheet'} · {instance.page_count} page(s)
                            {#if instance.copy_policy === 'reusable_master'}
                                · {instance.intended_copy_count} intended copies{/if}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-full bg-slate-100 px-2 py-1 text-[11px]"
                            >{instance.status}</span
                        >
                        {#if instance.status === 'printed'}
                            <button
                                type="button"
                                class="text-xs font-medium text-red-700"
                                onclick={() => voidInstance(instance)}
                                >Void</button
                            >
                        {/if}
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-1">
                    {#each instance.pages ?? [] as page (page.id)}
                        <span class="rounded bg-slate-50 px-2 py-1 text-[11px]">
                            Page {page.page_no}: {page.claim_count}
                            {page.claim_count === 1 ? 'claim' : 'claims'}
                            {#if page.active_authorization_count > 0}
                                · {page.active_authorization_count} authorized{/if}
                        </span>
                    {/each}
                </div>
            </div>
        {/each}
        {#each qrDisabledIssuances as issuance (issuance.id)}
            <div class="rounded-lg border border-slate-200 p-3">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-mono text-xs font-semibold">
                            Issuance {issuance.issuance_code}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            QR-disabled print run · {issuance.page_count} page(s) ·
                            {issuance.copy_policy === 'reusable_master'
                                ? 'Reusable master'
                                : 'Unique-instance policy'} ·
                            {issuance.intended_copy_count} intended copies
                        </p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px]">
                        {issuance.status}
                    </span>
                </div>
                {#if issuance.artifact_manifest_hash}
                    <p class="mt-2 break-all text-[11px] text-slate-500">
                        Artifact manifest SHA-256: {issuance.artifact_manifest_hash}
                    </p>
                {/if}
                {#if issuance.download_sha256}
                    <p class="mt-1 break-all text-[11px] text-slate-500">
                        Print bundle SHA-256: {issuance.download_sha256}
                    </p>
                {/if}
                {#if issuance.failure_code}
                    <p class="mt-1 text-xs text-red-700">
                        The print run failed. Queue a new print request to retry; this record is retained.
                    </p>
                {/if}
            </div>
        {/each}
    </div>

    {#if qrMode === 'qr_disabled'}
        <div class="mt-5 border-t border-slate-200 pt-4">
            <h3 class="text-sm font-semibold text-slate-950">
                No-QR capture
            </h3>
            <p class="mt-1 text-xs text-slate-600">
                QR-disabled print runs have no signed sheet identity and cannot
                be claimed through signed-QR recovery. A route-bound pending
                capture set is required before these sheets can be claimed.
            </p>
        </div>
    {:else}
    <div class="mt-5 border-t border-slate-200 pt-4">
        <h3 class="text-sm font-semibold text-slate-950">
            Unreadable or absent QR
        </h3>
        <p class="mt-1 text-xs text-slate-600">
            Present-but-invalid signatures cannot be overridden. This fallback
            stays blocked until explicit confirmation.
        </p>
        <div class="mt-3 grid gap-2 sm:grid-cols-[150px_1fr_auto]">
            <select
                class="rounded border-slate-300 text-sm"
                bind:value={recoveryState}
            >
                <option value="undecodable">Undecodable</option>
                <option value="absent">Absent</option>
                <option value="present_invalid">Present but invalid</option>
            </select>
            <input
                class="rounded border-slate-300 text-sm"
                bind:value={displayCode}
                placeholder="NI-… display code"
            />
            <button
                class="rounded bg-slate-950 px-3 py-2 text-sm font-medium text-white"
                type="button"
                onclick={resolveManual}>Resolve and confirm</button
            >
        </div>
        {#if recovery}
            <p class="mt-2 rounded bg-amber-50 p-2 text-xs text-amber-900">
                Resolved instance {recovery.instance_id}; recognition blocked: {recovery.recognition_blocked
                    ? 'yes'
                    : 'no'}.
            </p>
        {/if}
    </div>
    {/if}
</section>
