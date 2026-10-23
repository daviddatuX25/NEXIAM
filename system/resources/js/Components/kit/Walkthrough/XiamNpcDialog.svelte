<script lang="ts">
    import { onMount, tick } from 'svelte';
    import { usePage } from '@inertiajs/svelte';
    import XiamAvatar from '@/Components/kit/Xiam/XiamAvatar.svelte';
    import { getTour } from '@/lib/walkthrough/tours';
    import { resolveTour } from '@/lib/walkthrough/resolver';
    import {
        PERSONA_CONFIGS,
        type TourDefinition,
        type TourDestination,
    } from '@/lib/walkthrough/types';
    import {
        claimWalkthroughDialog,
        createWalkthroughDialogId,
        releaseWalkthroughDialog,
        walkthroughCoordinator,
    } from '@/lib/walkthrough/coordinator';
    import ChevronLeft from 'lucide-svelte/icons/chevron-left';
    import ChevronRight from 'lucide-svelte/icons/chevron-right';
    import Sparkles from 'lucide-svelte/icons/sparkles';
    import X from 'lucide-svelte/icons/x';
    import Zap from 'lucide-svelte/icons/zap';
    import { resolveXiamSession, xiamShouldMount } from '@/types/xiam';

    let {
        tourKey,
        pageKey = undefined,
        context = undefined,
        definition = undefined,
        open = $bindable(false),
        canAskAi = undefined,
    }: {
        tourKey: string;
        pageKey?: string;
        context?: unknown;
        definition?: TourDefinition;
        open?: boolean;
        canAskAi?: boolean;
    } = $props();
    let page: any = undefined;
    try {
        page = usePage();
    } catch {
        // Graceful fallback when rendered outside Inertia context (e.g. tests)
    }
    const effectivePageKey = $derived(
        pageKey ?? (typeof page?.component === 'string' ? `page:${page.component}` : `tour:${tourKey}`),
    );

    function portal(node: HTMLElement) {
        document.body.appendChild(node);
        return {
            destroy() {
                if (node.parentNode) {
                    node.parentNode.removeChild(node);
                }
            },
        };
    }

    const tour = $derived(
        definition
            ? resolveTour(definition, context as any)
            : getTour(tourKey, context as any),
    );
    const dialogId = createWalkthroughDialogId();
    let currentStepId = $state('');
    let history = $state<string[]>([]);
    let activeInstanceKey = $state('');
    let dialogElement = $state<HTMLElement | null>(null);
    let activeSessionToken = $state<string | null>(null);
    let reducedMotion = $state(false);
    let ownedDialog = false;
    let wasOpen = false;
    let returnFocus: HTMLElement | null = null;

    $effect(() => {
        if (!open || !tour) {
            return;
        }
        if (
            tour.instanceKey !== activeInstanceKey
            || !currentStepId
            || !tour.steps.some((step) => step.id === currentStepId)
        ) {
            activeInstanceKey = tour.instanceKey;
            const session = walkthroughCoordinator.activeForTour(tour.id);
            const resumeCursor = session?.cursor;
            currentStepId = resumeCursor && tour.steps.some((step) => step.id === resumeCursor)
                ? resumeCursor
                : tour.start || tour.steps[0]?.id || '';
            history = [];
        }
    });

    $effect(() => {
        if (open && !wasOpen) {
            if (!claimWalkthroughDialog(dialogId)) {
                open = false;
                return;
            }
            ownedDialog = true;
            wasOpen = true;
            returnFocus = document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
            const session = tour
                ? walkthroughCoordinator.activeForTour(tour.id)
                    ?? walkthroughCoordinator.beginManual(effectivePageKey, tour.revision, tour.id)
                : null;
            activeSessionToken = session?.token ?? null;
            void tick().then(() => dialogElement?.focus());
        } else if (!open && wasOpen) {
            wasOpen = false;
            activeSessionToken = null;
            activeInstanceKey = '';
            const focusTarget = returnFocus;
            returnFocus = null;
            if (ownedDialog) {
                releaseWalkthroughDialog(dialogId);
                ownedDialog = false;
            }
            void tick().then(() => {
                if (focusTarget?.isConnected) {
                    focusTarget.focus();
                }
            });
        }
    });

    onMount(() => {
        const motionPreference = typeof window.matchMedia === 'function'
            ? window.matchMedia('(prefers-reduced-motion: reduce)')
            : null;
        reducedMotion = motionPreference?.matches ?? false;
        const updateMotionPreference = (event: MediaQueryListEvent) => {
            reducedMotion = event.matches;
        };
        const cancelStaleTour = (event: Event) => {
            const token = (event as CustomEvent<{ token?: string }>).detail?.token;
            if (token && token === activeSessionToken) {
                activeSessionToken = null;
                open = false;
            }
        };
        motionPreference?.addEventListener('change', updateMotionPreference);
        window.addEventListener('nexiam:walkthrough:cancel', cancelStaleTour);
        return () => {
            motionPreference?.removeEventListener('change', updateMotionPreference);
            window.removeEventListener('nexiam:walkthrough:cancel', cancelStaleTour);
            releaseWalkthroughDialog(dialogId);
            stopTyping();
        };
    });
    let displayedText = $state('');
    let isTyping = $state(false);
    let typingTimer: number | undefined;
    let activeTypingStepId: string | null = null;

    function stopTyping(): void {
        if (typingTimer !== undefined) {
            window.clearInterval(typingTimer);
            typingTimer = undefined;
        }
        isTyping = false;
    }
    const currentStep = $derived(
        tour?.steps.find((step) => step.id === currentStepId) ?? null,
    );
    const stepNumber = $derived(
        tour
            ? Math.max(
                  0,
                  tour.steps.findIndex((step) => step.id === currentStepId) + 1,
              )
            : 0,
    );
    const activePersona = $derived(
        currentStep?.personaOverride ?? tour?.persona ?? 'staff-copilot',
    );
    const personaConfig = $derived(
        PERSONA_CONFIGS[activePersona] ?? PERSONA_CONFIGS['staff-copilot'],
    );

    const stepPlaybackKey = $derived(
        `${tour?.instanceKey ?? tourKey}:${currentStepId}:${history.length}`,
    );

    const isXiamActiveOnPage = $derived.by(() => {
        try {
            if (!page?.props) return false;
            const user = page.props.auth?.user ?? null;
            if (!user) {
                return false;
            }
            const session = resolveXiamSession(page.props.xiam);
            if (!session || !xiamShouldMount(session)) {
                return false;
            }
            return session.availability !== 'suspended';
        } catch {
            return false;
        }
    });

    const shouldShowAskAi = $derived.by(() => {
        if (canAskAi !== undefined) {
            return canAskAi;
        }
        if (currentStep?.allowAskAi !== undefined) {
            return currentStep.allowAskAi;
        }
        if (tour?.allowAskAi !== undefined) {
            return tour.allowAskAi;
        }
        return isXiamActiveOnPage;
    });

    $effect(() => {
        if (!open) {
            stopTyping();
            activeTypingStepId = null;
            return;
        }

        const step = currentStep;
        if (!step) {
            stopTyping();
            activeTypingStepId = null;
            return;
        }

        const stepKey = `${tour?.id ?? tourKey}:${step.id}:${step.text}`;
        if (activeTypingStepId === stepKey) {
            return;
        }

        stopTyping();
        activeTypingStepId = stepKey;

        const text = step.text;
        if (step.speechMode === 'instant' || reducedMotion) {
            displayedText = text;
            isTyping = false;
            return;
        }

        displayedText = '';
        isTyping = true;
        let position = 0;
        typingTimer = window.setInterval(() => {
            position = Math.min(position + 1, text.length);
            displayedText = text.slice(0, position);
            if (position === text.length) {
                stopTyping();
            }
        }, 25);
    });

    function revealText() {
        displayedText = currentStep?.text ?? '';
        stopTyping();
    }

    function interruptTour(): void {
        if (tour) {
            walkthroughCoordinator.interrupt(tour.id, currentStepId || null);
        }
        open = false;
    }

    function skipTour(): void {
        if (tour) {
            walkthroughCoordinator.finish(tour.id, 'skipped', currentStepId || null);
        }
        open = false;
    }

    function completeTour(): void {
        if (tour) {
            walkthroughCoordinator.finish(tour.id, 'completed', currentStepId || null);
        }
        open = false;
    }

    function askAssistant() {
        const topic = currentStep?.title || tour?.title || '';
        skipTour();
        window.dispatchEvent(
            new CustomEvent('nexiam:open-xiam-chat', {
                detail: { prompt: topic ? `Tell me more about: ${topic}` : '' },
            }),
        );
    }

    function follow(destination: TourDestination): void {
        if (destination.kind === 'complete') {
            completeTour();
            return;
        }

        if (!tour?.steps.some((step) => step.id === destination.stepId)) {
            interruptTour();
            return;
        }

        history = [...history, currentStepId];
        currentStepId = destination.stepId;
        walkthroughCoordinator.updateCursor(tour.id, destination.stepId);
    }

    function goBack(): void {
        const previous = history.at(-1);
        if (!previous || !tour) {
            return;
        }
        history = history.slice(0, -1);
        currentStepId = previous;
        walkthroughCoordinator.updateCursor(tour.id, previous);
    }

    function handleKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            event.preventDefault();
            skipTour();
            return;
        }

        if (event.key !== 'Tab' || !dialogElement) return;
        const focusable = Array.from(
            dialogElement.querySelectorAll<HTMLElement>(
                'button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])',
            ),
        );
        if (focusable.length === 0) {
            event.preventDefault();
            dialogElement.focus();
            return;
        }
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
</script>

{#if open && tour}
    <div
        use:portal
        class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-md transition-all duration-200 motion-reduce:transition-none sm:p-6 md:p-8"
        role="presentation"
        onclick={(event) => event.target === event.currentTarget && skipTour()}
    >
        <div
            bind:this={dialogElement}
            class="border-border/80 bg-card/90 text-card-foreground craft-card-depth relative flex w-full max-w-2xl flex-col justify-between overflow-hidden rounded-3xl border p-6 shadow-2xl backdrop-blur-xl outline-none motion-reduce:transition-none sm:min-h-[460px] sm:max-w-3xl sm:p-8 lg:max-w-4xl"
            role="dialog"
            aria-modal="true"
            data-xiam-walkthrough="true"
            tabindex="-1"
            onkeydown={handleKeydown}
        >
            {#if currentStep}
                <div class="flex flex-col gap-3.5 sm:gap-4.5">
                    <!-- Top Row Header: Phase Badge & Step Count on Left, Close Button on Right -->
                    <header class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            {#if currentStep.phase}
                                <span
                                    class="border-border/40 bg-muted/60 text-muted-foreground rounded-md border px-2 py-0.5 font-mono text-[11px] font-semibold tracking-wider uppercase"
                                >
                                    {currentStep.phase}
                                </span>
                            {/if}
                            <span
                                class="text-muted-foreground/80 text-xs font-medium"
                                >Step {stepNumber} of {tour.steps.length}</span
                            >
                        </div>
                        <button
                            type="button"
                            class="text-muted-foreground/80 hover:bg-muted/60 hover:text-foreground inline-flex size-8 items-center justify-center rounded-full transition-colors"
                            aria-label="Close tour"
                            title="Close"
                            onclick={skipTour}
                        >
                            <X class="size-4" />
                        </button>
                    </header>

                    <!-- Step Title -->
                    <h2
                        id="xiam-tour-title"
                        class="text-foreground text-xl leading-snug font-bold tracking-tight sm:text-2xl"
                    >
                        {currentStep.title}
                    </h2>

                    <!-- Interactive Content Stage: Avatar on Left (Bottom-Anchored, No Background) + Typewriter Speech Bubble on Right -->
                    <div
                        class="flex flex-col items-center gap-4 pt-1 sm:flex-row sm:items-end sm:gap-6"
                    >
                        <!-- Mascot Avatar: Clean illustration, zero background box, bottom-anchored aligned with typewriter section -->
                        <div
                            class="flex shrink-0 items-end justify-center self-end sm:self-end"
                        >
                            <div
                                class="-mb-1 flex w-[130px] items-end justify-center sm:-mb-2 sm:w-[176px]"
                            >
                                <XiamAvatar
                                    size={180}
                                    label="Xiam, {currentStep.emotion}"
                                    heldEmotion={currentStep.emotion}
                                    playbackKey={stepPlaybackKey}
                                />
                            </div>
                        </div>

                        <!-- Reading Canvas & Progress Dots Column -->
                        <div
                            class="flex w-full min-w-0 flex-1 flex-col justify-end"
                        >
                            <!-- Reading Canvas / Speech Bubble with Fixed Height & Ghost Pre-sizer -->
                            <button
                                type="button"
                                class={`group relative flex h-[168px] w-full cursor-text flex-col justify-between rounded-2xl border p-5 text-left transition-all duration-200 sm:h-[184px] sm:p-6 ${personaConfig.bubbleBorderClass} hover:border-primary/40 focus:ring-primary/25 overflow-y-auto focus:ring-2 focus:outline-none`}
                                aria-label="Tour explanation. Click to reveal all text immediately."
                                onclick={revealText}
                            >
                                <div class="grid w-full grid-cols-1">
                                    <!-- Invisible Ghost Text Sizer: pre-allocates exact spatial layout from frame 0 -->
                                    <div
                                        class="pointer-events-none invisible col-start-1 row-start-1 text-base leading-relaxed font-normal tracking-normal whitespace-pre-line antialiased select-none sm:text-[1.0625rem] sm:leading-8"
                                        aria-hidden="true"
                                    >
                                        {currentStep.text}
                                    </div>

                                    <!-- Visible Streamer Text: perfectly overlaid without shifting or resizing -->
                                    <div
                                        class="text-foreground/90 col-start-1 row-start-1 text-base leading-relaxed font-normal tracking-normal whitespace-pre-line antialiased sm:text-[1.0625rem] sm:leading-8"
                                    >
                                        {displayedText}{#if isTyping}<span
                                                aria-hidden="true"
                                                class={`ml-1 inline-block animate-pulse font-bold ${personaConfig.accentClass}`}
                                                >▍</span
                                            >{/if}
                                    </div>
                                </div>

                                {#if isTyping}
                                    <div
                                        class="text-muted-foreground/70 mt-2 flex shrink-0 items-center justify-end gap-1.5 font-mono text-[11px] transition-opacity"
                                    >
                                        <Zap class="size-3" />
                                        <span>Click text to reveal all</span>
                                    </div>
                                {/if}
                            </button>

                            <!-- Discrete Step Progress Indicator -->
                            <div
                                class="mt-3.5 flex items-center gap-1.5 px-1"
                                aria-hidden="true"
                            >
                                {#each tour.steps as s, idx (s.id)}
                                    <div
                                        class={`h-1.5 rounded-full transition-all duration-300 ${
                                            s.id === currentStepId
                                                ? 'bg-primary w-7 shadow-xs'
                                                : idx < stepNumber
                                                  ? 'bg-primary/40 w-2'
                                                  : 'bg-muted/80 w-2'
                                        }`}
                                        title={`Step ${idx + 1}: ${s.title}`}
                                    ></div>
                                {/each}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dialog Action Footer -->
                <footer
                    class="border-border/50 mt-6 flex flex-wrap items-center justify-between gap-3 border-t pt-4 sm:mt-7 sm:pt-5"
                >
                    {#if currentStep.advance.kind === 'next'}
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted/70 hover:text-foreground rounded-xl px-3.5 py-2 text-sm transition-colors"
                                onclick={skipTour}
                            >
                                Skip Tour
                            </button>
                            {#if shouldShowAskAi}
                                <button
                                    type="button"
                                    class="border-primary/25 bg-primary/10 text-primary hover:bg-primary/20 inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold shadow-xs transition-all"
                                    onclick={askAssistant}
                                    title="Ask Xiam AI assistant about this topic"
                                >
                                    <Sparkles class="size-3.5" />
                                    <span>Ask Xiam AI</span>
                                </button>
                            {/if}
                        </div>
                        <div class="ml-auto flex items-center gap-2.5">
                            <button
                                type="button"
                                class="border-border bg-muted/30 text-foreground hover:bg-muted inline-flex items-center gap-1 rounded-xl border px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-40"
                                disabled={history.length === 0}
                                onclick={goBack}
                            >
                                <ChevronLeft class="size-4" />
                                <span>Back</span>
                            </button>
                            <button
                                type="button"
                                class="bg-primary text-primary-foreground inline-flex items-center gap-1 rounded-xl px-5 py-2 text-sm font-semibold shadow-sm transition-all hover:opacity-95 active:scale-[0.99]"
                                onclick={() => {
                                    if (currentStep.advance.kind === 'next') follow(currentStep.advance.destination);
                                }}
                            >
                                <span
                                    >{currentStep.advance.destination.kind ===
                                    'complete'
                                        ? 'Complete Tour'
                                        : 'Next Step'}</span
                                >
                                {#if currentStep.advance.destination.kind !== 'complete'}
                                    <ChevronRight class="size-4" />
                                {/if}
                            </button>
                        </div>
                    {:else}
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted/70 hover:text-foreground rounded-xl px-3.5 py-2 text-sm transition-colors"
                                onclick={skipTour}
                            >
                                Skip Tour
                            </button>
                            {#if shouldShowAskAi}
                                <button
                                    type="button"
                                    class="border-primary/25 bg-primary/10 text-primary hover:bg-primary/20 inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold shadow-xs transition-all"
                                    onclick={askAssistant}
                                    title="Ask Xiam AI assistant about this topic"
                                >
                                    <Sparkles class="size-3.5" />
                                    <span>Ask Xiam AI</span>
                                </button>
                            {/if}
                        </div>
                        <div class="ml-auto flex flex-wrap justify-end gap-2.5">
                            {#if history.length > 0}
                                <button
                                    type="button"
                                    class="border-border bg-muted/30 text-foreground hover:bg-muted inline-flex items-center gap-1 rounded-xl border px-4 py-2 text-sm font-medium transition-colors"
                                    onclick={goBack}
                                >
                                    <ChevronLeft class="size-4" />
                                    <span>Back</span>
                                </button>
                            {/if}
                            {#each currentStep.advance.options as option (option.id)}
                                <button
                                    type="button"
                                    class="border-primary/30 bg-primary/10 text-foreground hover:bg-primary/20 rounded-full border px-5 py-2 text-sm font-semibold shadow-xs transition-all"
                                    onclick={() => follow(option.destination)}
                                >
                                    {option.label}
                                </button>
                            {/each}
                        </div>
                    {/if}
                </footer>
            {/if}
        </div>
    </div>
{/if}
