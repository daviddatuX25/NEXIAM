<script lang="ts">
    import { onDestroy, onMount, untrack } from 'svelte';
    import { page, router } from '@inertiajs/svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { csrfToken } from '@/lib/utils';
    import { noticePreviewDocument } from '@/lib/noticePreviewDocument';
    import { renderLiveEmailHtml, renderLivePortalHtml, DEFAULT_SAMPLE_CONTEXT } from '@/lib/noticeLivePreview';
    import { createNoticeStudioHistoryGuard, type NoticeHistoryGuard } from '@/lib/noticeStudioHistoryGuard';
    import { createNoticeMessageComposer, type ComposerSnapshot, type MessageComposerProps, type MessageState, type NoticeMessageComposer } from '@/lib/noticeMessageComposer';
    import { success, error } from '@/lib/toast';
    import {
        Smartphone,
        Tablet,
        Monitor,
        Mail,
        Bell,
        Type,
        AlignLeft,
        ExternalLink,
        Paperclip,
        Info,
        Send,
        ZoomIn,
        ZoomOut,
    } from 'lucide-svelte';
    import MessageBody from './MessageBody.svelte';
    import { blueprintToText, textToBlueprint } from '@/lib/noticeMessageBody';
    type PendingDocumentCandidate = { id: number; version: number; definition_sha256: string; render_sha256: string; pdf_url: string; endpoints: { pdf: string; thumbnail: string } };
    type ComposerPageProps = Omit<MessageComposerProps, 'family'> & {
        family: MessageComposerProps['family'] & {
            description?: string | null;
            event_key?: string | null;
            default_action_route?: string | null;
            default_action_label?: string | null;
            channels?: string[];
        };
        pending_document_candidate?: PendingDocumentCandidate | null;
    };
    let { family, state: initialState, draft = null, base_version, content_sha256 = null, output_digests, tokens = [], assets = [], sample_context = {}, endpoints, document_versions = [], versions = [], copy_source_version = null, supports_pdf = family.supports_pdf === true, read_only = false, content_schema_version, email_renderer_version, conversion_review, pending_document_candidate = null } = $props<ComposerPageProps>();
    const props = $derived({ family, state: initialState, draft, base_version, content_sha256, output_digests, tokens, assets, sample_context, endpoints, document_versions, versions, copy_source_version, supports_pdf, read_only, content_schema_version, email_renderer_version, conversion_review });
    let adapter = $state<NoticeMessageComposer | null>(null);
    let previewView = $state<'email' | 'portal'>('email');
    let mobileTab = $state<'write' | 'preview'>('write');
    let emailBodyText = $state(untrack(() => blueprintToText(initialState.email_blueprint)));
    let snapshot = $state<ComposerSnapshot | null>(null);
    let previewWidth = $state<390 | 768 | 1024>(390);
    let zoomMode = $state<'fit' | '100%'>('fit');
    let previewScale = $derived(
        zoomMode === 'fit'
            ? (previewWidth === 1024 ? 0.6 : previewWidth === 768 ? 0.8 : 1)
            : 1
    );
    let testMailReceipt = $state<{ test_send_id: string; status: string; recipient_masked: string; operation_id: string } | null>(null);
    let testMailUnknown = $state(false);
    let busy = $state(false);
    let checkingPreview = $state(false);
    let replaceIndex = $state<number | null>(null);
    let message = $state('');
    let imageBusy = $state(false);
    let uploadError = $state('');
    let imageFile: HTMLInputElement;
    let pendingImageFile: File | null = null;
    let composerState = $state<MessageState>(untrack(() => cloneMessageData(initialState)));
    let unsubscribe: (() => void) | undefined;
    let publicationUnknown = $state(false);
    let publishConfirm = $state(false);
    let publishConfirmationIdentity = $state('');
    let publishDialog: HTMLDivElement;
    let publishTrigger: HTMLButtonElement | null = null;
    let leaveDialog = $state(false);
    let discardConfirm = $state(false);
    let reloadServerConfirm = $state(false);
    let leaving = false;
    let pendingNavigation: (() => void) | null = null;
    let historyGuard: NoticeHistoryGuard | null = null;

    function cloneMessageData<T>(value: T): T {
        return JSON.parse(JSON.stringify(value)) as T;
    }
    $effect(() => {
        if (adapter) return;
        adapter = createNoticeMessageComposer(props, { csrf: csrfToken });
        const initialSnapshot = adapter.snapshot();
        composerState = cloneMessageData(initialSnapshot.state);
        emailBodyText = blueprintToText(initialSnapshot.state.email_blueprint);
        snapshot = initialSnapshot;
        unsubscribe = adapter.subscribe((next) => {
            snapshot = next;
            composerState = cloneMessageData(next.state);
            emailBodyText = blueprintToText(next.state.email_blueprint);
        });
    });
    onDestroy(() => { unsubscribe?.(); adapter?.dispose(); for (const asset of ownedAssets) if (asset.preview_url.startsWith('blob:')) URL.revokeObjectURL(asset.preview_url); });
    function hasUnsavedWork(): boolean {
        return Boolean(adapter?.hasUnsavedWork()) || imageBusy;
    }
    function offerNavigation(leave: () => void) {
        pendingNavigation = leave;
        leaveDialog = true;
    }
    function stayInComposer() {
        leaveDialog = false;
        pendingNavigation = null;
    }
    function leaveComposer() {
        const leave = pendingNavigation;
        pendingNavigation = null;
        leaveDialog = false;
        leaving = true;
        leave?.();
    }
    onMount(() => {
        historyGuard = createNoticeStudioHistoryGuard({
            hasUnsavedWork,
            onNavigation: offerNavigation,
        });
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
        return () => {
            historyGuard?.dispose();
            historyGuard = null;
            removeBefore();
            window.removeEventListener('beforeunload', beforeUnload);
        };
    });
    const blocks = $derived(composerState.email_blueprint?.blocks ?? []);
    const ownedAssets = $state(untrack(() => [...assets]));
    const preview = $derived(snapshot?.previews?.[previewView]);
    const digestFresh = $derived(Boolean(preview && preview.output_sha256 === snapshot?.output_digests?.[`${previewView}_sha256`]));
    const emailPreviewFresh = $derived(Boolean(snapshot?.previews.email && snapshot.previews.email.output_sha256 === snapshot.output_digests.email_sha256));
    const portalPreviewFresh = $derived(Boolean(snapshot?.previews.portal && snapshot.previews.portal.output_sha256 === snapshot.output_digests.portal_sha256));
    const hasSelectedAttachment = $derived(Boolean(supports_pdf && family.supports_pdf && composerState.document_revision_id !== null));
    const supportsAttachmentBinding = $derived(Boolean(supports_pdf && family.supports_pdf));
    const selectedAttachmentFresh = $derived(!supportsAttachmentBinding || (
        snapshot?.attachment_check !== null
        && snapshot?.attachment_check !== undefined
        && (hasSelectedAttachment
            ? String(snapshot.attachment_check.document?.id ?? '') === String(composerState.document_revision_id)
            : snapshot.attachment_check.document === null)
    ));
    const checksFresh = $derived(emailPreviewFresh && portalPreviewFresh && selectedAttachmentFresh);
    const canonicalActionRoute = $derived(family.default_action_route !== undefined ? family.default_action_route : (initialState.action_route ?? composerState.action_route));
    const actionEnabled = $derived(Boolean(canonicalActionRoute && composerState.action_route === canonicalActionRoute));
    const liveEmailHtml = $derived(renderLiveEmailHtml({
        subject: composerState.subject ?? '',
        bodyText: emailBodyText,
        actionLabel: composerState.action_label,
        actionEnabled,
        sampleContext: sample_context || DEFAULT_SAMPLE_CONTEXT,
        assets: ownedAssets,
    }));
    const livePortal = $derived(renderLivePortalHtml({
        subject: composerState.subject ?? '',
        bodyText: composerState.body ?? '',
        actionLabel: composerState.action_label,
        actionEnabled,
        sampleContext: sample_context || DEFAULT_SAMPLE_CONTEXT,
    }));
    let pendingDocumentCandidateState = $state<PendingDocumentCandidate | null>(untrack(() => pending_document_candidate));
    let pendingDocumentSelection = $state<number | null>(untrack(() => pending_document_candidate?.id ?? initialState.document_revision_id));
    const conversionBlocked = $derived(conversion_review?.status === 'blocked');
    const currentDocumentForPublish = $derived(document_versions.find((item) => item.id === composerState.document_revision_id));
    function publishIdentity(): string {
        return JSON.stringify({
            state: composerState,
            guards: snapshot?.guards,
            content: snapshot?.content_sha256,
            digests: snapshot?.output_digests,
            email: snapshot?.previews.email?.output_sha256,
            portal: snapshot?.previews.portal?.output_sha256,
            attachment: snapshot?.attachment_check?.binding_sha256 ?? null,
        });
    }
    function ensureBlueprint(state: MessageState): MessageState {
        return { ...state, email_blueprint: state.email_blueprint ?? { schema_version: 'notice-email-1', layout: 'flow', blocks: [] } };
    }
    function update(next: MessageState): void { composerState = next; adapter?.edit(ensureBlueprint(next)); }
    function updateTop(key: 'subject' | 'body' | 'action_label', value: string) { update({ ...composerState, [key]: value }); }
    function updateEmailBodyText(text: string): void {
        emailBodyText = text;
        update({ ...composerState, email_blueprint: textToBlueprint(text, composerState.email_blueprint) });
    }
    function removeBlock(index: number): void {
        update({ ...ensureBlueprint(composerState), email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: blocks.filter((_, blockIndex) => blockIndex !== index) } });
    }
    function updateImageAlt(index: number, alt: string): void {
        const nextBlocks = [...blocks];
        if (nextBlocks[index]) {
            nextBlocks[index] = { ...nextBlocks[index], alt };
            update({ ...ensureBlueprint(composerState), email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: nextBlocks } });
        }
    }
    let lastActionLabel = $state(untrack(() => initialState.action_label ?? family.default_action_label ?? ''));
    function toggleAction(enabled: boolean): void {
        if (!enabled) {
            if (composerState.action_label) lastActionLabel = composerState.action_label;
            update({ ...composerState, action_label: null, action_route: null });
            return;
        }
        if (!canonicalActionRoute) {
            message = 'This notice family has no canonical action destination.';
            return;
        }
        update({ ...composerState, action_route: canonicalActionRoute, action_label: lastActionLabel || null });
    }
    async function attachPendingDocument(): Promise<void> {
        if (!adapter || busy || imageBusy || !supportsAttachmentBinding) return;
        if (pendingDocumentSelection !== null && !document_versions.some((version) => version.id === pendingDocumentSelection)
            && pendingDocumentCandidateState?.id !== pendingDocumentSelection) {
            message = 'Choose an exact ready document version from this notice family.';
            return;
        }
        if (pendingDocumentCandidateState?.id === pendingDocumentSelection) {
            pendingDocumentCandidateState = null;
        }
        update({ ...composerState, document_revision_id: pendingDocumentSelection });
        await saveDraft();
        if (pendingDocumentCandidateState?.id === pendingDocumentSelection) {
            pendingDocumentCandidateState = null;
        }
    }
    function keepCurrentAttachment(): void {
        pendingDocumentCandidateState = null;
        pendingDocumentSelection = composerState.document_revision_id;
        message = 'The existing exact attachment selection is unchanged.';
    }
    async function saveDraft() {
        if (!adapter || busy || imageBusy) return;
        busy = true;
        message = 'Saving draft…';
        try {
            const result = await adapter.save();
            message = typeof result.operation_id === 'string' ? 'Draft saved.' : 'Save receipt unavailable.';
            success('Changes saved successfully.');
        } catch (caught) {
            message = '';
            error(caught instanceof Error ? caught.message : 'Draft was not saved. Your input is retained.');
        } finally {
            busy = false;
        }
    }
    async function discardDraft() {
        if (!adapter || busy || imageBusy || !snapshot?.draft) return;
        busy = true;
        message = 'Discarding draft…';
        try {
            await adapter.discard();
            message = 'Draft discarded.';
            leaving = true;
            router.visit(endpoints.return_url);
        } catch (error) {
            message = error instanceof Error ? error.message : 'The draft was not discarded. Your work is retained.';
        } finally {
            busy = false;
        }
    }
    async function editDocument() {
        if (!adapter || busy || imageBusy || !endpoints.document_edit) return;
        busy = true; message = 'Saving the message before opening the document editor…';
        try {
            const handoff = await adapter.saveForDocumentHandoff();
            if (!adapter.isSaveHandoffCurrent(handoff)) {
                message = 'The message changed after it was saved. Save the latest text before opening the document editor.';
                return;
            }
            leaving = true;
            router.visit(endpoints.document_edit);
        } catch (error) {
            message = error instanceof Error ? error.message : 'Message save failed. The document editor was not opened.';
        } finally { busy = false; }
    }
    async function reconcileLocalGuards() {
        if (!adapter || busy || imageBusy) return;
        busy = true;
        try {
            await adapter.reconcilePreservingLocal();
            composerState = cloneMessageData(adapter.snapshot().state);
            emailBodyText = blueprintToText(adapter.snapshot().state.email_blueprint);
            message = 'Current server guards loaded. Your text is unchanged; save it to apply your local edits.';
        } catch (error) {
            message = error instanceof Error ? error.message : 'Current server guards could not be loaded. Your local text is retained.';
        } finally {
            busy = false;
        }
    }
    function requestServerReload() {
        if (!adapter || busy || imageBusy) return;
        reloadServerConfirm = true;
    }
    async function reloadServerMessage() {
        if (!adapter || busy || imageBusy) return;
        busy = true;
        reloadServerConfirm = false;
        try {
            await adapter.reloadServerState({ confirmed: true });
            composerState = cloneMessageData(adapter.snapshot().state);
            emailBodyText = blueprintToText(adapter.snapshot().state.email_blueprint);
            message = 'The current server message was reloaded. Your local edits were replaced.';
        } catch (error) {
            message = error instanceof Error ? error.message : 'Current server text could not be reloaded. Your local text is retained.';
        } finally {
            busy = false;
        }
    }
    function previewDocument() {
        const selected = document_versions.find((version) => String(version.id) === String(composerState.document_revision_id));
        if (selected?.pdf_url) window.open(selected.pdf_url, '_blank', 'noopener,noreferrer');
    }
    async function uploadImage(file?: File) {
        if (!adapter || !endpoints.image_upload) { uploadError = 'Image upload is unavailable for this message.'; return; }
        const retrying = snapshot?.unknown_operation === 'image_upload';
        if (!file && !retrying) { uploadError = 'Choose a PNG or JPEG image to upload.'; return; }
        if (retrying && file && pendingImageFile && (file.name !== pendingImageFile.name || file.size !== pendingImageFile.size || file.lastModified !== pendingImageFile.lastModified)) {
            uploadError = 'The prior upload outcome is unknown. Retry the same operation before choosing a different image.'; return;
        }
        const selectedFile = file ?? pendingImageFile;
        if (!selectedFile) { uploadError = 'The image operation cannot be retried because its selected file is no longer available.'; return; }
        if (!retrying) pendingImageFile = selectedFile;
        imageBusy = true; uploadError = '';
        try {
            const receipt = await adapter.uploadImage(selectedFile);
            const asset = receipt.asset as { id: string | number; sha256: string; mime: string; width: number; height: number };
            const assetId = String(asset.id);
            const width = Math.min(600, Math.round(asset.width || 600));
            const height = Math.round(asset.height * width / (asset.width || 600));
            const imageBlock = { type: 'image', asset_id: assetId, alt: '', width, height };
            ownedAssets.push({ asset_id: assetId, sha256: asset.sha256, mime: asset.mime, width: asset.width, height: asset.height, preview_url: URL.createObjectURL(selectedFile) });
            pendingImageFile = null;
            const nextBlocks = [...blocks];
            if (replaceIndex !== null) { nextBlocks[replaceIndex] = imageBlock; } else { nextBlocks.push(imageBlock); }
            replaceIndex = null;
            update({ ...ensureBlueprint(composerState), email_blueprint: { schema_version: 'notice-email-1', layout: 'flow', blocks: nextBlocks } });
        } catch (error) {
            if (adapter.snapshot().unknown_operation !== 'image_upload') pendingImageFile = null;
            uploadError = adapter.snapshot().unknown_operation === 'image_upload'
                ? 'Image upload outcome unknown. Your existing image remains in place; retry the same upload operation.'
                : error instanceof Error ? error.message : 'Image upload failed. Existing content was not replaced.';
        } finally { imageBusy = false; if (imageFile) imageFile.value = ''; }
    }
    async function checkPreview() {
        if (!adapter || busy || imageBusy) return;
        busy = true;
        checkingPreview = true;
        previewView = 'email';
        mobileTab = 'preview';
        message = 'Checking preview…';
        try {
            const checked = await adapter.checkAll();
            if (!checked.email || !checked.portal) {
                message = 'The message changed while its previews were being checked. Run Check preview again to refresh both outputs.';
                return;
            }
            if (supportsAttachmentBinding && (
                !checked.attachment
                || (hasSelectedAttachment
                    ? String(checked.attachment.document?.id) !== String(composerState.document_revision_id)
                    : checked.attachment.document !== null)
            )) {
                message = 'The selected document binding changed during the check. Recheck the exact ready version or permitted none.';
                return;
            }
            message = supportsAttachmentBinding
                ? 'Email, Portal, and exact attachment-selection checks are current.'
                : 'Email and Portal previews are current.';
        } catch (error) {
            message = error instanceof Error
                ? `Preview check failed: ${error.message} Correct the reported issue and run Check preview again.`
                : 'Preview check failed. Review the message and selected attachment, then run Check preview again.';
        } finally {
            busy = false;
            checkingPreview = false;
        }
    }
    function reviewPublish(trigger: HTMLButtonElement): void {
        if (!adapter || busy || imageBusy) return;
        if (!checksFresh && snapshot?.unknown_operation !== 'publish') {
            message = 'Both Email and Portal previews, and the eligible attachment selection if present, must be current. Run Check preview before publishing.';
            mobileTab = 'preview';
            return;
        }
        publishTrigger = trigger;
        publishConfirmationIdentity = publishIdentity();
        publishConfirm = true;
        queueMicrotask(() => publishDialog?.querySelector<HTMLButtonElement>('[data-publish-cancel]')?.focus());
    }
    function cancelPublish(): void {
        publishConfirm = false;
        const trigger = publishTrigger;
        publishTrigger = null;
        queueMicrotask(() => trigger?.focus());
    }
    function handlePublishDialogKeydown(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            cancelPublish();
            return;
        }
        if (event.key !== 'Tab' || !publishDialog) return;
        const focusable = [...publishDialog.querySelectorAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled])')];
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }
    async function publishMessage(): Promise<void> {
        if (!adapter || busy || imageBusy) return;
        if (publishConfirmationIdentity !== publishIdentity()) {
            cancelPublish();
            message = 'The message or its proof changed. Run Check preview again and review the current version before publishing.';
            return;
        }
        publishConfirm = false;
        busy = true;
        message = 'Publishing the checked message for future occurrences…';
        try {
            const receipt = await adapter.publish();
            publicationUnknown = false;
            message = typeof receipt.active_version === 'number' ? 'Message published.' : 'Publication receipt incomplete. No success was recorded.';
        } catch (error) {
            publicationUnknown = snapshot?.status === 'unknown';
            message = error instanceof Error ? error.message : 'Publication was not confirmed. Your input is retained.';
        } finally {
            busy = false;
            publishTrigger?.focus();
            publishTrigger = null;
        }
    }
    async function sendTestEmail() {
        if (!adapter || busy || imageBusy || !endpoints.test_mail) return;
        busy = true; message = 'Checking the current Email preview for a synthetic staff test…';
        try {
            const current = adapter.snapshot();
            if (current.unknown_operation === 'test_mail') {
                const receipt = await adapter.sendTestMail();
                testMailReceipt = receipt; testMailUnknown = false;
                message = `Synthetic test email sent to the verified staff address ${receipt.recipient_masked}.`;
                return;
            }
            if (!current.previews.email || current.previews.email.output_sha256 !== current.output_digests.email_sha256) {
                const rendered = await adapter.preview('email');
                if (!rendered) throw new Error('Email content changed during preview. Refresh it before sending a test.');
            }
            const receipt = await adapter.sendTestMail();
            testMailReceipt = receipt; testMailUnknown = false;
            message = `Synthetic test email sent to the verified staff address ${receipt.recipient_masked}.`;
        } catch (error) {
            testMailUnknown = snapshot?.unknown_operation === 'test_mail';
            message = error instanceof Error ? error.message : 'Test email was not confirmed. Your work is retained.';
        } finally { busy = false; }
    }
    const staffEmail = $derived(page.props?.auth?.user?.email ?? 'verified staff address');
    const deskUrl = $derived(`/setup/notifications/${String(family.domain).toLowerCase()}`);
    const breadcrumbs = $derived([{ label: 'Setup', href: '/setup' }, { label: `${family.domain.charAt(0).toUpperCase()}${family.domain.slice(1).toLowerCase()} Notices`, href: deskUrl }, { label: family.name }]);
</script>
<svelte:head><title>{family.name} · Message composer</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-5 p-4 pb-28 sm:p-6 sm:pb-28" inert={publishConfirm}>
        <PageHeaderBar title={family.name} description={family.description ?? 'Configure candidate email and Portal notification.'}>
            {#snippet actions()}
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-xs font-medium text-muted-foreground shadow-2xs"
                        role="status"
                        aria-live="polite"
                        data-composer-status
                    >
                        <span class={`size-2 rounded-full ${snapshot?.status === 'saved' ? 'bg-emerald-500' : snapshot?.status === 'dirty' ? 'bg-amber-500' : snapshot?.status === 'error' || snapshot?.status === 'conflict' ? 'bg-destructive' : 'bg-primary/50'}`} aria-hidden="true"></span>
                        {snapshot?.status === 'loading'
                            ? 'Loading draft…'
                            : snapshot?.status === 'saving'
                                ? 'Saving draft…'
                                : snapshot?.status === 'unknown'
                                    ? snapshot.unknown_operation === 'test_mail'
                                        ? 'Test email outcome unknown. Retry the same test operation.'
                                        : snapshot.unknown_operation === 'publish'
                                            ? 'Publication outcome unknown. Retry the same publication operation.'
                                            : snapshot.unknown_operation === 'image_upload'
                                                ? 'Image upload outcome unknown. Retry the same image operation.'
                                                : snapshot.unknown_operation === 'discard'
                                                    ? 'Draft discard outcome unknown. Retry the same discard operation.'
                                                    : 'Save outcome unknown. Retry the same operation.'
                                    : snapshot?.status === 'conflict'
                                        ? 'Draft conflict. Your input is retained.'
                                        : snapshot?.status === 'error'
                                            ? 'Save failed. Your input is retained.'
                                        : snapshot?.status === 'saved'
                                            ? 'Draft saved'
                                            : snapshot?.status === 'dirty'
                                                ? 'Unsaved changes'
                                                : 'Ready to compose'}
                    </span>
                </div>
            {/snippet}
        </PageHeaderBar>
        {#if snapshot?.status === 'conflict'}
            <section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3" role="alert">
                <p class="min-w-0 flex-1 text-sm">Your text is retained. Load current server guards to keep these edits, or reload the server message after confirmation.</p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="min-h-11 rounded-md border px-3 text-sm" disabled={busy || imageBusy} onclick={() => void reconcileLocalGuards()}>Keep my text</button>
                    <button type="button" class="min-h-11 rounded-md border px-3 text-sm" disabled={busy || imageBusy} onclick={requestServerReload}>Reload server message…</button>
                </div>
            </section>
        {/if}
        {#if message}<p class="text-sm text-muted-foreground" role="status">{message}</p>{/if}
        <div role="group" class="flex gap-2 rounded-lg border border-border bg-muted/40 p-1 md:hidden" aria-label="Composer sections">
            <button class={`min-h-9 flex-1 rounded-md text-xs font-medium transition-all ${mobileTab === 'write' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground'}`} aria-pressed={mobileTab === 'write'} onclick={() => mobileTab = 'write'}>Write</button>
            <button class={`min-h-9 flex-1 rounded-md text-xs font-medium transition-all ${mobileTab === 'preview' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground'}`} aria-pressed={mobileTab === 'preview'} onclick={() => mobileTab = 'preview'}>Preview</button>
        </div>
        <div class="grid min-w-0 items-start gap-5 md:grid-cols-2">
            <section class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5 md:block" class:hidden={mobileTab === 'preview'} aria-label="Write message">
                {#if read_only}<p class="rounded-md border border-muted p-2.5 text-xs text-muted-foreground">Historical message version — read-only inspection.</p>{/if}
                <h2 class="text-base font-semibold tracking-tight text-foreground">Message composer</h2>

                <!-- Section: Subject and Portal Title -->
                <details class="group rounded-xl border border-border bg-card p-4 transition-all" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg border border-border bg-muted/50 text-primary">
                                <Type class="size-4" />
                            </div>
                            <h3 id="subject-title" class="text-sm font-semibold">Subject and Portal title</h3>
                            {#if conversionBlocked}<Badge variant="outline">Title discrepancy</Badge>{/if}
                        </div>
                        <span class="text-xs text-muted-foreground group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <div class="mt-3.5 space-y-3 pt-3 border-t border-border/60">
                        <label class="block space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Email subject and Portal title</span><input disabled={read_only} aria-label="Email subject and Portal title" class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm" value={composerState.subject ?? ''} oninput={(event) => updateTop('subject', event.currentTarget.value)} /></label>
                    </div>
                </details>

                <!-- Section: Email message body -->
                <details class="group rounded-xl border border-border bg-card p-4 transition-all" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg border border-border bg-muted/50 text-primary">
                                <AlignLeft class="size-4" />
                            </div>
                            <h3 id="email-body-title" class="text-sm font-semibold">Email message body</h3>
                        </div>
                        <span class="text-xs text-muted-foreground group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <div class="mt-3.5 space-y-3 pt-3 border-t border-border/60">
                        <div class="flex justify-end">
                            <button type="button" class="inline-flex min-h-8 items-center gap-1.5 rounded-md border px-2.5 text-xs font-medium hover:bg-muted" disabled={read_only || !endpoints.image_upload} onclick={() => { replaceIndex = null; imageFile?.click(); }}>Add image</button>
                        </div>
                        <MessageBody
                            text={emailBodyText}
                            {tokens}
                            readOnly={read_only}
                            onTextChange={updateEmailBodyText}
                            existingBlocks={blocks}
                            assets={ownedAssets}
                            onImageReplace={endpoints.image_upload ? (index) => { replaceIndex = index; imageFile?.click(); } : undefined}
                            onBlockRemove={removeBlock}
                            onAltChange={updateImageAlt}
                        />
                        <input bind:this={imageFile} class="sr-only" type="file" accept="image/png,image/jpeg" aria-label="Upload a PNG or JPEG image" onchange={(event) => uploadImage(event.currentTarget.files?.[0])} />
                        {#if imageBusy}<p role="status" class="text-xs text-muted-foreground">Uploading image…</p>{/if}
                        {#if uploadError}<div role="alert" class="space-y-2 text-xs text-destructive"><p>{uploadError}</p>{#if snapshot?.unknown_operation === 'image_upload'}<button type="button" class="min-h-8 rounded-md border px-2.5 text-xs" onclick={() => uploadImage()}>Retry same image operation</button>{/if}</div>{/if}
                    </div>
                </details>

                <!-- Section: Portal in-app notification -->
                <details class="group rounded-xl border border-border bg-card p-4 transition-all" open>
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg border border-border bg-muted/50 text-primary">
                                <Bell class="size-4" />
                            </div>
                            <h3 id="portal-caption-title" class="text-sm font-semibold">Portal in-app notification</h3>
                        </div>
                        <span class="text-xs text-muted-foreground group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <div class="mt-3.5 space-y-3 pt-3 border-t border-border/60">
                        <label class="block space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Portal caption</span><textarea disabled={read_only} aria-label="Portal caption" class="min-h-24 w-full rounded-md border border-input bg-background p-3 text-sm" maxlength="500" value={composerState.body ?? ''} oninput={(event) => updateTop('body', event.currentTarget.value)}></textarea><span class="flex items-center justify-between gap-2 text-xs text-muted-foreground"><span>Shown in applicant portal inbox</span><span>{(composerState.body ?? '').length}/500</span></span></label>
                    </div>
                </details>

                <!-- Section: Action button -->
                <details class="group rounded-xl border border-border bg-card p-4 transition-all">
                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md">
                        <div class="flex items-center gap-2.5">
                            <div class="flex size-7 items-center justify-center rounded-lg border border-border bg-muted/50 text-primary">
                                <ExternalLink class="size-4" />
                            </div>
                            <h3 id="message-action-heading" class="text-sm font-semibold">Action button</h3>
                            {#if actionEnabled}<Badge variant="success">Enabled</Badge>{/if}
                        </div>
                        <span class="text-xs text-muted-foreground group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <div class="mt-3.5 space-y-3 pt-3 border-t border-border/60">
                        {#if canonicalActionRoute}
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border border-border p-3 text-sm cursor-pointer"><input type="checkbox" class="size-4.5 rounded" disabled={read_only} checked={actionEnabled} onchange={(event) => toggleAction(event.currentTarget.checked)} /><span><span class="block font-medium">Include action button</span><span class="text-xs text-muted-foreground">Destination is fixed by this notice family.</span></span></label>
                            {#if actionEnabled}
                                <label class="block space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Action label</span><input disabled={read_only} aria-label="Shared action label" maxlength="64" required class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm" value={composerState.action_label ?? ''} oninput={(event) => { lastActionLabel = event.currentTarget.value; updateTop('action_label', event.currentTarget.value); }} /></label>
                                <label class="block space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Shared action destination</span><input disabled aria-label="Shared action destination" class="min-h-11 w-full rounded-md border border-input bg-muted px-3 text-sm" value={canonicalActionRoute ?? ''} /></label>
                            {:else}<p class="text-xs text-muted-foreground">No button is included in the email or portal card.</p>{/if}
                        {:else}<p class="rounded-md border border-muted p-3 text-xs text-muted-foreground">No canonical action destination is configured for this notice family.</p>{/if}
                    </div>
                </details>

                <!-- Section: Attachment (PDF document) -->
                {#if supports_pdf && family.supports_pdf}
                    <details class="group rounded-xl border border-border bg-card p-4 transition-all">
                        <summary class="flex cursor-pointer list-none items-center justify-between font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md">
                            <div class="flex items-center gap-2.5">
                                <div class="flex size-7 items-center justify-center rounded-lg border border-border bg-muted/50 text-primary">
                                    <Paperclip class="size-4" />
                                </div>
                                <h3 class="inline text-sm font-semibold">Attachment</h3> <span class="text-xs font-normal text-muted-foreground">(PDF document)</span>
                            </div>
                            <span class="text-xs text-muted-foreground group-open:rotate-180 transition-transform">▾</span>
                        </summary>
                        <div class="mt-3.5 space-y-4 pt-3 border-t border-border/60">
                            <p class="text-xs text-muted-foreground">{currentDocumentForPublish ? `Attachment: ${family.name} v${currentDocumentForPublish.version}` : 'Current saved attachment: none'}</p>
                            {#if pendingDocumentCandidateState && pendingDocumentCandidateState.id !== composerState.document_revision_id}
                                <div class="space-y-3 rounded-lg border border-primary/30 bg-primary/5 p-4" role="status">
                                    <p class="text-sm font-medium">Document version {pendingDocumentCandidateState.version} is ready.</p>
                                    <a class="text-xs text-primary underline underline-offset-4" href={pendingDocumentCandidateState.pdf_url} target="_blank" rel="noreferrer">Preview exact candidate version {pendingDocumentCandidateState.version}</a>
                                    <div class="flex flex-wrap gap-2"><button type="button" class="min-h-9 rounded-md bg-primary px-3 text-xs text-primary-foreground font-medium" disabled={busy || imageBusy || read_only} onclick={() => { pendingDocumentSelection = pendingDocumentCandidateState?.id ?? null; void attachPendingDocument(); }}>Attach this version</button><button type="button" class="min-h-9 rounded-md border px-3 text-xs font-medium" disabled={busy || imageBusy || read_only} onclick={keepCurrentAttachment}>Keep current attachment</button></div>
                                </div>
                            {/if}
                            <label class="block space-y-1.5"><span class="text-xs font-medium text-muted-foreground">Select an exact ready document version</span><select disabled={read_only} class="min-h-11 w-full rounded-md border border-input bg-background px-3 text-sm" aria-label="Attachment document version" value={pendingDocumentSelection ?? ''} onchange={(event) => pendingDocumentSelection = event.currentTarget.value ? Number(event.currentTarget.value) : null}><option value="">No document attached</option>{#if pendingDocumentCandidateState && !document_versions.some((version) => version.id === pendingDocumentCandidateState?.id)}<option value={pendingDocumentCandidateState.id}>{family.name} v{pendingDocumentCandidateState.version} · ready candidate</option>{/if}{#each document_versions as version (version.id)}<option value={version.id}>{family.name} v{version.version} ({version.status})</option>{/each}</select></label>
                            {#if pendingDocumentSelection !== composerState.document_revision_id}<button type="button" class="min-h-9 rounded-md border px-3 text-xs font-medium" disabled={busy || imageBusy || read_only} onclick={() => void attachPendingDocument()}>{pendingDocumentSelection === null ? 'Save no-attachment choice' : 'Attach this exact version'}</button>{/if}
                            <div class="flex flex-wrap gap-2">{#if currentDocumentForPublish?.pdf_url}<button type="button" class="min-h-9 rounded-md border px-3 text-xs font-medium" onclick={previewDocument} disabled={read_only}>Preview current attachment</button>{/if}{#if endpoints.document_edit}<button type="button" class="min-h-9 rounded-md border px-3 text-xs font-medium" disabled={busy || imageBusy || read_only} onclick={editDocument}>Edit document</button>{/if}</div>
                        </div>
                    </details>
                {/if}

                <!-- Section: Notice metadata & audience -->
                <details class="group rounded-xl border border-border/70 bg-muted/20 p-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between text-xs font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md">
                        <div class="flex items-center gap-2">
                            <Info class="size-3.5 text-muted-foreground" />
                            <span>Notice metadata &amp; audience</span>
                        </div>
                        <span class="text-xs text-muted-foreground group-open:rotate-180 transition-transform">▾</span>
                    </summary>
                    <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-2 pt-2 border-t border-border/40">
                        {#if family.description}<div><dt class="font-medium text-foreground">Description</dt><dd class="text-muted-foreground">{family.description}</dd></div>{/if}
                        {#if family.event_key}<div><dt class="font-medium text-foreground">Event key</dt><dd class="text-muted-foreground">{family.event_key}</dd></div>{/if}
                        {#if family.channels}<div><dt class="font-medium text-foreground">Channels</dt><dd class="text-muted-foreground">{family.channels.join(', ')}</dd></div>{/if}
                        <div class="text-muted-foreground sm:col-span-2">Recipient targeting is determined by the triggering workflow record.</div>
                    </dl>
                </details>
            </section>

            <!-- Section: Preview Pane -->
            <section class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-4 sm:p-5 md:sticky md:top-4 md:max-h-[calc(100dvh-12rem)] md:flex md:flex-col md:overflow-hidden" class:hidden={mobileTab === 'write'} aria-label="Message preview">
                <div class="flex shrink-0 flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold tracking-tight text-foreground">Preview</h2>
                    <div class="inline-flex rounded-lg border border-border bg-muted/40 p-1" role="group" aria-label="Preview channel">
                        <button
                            type="button"
                            aria-pressed={previewView === 'email'}
                            class={`inline-flex min-h-8 items-center gap-1.5 rounded-md px-3 text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${previewView === 'email' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            onclick={() => { previewView = 'email'; mobileTab = 'preview'; }}
                        >
                            <Mail class="size-3.5" />
                            <span>Email</span>
                        </button>
                        <button
                            type="button"
                            aria-pressed={previewView === 'portal'}
                            class={`inline-flex min-h-8 items-center gap-1.5 rounded-md px-3 text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${previewView === 'portal' ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                            onclick={() => { previewView = 'portal'; mobileTab = 'preview'; }}
                        >
                            <Bell class="size-3.5" />
                            <span>Portal</span>
                        </button>
                    </div>
                </div>
                <div class="min-h-0 flex-1 overflow-y-auto space-y-3">
                    <fieldset disabled={read_only} class="contents">
                        {#if endpoints.test_mail}
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/80 bg-muted/20 p-2.5">
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <span>Send test to:</span>
                                    <span class="rounded border border-border bg-background px-1.5 py-0.5 font-mono text-[11px] font-medium text-foreground">{staffEmail}</span>
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex min-h-8 items-center gap-1.5 rounded-md border border-border bg-background px-3 text-xs font-medium shadow-2xs hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-label="Send synthetic test email to verified staff address"
                                    disabled={read_only || busy || imageBusy || Boolean(snapshot?.unknown && snapshot?.unknown_operation !== 'test_mail') || (snapshot?.unknown_operation !== 'test_mail' && (!snapshot?.previews?.email || snapshot?.previews?.email.output_sha256 !== snapshot?.output_digests.email_sha256))}
                                    onclick={sendTestEmail}
                                >
                                    <Send class="size-3 text-primary" />
                                    <span>{snapshot?.unknown_operation === 'test_mail' || testMailUnknown ? 'Retry test email' : 'Send test email'}</span>
                                </button>
                            </div>
                        {/if}
                        {#if testMailReceipt}<p class="text-xs text-muted-foreground" role="status">Synthetic test receipt {testMailReceipt.test_send_id}: {testMailReceipt.status} to {testMailReceipt.recipient_masked}.</p>{/if}
                        {#if previewView === 'email'}
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="inline-flex items-center gap-1 rounded-lg border border-border bg-muted/40 p-1" role="group" aria-label="Email preview viewport width">
                                    <button
                                        type="button"
                                        class={`inline-flex min-h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${previewWidth === 390 ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                                        aria-pressed={previewWidth === 390}
                                        aria-label="390px"
                                        title="Phone (390px)"
                                        onclick={() => previewWidth = 390}
                                    >
                                        <Smartphone class="size-3.5" />
                                        <span class="text-[11px]">390px</span>
                                    </button>
                                    <button
                                        type="button"
                                        class={`inline-flex min-h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${previewWidth === 768 ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                                        aria-pressed={previewWidth === 768}
                                        aria-label="768px"
                                        title="Tablet (768px)"
                                        onclick={() => previewWidth = 768}
                                    >
                                        <Tablet class="size-3.5" />
                                        <span class="text-[11px]">768px</span>
                                    </button>
                                    <button
                                        type="button"
                                        class={`inline-flex min-h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${previewWidth === 1024 ? 'bg-background text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}`}
                                        aria-pressed={previewWidth === 1024}
                                        aria-label="1024px"
                                        title="Desktop (1024px)"
                                        onclick={() => previewWidth = 1024}
                                    >
                                        <Monitor class="size-3.5" />
                                        <span class="text-[11px]">1024px</span>
                                    </button>
                                </div>
                                {#if previewWidth !== 390}
                                    <button
                                        type="button"
                                        class="inline-flex min-h-8 items-center gap-1 rounded-md border border-border bg-background px-2.5 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground shadow-2xs"
                                        onclick={() => zoomMode = zoomMode === 'fit' ? '100%' : 'fit'}
                                        title="Toggle viewport scaling to fit pane"
                                    >
                                        {#if zoomMode === 'fit'}<ZoomOut class="size-3" /> Fit ({Math.round(previewScale * 100)}%){:else}<ZoomIn class="size-3" /> 100%{/if}
                                    </button>
                                {/if}
                            </div>
                            <div class="max-w-full overflow-x-auto overflow-y-hidden" aria-label={`Email preview at ${previewWidth}px CSS viewport`}>
                                <iframe title="Current production Email preview" width={previewWidth} sandbox="" referrerpolicy="no-referrer" class="h-full min-h-[30rem] rounded-lg border bg-white shadow-2xs" style={`width:${previewWidth}px;max-width:none;transform:scale(${previewScale});transform-origin:top left;transition:transform 0.2s ease;`} srcdoc={noticePreviewDocument(liveEmailHtml)}></iframe>
                            </div>
                        {:else}
                            <article class="mx-auto w-full max-w-xl space-y-4 rounded-xl border border-border bg-background p-5 shadow-2xs">
                                <div class="flex items-center gap-2 text-xs text-muted-foreground pb-2 border-b border-border/60">
                                    <Bell class="size-3.5 text-primary" />
                                    <span class="font-medium">Applicant Portal Notification Card</span>
                                </div>
                                <h3 class="text-lg font-semibold">{livePortal.title}</h3>
                                <p class="whitespace-pre-wrap text-sm text-foreground/90">{livePortal.body}</p>
                                {#if livePortal.action}
                                    <div class="pt-2">
                                        <span class="inline-flex min-h-9 items-center rounded-md bg-primary px-4 text-xs font-semibold text-primary-foreground shadow-xs">{livePortal.action.label}</span>
                                    </div>
                                {/if}
                            </article>
                        {/if}
                    </fieldset>
                </div>
            </section>
        </div>
        {#if !read_only}
            <footer data-notice-message-action-footer class="fixed bottom-0 left-0 right-0 z-20 border-t border-border bg-background/95 p-3 backdrop-blur sm:px-6 md:left-56">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-2">
                    <div class="flex shrink-0 items-center gap-2">
                        <button class="min-h-11 rounded-md border px-3 text-sm sm:px-4" disabled={busy || imageBusy || publicationUnknown || Boolean(snapshot?.unknown && snapshot?.unknown_operation !== 'draft')} onclick={saveDraft}>Save draft</button>
                        {#if snapshot?.draft}
                            <details class="relative">
                                <summary class="min-h-11 cursor-pointer list-none rounded-md border px-3 py-2 text-sm">More <span aria-hidden="true">v</span></summary>
                                <div class="absolute bottom-full left-0 z-30 mb-2 w-48 rounded-lg border border-border bg-background p-2 shadow-lg">
                                    <button type="button" class="min-h-11 w-full rounded-md px-3 text-left text-sm text-destructive hover:bg-muted" disabled={busy || imageBusy || Boolean(snapshot?.unknown && snapshot?.unknown_operation !== 'discard') || !(snapshot.draft.endpoints?.discard || endpoints.discard)} onclick={() => discardConfirm = true}>Discard draft</button>
                                </div>
                            </details>
                        {/if}
                    </div>
                    {#if (!checkingPreview && checksFresh) || snapshot?.unknown_operation === 'publish'}
                        <button type="button" class="min-h-11 flex-1 rounded-md bg-primary px-3 text-sm text-primary-foreground sm:flex-none sm:px-4" disabled={busy || imageBusy || !snapshot?.draft || !endpoints.publish || Boolean(snapshot?.unknown && snapshot?.unknown_operation !== 'publish')} onclick={(event) => reviewPublish(event.currentTarget)}>{publicationUnknown || snapshot?.unknown_operation === 'publish' ? 'Review publication outcome' : 'Review & publish'}</button>
                    {:else}
                        <button class="min-h-11 flex-1 rounded-md bg-primary px-3 text-sm text-primary-foreground sm:flex-none sm:px-4" disabled={busy || imageBusy || Boolean(snapshot?.unknown && snapshot?.unknown_operation !== 'draft')} onclick={checkPreview}>{checkingPreview ? 'Checking preview…' : 'Check preview'}</button>
                    {/if}
                </div>
            </footer>
        {/if}
    </div>
    {#if publishConfirm}
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4" role="presentation">
            <section bind:this={publishDialog} role="dialog" aria-modal="true" aria-label="Review and publish message?" aria-labelledby="publish-message-title" aria-describedby="publish-message-description" tabindex="-1" onkeydown={handlePublishDialogKeydown} class="w-full max-w-lg space-y-4 rounded-xl border border-border bg-background p-6 shadow-xl">
                <h2 id="publish-message-title" class="text-lg font-semibold">Review and publish message?</h2>
                <p id="publish-message-description" class="text-sm text-muted-foreground">This publishes an immutable message version for future occurrences only. It will not resend this notice or alter old deliveries.</p>
                <dl class="grid gap-3 rounded-lg border border-border bg-muted/20 p-3 text-sm sm:grid-cols-2">
                    <div><dt class="font-medium">Notice family</dt><dd>{family.name}</dd></div>
                    {#if family.description}<div><dt class="font-medium">Description</dt><dd>{family.description}</dd></div>{/if}
                    {#if family.event_key}<div><dt class="font-medium">Configured event</dt><dd>{family.event_key}</dd></div>{/if}
                    {#if family.channels}<div><dt class="font-medium">Supported channels</dt><dd>{family.channels.join(', ')}</dd></div>{/if}
                    <div><dt class="font-medium">Document</dt><dd>{currentDocumentForPublish ? `${family.name} version ${currentDocumentForPublish.version}` : 'None (no attachment)'}</dd></div>
                    <div class="sm:col-span-2"><dt class="font-medium">Current proofs</dt><dd>{emailPreviewFresh ? 'Email current' : 'Email stale'} · {portalPreviewFresh ? 'Portal current' : 'Portal stale'}{supportsAttachmentBinding ? ` · ${selectedAttachmentFresh ? 'attachment selection current' : 'attachment selection stale'}` : ''}</dd></div>
                    <div><dt class="font-medium">Recipient scope</dt><dd>Resolved per occurrence from the configured event; no targeting control is exposed.</dd></div>
                </dl>
                {#if publishConfirmationIdentity !== publishIdentity()}<p class="rounded-md border border-amber-500/40 bg-amber-500/5 p-3 text-sm" role="alert">The message, guards, selection, or proof changed while this review was open. Close this review and run Check preview again.</p>{/if}
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" data-publish-cancel class="min-h-11 rounded-md border px-4" disabled={busy} onclick={cancelPublish}>Cancel</button>
                    <button type="button" class="min-h-11 rounded-md bg-primary px-4 text-primary-foreground" disabled={busy || imageBusy || publishConfirmationIdentity !== publishIdentity() || (!checksFresh && snapshot?.unknown_operation !== 'publish')} onclick={() => void publishMessage()}>{snapshot?.unknown_operation === 'publish' ? 'Reconcile publication' : 'Publish message'}</button>
                </div>
            </section>
        </div>
    {/if}
    {#if discardConfirm}
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4">
            <div role="alertdialog" aria-modal="true" aria-labelledby="discard-message-title" aria-describedby="discard-message-description" class="w-full max-w-md space-y-4 rounded-xl border border-border bg-background p-6 shadow-xl">
                <h2 id="discard-message-title" class="text-lg font-semibold">Discard this message draft?</h2>
                <p id="discard-message-description" class="text-sm text-muted-foreground">The saved draft and unsaved edits will be discarded. Published message versions remain unchanged.</p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="min-h-11 rounded-md border px-4" disabled={busy || imageBusy} onclick={() => discardConfirm = false}>Keep editing</button>
                    <button type="button" class="min-h-11 rounded-md bg-destructive px-4 text-destructive-foreground" disabled={busy || imageBusy} onclick={() => { discardConfirm = false; void discardDraft(); }}>{snapshot?.unknown_operation === 'discard' ? 'Retry same discard operation' : 'Discard draft'}</button>
                </div>
            </div>
        </div>
    {/if}
    {#if reloadServerConfirm}
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4">
            <div role="alertdialog" aria-modal="true" aria-labelledby="reload-message-title" aria-describedby="reload-message-description" class="w-full max-w-md space-y-4 rounded-xl border border-border bg-background p-6 shadow-xl">
                <h2 id="reload-message-title" class="text-lg font-semibold">Reload the current server message?</h2>
                <p id="reload-message-description" class="text-sm text-muted-foreground">This replaces your local message text with the latest server version. It does not save or publish your local edits.</p>
                <div class="flex justify-end gap-3">
                    <button type="button" class="min-h-11 rounded-md border px-4" disabled={busy || imageBusy} onclick={() => reloadServerConfirm = false}>Keep my text</button>
                    <button type="button" class="min-h-11 rounded-md bg-destructive px-4 text-destructive-foreground" disabled={busy || imageBusy} onclick={() => void reloadServerMessage()}>Reload server message</button>
                </div>
            </div>
        </div>
    {/if}
    {#if leaveDialog}
        <div class="fixed inset-0 z-50 grid place-items-center bg-black/50 p-4">
            <div role="alertdialog" aria-modal="true" aria-labelledby="leave-composer-title" aria-describedby="leave-composer-description" class="w-full max-w-md space-y-4 rounded-xl border border-border bg-background p-6 shadow-xl">
                <h2 id="leave-composer-title" class="text-lg font-semibold">Keep editing this message?</h2>
                <p id="leave-composer-description" class="text-sm text-muted-foreground">Your unsaved or unconfirmed message remains here. Stay to keep editing, or leave without claiming it was saved.</p>
                <div class="flex justify-end gap-3"><button type="button" class="min-h-11 rounded-md border px-4" onclick={stayInComposer}>Stay</button><button type="button" class="min-h-11 rounded-md bg-destructive px-4 text-destructive-foreground" onclick={leaveComposer}>Leave</button></div>
            </div>
        </div>
    {/if}
</StaffLayout>
<style>
    @media (width < 64rem) {
        :global(body:has(footer[data-notice-message-action-footer]) details[data-xiam-assist-root]) {
            bottom: calc(5rem + env(safe-area-inset-bottom));
        }
    }
</style>
