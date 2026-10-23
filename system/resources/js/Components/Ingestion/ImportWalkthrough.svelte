<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { usePage } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import XiamAvatar from '@/Components/kit/Xiam/XiamAvatar.svelte';
    import { getPageGuideCoverage } from '@/lib/walkthrough/page-guides';
    import { getTour } from '@/lib/walkthrough/tours';
    import type { DataIngestionContext } from '@/lib/walkthrough/types';
    import {
        claimWalkthroughDialog,
        createWalkthroughDialogId,
        releaseWalkthroughDialog,
        walkthroughCoordinator,
        walkthroughLaunchBlocked,
    } from '@/lib/walkthrough/coordinator';
    import { ChevronLeft, ChevronRight, HelpCircle, X } from 'lucide-svelte';

    let {
        context = {},
        class: className = '',
    }: {
        context?: Partial<DataIngestionContext>;
        class?: string;
    } = $props();
    const headingId = $props.id();
    let open = $state(false);
    let stepId = $state('welcome');
    let heading = $state<HTMLHeadingElement | null>(null);
    let trigger = $state<HTMLButtonElement | null>(null);
    let root = $state<HTMLElement | null>(null);
    let activeSessionToken = $state<string | null>(null);
    let activeInstanceKey = $state('');
    let page: any = undefined;
    try {
        page = usePage();
    } catch {
        // The inline import guide remains usable outside an Inertia mount.
    }
    const instanceId = createWalkthroughDialogId();
    const progressPageKey = $derived(
        page && getPageGuideCoverage(page.component, page.url)?.tourKey === 'data-ingestion'
            ? `page:${page.component}`
            : 'tour:data-ingestion',
    );
    const resolvedContext = $derived({
        entityKey: 'imports',
        safetyKey: 'current',
        freshness: 'current' as const,
        activePreset: '',
        hasAcademicCycle: false,
        academicCycleLabel: null,
        hasSelectedFile: false,
        isAnalyzing: false,
        hasAnalysisResult: false,
        detectedSheetsCount: 0,
        recentBatchesCount: 0,
        pendingReviewCount: 0,
        ...context,
    });
    const tour = $derived(getTour('data-ingestion', resolvedContext));
    const stepIndex = $derived(
        Math.max(0, tour?.steps.findIndex((step) => step.id === stepId) ?? 0),
    );
    const step = $derived(tour?.steps[stepIndex]);
    $effect(() => {
        if (!open || !tour) {
            return;
        }
        if (
            tour.instanceKey !== activeInstanceKey
            || !tour.steps.some((candidate) => candidate.id === stepId)
        ) {
            activeInstanceKey = tour.instanceKey;
            const cursor = walkthroughCoordinator.activeForTour('data-ingestion')?.cursor;
            stepId = cursor && tour.steps.some((candidate) => candidate.id === cursor)
                ? cursor
                : tour.start;
        }
    });

    $effect(() => {
        if (!open || !step?.target || !root) {
            return;
        }
        const scope = root.closest('[role="dialog"]') ?? document;
        const candidates = scope.querySelectorAll<HTMLElement>(
            `[data-tour="${step.target.dataTour}"]`,
        );
        const target = Array.from(candidates).find(
            (element) => element.getClientRects().length > 0,
        );
        if (!target) {
            return;
        }
        target.setAttribute('data-import-guide-active', 'true');
        return () => target.removeAttribute('data-import-guide-active');
    });

    $effect(() => {
        if (!page || !trigger) {
            return;
        }
        return walkthroughCoordinator.registerTrigger({
            id: instanceId,
            tourKey: 'data-ingestion',
            componentName: page.component,
            url: page.url,
            element: trigger,
            open: () => {
                void start();
            },
        });
    });

    onMount(() => {
        const cancelStaleGuide = (event: Event): void => {
            const token = (event as CustomEvent<{ token?: string }>).detail?.token;
            if (!token || token !== activeSessionToken) {
                return;
            }
            activeSessionToken = null;
            activeInstanceKey = '';
            open = false;
            releaseWalkthroughDialog(instanceId);
            void tick().then(() => trigger?.focus());
        };
        window.addEventListener('nexiam:walkthrough:cancel', cancelStaleGuide);
        return () => {
            window.removeEventListener('nexiam:walkthrough:cancel', cancelStaleGuide);
            releaseWalkthroughDialog(instanceId);
        };
    });

    async function start(): Promise<void> {
        if (open || !tour || walkthroughLaunchBlocked(root?.closest('[role="dialog"][aria-modal="true"]') ?? null)) {
            return;
        }
        const session = walkthroughCoordinator.activeForTour('data-ingestion')
            ?? walkthroughCoordinator.beginManual(
                progressPageKey,
                tour.revision,
                'data-ingestion',
            );
        if (!claimWalkthroughDialog(instanceId)) {
            return;
        }
        activeSessionToken = session?.token ?? null;
        activeInstanceKey = tour.instanceKey;
        stepId = session?.cursor && tour.steps.some((candidate) => candidate.id === session.cursor)
            ? session.cursor
            : tour.start;
        open = true;
        await tick();
        heading?.focus();
    }

    async function close(): Promise<void> {
        if (open) {
            walkthroughCoordinator.interrupt('data-ingestion', stepId || null);
        }
        activeSessionToken = null;
        activeInstanceKey = '';
        releaseWalkthroughDialog(instanceId);
        open = false;
        await tick();
        trigger?.focus();
    }

    async function finish(): Promise<void> {
        if (tour) {
            walkthroughCoordinator.finish('data-ingestion', 'completed', stepId || null);
        }
        activeSessionToken = null;
        activeInstanceKey = '';
        releaseWalkthroughDialog(instanceId);
        open = false;
        await tick();
        trigger?.focus();
    }

    async function skipGuide(): Promise<void> {
        walkthroughCoordinator.finish('data-ingestion', 'skipped', stepId || null);
        activeSessionToken = null;
        activeInstanceKey = '';
        releaseWalkthroughDialog(instanceId);
        open = false;
        await tick();
        trigger?.focus();
    }

    async function move(direction: number): Promise<void> {
        if (!tour) {
            return;
        }
        const next = tour.steps[stepIndex + direction];
        if (!next) {
            await finish();
            return;
        }
        stepId = next.id;
        walkthroughCoordinator.updateCursor('data-ingestion', next.id);
        await tick();
        heading?.focus({ preventScroll: true });
    }
</script>

<div class="min-w-0 {className}" bind:this={root}>
    <button
        bind:this={trigger}
        type="button"
        class="text-primary hover:bg-primary/10 focus-visible:ring-ring inline-flex min-h-10 items-center gap-2 rounded-md px-2 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none"
        aria-expanded={open}
        aria-controls={headingId}
        onclick={() => (open ? close() : start())}
    >
        <HelpCircle class="size-4" />
        {open ? 'Hide guide' : 'Guide me'}
    </button>
    {#if open && step && tour}
        <section
            aria-labelledby={headingId}
            class="border-border bg-muted/30 mt-2 min-w-0 rounded-lg border p-3 sm:p-4"
        >
            <div class="flex items-start gap-3">
                <div class="hidden shrink-0 sm:block" aria-hidden="true">
                    <XiamAvatar
                        size={64}
                        heldEmotion={step.emotion}
                        label="Xiam import guide"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <h3
                            id={headingId}
                            bind:this={heading}
                            tabindex="-1"
                            class="text-foreground focus-visible:ring-ring rounded-sm text-sm font-semibold outline-none focus-visible:ring-2"
                        >
                            {step.title}
                        </h3>
                        <button
                            type="button"
                            aria-label="Close import guide"
                            class="text-muted-foreground hover:bg-muted focus-visible:ring-ring inline-flex size-9 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
                            onclick={close}
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <p
                        class="text-muted-foreground mt-2 max-w-prose text-sm leading-relaxed"
                        aria-live="polite"
                    >
                        {step.text}
                    </p>
                    <div
                        class="mt-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="text-muted-foreground text-xs"
                            >Xiam · {stepIndex + 1} of {tour.steps.length}</span
                        >
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onclick={skipGuide}
                                >Skip guide</Button
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                disabled={stepIndex === 0}
                                onclick={() => move(-1)}
                                ><ChevronLeft class="size-4" />Back</Button
                            >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onclick={() => move(1)}
                                >{stepIndex === tour.steps.length - 1
                                    ? 'Done'
                                    : 'Next'}<ChevronRight
                                    class="size-4"
                                /></Button
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>
    {/if}
</div>

<style>
    :global([data-import-guide-active='true']) {
        outline: 2px solid var(--primary);
        outline-offset: 3px;
    }
</style>
