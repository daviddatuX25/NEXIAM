<script>
    import { onDestroy } from 'svelte';
    import { X } from 'lucide-svelte';
    import OmrDiagnosticEvidencePanel from './OmrDiagnosticEvidencePanel.svelte';

    let {
        open = $bindable(false),
        page = null,
        labels = {},
        returnUrl = null,
        loading = false,
        error = null,
        onRetry = null,
        onPageChange = null,
        onPerPageChange = null,
        onGroupChange = null,
        onFilterChange = null,
        onRowOpen = null,
        onTabChange = null,
        onViewChange = null,
        initialTab = 'aligned',
        contextSummary = null,
        title = 'Evidence',
        subtitle = 'Read-only diagnostic evidence',
        onClose = null,
        onDirtyChange = null,
        onSaveDecision = null,
        onSaveAndNext = null,
        reviewable = false,
        blind = false,
        reviewReasons = [],
        onDirtyNavigation = null,
        captureReviewRequired = false,
        canAcceptReview = false,
        captureReviewBusy = false,
        onCaptureDecision = null,
    } = $props();

    let dialogEl = $state(null);
    let closeButton = $state(null);
    let previousFocus = null;
    let previousOverflow = '';
    let dirty = $state(false);
    let captureReviewNote = $state('');
    let reviewRailExpanded = $state(false);
    let lastContentScrollTop = 0;
    let touchStartY = null;

    function handleContentWheel(event) {
        if (typeof window === 'undefined' || window.innerWidth >= 1024) return;
        if (event.deltaY > 12) reviewRailExpanded = true;
        if (event.deltaY < -12 && event.currentTarget.scrollTop < 8) {
            reviewRailExpanded = false;
        }
    }

    function handleContentTouchStart(event) {
        touchStartY = event.touches?.[0]?.clientY ?? null;
    }

    function handleContentTouchMove(event) {
        if (
            touchStartY === null ||
            typeof window === 'undefined' ||
            window.innerWidth >= 1024
        ) {
            return;
        }
        const currentY = event.touches?.[0]?.clientY;
        if (typeof currentY !== 'number') return;
        const distance = touchStartY - currentY;
        if (distance > 24) {
            reviewRailExpanded = true;
            touchStartY = currentY;
        } else if (distance < -24 && event.currentTarget.scrollTop < 8) {
            reviewRailExpanded = false;
            touchStartY = currentY;
        }
    }

    function handleContentScroll(event) {
        if (typeof window === 'undefined' || window.innerWidth >= 1024) return;
        const scrollTop = event.currentTarget.scrollTop;
        if (scrollTop > lastContentScrollTop + 6 && scrollTop > 24) {
            reviewRailExpanded = true;
        } else if (scrollTop < lastContentScrollTop - 6 && scrollTop < 8) {
            reviewRailExpanded = false;
        }
        lastContentScrollTop = scrollTop;
    }
    let captureReviewError = $state(null);

    async function submitCaptureDecision(decision) {
        if (captureReviewBusy || !onCaptureDecision) return;
        if (
            decision === 'pass' &&
            (!canAcceptReview || loading || dirty)
        )
            return;
        captureReviewError = null;
        try {
            const saved = await onCaptureDecision(
                decision,
                captureReviewNote.trim(),
            );
            if (saved !== false) captureReviewNote = '';
        } catch (error) {
            captureReviewError =
                error?.message ?? 'The test review could not be completed.';
        }
    }

    function requestRecapture() {
        submitCaptureDecision('fail');
    }

    function portal(node) {
        document.body.appendChild(node);

        return {
            destroy() {
                node.remove();
            },
        };
    }

    function focusableElements() {
        if (!dialogEl) return [];
        return [
            ...dialogEl.querySelectorAll(
                'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ),
        ].filter(
            (element) =>
                element instanceof HTMLElement && element.offsetParent !== null,
        );
    }

    function close() {
        if (dirty) {
            if (onDirtyNavigation) {
                const allowClose = onDirtyNavigation({
                    kind: 'close',
                    label: 'closing the evidence dialog',
                });
                if (allowClose !== true) return;
            } else if (
                typeof window !== 'undefined' &&
                !window.confirm(
                    labels.unsavedCloseMessage ??
                        'Discard the unsaved item review decision?',
                )
            ) {
                return;
            }
        }
        open = false;
        onClose?.();
    }

    function handleDirtyChange(value) {
        dirty = Boolean(value);
        onDirtyChange?.(dirty);
    }

    function onBackdropClick(event) {
        if (event.target === event.currentTarget) close();
    }

    function onKeydown(event) {
        if (!open) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const elements = focusableElements();
        if (elements.length === 0) {
            event.preventDefault();
            dialogEl?.focus();
            return;
        }
        const first = elements[0];
        const last = elements.at(-1);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last?.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first?.focus();
        }
    }

    $effect(() => {
        if (!open || typeof document === 'undefined') return;
        previousFocus = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        queueMicrotask(() => closeButton?.focus());
        window.addEventListener('keydown', onKeydown);
        return () => {
            window.removeEventListener('keydown', onKeydown);
            document.body.style.overflow = previousOverflow;
            if (
                !open &&
                previousFocus instanceof HTMLElement &&
                document.contains(previousFocus)
            )
                previousFocus.focus();
        };
    });

    onDestroy(() => {
        if (typeof document !== 'undefined') {
            document.body.style.overflow = previousOverflow;
            if (
                previousFocus instanceof HTMLElement &&
                document.contains(previousFocus)
            )
                previousFocus.focus();
        }
    });
</script>

{#if open}
    <div
        use:portal
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/65 p-0 backdrop-blur-sm sm:p-4 md:p-6"
        role="presentation"
        onclick={onBackdropClick}
    >
        <div
            bind:this={dialogEl}
            class="border-border bg-background text-foreground flex h-dvh max-h-dvh w-full min-w-0 flex-col overflow-hidden border shadow-2xl outline-none sm:h-auto sm:max-h-[min(94vh,1080px)] sm:w-[min(98vw,1600px)] sm:rounded-xl"
            role="dialog"
            aria-modal="true"
            aria-labelledby="omr-evidence-modal-title"
            aria-describedby="omr-evidence-modal-subtitle"
            tabindex="-1"
        >
            <header
                class="border-border bg-card flex shrink-0 items-center justify-between gap-3 border-b px-3 py-2 sm:px-4"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <div class="min-w-0">
                        <h2
                            id="omr-evidence-modal-title"
                            class="truncate text-sm font-bold"
                        >
                            {title}
                        </h2>
                        <p
                            id="omr-evidence-modal-subtitle"
                            class="text-muted-foreground hidden truncate text-[11px] sm:block"
                        >
                            {subtitle}
                        </p>
                    </div>
                </div>
                <button
                    bind:this={closeButton}
                    type="button"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex size-9 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
                    aria-label={labels.closeLabel ?? 'Close evidence'}
                    onclick={close}
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </header>

            <div
                onscroll={handleContentScroll}
                onwheel={handleContentWheel}
                ontouchstart={handleContentTouchStart}
                ontouchmove={handleContentTouchMove}
                class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-2 sm:p-4"
            >
                <OmrDiagnosticEvidencePanel
                    {page}
                    {labels}
                    {returnUrl}
                    {loading}
                    {error}
                    {onRetry}
                    {onPageChange}
                    {onPerPageChange}
                    {onGroupChange}
                    {onFilterChange}
                    {onRowOpen}
                    {onTabChange}
                    {onViewChange}
                    {initialTab}
                    {contextSummary}
                    bind:mobileRailExpanded={reviewRailExpanded}
                    {reviewable}
                    {blind}
                    {reviewReasons}
                    {onSaveDecision}
                    {onSaveAndNext}
                    onDirtyChange={handleDirtyChange}
                    {onDirtyNavigation}
                    idPrefix="omr-evidence-modal"
                />
            </div>

            {#if captureReviewRequired && onCaptureDecision}
                <footer
                    class="border-border bg-card shrink-0 border-t px-3 py-2 sm:px-4"
                    aria-label="Review capture decision"
                >
                    {#if canAcceptReview}
                        <p class="text-muted-foreground mx-auto mb-2 max-w-4xl text-xs">
                            All flagged items have been reviewed. Accept this capture as the page's passing result, or request a recapture if preferred.
                        </p>
                    {/if}
                    <div class="mx-auto flex max-w-4xl items-end gap-2">
                        <label
                            class="min-w-0 flex-1 text-[10px] font-semibold"
                            for="omr-evidence-capture-review-note"
                        >
                            Review note <span class="text-muted-foreground"
                                >(optional)</span
                            >
                            <input
                                id="omr-evidence-capture-review-note"
                                bind:value={captureReviewNote}
                                maxlength="500"
                                placeholder="Only what the next reviewer needs"
                                class="border-input bg-background focus-visible:ring-ring mt-1 min-h-9 w-full rounded-md border px-2.5 text-xs font-normal focus-visible:ring-2 focus-visible:outline-none"
                            />
                        </label>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button
                                type="button"
                                class="border-border bg-background hover:bg-muted focus-visible:ring-ring inline-flex min-h-9 flex-1 items-center justify-center rounded-md border px-3 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 sm:flex-none"
                                disabled={captureReviewBusy}
                                onclick={requestRecapture}
                            >
                                Request recapture
                            </button>
                            {#if canAcceptReview}
                                <button
                                    type="button"
                                    class="focus-visible:ring-ring inline-flex min-h-9 flex-1 items-center justify-center rounded-md bg-emerald-700 px-3 text-xs font-semibold text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40 sm:flex-none"
                                    disabled={loading || captureReviewBusy || dirty}
                                    onclick={() => submitCaptureDecision('pass')}
                                >
                                    Complete review &amp; accept
                                </button>
                            {/if}
                        </div>
                    </div>
                    {#if captureReviewError}
                        <p
                            class="text-destructive mx-auto mt-2 max-w-4xl text-xs"
                            role="alert"
                        >
                            {captureReviewError}
                        </p>
                    {/if}
                </footer>
            {/if}
        </div>
    </div>
{/if}

<style>
    @media (prefers-reduced-motion: reduce) {
        :global([role='dialog']) {
            scroll-behavior: auto !important;
        }
    }
</style>
