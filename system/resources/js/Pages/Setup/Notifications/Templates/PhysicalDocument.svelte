<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { onDestroy, onMount, tick } from 'svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import ResultSheetStudio from '@/Components/ResultSheet/ResultSheetStudio.svelte';
    import PlaceholderPicker from '@/Components/ResultSheet/PlaceholderPicker.svelte';
    import { csrfToken } from '@/lib/utils';
    import { success } from '@/lib/toast';
    import { createNoticeDocumentStudioAdapter, type DocumentState, type DocumentWorkspaceProps, type NoticeDocumentStudioAdapter, type DocumentSnapshot, type DocumentPreview } from '@/lib/noticeDocumentStudio';
    import { createNoticeStudioHistoryGuard, type NoticeHistoryGuard } from '@/lib/noticeStudioHistoryGuard';
    import * as Dialog from '@/Components/ui/dialog';
    let props = $props<DocumentWorkspaceProps>();
    let adapter = $state<NoticeDocumentStudioAdapter | null>(null);
    let snapshot = $state<DocumentSnapshot | null>(null);
    let state = $state<DocumentState | null>(null);
    let sourceChosen = $state(false);
    let mobileTab = $state<'write' | 'preview'>('write');
    let busy = $state(false);
    let message = $state('');
    let errorMessage = $state('');
    let statusNode: HTMLElement;
    let pickerMode = $state<'copy' | 'insert'>('copy');
    let pickerOpen = $state(false);
    let placeholderTargetId = $state<string | null>(null);
    let pendingFile: File | null = null;
    let replaceWordConfirm = $state(false);
    let uploadInput: HTMLInputElement;
    let imageInput: HTMLInputElement;
    let pdfUrl = $state('');
    let checkingPdf = $state(false);
    let documentImages = $state<{ asset_id: string; src: string; label: string }[]>([]);
    let pendingUpload: { kind: 'word' | 'image'; file: File; targetId: string | null } | null = null;
    let unsubscribe: (() => void) | undefined;
    let pendingNavigation: (() => void) | null = null;
    let activeDialog = $state<'leave' | 'replace-source' | 'discard' | 'reload' | null>(null);
    let pendingCandidate = $state<{ url: string; version: number } | null>(null);
    let leaving = false;
    const hasDraft = $derived(Boolean(snapshot?.draft));
    let historyGuard: NoticeHistoryGuard | null = null;
    const preview = $derived(snapshot?.preview ?? null);
    const previewCurrent = $derived(Boolean(preview && snapshot?.render_sha256 && preview.render_sha256 === snapshot.render_sha256));
    const draftStatus = $derived(snapshot?.status === 'conflict' ? 'Stale' : snapshot?.status === 'unknown' ? 'Unknown' : snapshot && ['dirty', 'saving', 'error'].includes(snapshot.status) ? 'Unsaved changes' : 'Saved');
    const builderDocument = $derived(state?.source_kind === 'builder' ? state.builder_json : null);
    const sourceName = $derived(state?.source_kind === 'docx' ? 'Word document' : 'Visual designer');
    const builderComponents = $derived(Array.isArray(builderDocument?.components) ? builderDocument.components as Record<string, unknown>[] : []);
    function imageAsset(component: Record<string, unknown>) {
        const imageProps = (component.props ?? {}) as Record<string, unknown>;
        const assetId = typeof imageProps.asset_id === 'string' ? imageProps.asset_id : '';
        return (props.workspace.verified_images ?? []).find((asset) => asset.id === assetId) ?? null;
    }
    const containsUnverifiedImage = $derived(builderComponents.some((component) => {
        const imageProps = component.props;
        const assetId = imageProps && typeof imageProps === 'object' && 'asset_id' in imageProps ? imageProps.asset_id : null;
        return component.type === 'image' && (!assetId || (!imageAsset(component) && !documentImages.some((asset) => asset.asset_id === assetId)));
    }));
    const headerCatalog = $derived((props.builderCatalog.header ?? {}) as Record<string, unknown>);
    const fieldCatalog = $derived((props.builderCatalog.field ?? {}) as Record<string, unknown>);
    const safeBuilderCatalog = $derived({
        ...props.builderCatalog,
        header: { ...headerCatalog, default_props: { ...(headerCatalog.default_props as Record<string, unknown> ?? {}), title: '{{institution_name}}', subtitle: props.family.name } },
        field: { ...fieldCatalog, default_props: { ...(fieldCatalog.default_props as Record<string, unknown> ?? {}), label: props.family.domain.toLowerCase() === 'results' ? 'Applicant number' : 'Application number', binding: props.family.domain.toLowerCase() === 'results' ? 'applicant_reference' : 'application_no' } },
        image: props.builderCatalog.image ?? { label: 'Image', description: 'Place an uploaded, verified family image', category: 'Media', default_w: 60, default_h: 40 },
    });
    const deskUrl = $derived(`/setup/notifications/${String(props.family.domain).toLowerCase()}`);
    const breadcrumbs = $derived([
        { label: 'Setup', href: '/setup' },
        { label: 'Email & Notifications', href: '/setup/notifications' },
        { label: `${props.family.domain.charAt(0).toUpperCase()}${props.family.domain.slice(1).toLowerCase()} Notices`, href: deskUrl },
        { label: props.family.name, href: props.return_url },
        { label: 'PDF document' },
    ]);

    function clone<T>(value: T): T { return JSON.parse(JSON.stringify(value)) as T; }
    function imagesIn(): { asset_id: string; src: string; label: string }[] {
        return (props.workspace.verified_images ?? []).map((asset) => ({ asset_id: asset.id, src: asset.url, label: asset.filename }));
    }
    function resolveBuilderImages(value: DocumentState): DocumentState {
        if (!value.builder_json || !Array.isArray(value.builder_json.components)) return value;
        return { ...value, builder_json: { ...value.builder_json, components: (value.builder_json.components as Record<string, unknown>[]).map((component) => {
            if (component.type !== 'image') return component;
            const imageProps = (component.props ?? {}) as Record<string, unknown>;
            const verified = (props.workspace.verified_images ?? []).find((asset) => asset.id === imageProps.asset_id);
            return verified ? { ...component, props: { ...imageProps, src: verified.url } } : component;
        }) } };
    }
    function startAdapter() {
        if (adapter) return;
        adapter = createNoticeDocumentStudioAdapter(props, { csrf: csrfToken });
        snapshot = adapter.snapshot();
        state = resolveBuilderImages(clone(snapshot.state));
        documentImages = imagesIn();
        sourceChosen = Boolean(snapshot.draft?.state?.source_kind);
        unsubscribe = adapter.subscribe((next) => {
            snapshot = next;
            if (next.error) errorMessage = next.error;
        });
    }
    startAdapter();
    function setPdf(value: DocumentPreview) {
        pdfUrl = `data:application/pdf;base64,${value.pdf.pdf_base64}`;
    }
    function enterSource(source: 'docx' | 'builder') {
        if (!adapter || !state) return;
        const defaults = clone(props.builderDefaults ?? {});
        const next: DocumentState = source === 'builder'
            ? { source_kind: 'builder', document_asset_id: null, builder_json: Object.keys(defaults).length ? defaults : null, paper_size: String((defaults.page as Record<string, unknown> | undefined)?.paper_size ?? 'a4'), orientation: String((defaults.page as Record<string, unknown> | undefined)?.orientation ?? 'portrait') }
            : { source_kind: 'docx', document_asset_id: null, builder_json: null, paper_size: state.paper_size || 'a4', orientation: state.orientation || 'portrait' };
        state = next; sourceChosen = true; adapter.edit(next);
        if (source === 'docx') uploadInput?.focus();
    }
    async function replaceSource(source: 'docx' | 'builder') {
        if (!adapter || busy) return;
        busy = true; errorMessage = ''; message = 'Saving the current document before replacing its source…';
        try {
            await adapter.save(state?.source_kind === 'docx' ? 1 : 2);
            if (adapter.snapshot().status !== 'saved') throw new Error('The current source is not confirmed saved. It remains unchanged.');
            enterSource(source);
            activeDialog = null;
            message = 'Choose the new document source. The saved source remains in history.';
        } catch (error) { errorMessage = error instanceof Error ? error.message : 'Could not save the current source. Your work is retained.'; }
        finally { busy = false; }
    }
    function updateBuilder(next: unknown) {
        if (!adapter || busy || !sourceChosen) return;
        state = { ...state, builder_json: clone(next) as Record<string, unknown> };
        adapter.edit((current) => ({ ...current, builder_json: clone(next) as Record<string, unknown> }));
    }
    function openBuilderPlaceholder(selection: { targetId?: string }) {
        placeholderTargetId = selection.targetId ?? null;
        pickerMode = 'insert';
        pickerOpen = true;
    }
    function insertBuilderPlaceholder(selection: { key: string }) {
        if (!state?.builder_json || !placeholderTargetId) return;
        const components = Array.isArray(state.builder_json.components) ? state.builder_json.components as Record<string, unknown>[] : [];
        const next = { ...state.builder_json, components: components.map((component) => component.id === placeholderTargetId ? { ...component, props: { ...(component.props as Record<string, unknown> ?? {}), binding: selection.key } } : component) };
        updateBuilder(next);
        pickerOpen = false;
        placeholderTargetId = null;
    }
    function updateSetting(key: 'paper_size' | 'orientation', value: string) {
        if (!adapter || !state) return;
        const changed = { ...state, [key]: value };
        if (changed.builder_json) {
            const page = (changed.builder_json.page ?? {}) as Record<string, unknown>;
            changed.builder_json = { ...changed.builder_json, page: { ...page, [key]: value } };
        }
        state = changed; adapter.edit(changed);
    }
    async function saveDraft(stage = state?.source_kind === 'docx' ? 1 : 2) {
        if (!adapter || busy || !sourceChosen) return;
        // The adapter serializes and coalesces saves with newer local generations.
        busy = true; errorMessage = ''; message = 'Saving document draft…';
        try { await adapter.save(stage); message = 'Document draft saved.'; }
        catch (error) { errorMessage = error instanceof Error ? error.message : 'The document draft was not saved. Your work is retained.'; message = ''; }
        finally { busy = false; }
    }
    async function uploadWord(file?: File) {
        if (file && !replaceWordConfirm && state?.document_asset_id) { pendingFile = file; replaceWordConfirm = true; return; }
        replaceWordConfirm = false;
        if (!adapter || busy || !file) { if (!file) errorMessage = 'Choose a DOCX file to upload.'; return; }
        if (snapshot?.unknown_operation) { errorMessage = 'Retry the previous operation before selecting another file.'; return; }
        pendingUpload = { kind: 'word', file, targetId: null };
        busy = true; errorMessage = ''; message = 'Uploading and validating Word source…';
        try {
            await adapter.upload(file); pendingFile = null; message = 'Word source is saved to this document draft.'; state = adapter.snapshot().state;
        } catch (error) { errorMessage = error instanceof Error ? error.message : 'Word upload failed. Your existing draft is retained.'; message = ''; }
        finally { if (!adapter.snapshot().unknown_operation) pendingUpload = null; busy = false; }
    }
    async function uploadDocumentImage(file?: File, targetId: string | null = null): Promise<{ asset_id: string; src: string; label: string } | null> {
        if (!adapter || busy || !file) { if (!file) errorMessage = 'Choose a PNG or JPEG image to upload.'; return null; }
        if (snapshot?.unknown_operation) { errorMessage = 'Retry the previous operation before selecting another file.'; return null; }
        pendingUpload = { kind: 'image', file, targetId };
        busy = true; errorMessage = ''; message = 'Uploading and validating private document image…';
        try {
            const result = await adapter.uploadImage(file);
            const entry = completeImageUpload(result, targetId);
            message = targetId ? 'Verified image uploaded. It is applied only if the original image component still exists and is unlocked.' : 'Verified image added to this document family. Use the Designer palette to place it.';
            return entry;
        } catch (error) {
            errorMessage = error instanceof Error ? error.message : 'Image upload failed. Existing document content is retained.';
            message = '';
            return null;
        } finally {
            if (!adapter.snapshot().unknown_operation) pendingUpload = null;
            busy = false;
        }
    }
    function completeImageUpload(result: Record<string, unknown>, targetId: string | null): { asset_id: string; src: string; label: string } {
        const entry = addDocumentImage(result);
        if (!adapter || !targetId || state?.source_kind !== 'builder' || !state.builder_json || !Array.isArray(state.builder_json.components)) return entry;
        const components = state.builder_json.components as Record<string, unknown>[];
        const target = components.find((component) => component.id === targetId);
        if (!target || target.type !== 'image' || Boolean(target.locked)) return entry;
        const next = { ...state, builder_json: { ...state.builder_json, components: components.map((component) => component === target ? { ...component, props: { ...(component.props as Record<string, unknown> ?? {}), asset_id: entry.asset_id, src: entry.src } } : component) } };
        state = next;
        adapter.edit(next);
        return entry;
    }
    function addDocumentImage(result: Record<string, unknown>): { asset_id: string; src: string; label: string } {
        const asset = result.asset as { id?: string } | undefined;
        const previewUrl = typeof result.preview_url === 'string' ? result.preview_url : null;
        if (!asset?.id || !previewUrl) throw new Error('The upload receipt did not include a verified image asset and preview URL.');
        const entry = { asset_id: asset.id, src: previewUrl, label: `Image ${asset.id.slice(0, 8)}` };
        if (!documentImages.some((item) => item.asset_id === entry.asset_id)) documentImages = [...documentImages, entry];
        return entry;
    }
    function candidateReturnUrl(receipt: Record<string, unknown>): string {
        const candidate = receipt.document_version;
        const returnUrl = receipt.return_url;
        const expectedReturnUrl = props.endpoints.candidate_return_url;
        if (!candidate || typeof candidate !== 'object' || !('id' in candidate) || typeof candidate.id !== 'number'
            || !Number.isSafeInteger(candidate.id) || candidate.id < 1
            || !('version' in candidate) || typeof candidate.version !== 'number'
            || !Number.isSafeInteger(candidate.version) || candidate.version < 1
            || typeof returnUrl !== 'string' || !expectedReturnUrl) {
            throw new Error('The ready document receipt is missing its exact candidate or safe composer return URL.');
        }
        const exactReturn = new URL(returnUrl, window.location.origin);
        const expected = new URL(expectedReturnUrl, window.location.origin);
        if (exactReturn.origin !== window.location.origin || exactReturn.pathname !== expected.pathname
            || exactReturn.pathname !== expectedReturnUrl) {
            throw new Error('The ready document receipt did not return to this notice composer.');
        }
        exactReturn.search = '';
        exactReturn.searchParams.set('document_candidate_id', String(candidate.id));
        exactReturn.searchParams.set('document_candidate_version', String(candidate.version));
        return `${exactReturn.pathname}${exactReturn.search}`;
    }

    async function retryUnknown() {
        if (!adapter || (busy && !snapshot?.unknown_operation)) return;
        busy = true; errorMessage = ''; message = 'Retrying the same document operation…';
        const operation = snapshot?.unknown_operation;
        try {
            const result = await adapter.retry();
            if (pendingUpload) {
                const intended = pendingUpload;
                const uploadResult = operation === 'draft'
                    ? intended.kind === 'image' ? await adapter.uploadImage(intended.file) : await adapter.upload(intended.file)
                    : result;
                if (intended.kind === 'image') completeImageUpload(uploadResult, intended.targetId);
                else state = adapter.snapshot().state;
                pendingFile = null; pendingUpload = null;
            }
            message = 'The original document operation was confirmed.';
            if (operation === 'version') {
                const destination = candidateReturnUrl(result);
                const version = result.document_version as { version: number };
                pendingCandidate = { url: destination, version: version.version };
                if (adapter.snapshot().status === 'dirty') {
                    message = `Document version ${version.version} is ready. Newer local edits remain unsaved; save them before returning to the candidate. The message still uses its previous selection.`;
                } else {
                    message = `Document version ${version.version} is ready. The message still uses its previous selection.`;
                    success(message);
                }
            }
        }
        catch (error) { errorMessage = error instanceof Error ? error.message : 'The operation is still unconfirmed; your work is retained.'; message = ''; }
        finally { if (!adapter.snapshot().unknown_operation) pendingUpload = null; busy = false; }
    }
    async function checkPdf() {
        if (!adapter || busy || !sourceChosen || containsUnverifiedImage) return;
        busy = true; checkingPdf = true; errorMessage = ''; message = 'Checking the current document and preparing its PDF…';
        try {
            const result = await adapter.preview(); setPdf(result); mobileTab = 'preview'; message = 'Current PDF check passed.';
        } catch (error) { errorMessage = error instanceof Error ? error.message : 'PDF check failed. Correct the document findings and check again.'; message = ''; }
        finally { busy = false; checkingPdf = false; }
    }
    async function saveVersion() {
        if (!adapter || busy || !previewCurrent) return;
        busy = true; errorMessage = ''; message = 'Saving immutable document version…';
        let confirmedCandidateUrl: string | null = null;
        try {
            const result = await adapter.saveVersion();
            const destination = candidateReturnUrl(result);
            const version = result.document_version as { version: number };
            pendingCandidate = { url: destination, version: version.version };
            if (adapter.snapshot().status === 'dirty') {
                message = `Document version ${version.version} is ready. Newer local edits remain unsaved; save them before returning to the candidate. The message still uses its previous selection.`;
            } else {
                message = `Document version ${version.version} is ready. The message still uses its previous selection.`;
                success(message);
                confirmedCandidateUrl = destination;
            }
            await tick();
            if (confirmedCandidateUrl) {
                leaving = true;
                router.visit(confirmedCandidateUrl);
            }
        }
        catch (error) { errorMessage = error instanceof Error ? error.message : 'The document version could not be confirmed or safely returned. If it was saved, the message remains unchanged until the exact version is revalidated.'; message = ''; }
        finally { busy = false; }
    }
    async function saveAndReturnCandidate() {
        if (!adapter || busy || !pendingCandidate || snapshot?.unknown_operation) return;
        busy = true; errorMessage = '';
        try {
            if (adapter.snapshot().status !== 'saved') {
                message = 'Saving the newer document edits before returning to the exact ready version…';
                await adapter.save(state?.source_kind === 'docx' ? 1 : 2);
                if (adapter.snapshot().status !== 'saved') throw new Error('The latest document edit is not confirmed yet. The ready candidate remains pending.');
            }
            const destination = pendingCandidate.url;
            pendingCandidate = null;
            leaving = true;
            router.visit(destination);
        } catch (error) { errorMessage = error instanceof Error ? error.message : 'The newer document edits are not confirmed. The ready candidate remains pending.'; }
        finally { busy = false; }
    }
    async function discardDraft() {
        if (!adapter || busy || !snapshot?.draft) return;
        busy = true; errorMessage = ''; message = 'Discarding document draft…';
        try { await adapter.discard(); message = 'Document draft discarded.'; router.visit(props.return_url); }
        catch (error) { errorMessage = error instanceof Error ? error.message : 'The draft was not discarded. Your work is retained.'; message = ''; }
        finally { busy = false; }
    }
    function hasUnsavedWork() { return Boolean(snapshot && ['dirty', 'saving', 'unknown', 'error', 'conflict'].includes(snapshot.status)) || busy; }
    function offerNavigation(leave: () => void) {
        if (!hasUnsavedWork()) { leaving = true; leave(); return; }
        pendingNavigation = leave;
        activeDialog = 'leave';
    }
    async function saveAndContinue() {
        if (!adapter || busy || !pendingNavigation || snapshot?.unknown_operation) return;
        busy = true; errorMessage = ''; message = 'Saving document draft before leaving…';
        try {
            await adapter.save(state?.source_kind === 'docx' ? 1 : 2);
            if (adapter.snapshot().status !== 'saved') throw new Error('The latest document edit is not confirmed yet.');
            const leave = pendingNavigation;
            pendingNavigation = null; activeDialog = null; leaving = true; leave?.();
        } catch (error) { errorMessage = error instanceof Error ? error.message : 'Document draft was not confirmed; your work is retained.'; }
        finally { busy = false; }
    }
    function offerBack() { offerNavigation(() => router.visit(props.return_url)); }
    async function reloadDocument(preserveLocal: boolean) {
        if (!adapter || busy) return;
        busy = true; errorMessage = '';
        try {
            await adapter.load(preserveLocal);
            state = resolveBuilderImages(adapter.snapshot().state);
            sourceChosen = Boolean(adapter.snapshot().draft || preserveLocal);
            activeDialog = null;
            message = preserveLocal ? 'Current document guards loaded. Review your retained local changes, then save.' : 'Current server document loaded.';
        } catch (error) { errorMessage = error instanceof Error ? error.message : 'Reload failed. Your local work is retained.'; }
        finally { busy = false; }
    }
    function closeDialog(dialog: NonNullable<typeof activeDialog>) {
        if (activeDialog !== dialog || busy) return;
        activeDialog = null;
        if (dialog === 'leave') pendingNavigation = null;
    }
    onMount(() => {
        startAdapter();
        historyGuard = createNoticeStudioHistoryGuard({ hasUnsavedWork, onNavigation: offerNavigation });
        const removeBefore = router.on('before', (event) => {
            if (leaving || event.detail.visit.method !== 'get' || !hasUnsavedWork()) return;
            const destination = event.detail.visit.url;
            event.preventDefault();
            offerNavigation(() => router.visit(destination));
        });
        const beforeUnload = (event: BeforeUnloadEvent) => {
            if (!hasUnsavedWork()) return;
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', beforeUnload);
        return () => { historyGuard?.dispose(); historyGuard = null; removeBefore(); window.removeEventListener('beforeunload', beforeUnload); };
    });
    onDestroy(() => { unsubscribe?.(); adapter?.dispose(); });
</script>

<svelte:head><title>{props.family.name} · PDF document</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="flex min-h-[calc(100dvh-9rem)] min-w-0 flex-col gap-3 p-3 sm:p-5">
        <header class="sticky top-0 z-30 flex min-w-0 flex-col items-stretch gap-3 border-b bg-background py-3 min-[769px]:flex-row min-[769px]:items-center min-[769px]:justify-between">
            <div class="flex min-w-0 items-center gap-2"><Button type="button" variant="outline" onclick={offerBack} aria-label="Back to notice">Back</Button><div class="min-w-0"><PageHeaderBar title={`Edit ${props.family.name} document`} description="Create a separate PDF document. Saving it does not change the message or attach it automatically." /></div></div>
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-2 min-[769px]:justify-end">
                <Badge variant="outline" aria-label="Document draft status">{draftStatus}</Badge>
                <div class="grid min-w-0 grid-cols-2 gap-2 min-[769px]:flex">
                    <Button type="button" variant="outline" class="min-w-0" onclick={() => void saveDraft()} disabled={busy || !sourceChosen}>Save draft</Button>
                    <Button type="button" variant="outline" class="min-w-0" onclick={() => void checkPdf()} disabled={busy || !sourceChosen || containsUnverifiedImage}>Check PDF</Button>
                    <Button type="button" class="col-span-2 min-w-0 min-[769px]:col-span-1" onclick={() => void saveVersion()} disabled={busy || !previewCurrent || containsUnverifiedImage}>Save document version</Button>
                </div>
            </div>
        </header>
        <div class="flex min-w-0 flex-col items-start justify-between gap-2 rounded-lg border bg-card px-3 py-2 text-sm min-[769px]:flex-row min-[769px]:items-center"><p class="min-w-0 break-words" aria-live="polite">{message || (snapshot?.status === 'unknown' ? 'Document save outcome unknown; retry the same operation.' : snapshot?.status === 'conflict' ? 'A newer document draft exists. Local work is preserved.' : snapshot?.status === 'dirty' ? 'Unsaved document changes' : hasDraft ? 'Document draft saved' : 'No document draft saved yet')}</p>{#if snapshot?.unknown_operation}<Button type="button" variant="outline" onclick={() => void retryUnknown()} disabled={busy}>Retry same operation</Button>{/if}</div>
        {#if errorMessage}<div bind:this={statusNode} tabindex="-1" role="alert" class="rounded-lg border border-destructive p-3 text-sm text-destructive">{errorMessage}</div>{/if}
        {#if snapshot?.status === 'conflict'}<section role="status" class="space-y-2 rounded-lg border p-3 text-sm"><p>Your local document is preserved. Load current guards to reconcile your work, or replace local edits with the server draft.</p><div class="flex flex-wrap gap-2"><Button variant="outline" onclick={() => void reloadDocument(true)} disabled={busy}>Reconcile local changes</Button><Button variant="outline" onclick={() => activeDialog = 'reload'} disabled={busy}>Reload server draft</Button></div></section>{/if}
        {#if pendingCandidate}<section role="status" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-primary/40 bg-card p-3 text-sm"><p>Ready document version {pendingCandidate.version} is pending. Returning does not select or attach it; the message remains unchanged.</p><Button type="button" onclick={() => void saveAndReturnCandidate()} disabled={busy || Boolean(snapshot?.unknown_operation) || snapshot?.status === 'conflict'}>{snapshot?.status === 'saved' ? 'Return with ready version' : 'Save newer edits and return'}</Button></section>{/if}
        <div class="flex gap-2 min-[769px]:hidden" role="group" aria-label="Document workspace view"><Button type="button" variant={mobileTab === 'write' ? 'default' : 'outline'} aria-pressed={mobileTab === 'write'} onclick={() => mobileTab = 'write'}>Write</Button><Button type="button" variant={mobileTab === 'preview' ? 'default' : 'outline'} aria-pressed={mobileTab === 'preview'} onclick={() => mobileTab = 'preview'}>Preview</Button></div>
        {#if !sourceChosen}
            <section class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 sm:p-6" aria-labelledby="source-choice-heading">
                <div class="sm:col-span-2"><h2 id="source-choice-heading" class="text-lg font-semibold">Choose a document source</h2><p class="mt-1 text-sm text-muted-foreground">Choose before editing. An empty designer draft may be saved, but it cannot pass PDF Check or become a ready version until it has meaningful printable content and required fields.</p></div>
                <button type="button" aria-label="Start from a Word document" class="min-h-32 rounded-lg border p-4 text-left hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={() => enterSource('docx')}><span class="block font-semibold">Start from a Word document</span><span class="mt-2 block text-sm text-muted-foreground">Upload a safe DOCX, then check the actual server-rendered PDF.</span></button>
                <button type="button" aria-label="Design a document" class="min-h-32 rounded-lg border p-4 text-left hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" onclick={() => enterSource('builder')}><span class="block font-semibold">Design a document</span><span class="mt-2 block text-sm text-muted-foreground">Use the familiar physical document designer and its typed fields.</span></button>
            </section>
        {:else}
            <div class="grid min-h-0 min-w-0 flex-1 gap-3 min-[769px]:grid-cols-2">
                <section class={`${mobileTab === 'write' ? 'flex' : 'hidden'} min-h-[50vh] min-w-0 flex-col overflow-hidden rounded-xl border bg-card min-[769px]:flex`} aria-label="Write document">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b p-3"><div><h2 class="font-semibold">{sourceName}</h2><p class="text-xs text-muted-foreground">Document work is independent from the message.</p></div><button type="button" class="min-h-10 rounded-md border px-3 text-sm" disabled={busy} onclick={() => activeDialog = 'replace-source'}>Change source</button></div>
                    <div class="flex flex-wrap gap-3 border-b p-3"><label class="text-xs">Paper size<select disabled={busy} class="ml-2 min-h-10 rounded-md border bg-background px-2" value={state?.paper_size ?? 'a4'} onchange={(event) => updateSetting('paper_size', event.currentTarget.value)}><option value="a4">A4</option><option value="letter">Letter</option><option value="legal">Legal</option></select></label><label class="text-xs">Orientation<select disabled={busy} class="ml-2 min-h-10 rounded-md border bg-background px-2" value={state?.orientation ?? 'portrait'} onchange={(event) => updateSetting('orientation', event.currentTarget.value)}><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></label></div>
                    {#if state?.source_kind === 'docx'}
                        <div class="space-y-4 p-4">
                            <button type="button" class="min-h-11 rounded-md border px-3 text-sm" onclick={() => { pickerMode = 'copy'; pickerOpen = true; }}>View permitted document fields</button>
                            <label class="block space-y-2 text-sm font-medium">Upload or replace Word document (.docx)<input bind:this={uploadInput} class="block min-h-11 w-full rounded-md border p-2 text-sm" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" disabled={busy || Boolean(snapshot?.unknown_operation)} onchange={(event) => void uploadWord(event.currentTarget.files?.[0])} /><span class="block text-xs font-normal text-muted-foreground">DOCX only, up to 10 MiB. The original remains private and is not imported into the message.</span></label>
                            {#if state.document_asset_id}<p class="text-sm" role="status">A Word source is attached to this document draft.</p>{:else}<p class="text-sm text-muted-foreground">No Word source uploaded yet.</p>{/if}
                        </div>
                    {:else}
                        <div class="flex min-h-0 flex-1 flex-col">
                            {#if props.endpoints.image_upload}<div class="flex flex-wrap items-center gap-2 border-b p-2"><Button type="button" variant="outline" onclick={() => imageInput?.click()} disabled={busy}>Upload PNG/JPEG</Button><input bind:this={imageInput} class="sr-only" aria-label="Upload verified document image" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" disabled={busy || Boolean(snapshot?.unknown_operation)} onchange={(event) => void uploadDocumentImage(event.currentTarget.files?.[0])} /><span class="text-xs text-muted-foreground">PNG/JPEG up to 2 MiB.</span></div>{/if}
                            {#if containsUnverifiedImage}<div role="alert" class="m-3 rounded-lg border border-destructive/50 p-3 text-sm">This saved design has an image without a verified same-family asset reference. Its source is preserved but cannot pass PDF Check or become ready. Replace it with an uploaded verified image or remove the image component.</div>{/if}
                            <div class="flex min-h-[48vh] min-w-0 flex-1"><ResultSheetStudio fullBleed builderDocument={builderDocument} builderDefaults={props.builderDefaults} builderCatalog={safeBuilderCatalog} builderCapabilities={props.builderCapabilities} placeholderGroups={props.placeholderGroups} sampleContext={props.sampleContext} onChange={updateBuilder} onOpenPlaceholders={openBuilderPlaceholder} allowNoticeDocumentImages={true} noticeDocumentImageAssets={documentImages} onNoticeDocumentImageUpload={uploadDocumentImage} /></div>
                        </div>
                    {/if}
                </section>
                <section class={`${mobileTab === 'preview' ? 'flex' : 'hidden'} min-h-[50vh] min-w-0 flex-col overflow-hidden rounded-xl border bg-card min-[769px]:flex`} aria-label="Actual PDF preview">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b p-3"><div><h2 class="font-semibold">PDF preview</h2><p class="text-xs text-muted-foreground">Only server-returned PDF bytes can pass the current check.</p></div><Button type="button" variant="outline" onclick={() => void checkPdf()} disabled={busy || !sourceChosen || containsUnverifiedImage}>Check current document</Button></div>
                    {#if checkingPdf}<div role="status" class="min-h-[55vh] space-y-4 p-6"><p>Rendering and validating the current PDF…</p><div class="h-8 w-2/3 rounded bg-muted motion-safe:animate-pulse"></div><div class="h-80 rounded bg-muted motion-safe:animate-pulse"></div></div>{:else if pdfUrl && previewCurrent}<iframe title="Checked PDF document preview" src={pdfUrl} class="min-h-[55vh] w-full flex-1 bg-muted" />{:else}<div class="flex min-h-[55vh] flex-1 items-center justify-center p-6 text-center"><div class="max-w-md space-y-2"><p class="font-medium">{pdfUrl ? 'This PDF check is no longer current.' : 'No current PDF check'}</p><p class="text-sm text-muted-foreground">Include meaningful content and the required {props.family.slug === 'released-online' ? 'applicant_reference' : 'application_no'} field, then choose Check PDF. Correct any server findings and check again.</p></div></div>{/if}
                    {#if previewCurrent}<p class="break-all border-t p-3 text-xs text-muted-foreground" role="status">Current PDF check passed · SHA-256 {preview?.pdf.sha256}</p>{/if}
                </section>
            </div>
            {#if snapshot?.preview?.diagnostics?.length}<section aria-label="Document corrections" class="rounded-lg border p-3"><h2 class="font-semibold">Corrections to make</h2><ul class="mt-2 list-disc space-y-1 pl-5 text-sm">{#each snapshot.preview.diagnostics as diagnostic}<li>{typeof diagnostic === 'string' ? diagnostic : JSON.stringify(diagnostic)}</li>{/each}</ul></section>{/if}
            {#if hasDraft}<div class="flex flex-wrap justify-end"><Button type="button" variant="outline" onclick={() => activeDialog = 'discard'} disabled={busy}>Discard document draft</Button></div>{/if}
        {/if}
        <PlaceholderPicker open={pickerOpen} mode={pickerMode} placeholderGroups={props.placeholderGroups} onclose={() => { pickerOpen = false; placeholderTargetId = null; }} oncopy={(value) => { void navigator.clipboard?.writeText(String(value.macro)); pickerOpen = false; }} oninsert={insertBuilderPlaceholder} />
        {#if replaceWordConfirm}
            <Dialog.Dialog open={true} onOpenChange={(open) => { if (!open && !busy) { replaceWordConfirm = false; pendingFile = null; } }}>
                <Dialog.DialogContent onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }}>
                    <Dialog.DialogHeader><Dialog.DialogTitle>Replace the saved Word source?</Dialog.DialogTitle><Dialog.DialogDescription>The draft will use the selected file. Ready versions and existing deliveries remain unchanged.</Dialog.DialogDescription></Dialog.DialogHeader>
                    <Dialog.DialogFooter><Button type="button" variant="outline" onclick={() => { replaceWordConfirm = false; pendingFile = null; }}>Keep current Word source</Button><Button type="button" onclick={() => void uploadWord(pendingFile ?? undefined)} disabled={busy || !pendingFile}>Replace Word source</Button></Dialog.DialogFooter>
                </Dialog.DialogContent>
            </Dialog.Dialog>
        {/if}
        {#if activeDialog === 'leave'}
            <Dialog.Dialog open={true} onOpenChange={(open) => { if (!open) closeDialog('leave'); }}>
                <Dialog.DialogContent onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }}>
                    <Dialog.DialogHeader><Dialog.DialogTitle>Save document work before leaving?</Dialog.DialogTitle><Dialog.DialogDescription>Your document changes are not confirmed yet. Save this document draft before returning; the message and its selected version remain unchanged.</Dialog.DialogDescription></Dialog.DialogHeader>
                    <Dialog.DialogFooter><Button type="button" onclick={() => void saveAndContinue()} disabled={busy || Boolean(snapshot?.unknown_operation)}>Save draft and continue</Button><Button type="button" variant="outline" onclick={() => closeDialog('leave')} disabled={busy}>Stay here</Button></Dialog.DialogFooter>
                </Dialog.DialogContent>
            </Dialog.Dialog>
        {/if}
        {#if activeDialog === 'replace-source'}
            <Dialog.Dialog open={true} onOpenChange={(open) => { if (!open) closeDialog('replace-source'); }}>
                <Dialog.DialogContent onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }}>
                    <Dialog.DialogHeader><Dialog.DialogTitle>Save and replace this document source?</Dialog.DialogTitle><Dialog.DialogDescription>The current source is saved to this document draft before you choose a replacement.</Dialog.DialogDescription></Dialog.DialogHeader>
                    <Dialog.DialogFooter><Button type="button" variant="outline" onclick={() => void replaceSource('docx')} disabled={busy}>Replace with Word document</Button><Button type="button" variant="outline" onclick={() => void replaceSource('builder')} disabled={busy}>Replace with Visual designer</Button><Button type="button" variant="ghost" onclick={() => closeDialog('replace-source')} disabled={busy}>Keep current source</Button></Dialog.DialogFooter>
                </Dialog.DialogContent>
            </Dialog.Dialog>
        {/if}
        {#if activeDialog === 'discard'}
            <Dialog.Dialog open={true} onOpenChange={(open) => { if (!open) closeDialog('discard'); }}>
                <Dialog.DialogContent onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }}>
                    <Dialog.DialogHeader><Dialog.DialogTitle>Discard this document draft?</Dialog.DialogTitle><Dialog.DialogDescription>Only this document draft will be discarded. The message draft and saved document versions remain unchanged.</Dialog.DialogDescription></Dialog.DialogHeader>
                    <Dialog.DialogFooter><Button type="button" variant="destructive" onclick={() => { activeDialog = null; void discardDraft(); }} disabled={busy}>Discard document draft</Button><Button type="button" variant="outline" onclick={() => closeDialog('discard')} disabled={busy}>Keep editing</Button></Dialog.DialogFooter>
                </Dialog.DialogContent>
            </Dialog.Dialog>
        {/if}
        {#if activeDialog === 'reload'}
            <Dialog.Dialog open={true} onOpenChange={(open) => { if (!open) closeDialog('reload'); }}>
                <Dialog.DialogContent onEscapeKeydown={(event) => { if (busy) event.preventDefault(); }}>
                    <Dialog.DialogHeader><Dialog.DialogTitle>Replace local work with the server draft?</Dialog.DialogTitle><Dialog.DialogDescription>This overwrites your current local document state with the latest server draft. Choose Reconcile local changes to keep your edits instead.</Dialog.DialogDescription></Dialog.DialogHeader>
                    <Dialog.DialogFooter><Button type="button" variant="destructive" onclick={() => void reloadDocument(false)} disabled={busy}>Replace local work</Button><Button type="button" variant="outline" onclick={() => closeDialog('reload')} disabled={busy}>Cancel</Button></Dialog.DialogFooter>
                </Dialog.DialogContent>
            </Dialog.Dialog>
        {/if}
    </div>
</StaffLayout>
