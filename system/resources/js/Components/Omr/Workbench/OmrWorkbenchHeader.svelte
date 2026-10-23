<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft, Camera, FileText, Layers, LayoutGrid, Settings } from 'lucide-svelte';
    import type {
        OmrWorkbenchCommand,
        OmrWorkbenchProjection,
    } from '@/lib/omrWorkbench';

    type ValueRecord = Record<string, unknown>;

    let {
        projection,
        sheetsCount = 0,
        connectionState = 'live',
        activeStageView = 'camera',
        onStageViewChange,
        onBack,
        sessionTitle,
        onSettings,
        onShortcuts,
        onRestart,
        onComplete,
        onOpenGuide,
    }: {
        projection: OmrWorkbenchProjection;
        sheetsCount?: number;
        connectionState?: string;
        activeStageView?: 'camera' | 'queue' | 'review' | 'triage';
        onStageViewChange?: (view: 'camera' | 'queue' | 'review' | 'triage') => void;
        onBack?: () => void;
        sessionTitle?: string;
        onSettings?: () => void;
        onShortcuts?: () => void;
        onRestart?: () => void;
        onComplete?: (command: CompletionCommand) => void;
        onOpenGuide?: () => void | Promise<void>;
    } = $props();

    type CompletionCommand = Extract<
        OmrWorkbenchCommand,
        'production_complete' | 'diagnostic_compare' | 'diagnostic_complete'
    >;

    function asRecord(value: unknown): ValueRecord {
        return value !== null && typeof value === 'object'
            ? (value as ValueRecord)
            : {};
    }

    function textValue(value: unknown): string | null {
        if (typeof value !== 'string' && typeof value !== 'number') {
            return null;
        }

        const text = String(value).trim();
        return text === '' ? null : text;
    }

    const context = $derived(asRecord(projection.context));
    const contextTitle = $derived(
        sessionTitle ??
            (projection.execution_kind === 'diagnostic'
                ? 'Test Kit Bundle Acceptance'
                : (textValue(context.title) ??
                  textValue(context.name) ??
                  textValue(context.label) ??
                  'OMR scoring workbench')),
    );
    const isDiagnostic = $derived(projection.execution_kind === 'diagnostic');
    const backHref = $derived(
        isDiagnostic
            ? (textValue(context.materials_url) ??
              textValue(context.back_url) ??
              '/setup/exam-materials/test-kits')
            : (textValue(context.session_url) ??
              textValue(context.back_url) ??
              (textValue(context.session_id)
                  ? `/grading/sessions/${encodeURIComponent(textValue(context.session_id) ?? '')}`
                  : '/grading')),
    );
    const completeCommand = $derived(
        projection.commands.find(
            (command): command is CompletionCommand =>
                command === 'production_complete' ||
                command === 'diagnostic_compare' ||
                command === 'diagnostic_complete',
        ) ?? null,
    );
    const backLabel = $derived(isDiagnostic ? 'Exit Run' : 'Exit Session');
    const stageTabs = $derived([
        { view: 'camera' as const, label: 'Camera', icon: Camera },
        { view: 'queue' as const, label: `Scans Queue (${sheetsCount})`, icon: LayoutGrid },
        { view: 'review' as const, label: 'Review', icon: FileText },
        { view: 'triage' as const, label: 'Rapid Triage', icon: Layers },
    ]);

    function complete(): void {
        if (completeCommand) {
            onComplete?.(completeCommand);
        }
    }

</script>

<header
    class="sticky top-0 z-40 flex min-h-11 shrink-0 items-center justify-between gap-2 border-b border-border/60 bg-card/95 px-3 py-1.5 text-xs text-foreground backdrop-blur-sm sm:min-h-12 sm:px-5 sm:py-2"
    data-omr-workbench-header
>
    <div class="grid min-w-0 flex-1 grid-cols-[44px_minmax(0,1fr)] items-center gap-x-2 gap-y-1 sm:flex sm:gap-x-3">
        {#if onBack}
            <button
                type="button"
                class="inline-flex min-h-[40px] min-w-[40px] shrink-0 items-center justify-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground sm:min-h-0 sm:min-w-0 sm:justify-start"
                aria-label={`Back to ${backLabel}`}
                onclick={onBack}
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                <span class="hidden sm:inline">{backLabel}</span>
            </button>
        {:else}
            <Link
                href={backHref}
                class="inline-flex min-h-[40px] min-w-[40px] shrink-0 items-center justify-center gap-1.5 text-muted-foreground transition-colors hover:text-foreground sm:min-h-0 sm:min-w-0 sm:justify-start"
                aria-label={`Back to ${backLabel}`}
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                <span class="hidden sm:inline">{backLabel}</span>
            </Link>
        {/if}
        <h1
            class="min-w-0 truncate font-sans text-xs font-semibold tracking-tight text-foreground sm:max-w-64 sm:text-sm"
            data-omr-context-title
            title={contextTitle}
        >
            {contextTitle}
        </h1>
        <nav class="hidden min-w-0 items-center gap-1 overflow-x-auto md:flex" aria-label="OMR stages" role="tablist">
            {#each stageTabs as tab}
                <button
                    type="button"
                    class={`inline-flex shrink-0 items-center gap-1.5 rounded-md px-2.5 py-1.5 font-medium transition-colors ${activeStageView === tab.view ? 'bg-primary/10 text-primary' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                    role="tab"
                    aria-selected={activeStageView === tab.view}
                    onclick={() => onStageViewChange?.(tab.view)}
                >
                    <tab.icon class="size-3.5" aria-hidden="true" />
                    <span>{tab.label}</span>
                </button>
            {/each}
        </nav>
        {#if isDiagnostic}
            <span
                class="col-start-2 inline-flex min-w-0 max-w-full items-center gap-1.5 justify-self-start rounded-full border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-medium text-amber-700 sm:text-[11px] dark:text-amber-300"
                data-omr-diagnostic-banner
            >
                <span class="truncate"><span class="sm:hidden">Diagnostic · no score writes</span><span class="hidden sm:inline">Diagnostic projection · no production writes</span></span>
                <span class="hidden text-[10px] text-amber-800/80 xl:inline dark:text-amber-300/80">(isolated from production captures, page claims, scores)</span>
            </span>
        {/if}
    </div>
    <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
        <span
            class="inline-flex items-center gap-1.5 rounded-full border border-border/70 bg-muted/30 px-2 py-1 text-[11px] font-medium text-muted-foreground sm:px-2.5"
            role="status"
            aria-live="polite"
            aria-label={`Connection ${connectionState}`}
            data-omr-workbench-status
        >
            <span
                class={`size-1.5 rounded-full ${connectionState === 'live' || connectionState === 'connected' ? 'bg-emerald-500' : connectionState === 'connecting' ? 'bg-amber-500' : 'bg-rose-500'}`}
                aria-hidden="true"
            ></span>
            <span class="hidden sm:inline">{connectionState}</span>
            <span class="font-mono">{sheetsCount}</span>
        </span>
        {#if onOpenGuide}
            <button
                type="button"
                class="hidden rounded-md border border-border/60 px-2.5 py-1.5 text-xs font-semibold transition-colors hover:bg-muted sm:inline-flex"
                data-omr-open-guide
                onclick={() => onOpenGuide()}
            >
                Shading Guide
            </button>
        {/if}

        {#if completeCommand}
            <button
                type="button"
                class="min-h-11 rounded-md border border-emerald-700/50 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 transition-colors hover:bg-emerald-500/10 sm:min-h-0 dark:text-emerald-300"
                onclick={complete}
            >
                Complete
            </button>
        {/if}
        <button
            type="button"
            class="inline-flex size-8 min-h-[40px] min-w-[40px] items-center justify-center rounded-md border border-border/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground sm:min-h-8 sm:min-w-8"
            aria-label="Settings"
            onclick={() => onSettings?.()}
        >
            <Settings class="size-4" aria-hidden="true" />
        </button>
    </div>
</header>
