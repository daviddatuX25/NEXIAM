<script module>
    let consumedFlash = null;
    let consumedFlashToken = null;
    let nextFlashSequence = 0;
    let restoringFromHistory = false;
    const ignoredErrorVisitIds = new Set();
    const wrappedVisits = new WeakSet();
    const handledPages = new WeakSet();
    const pendingFlashVisits = new Map();
</script>

<script>
    /**
     * Flash → toast bridge (Inertia Svelte 3 safe).
     *
     * Inertia `page` is $state via usePage() — not a classic store.
     * Do not use page.subscribe().
     */
    import { onMount, untrack } from 'svelte';
    import {
        toasts,
        dismiss,
        success,
        error,
        info,
        warning,
    } from '@/lib/toast.js';
    import { fly } from 'svelte/transition';
    import { router, usePage } from '@inertiajs/svelte';
    import {
        AlertTriangle,
        CheckCircle,
        Info,
        X,
        XCircle,
    } from 'lucide-svelte';

    let { placement = 'viewport' } = $props();

    const page = usePage();
    const toastPlacement = $derived(
        placement === 'below-anchor' ? 'below-anchor' : 'viewport',
    );

    onMount(() => {
        const isFlashVisit = (visit) =>
            !visit?.poll &&
            !visit?.deferredProps &&
            !visit?.prefetch &&
            (!Array.isArray(visit?.only) ||
                visit.only.length === 0 ||
                visit.only.includes('flash')) &&
            (!Array.isArray(visit?.except) || !visit.except.includes('flash'));

        const isPreservedErrorVisit = (visit) =>
            Boolean(
                visit?.poll ||
                    visit?.preserveErrors ||
                    visit?.deferredProps ||
                    visit?.prefetch,
            );

        const applyVisitFlashPolicy = (incomingPage, isFreshFlash) => {
            const incomingFlash = incomingPage?.props?.flash;
            if (!incomingFlash || typeof incomingFlash !== 'object') {
                return;
            }
            if (isFreshFlash) {
                incomingPage.props.flash = {
                    ...incomingFlash,
                    __flashToken: `flash-${++nextFlashSequence}`,
                };
            } else if (consumedFlashToken !== null) {
                incomingFlash.__flashToken = consumedFlashToken;
            }
        };

        const removeStart = router?.on?.('start', (event) => {
            const visit = event?.detail?.visit;
            if (!visit || typeof visit !== 'object') {
                return;
            }
            const flashVisit = isFlashVisit(visit);
            if (flashVisit) {
                restoringFromHistory = false;
            }
            if (visit.id && isPreservedErrorVisit(visit)) {
                ignoredErrorVisitIds.add(visit.id);
            }
            const visitKey = visit.id ?? visit;
            pendingFlashVisits.set(visitKey, flashVisit);

            if (!wrappedVisits.has(visit)) {
                wrappedVisits.add(visit);
                const originalOnBeforeUpdate =
                    typeof visit.onBeforeUpdate === 'function'
                        ? visit.onBeforeUpdate
                        : null;
                visit.onBeforeUpdate = (incomingPage) => {
                    pendingFlashVisits.delete(visitKey);
                    if (incomingPage && typeof incomingPage === 'object') {
                        handledPages.add(incomingPage);
                        applyVisitFlashPolicy(incomingPage, flashVisit);
                    }
                    return originalOnBeforeUpdate?.(incomingPage);
                };
            }
        });

        const removeBeforeUpdate = router?.on?.('beforeUpdate', (event) => {
            const incomingPage = event?.detail?.page;
            if (!incomingPage || typeof incomingPage !== 'object') {
                return;
            }
            if (handledPages.has(incomingPage)) {
                return;
            }
            let flashVisit = false;
            if (pendingFlashVisits.size > 0) {
                const [firstKey, firstVal] = pendingFlashVisits
                    .entries()
                    .next().value;
                pendingFlashVisits.delete(firstKey);
                flashVisit = firstVal;
            }
            applyVisitFlashPolicy(incomingPage, flashVisit);
        });

        const removeFinish = router?.on?.('finish', (event) => {
            const visit = event?.detail?.visit;
            const visitId = visit?.id;
            if (visitId) {
                ignoredErrorVisitIds.delete(visitId);
                pendingFlashVisits.delete(visitId);
            }
            if (visit) {
                pendingFlashVisits.delete(visit);
            }
        });

        const removeError = router?.on?.('error', (event) => {
            const visitId = event?.detail?.visitId;
            if (visitId && ignoredErrorVisitIds.has(visitId)) {
                return;
            }
            const messages = new Set();
            const collect = (value) => {
                if (typeof value === 'string' && value.trim()) {
                    messages.add(value.trim());
                } else if (value && typeof value === 'object') {
                    Object.values(value).forEach(collect);
                }
            };
            collect(event?.detail?.errors);
            if (messages.size > 0) {
                error([...messages].join('\n'));
            }
        });

        const handlePopstate = (event) => {
            if (event?.state?.page) {
                restoringFromHistory = true;
            }
        };
        if (typeof window !== 'undefined') {
            window.addEventListener('popstate', handlePopstate);
        }

        return () => {
            removeStart?.();
            removeBeforeUpdate?.();
            removeFinish?.();
            removeError?.();
            if (typeof window !== 'undefined') {
                window.removeEventListener('popstate', handlePopstate);
            }
        };
    });

    $effect(() => {
        const flash = page.props?.flash;
        if (restoringFromHistory) {
            consumedFlash = flash;
            consumedFlashToken =
                flash?.__flashToken ?? `flash-${++nextFlashSequence}`;
            restoringFromHistory = false;
            return;
        }
        const token = flash?.__flashToken ?? null;
        if (
            flash === consumedFlash ||
            (token !== null && token === consumedFlashToken)
        ) {
            return;
        }
        consumedFlash = flash;
        consumedFlashToken = token ?? `flash-${++nextFlashSequence}`;
        if (!flash) {
            return;
        }

        const dispatchFlashToast = (field, value) => {
            if (typeof value !== 'string' || value.trim() === '') {
                return;
            }


            untrack(() => {
                if (field === 'status') {
                    if (/\b(denied|cannot|could not|failed|error|not permitted)\b/i.test(value)) {
                        error(value);
                    } else {
                        success(value);
                    }
                } else if (field === 'message' || field === 'success') {
                    success(value);
                } else if (field === 'warning') {
                    warning(value);
                } else if (field === 'error') {
                    error(value);
                }
            });
        };

        dispatchFlashToast('status', flash.status);
        dispatchFlashToast('message', flash.message);
        dispatchFlashToast('success', flash.success);
        dispatchFlashToast('warning', flash.warning);
        dispatchFlashToast('error', flash.error);
    });

</script>

<div
    class={`toast-manager pointer-events-none z-[99999] flex flex-col gap-2 ${toastPlacement === 'below-anchor' ? 'toast-placement-below-anchor' : 'toast-placement-viewport'}`}
    data-toast-placement={toastPlacement}
>
    {#each $toasts as toast (toast.id)}
        <div
            class="toast-{toast.type} pointer-events-auto flex min-w-0 items-start gap-3 rounded-lg border px-4 py-3 shadow-xl backdrop-blur-sm transition-all duration-200"
            transition:fly={{ y: 8, duration: 200 }}
            role={toast.type === 'warning' || toast.type === 'error'
                ? 'alert'
                : 'status'}
            aria-live={toast.type === 'warning' || toast.type === 'error'
                ? 'assertive'
                : 'polite'}
            aria-atomic="true"
        >
            <div class="shrink-0">
                {#if toast.type === 'success'}
                    <CheckCircle
                        class="text-success size-5"
                        aria-hidden="true"
                    />
                {:else if toast.type === 'error'}
                    <XCircle
                        class="text-destructive size-5"
                        aria-hidden="true"
                    />
                {:else if toast.type === 'warning'}
                    <AlertTriangle
                        class="text-warning-foreground size-5"
                        aria-hidden="true"
                    />
                {:else}
                    <Info class="text-info size-5" aria-hidden="true" />
                {/if}
            </div>

            <p class="text-foreground min-w-0 flex-1 text-sm font-medium">
                {toast.message}
            </p>

            <button
                type="button"
                class="hover:bg-accent focus:ring-ring flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-md transition-colors focus:ring-2 focus:outline-none"
                onclick={() => dismiss(toast.id)}
                aria-label="Dismiss notification"
                title="Dismiss notification"
            >
                <X class="text-muted-foreground size-4" aria-hidden="true" />
            </button>
        </div>
    {/each}
</div>

<style>
    .toast-manager {
        max-width: calc(100vw - 2rem);
    }

    .toast-placement-viewport {
        position: fixed;
        inset-block-start: max(1rem, env(safe-area-inset-top));
        inset-inline-end: max(1rem, env(safe-area-inset-right));
        width: min(24rem, calc(100vw - 2rem));
    }

    .toast-placement-below-anchor {
        position: absolute;
        inset-block-start: calc(100% + 0.75rem);
        inset-inline-end: max(1rem, env(safe-area-inset-right));
        width: min(24rem, calc(100vw - 2rem));
    }

    .toast-success {
        background-color: color-mix(in srgb, var(--success) 14%, var(--card-solid, var(--background)));
        border-color: color-mix(in srgb, var(--success) 35%, var(--border));
    }

    .toast-error {
        background-color: color-mix(
            in srgb,
            var(--destructive) 14%,
            var(--card-solid, var(--background))
        );
        border-color: color-mix(in srgb, var(--destructive) 35%, var(--border));
    }

    .toast-warning {
        background-color: color-mix(in srgb, var(--warning) 18%, var(--card-solid, var(--background)));
        border-color: color-mix(in srgb, var(--warning) 40%, var(--border));
    }

    .toast-info {
        background-color: color-mix(
            in srgb,
            var(--info, var(--primary)) 14%,
            var(--card-solid, var(--background))
        );
        border-color: color-mix(
            in srgb,
            var(--info, var(--primary)) 35%,
            var(--border)
        );
    }

    @media (max-width: 639px) {
        .toast-placement-viewport,
        .toast-placement-below-anchor {
            position: fixed;
            inset-block-start: max(1rem, env(safe-area-inset-top));
            inset-inline-start: max(1rem, env(safe-area-inset-left));
            inset-inline-end: max(1rem, env(safe-area-inset-right));
            width: auto;
            z-index: 99999;
        }
    }
</style>
