<script lang="ts">
    import type { Snippet } from 'svelte';
    import {
        AlertCircle,
        CheckCircle2,
        Loader2,
        RefreshCw,
        ScanLine,
    } from 'lucide-svelte';
    import type { OmrWorkbenchProjection } from '@/lib/omrWorkbench';

    type ProjectionContext = Record<string, unknown>;

    type ProgressState = {
        completed: number;
        total: number | null;
        known: boolean;
    };

    type RetryCallback = () => void | Promise<void>;
    const OMR_PIPELINE_STAGES = [
        { label: 'Queued & Image Preflight', percent: 22 },
        { label: 'Fiducial & Corner Detection', percent: 48 },
        { label: 'Perspective Warp & Bubble ROI Inference', percent: 74 },
        { label: 'Mark Classification & Evidence Assembly', percent: 92 },
    ] as const;

    function pipelineStageIndex(value: string): number {
        const normalized = value.toLowerCase().trim().replace(/[_-]+/g, ' ');

        if (
            normalized.includes('queued') ||
            normalized.includes('preflight') ||
            normalized.includes('image')
        ) {
            return 0;
        }

        if (
            normalized.includes('fiducial') ||
            normalized.includes('corner') ||
            normalized.includes('orientation') ||
            normalized.includes('layout') ||
            normalized.includes('marker')
        ) {
            return 1;
        }

        if (
            normalized.includes('warp') ||
            normalized.includes('perspective') ||
            normalized.includes('rectif') ||
            normalized.includes('bubble') ||
            normalized.includes('roi') ||
            normalized.includes('infer') ||
            normalized.includes('recogniz')
        ) {
            return 2;
        }

        if (
            normalized.includes('classif') ||
            normalized.includes('mark') ||
            normalized.includes('evidence') ||
            normalized.includes('assembly') ||
            normalized.includes('resolv')
        ) {
            return 3;
        }

        return -1;
    }

    function pipelineStageForPercent(percent: number): number {
        if (percent < 35) return 0;
        if (percent < 61) return 1;
        if (percent < 83) return 2;
        return 3;
    }

    let estimatedPipelineStep = $state(0);

    let {
        projection,
        onRetry = undefined,
    }: {
        projection: OmrWorkbenchProjection;
        onRetry?: RetryCallback;
    } = $props();

    function asRecord(value: unknown): ProjectionContext {
        if (
            value === null ||
            typeof value !== 'object' ||
            Array.isArray(value)
        ) {
            return {};
        }

        return value as ProjectionContext;
    }

    function text(value: unknown, fallback: string): string {
        return typeof value === 'string' && value.trim() !== ''
            ? value.trim()
            : fallback;
    }

    function nonNegativeInteger(value: unknown): number | null {
        if (typeof value !== 'number' || !Number.isFinite(value)) {
            return null;
        }

        return Math.max(0, Math.floor(value));
    }

    function readProgress(value: unknown): ProgressState {
        const source = asRecord(value);
        const completed = nonNegativeInteger(source.completed) ?? 0;
        const total = nonNegativeInteger(source.total);
        const known = source.known === true && total !== null && total > 0;

        return {
            completed: known ? Math.min(completed, total as number) : completed,
            total,
            known,
        };
    }

    function phaseLabel(value: string): string {
        return (
            {
                capture: 'Capture',
                preflight: 'Preflight',
                context: 'Context',
                processing: 'Processing',
                evidence: 'Evidence',
                review: 'Review',
                recovery: 'Needs attention',
                completion: 'Completion',
                complete: 'Complete',
                terminal: 'Terminal',
                queued: 'Queued',
                preparing: 'Preparing',
                recognizing: 'Recognizing',
                resolving: 'Resolving',
                needs_review: 'Needs review',
                ready: 'Ready',
                confirmed: 'Confirmed',
                failed: 'Failed',
            }[value] ?? 'Processing'
        );
    }

    function retryable(value: string): boolean {
        return (
            value === 'recovery' ||
            value === 'failed' ||
            value === 'error' ||
            value === 'retrying' ||
            value.includes('failed') ||
            value.includes('error')
        );
    }

    const context = $derived(asRecord(projection?.context));
    const phase = $derived(text(projection?.phase, 'processing'));
    const stage = $derived(text(context.stage, phase));
    const rawStatus = $derived(text(context.raw_status, stage));
    const progress = $derived(readProgress(context.progress));
    const progressPercent = $derived(
        progress.known && progress.total !== null
            ? Math.round((progress.completed / progress.total) * 100)
            : null,
    );
    const stateLabel = $derived(phaseLabel(phase));
    const detailLabel = $derived(
        stage !== phase ? phaseLabel(stage) : stateLabel,
    );
    const isRecoverable = $derived(
        retryable(phase) || retryable(stage) || retryable(rawStatus),
    );
    const isComplete = $derived(
        phase === 'complete' ||
            phase === 'terminal' ||
            rawStatus === 'confirmed',
    );
    const stateTone = $derived(
        isRecoverable
            ? 'border-destructive/30 bg-destructive/5 text-destructive'
            : isComplete
              ? 'border-emerald-500/30 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300'
              : 'border-border bg-muted/40 text-muted-foreground',
    );
    const serverPipelineStep = $derived(
        Math.max(pipelineStageIndex(stage), pipelineStageIndex(rawStatus)),
    );
    const currentPipelineStep = $derived(
        isComplete
            ? OMR_PIPELINE_STAGES.length - 1
            : progressPercent !== null
              ? serverPipelineStep >= 0
                  ? serverPipelineStep
                  : pipelineStageForPercent(progressPercent)
              : Math.max(serverPipelineStep, estimatedPipelineStep),
    );
    const currentPipelineLabel = $derived(
        OMR_PIPELINE_STAGES[currentPipelineStep].label,
    );
    const effectiveProgress = $derived(
        progressPercent ??
            (isComplete
                ? 100
                : OMR_PIPELINE_STAGES[currentPipelineStep].percent),
    );

    $effect(() => {
        if (progressPercent !== null || isComplete || isRecoverable) {
            estimatedPipelineStep = 0;
            return;
        }

        const timer = setInterval(() => {
            estimatedPipelineStep = Math.min(
                estimatedPipelineStep + 1,
                OMR_PIPELINE_STAGES.length - 1,
            );
        }, 5000);

        return () => clearInterval(timer);
    });
</script>

<section
    data-omr-stage="processing"
    class="border-border bg-card min-w-0 overflow-hidden rounded-xl border shadow-xs"
    aria-labelledby="omr-processing-stage-title"
>
    <header
        class="border-border/70 flex flex-col gap-3 border-b px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5"
    >
        <div class="min-w-0">
            <div
                class="text-primary flex items-center gap-2 text-xs font-semibold tracking-[0.12em] uppercase"
            >
                <ScanLine class="size-4 shrink-0" aria-hidden="true" />
                <span>OMR workbench</span>
            </div>
            <h2
                id="omr-processing-stage-title"
                class="mt-1 text-lg font-semibold tracking-tight"
            >
                Processing
            </h2>
            <p
                class="text-muted-foreground mt-1 max-w-2xl text-sm leading-relaxed"
            >
                Run progress uses server-reported work units when available.
                Otherwise, the percentage estimates progress from the active
                OMR pipeline stage.
            </p>
        </div>
        <span
            data-omr-stage-state={phase}
            class={`inline-flex min-h-7 items-center gap-1.5 self-start rounded-full border px-2.5 py-1 text-xs font-semibold ${stateTone}`}
            role="status"
            aria-live="polite"
        >
            {#if isRecoverable}
                <AlertCircle class="size-3.5" aria-hidden="true" />
            {:else if isComplete}
                <CheckCircle2 class="size-3.5" aria-hidden="true" />
            {:else}
                <Loader2 class="size-3.5 animate-spin" aria-hidden="true" />
            {/if}
            {detailLabel}
        </span>
    </header>

    <div class="min-w-0 space-y-4 p-3 sm:p-4">
        <div
            data-omr-processing-progress
            class="border-border/80 bg-background/70 min-w-0 rounded-lg border p-3 sm:p-4"
        >
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
            >
                <div class="min-w-0">
                    <p class="text-sm font-semibold">Run progress</p>
                    <p
                        class="text-muted-foreground mt-0.5 text-xs leading-relaxed"
                    >
                        {#if progress.known && progress.total !== null}
                            {progress.completed} of {progress.total} units processed
                        {:else if progress.completed > 0}
                            {progress.completed} unit{progress.completed === 1
                                ? ''
                                : 's'} processed
                        {:else}
                            No completed units have been reported yet
                        {/if}
                    </p>
                </div>
                <span
                    class="text-muted-foreground shrink-0 font-mono text-xs tabular-nums"
                >
                    {Math.round(effectiveProgress)}%
                </span>
            </div>

            <div
                class="bg-muted mt-3 h-2 w-full overflow-hidden rounded-full"
                data-omr-processing-indeterminate={progressPercent === null
                    ? ''
                    : undefined}
                role="progressbar"
                aria-label="Processing progress"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow={Math.round(effectiveProgress)}
                aria-valuetext={progressPercent !== null
                    ? `${progress.completed} of ${progress.total} units processed (${Math.round(effectiveProgress)}%)`
                    : `${currentPipelineLabel}: ${Math.round(effectiveProgress)}% estimated`}
            >
                <div
                    class="bg-primary h-full rounded-full transition-[width] duration-500 motion-reduce:transition-none"
                    style={`width: ${effectiveProgress}%`}
                ></div>
            </div>

            <ol
                class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2"
                aria-label="OMR engine pipeline phases"
            >
                {#each OMR_PIPELINE_STAGES as pipelineStage, index}
                    {@const stepState =
                        isComplete || index < currentPipelineStep
                            ? 'complete'
                            : index === currentPipelineStep
                              ? isRecoverable
                                  ? 'failed'
                                  : 'current'
                              : 'pending'}
                    <li
                        class={`flex min-w-0 items-center justify-between gap-2 rounded-md border px-2.5 py-2 ${
                            stepState === 'complete'
                                ? 'border-primary/25 bg-primary/5'
                                : stepState === 'current'
                                  ? 'border-primary/60 bg-primary/10'
                                  : stepState === 'failed'
                                    ? 'border-destructive/30 bg-destructive/5'
                                    : 'border-border/70 bg-background/40'
                        }`}
                        aria-current={stepState === 'current' ||
                        stepState === 'failed'
                            ? 'step'
                            : undefined}
                        aria-label={`Step ${index + 1}: ${pipelineStage.label}; ${stepState}`}
                    >
                        <span
                            class={`inline-flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold ${
                                stepState === 'complete' ||
                                stepState === 'current'
                                    ? 'bg-primary text-primary-foreground'
                                    : stepState === 'failed'
                                      ? 'bg-destructive text-destructive-foreground'
                                      : 'bg-muted text-muted-foreground'
                            }`}
                            aria-hidden="true"
                        >
                            {index + 1}
                        </span>
                        <span class="min-w-0 flex-1 text-xs font-medium">
                            {pipelineStage.label}
                        </span>
                        <span class="shrink-0 font-mono text-[10px] tabular-nums">
                            {pipelineStage.percent}%
                        </span>
                    </li>
                {/each}
            </ol>

            {#if progressPercent === null}
                <p
                    class="text-muted-foreground mt-2 text-xs leading-relaxed"
                    role="status"
                >
                    {#if progress.completed > 0}
                        {progress.completed} unit{progress.completed === 1
                            ? ''
                            : 's'}
                        have completed; estimated phase:
                        {currentPipelineLabel}.
                    {:else}
                        The total unit count is not yet known. Estimated
                        progress: {currentPipelineLabel}.
                    {/if}
                </p>
            {/if}
        </div>

        <div
            data-omr-processing-status
            class="border-border/70 bg-muted/20 text-muted-foreground flex flex-col gap-2 rounded-lg border px-3 py-2.5 text-xs leading-relaxed sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-4"
            role="status"
            aria-live="polite"
        >
            <span>
                Server status: <strong class="text-foreground font-semibold"
                    >{detailLabel}</strong
                >
            </span>
            {#if rawStatus !== detailLabel}
                <span
                    class="text-muted-foreground/80 truncate"
                    title={rawStatus}>{rawStatus}</span
                >
            {/if}
        </div>

        {#if isRecoverable && onRetry}
            <div
                class="border-destructive/30 bg-destructive/5 flex flex-col gap-3 rounded-lg border px-3 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-4"
            >
                <div class="flex min-w-0 items-start gap-2.5">
                    <AlertCircle
                        class="text-destructive mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <p class="text-destructive text-xs leading-relaxed">
                        Processing did not complete. Retry through the
                        host-owned command when the source is ready.
                    </p>
                </div>
                <button
                    type="button"
                    class="border-destructive/30 text-destructive hover:bg-destructive/10 focus-visible:ring-ring inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-md border px-3 py-2 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    onclick={() => onRetry?.()}
                >
                    <RefreshCw class="size-3.5" aria-hidden="true" />
                    Retry processing
                </button>
            </div>
        {/if}
    </div>
</section>
