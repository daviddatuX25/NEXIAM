<script>
    import { useForm } from '@inertiajs/svelte';
    import { X } from 'lucide-svelte';
    import { newIdempotencyKey } from '@/lib/utils';
    import { toastSuccess } from '@/lib/toast.js';
    import { GWA_SCALES, getGwaScaleConfig, normalizeGwaValue } from '@/lib/gwa-scale.js';

    let {
        open = false,
        onClose = () => {},
        attemptId = null,
        baselineEvidence = {},
        benchmarkActivationId = null,
        schemaDigest = null,
    } = $props();

    const form = useForm({
        gwa: null,
        gwa_scale: 'percentage_100',
        counselor_notes: '',
        evidence_source: 'examinee_statement',
        benchmark_activation_id: null,
        schema_digest: null,
        idempotency_key: newIdempotencyKey(),
    });
    let wasOpen = $state(false);
    let sessionActivationId = $state(null);
    let sessionSchemaDigest = $state(null);
    let gwa = $state('');
    let gwaScale = $state('percentage_100');
    let counselorNotes = $state('');
    const activeGwaScale = $derived(getGwaScaleConfig(gwaScale));
    const convertedPreview = $derived(
        gwaScale !== 'percentage_100' ? normalizeGwaValue(gwa, gwaScale) : null,
    );
    const hasEvidenceContext = $derived(benchmarkActivationId !== null && benchmarkActivationId !== undefined && typeof schemaDigest === 'string' && schemaDigest.length > 0);
    const sessionIsStale = $derived(open && (String(benchmarkActivationId ?? '') !== String(sessionActivationId ?? '') || String(schemaDigest ?? '') !== String(sessionSchemaDigest ?? '')));

    $effect(() => {
        if (!open) {
            wasOpen = false;
            return;
        }
        if (wasOpen) return;
        wasOpen = true;
        sessionActivationId = benchmarkActivationId;
        sessionSchemaDigest = schemaDigest;
        gwaScale = baselineEvidence?.gwa_scale ?? 'percentage_100';
        gwa = baselineEvidence?.gwa_raw ?? baselineEvidence?.gwa ?? '';
        counselorNotes = baselineEvidence?.counselor_notes ?? '';
        form.clearErrors();
    });

    function submit(event) {
        event.preventDefault();
        if (!attemptId || sessionIsStale) return;
        const parsedGwa = String(gwa).trim() === '' ? null : Number.parseFloat(String(gwa));
        form.setStore({
            gwa: parsedGwa,
            gwa_scale: gwaScale,
            counselor_notes: counselorNotes,
            evidence_source: baselineEvidence?.evidence_source ?? 'examinee_statement',
            benchmark_activation_id: sessionActivationId,
            schema_digest: sessionSchemaDigest,
        });
        form.post(`/consultation/${attemptId}/evidence`, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                toastSuccess('Counselor notes and academic evidence recorded successfully.');
            },
        });
    }
</script>

{#if open}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-3 sm:p-6" role="presentation" onclick={(event) => { if (event.target === event.currentTarget && !form.processing) onClose(); }}>
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-border bg-background shadow-xl" role="dialog" aria-modal="true" aria-labelledby="supplemental-evidence-title" tabindex="-1" onkeydown={(event) => { if (event.key === 'Escape' && !form.processing) onClose(); }}>
            <header class="flex items-start justify-between gap-4 border-b border-border/60 px-5 py-4">
                <div>
                    <h2 id="supplemental-evidence-title" class="text-base font-semibold tracking-tight">Record Notes &amp; Academic Evidence</h2>
                    <p class="mt-1 text-sm text-muted-foreground">Add prior academic standing and counselor context for this applicant.</p>
                </div>
                <button type="button" class="rounded-md p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground" onclick={onClose} aria-label="Close notes and academic evidence" disabled={form.processing}><X class="size-4" /></button>
            </header>
            <form onsubmit={submit} class="flex min-h-0 flex-1 flex-col">
                <div class="space-y-5 overflow-y-auto px-5 py-4">
                    {#if !hasEvidenceContext}
                        <p role="alert" class="rounded-md border border-amber-500/40 bg-amber-50/50 p-3 text-sm text-foreground">No active benchmark binding is available. Notes and GWA can still be recorded without a benchmark snapshot.</p>
                    {:else if sessionIsStale}
                        <p role="alert" class="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive">The active benchmark or schema changed while this form was open. Close and reopen the form to review the current source.</p>
                    {/if}
                    <div class="space-y-1.5">
                        <div class="flex items-baseline justify-between gap-2">
                            <label for="supplemental-gwa-input" class="text-sm font-semibold">High School / Prior GWA</label>
                            {#if convertedPreview !== null}
                                <span class="font-mono text-xs text-muted-foreground">≈ {convertedPreview.toFixed(2)} / 100</span>
                            {/if}
                        </div>
                        <div class="relative flex items-center">
                            <input
                                id="supplemental-gwa-input"
                                type="number"
                                min={activeGwaScale.min}
                                max={activeGwaScale.max}
                                step={activeGwaScale.step}
                                bind:value={gwa}
                                disabled={sessionIsStale || form.processing}
                                class="h-10 w-full rounded-md border border-input bg-background pl-3 pr-44 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                placeholder={activeGwaScale.placeholder}
                            />
                            <div class="absolute inset-y-1 right-1 flex items-center">
                                <select
                                    id="supplemental-gwa-scale"
                                    bind:value={gwaScale}
                                    disabled={sessionIsStale || form.processing}
                                    class="h-full rounded-md border border-border/60 bg-muted/40 px-2 text-xs font-medium text-muted-foreground hover:bg-muted/70 hover:text-foreground focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                                    aria-label="Grading scale unit"
                                >
                                    {#each GWA_SCALES as scale (scale.value)}
                                        <option value={scale.value}>{scale.label}</option>
                                    {/each}
                                </select>
                            </div>
                        </div>
                        {#if form.errors.gwa}<p class="text-xs text-destructive">{form.errors.gwa}</p>{/if}
                    </div>
                    <div class="space-y-1.5">
                        <label for="counselor-notes" class="text-sm font-semibold">Counselor notes</label>
                        <textarea id="counselor-notes" bind:value={counselorNotes} maxlength="1000" rows="5" disabled={sessionIsStale || form.processing} class="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="Record relevant context for this applicant."></textarea>
                        {#if form.errors.counselor_notes}<p class="text-xs text-destructive">{form.errors.counselor_notes}</p>{/if}
                    </div>
                </div>
                <footer class="flex justify-end gap-2 border-t border-border/60 px-5 py-3">
                    <button type="button" class="h-9 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted" onclick={onClose} disabled={form.processing}>Cancel</button>
                    <button type="submit" class="h-9 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60" disabled={form.processing || !attemptId || sessionIsStale}>{form.processing ? 'Recording…' : 'Record Notes'}</button>
                </footer>
            </form>
        </div>
    </div>
{/if}
