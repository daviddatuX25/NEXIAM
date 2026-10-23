<script lang="ts">
    import { onDestroy, onMount, tick } from 'svelte';
    import type { AxiosInstance } from 'axios';
    import XiamDock from '@/Components/kit/Xiam/XiamDock.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
    import WayfindingEventBridge from '@/Components/Wayfinding/WayfindingEventBridge.svelte';
    import { http, Link, router, usePage } from '@inertiajs/svelte';
    import { createStaffGenerationLifecycle, installStaffGenerationGuard } from '@/lib/staff-account-generation';
    import {
        getPageGuideCoverage,
        resolvePageGuide,
        type PageGuideCoverageEntry,
    } from '@/lib/walkthrough/page-guides';
    import { anonymousWalkthroughScope } from '@/lib/walkthrough/preferences';
    import {
        walkthroughCoordinator,
        walkthroughLaunchBlocked,
        type WalkthroughProgressEnvelope,
        WALKTHROUGH_PLAYBACK_STORAGE_PREFIX,
        type WalkthroughPlaybackMode,
    } from '@/lib/walkthrough/coordinator';
    import type { TourDefinition } from '@/lib/walkthrough/types';
    import { hasTour } from '@/lib/walkthrough/tours';
    import { resolveTour } from '@/lib/walkthrough/resolver';

    let { App, appProps } = $props();
    const page = usePage();
    const workspace = $derived(page.props.practice_workspace ?? null);
    const progressEnvelope = $derived(
        (page.props.walkthroughProgress as WalkthroughProgressEnvelope | null | undefined) ?? null,
    );
    const componentName = $derived(page.component ?? '');
    const currentUrl = $derived(page.url ?? '/');
    let currentGuide = $state<TourDefinition | null>(null);
    let currentCoverage = $state<PageGuideCoverageEntry | null>(null);

    function pageProgressKey(pageComponent: string): string {
        return `page:${pageComponent}`;
    }

    const currentProgressPageKey = $derived(
        currentGuide ? pageProgressKey(currentCoverage?.componentName ?? componentName) : null,
    );
    let fallbackDialogOpen = $state(false);
    let registryVersion = $state(0);
    let safetyVersion = $state(0);
    let playbackMode = $state<WalkthroughPlaybackMode>(walkthroughCoordinator.getPlaybackMode());
    let pageGuideGeneration = 0;
    let activeGuideIdentity = '';
    let stopPendingWait: (() => void) | null = null;
    let pendingGuideAfterSafety: {
        guide: TourDefinition;
        pageComponent: string;
        pageUrl: string;
        pageKey: string;
        generation: number;
        scopeKey: string;
    } | null = null;

    const isPublicOrGuestPage = $derived(
        componentName === 'Home'
            || componentName.startsWith('Public/')
            || componentName === 'Applicant/Login'
            || componentName === 'Auth/Login'
    );

    async function launchFirstPageGuide(
        guide: TourDefinition,
        pageComponent: string,
        pageUrl: string,
        pageKey: string,
        generation: number,
        scopeKey: string,
    ): Promise<void> {
        await tick();
        await new Promise<void>((resolve) => window.setTimeout(resolve, 150));
        if (generation !== pageGuideGeneration || scopeKey !== walkthroughCoordinator.getScope().key) {
            return;
        }
        if (!walkthroughCoordinator.canAutomaticallyStart(pageKey, guide.revision)) {
            return;
        }
        const safe = await waitUntilWalkthroughSafe(generation, scopeKey);
        if (!safe) {
            if (
                generation === pageGuideGeneration
                && scopeKey === walkthroughCoordinator.getScope().key
                && walkthroughLaunchBlocked()
            ) {
                pendingGuideAfterSafety = { guide, pageComponent, pageUrl, pageKey, generation, scopeKey };
            }
            return;
        }
        if (generation !== pageGuideGeneration) {
            return;
        }
        const session = walkthroughCoordinator.beginAutomatic(pageKey, guide.revision, guide.id);
        if (!session) {
            return;
        }
        if (isPublicOrGuestPage) {
            return;
        }
        if (currentCoverage?.status === 'deferred') {
            // Deferred status means no automatic pop-up; guide remains accessible via the trigger glyph
            return;
        }
        const trigger = walkthroughCoordinator.matchingTrigger(guide.id, pageComponent, pageUrl);
        if (trigger) {
            trigger.open();
            return;
        }
        fallbackDialogOpen = true;
    }

    function waitUntilWalkthroughSafe(generation: number, scopeKey: string): Promise<boolean> {
        if (!walkthroughLaunchBlocked()) {
            return Promise.resolve(true);
        }
        stopPendingWait?.();

        return new Promise<boolean>((resolve) => {
            let settled = false;
            let pollTimer: number | undefined;
            let timeoutTimer: number | undefined;
            let observer: MutationObserver | null = null;

            const finish = (safe: boolean): void => {
                if (settled) {
                    return;
                }
                settled = true;
                if (pollTimer !== undefined) {
                    window.clearInterval(pollTimer);
                }
                if (timeoutTimer !== undefined) {
                    window.clearTimeout(timeoutTimer);
                }
                observer?.disconnect();
                document.removeEventListener('focusin', check, true);
                document.removeEventListener('focusout', check, true);
                document.removeEventListener('input', check, true);
                document.removeEventListener('change', check, true);
                if (stopPendingWait === cancel) {
                    stopPendingWait = null;
                }
                resolve(safe);
            };

            const check = (): void => {
                if (
                    generation !== pageGuideGeneration
                    || scopeKey !== walkthroughCoordinator.getScope().key
                ) {
                    finish(false);
                    return;
                }
                if (!walkthroughLaunchBlocked()) {
                    finish(true);
                }
            };

            const cancel = (): void => finish(false);
            stopPendingWait = cancel;
            document.addEventListener('focusin', check, true);
            document.addEventListener('focusout', check, true);
            document.addEventListener('input', check, true);
            document.addEventListener('change', check, true);
            if (document.body && typeof MutationObserver !== 'undefined') {
                observer = new MutationObserver(check);
                observer.observe(document.body, {
                    attributes: true,
                    attributeFilter: ['aria-modal', 'data-camera-active', 'data-walkthrough-blocker', 'hidden', 'open', 'role'],
                    childList: true,
                    subtree: true,
                });
            }
            pollTimer = window.setInterval(check, 250);
            timeoutTimer = window.setTimeout(() => finish(false), 120_000);
            check();
        });
    }


    function resumeQueuedGuideWhenSafe(): void {
        safetyVersion += 1;
        const pendingGuide = pendingGuideAfterSafety;
        if (!pendingGuide || walkthroughLaunchBlocked()) {
            return;
        }
        if (
            pendingGuide.generation !== pageGuideGeneration
            || pendingGuide.scopeKey !== walkthroughCoordinator.getScope().key
        ) {
            pendingGuideAfterSafety = null;
            return;
        }
        pendingGuideAfterSafety = null;
        void launchFirstPageGuide(
            pendingGuide.guide,
            pendingGuide.pageComponent,
            pendingGuide.pageUrl,
            pendingGuide.pageKey,
            pendingGuide.generation,
            pendingGuide.scopeKey,
        );
    }

    $effect(() => {
        const csrfToken = page.props.csrf_token;
        walkthroughCoordinator.setCsrfToken(
            typeof csrfToken === 'string' ? csrfToken : null,
        );
        const envelope = progressEnvelope;
        walkthroughCoordinator.configure(envelope);

        const pageComponent = componentName;
        const pageUrl = currentUrl;
        const guide = resolvePageGuide(pageComponent, pageUrl, page.props);
        const coverage = getPageGuideCoverage(pageComponent, pageUrl) ?? null;
        const eligibleGuide = coverage?.status === 'excluded' ? null : guide ?? null;
        currentGuide = eligibleGuide;
        currentCoverage = coverage;
        walkthroughCoordinator.setCurrentPage(
            eligibleGuide ? pageProgressKey(coverage?.componentName ?? pageComponent) : null,
            eligibleGuide?.revision ?? 1,
        );

        const scopeKey = envelope?.scope.key ?? anonymousWalkthroughScope().key;
        const pageKey = eligibleGuide ? pageProgressKey(coverage?.componentName ?? pageComponent) : null;
        const identity = pageKey
            ? `${scopeKey}:${pageKey}:r${eligibleGuide?.revision ?? 1}:${playbackMode}`
            : `${scopeKey}:none:${pageComponent}:${playbackMode}`;
        if (identity === activeGuideIdentity) {
            return;
        }
        activeGuideIdentity = identity;
        pageGuideGeneration += 1;
        pendingGuideAfterSafety = null;
        fallbackDialogOpen = false;
        if (!isPublicOrGuestPage && eligibleGuide && pageKey) {
            void launchFirstPageGuide(
                eligibleGuide,
                pageComponent,
                pageUrl,
                pageKey,
                pageGuideGeneration,
                scopeKey,
            );
        }
    });


    onMount(() => {
        let disposed = false;
        let safetyQueued = false;
        const handleSafetyEvent = (): void => {
            if (safetyQueued || disposed) return;
            safetyQueued = true;
            queueMicrotask(() => {
                safetyQueued = false;
                if (!disposed) resumeQueuedGuideWhenSafe();
            });
        };
        const unsubscribeCoordinator = walkthroughCoordinator.subscribe(() => {
            registryVersion += 1;
            playbackMode = walkthroughCoordinator.getPlaybackMode();
            handleSafetyEvent();
        });
        const handleStorage = (event: StorageEvent): void => {
            if (event.key === null || event.key.startsWith(WALKTHROUGH_PLAYBACK_STORAGE_PREFIX)) {
                walkthroughCoordinator.refreshPlaybackMode();
            }
            if (event.key === null || event.key === 'nexiam:walkthrough:v2:cache') {
                walkthroughCoordinator.refreshTerminalProgress();
                safetyVersion += 1;
            }
        };
        const observer = typeof MutationObserver === 'undefined' || !document.body
            ? null
            : new MutationObserver(handleSafetyEvent);

        document.addEventListener('focusin', handleSafetyEvent, true);
        document.addEventListener('focusout', handleSafetyEvent, true);
        document.addEventListener('input', handleSafetyEvent, true);
        document.addEventListener('change', handleSafetyEvent, true);
        document.addEventListener('pause', handleSafetyEvent, true);
        document.addEventListener('ended', handleSafetyEvent, true);
        document.addEventListener('emptied', handleSafetyEvent, true);
        document.addEventListener('loadedmetadata', handleSafetyEvent, true);
        if (observer && document.body) {
            observer.observe(document.body, {
                attributes: true,
                attributeFilter: ['aria-modal', 'data-camera-active', 'data-walkthrough-blocker', 'hidden', 'open', 'role'],
                childList: true,
                subtree: true,
            });
        }
        window.addEventListener('storage', handleStorage);
        registryVersion += 1;
        safetyVersion += 1;

        return () => {
            disposed = true;
            unsubscribeCoordinator();
            observer?.disconnect();
            document.removeEventListener('focusin', handleSafetyEvent, true);
            document.removeEventListener('focusout', handleSafetyEvent, true);
            document.removeEventListener('input', handleSafetyEvent, true);
            document.removeEventListener('change', handleSafetyEvent, true);
            document.removeEventListener('pause', handleSafetyEvent, true);
            document.removeEventListener('ended', handleSafetyEvent, true);
            document.removeEventListener('emptied', handleSafetyEvent, true);
            document.removeEventListener('loadedmetadata', handleSafetyEvent, true);
            window.removeEventListener('storage', handleStorage);
        };
    });

    const initialGeneration =
        typeof window !== 'undefined'
            && typeof page.props.auth?.staff_inactivity_generation === 'string'
            && page.props.auth.staff_inactivity_generation.trim() !== ''
            ? page.props.auth.staff_inactivity_generation
            : null;

    const generationLifecycle = createStaffGenerationLifecycle(
        initialGeneration,
        (generation) => installStaffGenerationGuard({
            generation,
            router,
            axios: (window as Window & { axios?: AxiosInstance }).axios,
        }),
        (path) => window.location.replace(path),
    );
    const removeGenerationResponseHandler = http.onResponse((response) => {
        generationLifecycle.updateFromInertiaResponse(response.data);
        return response;
    });


    $effect(() => {
        const value = page.props.auth?.staff_inactivity_generation;
        generationLifecycle.update(
            typeof value === 'string' && value.trim() !== '' ? value : null,
        );
    });

    onDestroy(() => {
        stopPendingWait?.();
        removeGenerationResponseHandler();
        generationLifecycle.dispose();
    });

</script>

{#if workspace}
    <aside class="practice-workspace-context border-b border-border bg-muted px-4 py-2 text-sm text-foreground print:hidden" aria-label="Practice workspace context">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-3 gap-y-1">
            <span class="font-medium">Practice workspace</span>
            <span class="min-w-0 break-words text-muted-foreground">{workspace.label} · {workspace.purpose}</span>
            {#if workspace.leave_url}
                <Link href={workspace.leave_url} class="inline-flex min-h-11 items-center text-primary underline underline-offset-4 hover:text-primary/80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Leave practice workspace</Link>
            {:else if workspace.leave_note}
                <span class="text-muted-foreground">{workspace.leave_note}</span>
            {/if}
        </div>
    </aside>
{/if}
<App {...appProps} />
<WayfindingEventBridge />
{#if fallbackDialogOpen && currentGuide}
    <XiamNpcDialog
        tourKey={currentGuide.id}
        pageKey={currentProgressPageKey ?? pageProgressKey(componentName)}
        definition={currentGuide}
        bind:open={fallbackDialogOpen}
    />
{/if}
<XiamDock />
