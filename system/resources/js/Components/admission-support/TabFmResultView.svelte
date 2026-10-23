<script>
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { ChevronDown, MessageSquareText, ScrollText, ArrowUpRight, Info } from 'lucide-svelte';
    import { hasValidTabFmNeuralProvenance } from '@/lib/tabFmResultGuards';
    import AptitudeRadarHexagon from '@/Components/admission-support/AptitudeRadarHexagon.svelte';
    import HollandRadarHexagon from '@/Components/admission-support/HollandRadarHexagon.svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import * as Tabs from '@/Components/ui/tabs';
    let { model, sourceKind = 'active', onAddToAdvisedPool, vocationalProfile = null, onOpenSupplementalEvidence, availablePrograms = [], publishedAdvisingNotes = [], threads = [], onOpenThread, scopedProgramCode = null } = $props();
    const effectiveSourceKind = $derived(model?.source_kind === 'recorded' || sourceKind === 'recorded' ? 'recorded' : sourceKind);

    let selectedProgramCode = $state(null);
    let showAllPrograms = $state(false);
    let mobileRadarOpen = $state(false);
    let radarViewMode = $state('cognitive');
    let activeModalProgram = $state(null);
    let notesThreadsOpen = $state(false);
    let notesThreadsTab = $state('notes');
    let infoDialogOpen = $state(false);

    function notesForProgram(programCode) {
        if (!programCode || !Array.isArray(publishedAdvisingNotes)) return [];
        return publishedAdvisingNotes.filter((n) => n?.program_code === programCode);
    }

    function threadsForProgram(programCode) {
        if (!programCode || !Array.isArray(threads)) return [];
        return threads.filter((t) => t?.program_code === programCode);
    }

    function openProgramNotesThreads(recommendation, initialTab = 'notes') {
        activeModalProgram = recommendation;
        notesThreadsTab = initialTab;
        notesThreadsOpen = true;
    }
    const effectiveVocationalProfile = $derived(
        vocationalProfile ?? model?.vocational_profile ?? model?.applicant_evidence?.vocational_profile ?? null
    );
    const fallbackProgram = $derived.by(() => {
        if (selectedProgram) {
            return selectedProgram;
        }
        const evidence = model?.applicant_evidence ?? {};
        const preferredCode = selectedProgramCode
            ?? evidence.preferred_program_code
            ?? evidence.program_preference_1_code
            ?? effectiveVocationalProfile?.preferred_program_code
            ?? effectiveVocationalProfile?.program_preference_1_code
            ?? null;
        if (preferredCode) {
            const matched = availablePrograms.find((p) => (p.code ?? p.program_code) === preferredCode);
            const target = effectiveVocationalProfile?.program_targets?.[preferredCode];
            return {
                program_code: preferredCode,
                program_name: matched?.name ?? matched?.program_name ?? target?.name ?? preferredCode,
            };
        }
        return null;
    });

    $effect(() => {
        if (typeof document === 'undefined') {
            return;
        }

        const main = document.getElementById('main-content');

        if (!main) {
            return;
        }

        if (mobileRadarOpen) {
            main.style.overflow = 'hidden';
        } else {
            main.style.overflow = '';
        }

        return () => {
            if (main) {
                main.style.overflow = '';
            }
        };
    });

    const isNeuralInference = $derived(hasValidTabFmNeuralProvenance(model));
    const isUnavailable = $derived(
        model?.status === 'unavailable'
        || (model?.status === 'evaluated' && model?.evaluated !== false && !isNeuralInference),
    );
    const isHistoricalOnly = $derived(
        effectiveSourceKind === 'recorded'
        && (model?.current_applicable !== true || !isNeuralInference)
        && model?.status === 'evaluated'
        && model?.evaluated !== false,
    );
    const recommendations = $derived.by(() => {
        if (isUnavailable || isHistoricalOnly || !Array.isArray(model?.recommendations)) return [];
        const available = model.recommendations;
        return scopedProgramCode
            ? available.filter((recommendation) => recommendation?.program_code === scopedProgramCode).slice(0, 1)
            : available;
    });
    const topPrograms = $derived(recommendations.slice(0, 4));
    const remainingPrograms = $derived(recommendations.slice(4));
    const isEvaluated = $derived(model?.status === 'evaluated' && model?.evaluated !== false && isNeuralInference && !isHistoricalOnly);
    const hasAdviceHandoff = $derived(typeof onAddToAdvisedPool === 'function' && isEvaluated);
    const selectedProgram = $derived.by(() => {
        if (!recommendations.length) return null;
        if (scopedProgramCode) return recommendations[0];
        return recommendations.find((recommendation) => recommendation.program_code === selectedProgramCode) ?? recommendations[0];
    });
    const sourceVersion = $derived(model?.source_version ?? model?.benchmark_version ?? null);
    const sourceVersionLabel = $derived(sourceVersion === null || sourceVersion === undefined
        ? null
        : effectiveSourceKind === 'recorded' || effectiveSourceKind === 'draft' || String(sourceVersion).startsWith('v') ? String(sourceVersion) : `v${sourceVersion}`);
    const sourceLabel = $derived(effectiveSourceKind === 'recorded'
        ? 'Recorded analysis'
        : effectiveSourceKind === 'draft' ? 'Saved draft' : model?.benchmark_context_changed ? 'Recorded benchmark' : 'Active benchmark');
    const evaluationBackendLabel = $derived(
        isEvaluated && typeof model?.inference_backend === 'string' && model.inference_backend.trim() !== ''
            ? effectiveSourceKind === 'recorded' ? model.inference_backend : backendLabel(model.inference_backend)
            : null,
    );

    function bandLabel(band) {
        if (band === 'high_match') return 'High match';
        if (band === 'moderate_match') return 'Moderate match';
        if (band === 'below_threshold') return 'Below threshold';
        return 'Band unavailable';
    }

    function bandClass(band) {
        if (band === 'high_match') return 'border-emerald-600/40 bg-emerald-600/10 text-emerald-800 dark:text-emerald-200';
        if (band === 'moderate_match') return 'border-amber-600/40 bg-amber-500/10 text-amber-800 dark:text-amber-200';
        if (band === 'below_threshold') return 'border-rose-600/40 bg-rose-500/10 text-rose-800 dark:text-rose-200';
        return 'border-border bg-muted text-foreground';
    }

    function indexValue(recommendation) {
        if (recommendation?.compatibility_index === null || recommendation?.compatibility_index === undefined) return null;
        const value = Number(recommendation.compatibility_index);
        return Number.isFinite(value) ? value : null;
    }

    function boundedIndex(value) {
        return Math.max(0, Math.min(100, value));
    }

    function indexLabel(value) {
        if (value === null || value === undefined) return '';
        const num = Number(value);
        if (!Number.isFinite(num)) return String(value);
        return Number.isInteger(num) ? String(num) : num.toFixed(2);
    }

    function backendLabel(backend) {
        if (backend === 'tabpfn_pytorch') return 'TabPFN transformer';
        if (['bayesian_prior_kernel_v1', 'in_process_fallback', 'in_process_heuristics'].includes(backend)) return 'Not an approved neural backend';
        return backend || 'Backend not reported';
    }
</script>
<svelte:window onkeydown={(event) => {
    if (mobileRadarOpen && event.key === 'Escape') {
        mobileRadarOpen = false;
    }
}} />

<Card.Root class="overflow-hidden border-border/70 shadow-sm lg:overflow-clip">
    <Card.Header class="border-b bg-muted/20 pb-4">
        <div class={scopedProgramCode ? 'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between' : 'flex flex-wrap items-start justify-between gap-3'}>
            <div class="min-w-0 flex-1">
                <Card.Title class="text-base font-semibold">{scopedProgramCode ? `TABFM — ${scopedProgramCode}` : 'Course Placement & Compatibility Triage'}</Card.Title>
                <Card.Description class="mt-1 text-sm">{scopedProgramCode ? 'Read-only program-specific analysis. This advisory result does not determine admission.' : effectiveSourceKind === 'recorded' ? 'Stored compatibility analysis; this is not a current evaluation.' : 'Multi-dimensional applicant evaluation for post-examination counseling.'}</Card.Description>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline">{sourceLabel}{sourceVersionLabel ? ` · ${sourceVersionLabel}` : ' · version unavailable'}</Badge>
                {#if isEvaluated}
                    <Badge variant="secondary">{effectiveSourceKind === 'recorded' ? 'Recorded analysis' : 'Advisory decision support'}</Badge>
                {/if}
            </div>
        </div>
    </Card.Header>

    <Card.Content class="space-y-4 pt-4">
        {#if scopedProgramCode}
            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground" aria-label="Analysis provenance">
                {#if sourceVersionLabel}<Badge variant="outline">{sourceLabel} · {sourceVersionLabel}</Badge>{/if}
                {#if model?.evaluated_at}<span>Evaluated {model.evaluated_at}</span>{/if}
                {#if model?.inference_backend}<span>· {model.inference_backend}</span>{/if}
                {#if model?.engine_version}<span>· engine {model.engine_version}</span>{/if}
            </div>
            {#if isHistoricalOnly}
                <div class="rounded-lg border border-border/70 bg-muted/20 p-4" role="status">
                    <h3 class="text-sm font-semibold text-foreground">Historical result · not current advice</h3>
                    <p class="mt-1 text-sm text-muted-foreground">{model?.message ?? 'This recorded result is not applicable to the current assessment. It is retained for history and is not shown as current advice.'}</p>
                </div>
            {:else if isUnavailable}
                <div class="rounded-lg border border-amber-600/40 bg-amber-50/50 p-4 dark:bg-amber-950/20" role="status">
                    <h3 class="text-sm font-semibold text-foreground">TABFM unavailable</h3>
                    <p class="mt-1 text-sm text-foreground">{model?.message ?? 'A valid TABFM result is not available for this program.'}</p>
                </div>
            {:else if !isEvaluated}
                <div class="rounded-lg border border-border/70 bg-muted/20 p-4" role="status">
                    <h3 class="text-sm font-semibold text-foreground">{model?.status === 'result_not_ready' ? 'TABFM result not ready' : model?.status === 'no_analysis' ? 'TABFM not evaluated' : 'TABFM result unavailable'}</h3>
                    <p class="mt-1 break-words text-sm text-muted-foreground">{model?.message ?? (model?.status === 'result_not_ready' ? 'A TABFM result will be available when the required result data is ready.' : 'No current TABFM result is available for this program.')}</p>
                </div>
            {:else if !selectedProgram}
                <div class="rounded-lg border border-border/70 bg-muted/20 p-4" role="status">
                    <h3 class="text-sm font-semibold text-foreground">No program result</h3>
                    <p class="mt-1 text-sm text-muted-foreground">This evaluation did not return a result for {scopedProgramCode}.</p>
                </div>
            {:else}
                <div class="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-2 xl:items-start">
                    <AptitudeRadarHexagon radarComparison={selectedProgram.radar_comparison ?? null} {selectedProgram} />
                    <section class="min-w-0 space-y-3" aria-label={`${scopedProgramCode} analysis explanation`}>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class={bandClass(selectedProgram.band)}>{bandLabel(selectedProgram.band)}</Badge>
                            {#if indexValue(selectedProgram) !== null}
                                <span class="font-mono text-base font-bold tabular-nums text-foreground">{indexLabel(indexValue(selectedProgram))}% Compatibility index</span>
                            {:else}
                                <span class="text-sm font-medium text-muted-foreground">Compatibility index unavailable</span>
                            {/if}
                        </div>
                        <p class="text-sm text-muted-foreground">This compatibility index is advisory and is not an admission probability.</p>
                        <p class="text-sm font-medium text-foreground">{selectedProgram.passed_cutoff === true ? 'Met recorded cutoff' : selectedProgram.passed_cutoff === false ? 'Below recorded cutoff' : 'Cutoff result unavailable'}</p>
                        {#if selectedProgram.drivers?.length}
                            <div class="space-y-2 border-t border-border/50 pt-3">
                                <h3 class="text-sm font-semibold text-foreground">Result explanation</h3>
                                <ul class="list-disc space-y-1 pl-5 text-sm text-foreground">
                                    {#each selectedProgram.drivers as driver}
                                        <li class="break-words">{driver}</li>
                                    {/each}
                                </ul>
                            </div>
                        {:else}
                            <p class="border-t border-border/50 pt-3 text-sm text-muted-foreground">No explanation details were supplied for this program result.</p>
                        {/if}
                    </section>
                </div>
            {/if}
        {:else}
        {#if isUnavailable && !isHistoricalOnly}
            <div class="rounded-lg border border-amber-600/40 bg-amber-50/50 p-4 dark:bg-amber-950/20" role="status">
                <h3 class="text-sm font-semibold text-foreground">TabFM evaluation unavailable</h3>
                <p class="mt-1 text-sm text-foreground">{model?.message ?? (model?.status === 'evaluated' ? 'This result does not include valid neural inference provenance and cannot be shown.' : 'No compatibility result is available.')}</p>
            </div>
            {#if effectiveVocationalProfile && fallbackProgram}
                <div class="pt-2">
                    <HollandRadarHexagon
                        vocationalProfile={effectiveVocationalProfile}
                        selectedProgram={fallbackProgram}
                        applicantEvidence={model?.applicant_evidence}
                        {onOpenSupplementalEvidence}
                    />
                </div>
            {/if}
        {:else if isHistoricalOnly}
            <div class="rounded-lg border border-border/70 bg-muted/20 p-4" role="status">
                <h3 class="text-sm font-semibold text-foreground">Historical result · not current advice</h3>
                <p class="mt-1 text-sm text-muted-foreground">This recorded result is not applicable to the current assessment. It is kept for history and is not shown as a current score or programme ranking.</p>
            </div>
            {#if effectiveVocationalProfile && fallbackProgram}
                <div class="pt-2">
                    <HollandRadarHexagon
                        vocationalProfile={effectiveVocationalProfile}
                        selectedProgram={fallbackProgram}
                        applicantEvidence={model?.applicant_evidence}
                        {onOpenSupplementalEvidence}
                    />
                </div>
            {/if}
        {:else if !isEvaluated}
            <div class="rounded-lg border border-border/70 bg-muted/20 p-4" role="status">
                <h3 class="text-sm font-semibold text-foreground">TabFM result not available</h3>
                <p class="mt-1 text-sm text-muted-foreground">{model?.message ?? 'A TabFM result has not been generated for this applicant.'}</p>
            </div>
            {#if effectiveVocationalProfile && fallbackProgram}
                <div class="pt-2">
                    <HollandRadarHexagon
                        vocationalProfile={effectiveVocationalProfile}
                        selectedProgram={fallbackProgram}
                        applicantEvidence={model?.applicant_evidence}
                        {onOpenSupplementalEvidence}
                    />
                </div>
            {/if}
        {:else}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-foreground">Ranked programme matches</h3>
                    {#if model?.benchmark_name}
                        <p class="text-sm text-muted-foreground">Benchmark: {model.benchmark_name}</p>
                    {/if}
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    {#if evaluationBackendLabel}
                        <Badge variant="outline" class="font-mono text-xs">Backend · {evaluationBackendLabel}</Badge>
                    {/if}
                    <span class="text-sm font-medium text-foreground">CMI · 0–100 index</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                <p>CMI is an index score, not an admission probability.</p>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 font-medium text-primary hover:underline cursor-pointer focus-visible:outline-none"
                    onclick={() => { infoDialogOpen = true; }}
                >
                    <Info class="size-3.5" />
                    <span>How CMI works</span>
                </button>
            </div>

            {#if recommendations.length === 0}
                <p class="rounded-lg border border-border/70 bg-muted/20 p-4 text-sm text-foreground" role="status">The evaluation did not return ranked programmes.</p>
            {:else}
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(340px,1fr)] lg:items-start">
                    <div class="min-w-0 space-y-2">
                        <ol class="space-y-3" aria-label="Ranked programme matches">
                            {#each topPrograms as recommendation, rankIndex (recommendation.program_code)}
                                {@const isSelected = selectedProgram?.program_code === recommendation.program_code}
                                {@const index = indexValue(recommendation)}
                                <li>
                                    <div class={`rounded-xl border transition-colors ${isSelected ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/20' : 'border-border/70 bg-background hover:bg-muted/30'}`}>
                                        <button
                                            type="button"
                                            class="w-full p-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60 cursor-pointer block rounded-xl"
                                            aria-label={`Select ${recommendation.program_code} for comparison`}
                                            onclick={() => { selectedProgramCode = recommendation.program_code; }}
                                            aria-pressed={isSelected}
                                        >
                                            <span class="flex w-full flex-wrap items-start justify-between gap-2">
                                                <span class="flex min-w-0 items-start gap-2">
                                                    <span class={`shrink-0 rounded-md px-2 py-1 font-mono text-sm font-bold ${isSelected ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'}`}>#{recommendation.rank ?? rankIndex + 1}</span>
                                                    <span class="min-w-0">
                                                        <span class="block break-words font-bold text-foreground">{recommendation.program_code}</span>
                                                        <span class="block break-words text-sm text-foreground">{recommendation.program_name}</span>
                                                    </span>
                                                </span>
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline" class={bandClass(recommendation.band)}>{bandLabel(recommendation.band)}</Badge>
                                                    {#if index !== null}
                                                        <span class="font-mono text-base font-bold tabular-nums text-foreground">{indexLabel(index)}% Match</span>
                                                    {:else}
                                                        <span class="text-sm font-semibold text-foreground">Match index unavailable</span>
                                                    {/if}
                                                </span>
                                            </span>
                                            {#if index !== null}
                                                <div
                                                    class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-muted"
                                                    role="progressbar"
                                                    aria-label={`${recommendation.program_code} CMI index`}
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                    aria-valuenow={boundedIndex(index)}
                                                >
                                                    <div class="h-full rounded-full bg-emerald-600 transition-[width] duration-200" style={`width: ${boundedIndex(index)}%`}></div>
                                                </div>
                                            {/if}
                                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                                <Badge variant="outline" class={recommendation.passed_cutoff === true ? 'border-emerald-600/40 bg-emerald-600/10 text-emerald-800 dark:text-emerald-200' : recommendation.passed_cutoff === false ? 'border-amber-600/40 bg-amber-500/10 text-amber-800 dark:text-amber-200' : 'border-border text-foreground'}>
                                                    {recommendation.passed_cutoff === true ? 'Met recorded cutoff' : recommendation.passed_cutoff === false ? 'Below recorded cutoff' : 'Cutoff unavailable'}
                                                </Badge>
                                                {#if recommendation.quota?.configured}
                                                    <span class="rounded-md border border-border/70 px-2 py-1 text-sm font-medium text-foreground" title={recommendation.quota.capacity !== null && recommendation.quota.capacity !== undefined ? `${recommendation.quota.committed}/${recommendation.quota.capacity} committed` : 'Capacity not reported'}>{recommendation.quota.status === 'full' ? 'Quota full' : typeof recommendation.quota.remaining === 'number' ? `${recommendation.quota.remaining} seats left` : 'Capacity unavailable'}</span>
                                                {/if}
                                            </div>
                                        </button>
                                        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border/50 px-3 py-2">
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                aria-label={`View advising notes and threads for ${recommendation.program_code}`}
                                                onclick={() => openProgramNotesThreads(recommendation, 'notes')}
                                            >
                                                <MessageSquareText class="size-3.5 text-primary" aria-hidden="true" />
                                                <span>Notes &amp; Threads</span>
                                                {#if (notesForProgram(recommendation.program_code).length + threadsForProgram(recommendation.program_code).length) > 0}
                                                    <Badge variant="secondary" class="px-1.5 py-0 font-mono text-[10px]">
                                                        {notesForProgram(recommendation.program_code).length + threadsForProgram(recommendation.program_code).length}
                                                    </Badge>
                                                {/if}
                                            </button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                class="lg:hidden justify-center font-semibold gap-1.5"
                                                onclick={() => {
                                                    selectedProgramCode = recommendation.program_code;
                                                    mobileRadarOpen = true;
                                                }}
                                            >
                                                📊 Compare on Radar
                                            </Button>
                                        </div>
                                    </div>
                                </li>
                            {/each}
                        </ol>

                        {#if remainingPrograms.length > 0}
                            <div>
                                <button type="button" class="flex min-h-11 w-full items-center justify-between rounded-lg border border-dashed border-border/70 px-3 py-2 text-sm font-medium text-foreground hover:bg-muted/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60" onclick={() => { showAllPrograms = !showAllPrograms; }} aria-expanded={showAllPrograms}>
                                    <span>{showAllPrograms ? 'Hide' : 'Show'} {remainingPrograms.length} more ranked programmes</span>
                                    <ChevronDown class={`size-4 transition-transform ${showAllPrograms ? 'rotate-180' : ''}`} aria-hidden="true" />
                                </button>
                                {#if showAllPrograms}
                                    <ol class="mt-3 space-y-3" start="5" aria-label="Additional ranked programme matches">
                                        {#each remainingPrograms as recommendation, rankIndex (recommendation.program_code)}
                                            {@const isSelected = selectedProgram?.program_code === recommendation.program_code}
                                            {@const index = indexValue(recommendation)}
                                            <li>
                                                <div class={`rounded-xl border transition-colors ${isSelected ? 'border-primary/60 bg-primary/5 ring-1 ring-primary/20' : 'border-border/70 bg-background hover:bg-muted/30'}`}>
                                                    <button
                                                        type="button"
                                                        class="w-full p-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/60 cursor-pointer block rounded-xl"
                                                        aria-label={`Select ${recommendation.program_code} for comparison`}
                                                        onclick={() => { selectedProgramCode = recommendation.program_code; }}
                                                        aria-pressed={isSelected}
                                                    >
                                                        <span class="flex w-full flex-wrap items-start justify-between gap-2">
                                                            <span class="flex min-w-0 items-start gap-2">
                                                                <span class={`shrink-0 rounded-md px-2 py-1 font-mono text-sm font-bold ${isSelected ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'}`}>#{recommendation.rank ?? rankIndex + 5}</span>
                                                                <span class="min-w-0">
                                                                    <span class="block break-words font-bold text-foreground">{recommendation.program_code}</span>
                                                                    <span class="block break-words text-sm text-foreground">{recommendation.program_name}</span>
                                                                </span>
                                                            </span>
                                                            <span class="flex flex-wrap items-center gap-2">
                                                                <Badge variant="outline" class={bandClass(recommendation.band)}>{bandLabel(recommendation.band)}</Badge>
                                                                {#if index !== null}
                                                                    <span class="font-mono text-base font-bold tabular-nums text-foreground">{indexLabel(index)}% Match</span>
                                                                {:else}
                                                                    <span class="text-sm font-semibold text-foreground">Match index unavailable</span>
                                                                {/if}
                                                            </span>
                                                        </span>
                                                        {#if index !== null}
                                                            <div
                                                                class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-muted"
                                                                role="progressbar"
                                                                aria-label={`${recommendation.program_code} CMI index`}
                                                                aria-valuemin="0"
                                                                aria-valuemax="100"
                                                                aria-valuenow={boundedIndex(index)}
                                                            >
                                                                <div class="h-full rounded-full bg-emerald-600 transition-[width] duration-200" style={`width: ${boundedIndex(index)}%`}></div>
                                                            </div>
                                                        {/if}
                                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                                            <Badge variant="outline" class={recommendation.passed_cutoff === true ? 'border-emerald-600/40 bg-emerald-600/10 text-emerald-800 dark:text-emerald-200' : recommendation.passed_cutoff === false ? 'border-amber-600/40 bg-amber-500/10 text-amber-800 dark:text-amber-200' : 'border-border text-foreground'}>
                                                                {recommendation.passed_cutoff === true ? 'Met recorded cutoff' : recommendation.passed_cutoff === false ? 'Below recorded cutoff' : 'Cutoff unavailable'}
                                                            </Badge>
                                                            {#if recommendation.quota?.configured}
                                                                <span class="rounded-md border border-border/70 px-2 py-1 text-sm font-medium text-foreground" title={recommendation.quota.capacity !== null && recommendation.quota.capacity !== undefined ? `${recommendation.quota.committed}/${recommendation.quota.capacity} committed` : 'Capacity not reported'}>{recommendation.quota.status === 'full' ? 'Quota full' : typeof recommendation.quota.remaining === 'number' ? `${recommendation.quota.remaining} seats left` : 'Capacity unavailable'}</span>
                                                            {/if}
                                                        </div>
                                                    </button>
                                                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border/50 px-3 py-2">
                                                        <button
                                                            type="button"
                                                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                            aria-label={`View advising notes and threads for ${recommendation.program_code}`}
                                                            onclick={() => openProgramNotesThreads(recommendation, 'notes')}
                                                        >
                                                            <MessageSquareText class="size-3.5 text-primary" aria-hidden="true" />
                                                            <span>Notes &amp; Threads</span>
                                                            {#if (notesForProgram(recommendation.program_code).length + threadsForProgram(recommendation.program_code).length) > 0}
                                                                <Badge variant="secondary" class="px-1.5 py-0 font-mono text-[10px]">
                                                                    {notesForProgram(recommendation.program_code).length + threadsForProgram(recommendation.program_code).length}
                                                                </Badge>
                                                            {/if}
                                                        </button>
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            class="lg:hidden justify-center font-semibold gap-1.5"
                                                            onclick={() => {
                                                                selectedProgramCode = recommendation.program_code;
                                                                mobileRadarOpen = true;
                                                            }}
                                                        >
                                                            📊 Compare on Radar
                                                        </Button>
                                                    </div>
                                                </div>
                                            </li>
                                        {/each}
                                    </ol>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <aside class="hidden space-y-4 lg:sticky lg:top-4 lg:block" aria-label="Aptitude comparison and advised-pool action">
                        {#if isEvaluated && selectedProgram}
                            <div class="grid w-full grid-cols-2 gap-1 rounded-xl border border-border/50 bg-muted/70 p-1 text-xs font-semibold select-none" role="group" aria-label="Radar chart view selector">
                                <button
                                    type="button"
                                    aria-current={radarViewMode === 'cognitive' ? 'true' : undefined}
                                    class="flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-center transition-all {radarViewMode === 'cognitive' ? 'bg-background font-bold text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                                    onclick={() => { radarViewMode = 'cognitive'; }}
                                >
                                    <span aria-hidden="true">🧠</span>
                                    <span>Cognitive Aptitude</span>
                                </button>
                                <button
                                    type="button"
                                    aria-current={radarViewMode === 'vocational' ? 'true' : undefined}
                                    class="flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-center transition-all {radarViewMode === 'vocational' ? 'bg-background font-bold text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                                    onclick={() => { radarViewMode = 'vocational'; }}
                                >
                                    <span aria-hidden="true">📜</span>
                                    <span>Curriculum Fit</span>
                                </button>
                            </div>
                            {#if radarViewMode === 'cognitive'}
                                <AptitudeRadarHexagon
                                    radarComparison={selectedProgram.radar_comparison ?? null}
                                    selectedProgram={selectedProgram}
                                />
                            {:else}
                                <HollandRadarHexagon
                                    vocationalProfile={vocationalProfile ?? model?.vocational_profile ?? model?.applicant_evidence?.vocational_profile}
                                    selectedProgram={selectedProgram}
                                    applicantEvidence={model?.applicant_evidence}
                                    {onOpenSupplementalEvidence}
                                />
                            {/if}
                            {#if hasAdviceHandoff && effectiveSourceKind !== 'draft' && (effectiveSourceKind !== 'recorded' || model?.current_applicable === true)}
                                <Button
                                    type="button"
                                    class="w-full justify-center font-semibold shadow-xs"
                                    aria-label={`Add ${selectedProgram.program_code} to the advised pool through the advising decision`}
                                    onclick={() => onAddToAdvisedPool(selectedProgram)}
                                >
                                    + Add {selectedProgram.program_code} to Advised Pool
                                </Button>
                            {/if}
                        {/if}
                    </aside>
                    {#if isEvaluated && selectedProgram}
                        <Dialog.Root bind:open={mobileRadarOpen}>
                            <Dialog.Content
                                class="border-border/80 bg-card fixed inset-x-0 bottom-0 top-auto left-0 right-0 z-[80] flex max-h-[90dvh] w-full max-w-none translate-x-0 translate-y-0 flex-col overflow-hidden rounded-t-2xl border p-0 shadow-2xl sm:inset-auto sm:top-1/2 sm:left-1/2 sm:max-w-xl sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-2xl lg:hidden"
                                showCloseButton={false}
                            >
                                <div class="shrink-0 px-4 pt-3">
                                    <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-muted-foreground/30" aria-hidden="true"></div>
                                    <div class="flex items-start justify-between gap-4 border-b border-border/60 pb-4">
                                        <div class="min-w-0">
                                            <Dialog.Title class="break-words text-base font-semibold tracking-tight text-foreground">
                                                {selectedProgram.program_code} · {selectedProgram.program_name}
                                            </Dialog.Title>
                                            <Dialog.Description class="mt-1 text-sm text-muted-foreground">
                                                {indexLabel(selectedProgram.compatibility_index)}% Match · {selectedProgram.quota?.remaining ?? 0} seats left
                                            </Dialog.Description>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="size-11 shrink-0 rounded-full"
                                            aria-label="Close radar comparison"
                                            onclick={() => { mobileRadarOpen = false; }}
                                        >
                                            <span aria-hidden="true">✕</span>
                                        </Button>
                                    </div>
                                </div>
                                <div class="min-h-0 flex-1 touch-pan-y overflow-y-auto overscroll-contain px-4 py-4">
                                    <div class="mb-3 grid w-full grid-cols-2 gap-1 rounded-xl border border-border/50 bg-muted/70 p-1 text-xs font-semibold select-none" role="group" aria-label="Radar chart view selector">
                                        <button
                                            type="button"
                                            aria-current={radarViewMode === 'cognitive' ? 'true' : undefined}
                                            class="flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-center transition-all {radarViewMode === 'cognitive' ? 'bg-background font-bold text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                                            onclick={() => { radarViewMode = 'cognitive'; }}
                                        >
                                            <span aria-hidden="true">🧠</span>
                                            <span>Cognitive Aptitude</span>
                                        </button>
                                        <button
                                            type="button"
                                            aria-current={radarViewMode === 'vocational' ? 'true' : undefined}
                                            class="flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-center transition-all {radarViewMode === 'vocational' ? 'bg-background font-bold text-foreground shadow-xs' : 'text-muted-foreground hover:text-foreground'}"
                                            onclick={() => { radarViewMode = 'vocational'; }}
                                        >
                                            <span aria-hidden="true">📜</span>
                                            <span>Curriculum Fit</span>
                                        </button>
                                    </div>
                                    {#if radarViewMode === 'cognitive'}
                                        <AptitudeRadarHexagon
                                            radarComparison={selectedProgram.radar_comparison ?? null}
                                            selectedProgram={selectedProgram}
                                        />
                                    {:else}
                                        <HollandRadarHexagon
                                            vocationalProfile={vocationalProfile ?? model?.vocational_profile ?? model?.applicant_evidence?.vocational_profile}
                                            selectedProgram={selectedProgram}
                                            applicantEvidence={model?.applicant_evidence}
                                            {onOpenSupplementalEvidence}
                                        />
                                    {/if}
                                </div>
                                {#if hasAdviceHandoff && effectiveSourceKind !== 'draft' && (effectiveSourceKind !== 'recorded' || model?.current_applicable === true)}
                                    <div class="shrink-0 border-t border-border/70 bg-card px-4 pt-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                                        <Button
                                            type="button"
                                            class="w-full justify-center font-semibold shadow-xs"
                                            aria-label={`Add ${selectedProgram.program_code} to the advised pool through the advising decision`}
                                            onclick={() => {
                                                mobileRadarOpen = false;
                                                onAddToAdvisedPool(selectedProgram);
                                            }}
                                        >
                                            + Add {selectedProgram.program_code} to Advised Pool
                                        </Button>
                                    </div>
                                {/if}
                            </Dialog.Content>
                        </Dialog.Root>
                    {/if}
                    <Dialog.Root bind:open={notesThreadsOpen}>
                        <Dialog.Content class="sm:max-w-xl max-h-[85vh] flex flex-col p-0">
                            {#if activeModalProgram}
                                {@const modalNotes = notesForProgram(activeModalProgram.program_code)}
                                {@const modalThreads = threadsForProgram(activeModalProgram.program_code)}
                                <div class="border-b border-border/60 px-5 py-4">
                                    <Dialog.Header>
                                        <Dialog.Title class="flex items-center gap-2 text-base font-bold">
                                            <span class="rounded bg-primary/10 px-2 py-0.5 font-mono text-xs text-primary">{activeModalProgram.program_code}</span>
                                            <span>{activeModalProgram.program_name}</span>
                                        </Dialog.Title>
                                        <Dialog.Description class="text-xs">
                                            Program advising guidance and case discussion threads for {activeModalProgram.program_code}.
                                        </Dialog.Description>
                                    </Dialog.Header>
                                </div>
                                <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                                    <Tabs.Root value={notesThreadsTab} onValueChange={(v) => { notesThreadsTab = v; }}>
                                        <Tabs.List class="mb-4 grid w-full grid-cols-2 rounded-lg border border-border/70 bg-muted/60 p-1">
                                            <Tabs.Trigger value="notes" class="flex items-center justify-center gap-1.5 rounded-md py-1.5 text-xs font-semibold">
                                                <ScrollText class="size-3.5" />
                                                <span>Advising Notes ({modalNotes.length})</span>
                                            </Tabs.Trigger>
                                            <Tabs.Trigger value="threads" class="flex items-center justify-center gap-1.5 rounded-md py-1.5 text-xs font-semibold">
                                                <MessageSquareText class="size-3.5" />
                                                <span>Threads ({modalThreads.length})</span>
                                            </Tabs.Trigger>
                                        </Tabs.List>

                                        <Tabs.Content value="notes" class="space-y-3">
                                            {#if modalNotes.length === 0}
                                                <p class="rounded-lg border border-dashed border-border/70 bg-muted/20 p-4 text-center text-xs text-muted-foreground">
                                                    No published advising notes for {activeModalProgram.program_code}.
                                                </p>
                                            {:else}
                                                {#each modalNotes as note (note.revision_id ?? note.note_id)}
                                                    <div class="space-y-2 rounded-lg border border-border/80 bg-card p-3.5 text-sm">
                                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                                            <span class="font-semibold text-foreground">{note.program_code} · Revision {note.version ?? 1}</span>
                                                            {#if note.published_at}
                                                                <span class="text-xs text-muted-foreground">{note.published_at}</span>
                                                            {/if}
                                                        </div>
                                                        <p class="whitespace-pre-wrap break-words text-xs leading-relaxed text-foreground">{note.content}</p>
                                                        {#if note.author_name}
                                                            <p class="text-[11px] text-muted-foreground">Author: {note.author_name}</p>
                                                        {/if}
                                                    </div>
                                                {/each}
                                            {/if}
                                        </Tabs.Content>

                                        <Tabs.Content value="threads" class="space-y-3">
                                            {#if modalThreads.length === 0}
                                                <div class="space-y-2 rounded-lg border border-dashed border-border/70 bg-muted/20 p-4 text-center">
                                                    <p class="text-xs text-muted-foreground">No case threads recorded for {activeModalProgram.program_code}.</p>
                                                    {#if typeof onOpenThread === 'function'}
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        class="h-8 text-xs"
                                                        onclick={() => {
                                                            notesThreadsOpen = false;
                                                            onOpenThread(null);
                                                        }}
                                                    >
                                                        Open Case Threads Tab
                                                    </Button>
                                                    {/if}
                                                </div>
                                            {:else}
                                                {#each modalThreads as thread (thread.id)}
                                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/80 bg-card p-3.5 text-sm">
                                                        <div class="min-w-0 space-y-1">
                                                            <div class="flex flex-wrap items-center gap-1.5">
                                                                <span class="font-semibold text-foreground">{thread.subject}</span>
                                                                {#if thread.unread_count > 0}
                                                                    <Badge variant="default" class="text-[10px]">{thread.unread_count} unread</Badge>
                                                                {/if}
                                                            </div>
                                                            <p class="text-xs text-muted-foreground">
                                                                {thread.thread_type === 'internal_ph_guidance' ? 'Internal PH · Guidance' : 'Applicant Support'} · {thread.status ?? 'open'}
                                                            </p>
                                                        </div>
                                                        {#if typeof onOpenThread === 'function'}
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            class="h-8 gap-1 text-xs"
                                                            onclick={() => {
                                                                notesThreadsOpen = false;
                                                                onOpenThread(thread);
                                                            }}
                                                        >
                                                            <span>Open Thread</span>
                                                            <ArrowUpRight class="size-3.5" />
                                                        </Button>
                                                        {/if}
                                                    </div>
                                                {/each}
                                            {/if}
                                        </Tabs.Content>
                                    </Tabs.Root>
                                </div>
                            {/if}
                        </Dialog.Content>
                    </Dialog.Root>
                </div>
            {/if}
            {/if}

        {/if}


        {#if !scopedProgramCode}
        <details class="border-t border-border/50 pt-3 text-sm text-foreground">
            <summary class="min-h-10 cursor-pointer py-2 font-semibold">Technical details</summary>
            <dl class="mt-3 grid gap-x-3 gap-y-1 sm:grid-cols-[max-content_minmax(0,1fr)]">
                <dt>Result status</dt><dd>{model?.status ?? 'not reported'}</dd>
                <dt>Source</dt><dd>{sourceLabel}{sourceVersionLabel ? ` ${sourceVersionLabel}` : ' version unavailable'}</dd>
                <dt>Inference backend</dt><dd>{effectiveSourceKind === 'recorded' ? (model?.inference_backend ?? 'not reported') : backendLabel(model?.inference_backend)}</dd>
                {#if model?.engine_version}<dt>Engine version</dt><dd>{model.engine_version}</dd>{/if}
                {#if model?.evaluated_at}<dt>Evaluated</dt><dd>{model.evaluated_at}</dd>{/if}
                {#if model?.evaluation_context}<dt>Evaluation context</dt><dd>{JSON.stringify(model.evaluation_context)}</dd>{/if}
                {#if model?.fallback_reason}<dt>Result reason</dt><dd>{model.fallback_reason}</dd>{/if}
                {#if model?.message}<dt>Provider message</dt><dd>{model.message}</dd>{/if}
            </dl>
        </details>
        {/if}
    </Card.Content>
{#if infoDialogOpen}
    <Dialog.Root bind:open={infoDialogOpen}>
        <Dialog.Content class="border-border/80 bg-card max-w-lg rounded-2xl p-6 shadow-2xl">
            <Dialog.Title class="text-base font-bold text-foreground">
                How Course Match Index (CMI) Works
            </Dialog.Title>
            <div class="mt-3 text-sm text-muted-foreground space-y-2">
                <p>
                    The Course Match Index (CMI) is an advisory decision-support tool powered by TabFM's in-context learning engine:
                </p>
                <ul class="list-disc list-inside space-y-1.5 text-xs text-foreground/90 mt-2">
                    <li><strong>Benchmark Exemplars:</strong> Measures the applicant's standardized aptitude percentiles and High School GWA against 5 department benchmark tiers.</li>
                    <li><strong>Cutoff Floor Clearance:</strong> Higher match percentages do not waive department prerequisites; all Tier 3 Cutoff Floors must be independently satisfied.</li>
                    <li><strong>Counselor Authority:</strong> CMI outputs are non-binding. The Guidance Counselor and Admissions Committee retain final placement authority.</li>
                </ul>
            </div>
            <div class="mt-5 flex justify-end">
                <Button variant="outline" size="sm" onclick={() => { infoDialogOpen = false; }}>
                    Close
                </Button>
            </div>
        </Dialog.Content>
    </Dialog.Root>
{/if}
</Card.Root>
