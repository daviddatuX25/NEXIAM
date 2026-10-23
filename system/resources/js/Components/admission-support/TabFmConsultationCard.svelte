<script>
    import ApplicantEvidenceBar from './ApplicantEvidenceBar.svelte';
    import TabFmResultView from './TabFmResultView.svelte';
    import { hasValidTabFmNeuralProvenance } from '@/lib/tabFmResultGuards';

    let { model, available_programs = [], onAddToAdvisedPool, showEvidenceBar = true, vocationalProfile = null, onOpenSupplementalEvidence, publishedAdvisingNotes = [], threads = [], onOpenThread, scopedProgramCode = null } = $props();
    const canAttachAdviceCallback = $derived(
        typeof onAddToAdvisedPool === 'function'
        && model?.status === 'evaluated'
        && model?.evaluated !== false
        && hasValidTabFmNeuralProvenance(model)
        && Array.isArray(model?.recommendations)
        && model.recommendations.length > 0
        && (model?.source_kind !== 'recorded' || model?.current_applicable === true),
    );

    const recordedResult = $derived(model?.recorded_result ?? null);
    const recordedPayload = $derived.by(() => {
        const payload = recordedResult?.payload;
        if (
            payload === null
            || typeof payload !== 'object'
            || Array.isArray(payload)
            || payload.status !== 'evaluated'
            || !Array.isArray(payload.recommendations)
        ) {
            return null;
        }

        return {
            ...payload,
            source_kind: 'recorded',
            current_applicable: false,
            evaluated_at: payload.evaluated_at ?? recordedResult.evaluated_at ?? recordedResult.recorded_at ?? null,
            source_version: payload.source_version ?? payload.benchmark_version ?? recordedResult.benchmark_version ?? null,
            benchmark_version: payload.benchmark_version ?? recordedResult.benchmark_version ?? null,
            snapshot_version: payload.snapshot_version ?? recordedResult.snapshot_version ?? null,
            inference_backend: payload.inference_backend ?? recordedResult.inference_backend ?? null,
            engine_version: payload.engine_version ?? recordedResult.engine_version ?? null,
            evaluation_context: payload.evaluation_context ?? recordedResult.assessment_context ?? null,
        };
    });
    const recordedTimestamp = $derived(recordedResult?.evaluated_at ?? recordedResult?.recorded_at ?? recordedResult?.payload?.evaluated_at ?? null);
    const safeRecordedPayload = $derived(recordedPayload ?? {
        status: 'unavailable',
        evaluated: false,
        source_kind: 'recorded',
        current_applicable: false,
        recommendations: [],
        message: 'The stored TabFM result is malformed and cannot be displayed.',
    });
</script>

<div class="space-y-4">
    {#if !scopedProgramCode && showEvidenceBar && model?.applicant_evidence && Object.keys(model.applicant_evidence).length > 0}
        <ApplicantEvidenceBar evidence={model.applicant_evidence} inputSchema={model?.input_schema} />
    {/if}
    {#if !scopedProgramCode && model?.benchmark_context_changed}
        <p class="rounded-md border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-muted-foreground">
            The displayed result uses an earlier benchmark version. Existing recommendations remain pinned to the version that produced them.
        </p>
    {/if}

    {#if recordedResult && !scopedProgramCode}
        <details class="rounded-lg border border-border/70 bg-muted/10 px-3 py-2">
            <summary class="min-h-10 cursor-pointer py-2 text-sm font-semibold text-foreground">
                Recorded analysis{recordedTimestamp ? ` · ${recordedTimestamp}` : ''}
            </summary>
            <div class="pt-2">
                <TabFmResultView model={safeRecordedPayload} sourceKind="recorded" availablePrograms={available_programs} {vocationalProfile} {onOpenSupplementalEvidence} {publishedAdvisingNotes} {threads} {onOpenThread} {scopedProgramCode} />
            </div>
        </details>
    {/if}

    {#if canAttachAdviceCallback}
        <TabFmResultView model={model} sourceKind={model?.source_kind === 'recorded' ? 'recorded' : 'active'} availablePrograms={available_programs} {onAddToAdvisedPool} {vocationalProfile} {onOpenSupplementalEvidence} {publishedAdvisingNotes} {threads} {onOpenThread} {scopedProgramCode} />
    {:else}
        <TabFmResultView model={model} sourceKind={model?.source_kind === 'recorded' ? 'recorded' : 'active'} availablePrograms={available_programs} {vocationalProfile} {onOpenSupplementalEvidence} {publishedAdvisingNotes} {threads} {onOpenThread} {scopedProgramCode} />
    {/if}
</div>
