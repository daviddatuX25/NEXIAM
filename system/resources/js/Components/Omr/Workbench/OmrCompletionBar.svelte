<script lang="ts">
    import {
        ArrowRight,
        CheckCircle2,
        FlaskConical,
        LoaderCircle,
        ShieldCheck,
    } from 'lucide-svelte';
    import type {
        OmrWorkbenchCommand,
        OmrWorkbenchCompletion,
        OmrWorkbenchProjection,
    } from '@/lib/omrWorkbench';

    type CompletionCommand = Extract<
        OmrWorkbenchCommand,
        'production_complete' | 'diagnostic_compare' | 'diagnostic_complete'
    >;
    type CompleteCallback = (
        command: CompletionCommand,
    ) => void | Promise<void>;

    let {
        projection,
        onComplete = undefined,
    }: {
        projection: OmrWorkbenchProjection;
        onComplete?: CompleteCallback;
    } = $props();

    let submitting = $state(false);

    function hasCommand(command: CompletionCommand): boolean {
        return projection.commands.includes(command);
    }

    function text(value: unknown, fallback: string): string {
        return typeof value === 'string' && value.trim() !== ''
            ? value.trim()
            : fallback;
    }

    function number(value: unknown): number | null {
        if (typeof value !== 'number' || !Number.isFinite(value)) {
            return null;
        }

        return Math.max(0, Math.floor(value));
    }

    function completionCommand(): CompletionCommand | null {
        if (projection.execution_kind === 'production') {
            return hasCommand('production_complete')
                ? 'production_complete'
                : null;
        }

        if (hasCommand('diagnostic_complete')) {
            return 'diagnostic_complete';
        }

        if (hasCommand('diagnostic_compare')) {
            return 'diagnostic_compare';
        }

        return null;
    }

    function commandLabel(command: CompletionCommand | null): string {
        if (command === 'production_complete') {
            return 'Confirm production completion';
        }
        if (command === 'diagnostic_compare') {
            return 'Compare diagnostic outcome';
        }
        if (command === 'diagnostic_complete') {
            return 'Record diagnostic outcome';
        }

        return 'Waiting for review';
    }

    function completionTitle(
        value: OmrWorkbenchCompletion | null,
        diagnostic: boolean,
    ): string {
        if (diagnostic) {
            return text(value?.label, 'Projected diagnostic outcome');
        }

        return text(value?.label, 'Production completion');
    }

    function completionMessage(
        value: OmrWorkbenchCompletion | null,
        diagnostic: boolean,
    ): string {
        if (diagnostic) {
            if (value?.outcome === 'blocked') {
                return 'The diagnostic outcome is blocked and remains isolated from production records.';
            }

            return 'This projected outcome is for diagnostic evidence only; it does not write production data or satisfy physical qualification.';
        }

        if (value !== null) {
            return 'The production run has an immutable completion result from the server.';
        }

        return 'Review the queued sheets and resolve every blocking issue before confirming this production run.';
    }

    async function invokeCompletion(): Promise<void> {
        const command = completionCommand();
        if (!command || !onComplete || submitting) {
            return;
        }

        submitting = true;
        try {
            await onComplete(command);
        } finally {
            submitting = false;
        }
    }

    const diagnostic = $derived(projection.execution_kind === 'diagnostic');
    const completion = $derived(projection.completion);
    const command = $derived(completionCommand());
    const completedCaptureCount = $derived(number(completion?.capture_count));
    const readyToComplete = $derived(
        completion === null &&
            command !== null &&
            onComplete !== undefined,
    );
    const completionStatus = $derived(
        completion !== null
            ? text(completion.status, completion.outcome ?? 'recorded')
            : readyToComplete
              ? 'ready'
              : 'review_required',
    );
    const official = $derived(
        !diagnostic && completion !== null && completion.official === true,
    );
    const title = $derived(completionTitle(completion, diagnostic));
    const message = $derived(completionMessage(completion, diagnostic));
</script>

<section
    class="border-border bg-card min-w-0 overflow-hidden rounded-lg border shadow-xs"
    data-omr-completion-bar
    data-omr-completion-kind={diagnostic ? 'diagnostic' : 'production'}
    aria-labelledby="omr-completion-title"
>
    <div
        class="flex flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center sm:justify-between sm:px-3"
    >
        <div class="flex min-w-0 items-start gap-2">
            <div
                class={`mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full ${diagnostic ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'}`}
                aria-hidden="true"
            >
                {#if diagnostic}
                    <FlaskConical class="size-3.5" />
                {:else if completion !== null}
                    <CheckCircle2 class="size-3.5" />
                {:else}
                    <ShieldCheck class="size-3.5" />
                {/if}
            </div>
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        id="omr-completion-title"
                        class="text-sm font-semibold tracking-tight"
                    >
                        {title}
                    </h2>
                    <span
                        class={`inline-flex min-h-5 items-center rounded-full border px-2 py-0.5 text-[10px] font-semibold ${completion === null && !readyToComplete ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200' : diagnostic ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'}`}
                        data-omr-completion-status={completionStatus}
                    >
                        {#if completion === null && !readyToComplete}
                            Review required
                        {:else if diagnostic}
                            Projected / unofficial
                        {:else if official}
                            Official
                        {:else if completion !== null}
                            Recorded
                        {:else}
                            Ready
                        {/if}
                    </span>
                </div>
                <p
                    class="text-muted-foreground mt-0.5 max-w-2xl text-xs leading-relaxed"
                    data-omr-completion-message
                >
                    {message}
                </p>
                {#if completedCaptureCount !== null}
                    <p
                        class="text-muted-foreground mt-1 text-[11px] tabular-nums"
                        data-omr-completion-captures
                    >
                        {completedCaptureCount} capture{completedCaptureCount ===
                        1
                            ? ''
                            : 's'} included
                    </p>
                {/if}
            </div>
</div>

        {#if command && onComplete && completion === null && readyToComplete}
            <button
                type="button"
                class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-8 shrink-0 items-center justify-center gap-2 rounded-md px-3 py-1.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50"
                data-omr-completion-command={command}
                disabled={submitting}
                onclick={invokeCompletion}
            >
                {#if submitting}
                    <LoaderCircle
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    Working…
                {:else}
                    {commandLabel(command)}
                    <ArrowRight class="size-4" aria-hidden="true" />
                {/if}
            </button>
        {:else if completion !== null}
            <span
                class="text-muted-foreground inline-flex min-h-8 shrink-0 items-center gap-2 text-xs font-medium"
                data-omr-completion-immutable
            >
                <CheckCircle2
                    class="size-4 text-emerald-600 dark:text-emerald-300"
                    aria-hidden="true"
                />
                Immutable result
            </span>
        {:else}
            <span
                class="text-muted-foreground inline-flex min-h-8 shrink-0 items-center gap-2 text-xs font-medium"
                data-omr-completion-waiting
            >
                <ShieldCheck class="size-4" aria-hidden="true" />
                Completion unavailable
            </span>
        {/if}
    </div>
</section>
