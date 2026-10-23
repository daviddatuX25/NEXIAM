<script>
    import { untrack } from 'svelte';
    import AnswerSheetKeyPreview from '@/Components/Setup/AnswerSheetKeyPreview.svelte';
    let { previewUrl = null, contextRevision = null, contextSnapshot = null } = $props();
    let payload = $state(null);
    let status = $state('loading');
    let errorCode = $state(null);
    let activePageNo = $state(1);
    let generation = 0;
    let controller = null;
    let currentRequestKey = null;

    function canonicalSnapshotKey(snapshot) {
        if (!snapshot || typeof snapshot !== 'object') return '';
        return JSON.stringify(
            Object.keys(snapshot)
                .sort()
                .reduce((acc, key) => {
                    acc[key] = snapshot[key] ?? null;
                    return acc;
                }, {}),
        );
    }

    const pages = $derived(payload?.pages ?? []);
    const identity = $derived(`${previewUrl ?? ''}:${contextRevision ?? ''}:${canonicalSnapshotKey(contextSnapshot)}`);
    const payloadMatchesContext = $derived.by(() => {
        if (!payload) return false;
        const responseContext = payload.context ?? {};
        const expectedEntries = Object.entries(contextSnapshot ?? {});
        if (expectedEntries.length > 0) {
            return expectedEntries.every(([key, value]) => String(responseContext[key] ?? '') === String(value ?? ''));
        }
        const responseRevision = responseContext.preparation_revision ?? responseContext.composition_revision;
        return contextRevision === null || responseRevision === null || responseRevision === undefined ||
            Number(responseRevision) === Number(contextRevision);
    });
    const visiblePages = $derived(payloadMatchesContext ? pages : []);
    const visiblePage = $derived(visiblePages.find((item) => Number(item.page_no) === activePageNo) ?? null);
    const viewerStatus = $derived(
        status === 'incomplete'
            ? (payloadMatchesContext ? 'ready' : 'loading')
            : (status === 'ready' && !payloadMatchesContext ? 'loading' : status),
    );

    $effect(() => {
        const requestIdentity = identity;
        if (requestIdentity === currentRequestKey) {
            return;
        }
        const requestUrl = untrack(() => previewUrl);
        const requestedContextRevision = untrack(() => contextRevision);
        const requestedContextSnapshot = untrack(() => contextSnapshot);
        const requestGeneration = ++generation;
        currentRequestKey = requestIdentity;
        controller?.abort();
        controller = null;
        payload = null;
        activePageNo = 1;
        errorCode = null;
        if (!requestUrl) {
            status = 'unavailable';
            errorCode = null;
            return;
        }
        const requestController = new AbortController();
        controller = requestController;
        status = 'loading';
        fetch(requestUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: requestController.signal })
            .then(async (response) => {
                const data = await response.json();
                if (!response.ok) {
                    const error = new Error('preview_failed');
                    error.data = data;
                    throw error;
                }
                return data;
            })
            .then((data) => {
                if (requestController.signal.aborted || requestGeneration !== generation || currentRequestKey !== requestIdentity) return;
                const responseContext = data?.context ?? {};
                const snapshotEntries = Object.entries(requestedContextSnapshot ?? {});
                const snapshotIsStale = snapshotEntries.some(([key, value]) => String(responseContext[key] ?? '') !== String(value ?? ''));
                const currentRevision = responseContext.preparation_revision ?? responseContext.composition_revision;
                const revisionIsStale = snapshotEntries.length === 0 &&
                    currentRevision !== undefined &&
                    currentRevision !== null &&
                    Number(currentRevision) !== Number(requestedContextRevision);
                if (snapshotIsStale || revisionIsStale) {
                    status = 'unavailable';
                    errorCode = data?.unavailable_code ?? null;
                    return;
                }
                payload = data;
                status = ['ready', 'incomplete'].includes(data?.state) ? data.state : 'unavailable';
                errorCode = data?.unavailable_code ?? null;
            })
            .catch((error) => {
                if (requestController.signal.aborted || requestGeneration !== generation || currentRequestKey !== requestIdentity) return;
                status = 'error';
                errorCode = error?.data?.unavailable_code ?? null;
            });

        return () => {
            requestController.abort();
            if (controller === requestController) controller = null;
        };
    });

    function changePage(pageNo) {
        activePageNo = pageNo;
    }
</script>

{#if previewUrl}
    {#if payloadMatchesContext && payload?.issues?.length > 0}
        <section class="mb-3 rounded-lg border border-amber-500/30 bg-amber-500/5 p-3" aria-label="Kit composition preview issues">
            <h2 class="text-sm font-semibold">Preview needs review</h2>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-muted-foreground">
                {#each payload.issues as issue (`${issue.code}:${issue.message}`)}<li>{issue.message}</li>{/each}
            </ul>
        </section>
    {/if}
    <AnswerSheetKeyPreview
        page={visiblePage}
        bubbles={visiblePage?.bubbles ?? []}
        answers={payloadMatchesContext ? (payload?.answers ?? []) : []}
        status={viewerStatus}
        {errorCode}
        pages={visiblePages}
        pageCount={payloadMatchesContext ? (payload?.page_count ?? visiblePages.length) : 0}
        {activePageNo}
        scopeLabel={payloadMatchesContext ? 'Saved Kit composition' : null}
        readOnly={true}
        structuralOnly={status === 'incomplete'}
        onPageChange={changePage}
    />
{/if}
