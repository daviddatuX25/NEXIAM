<script>
    import { Button } from '@/Components/ui/button';
    import { SlidersHorizontal } from 'lucide-svelte';

    let { evidence = {}, onRecordSupplementalEvidence = () => {} } = $props();
    let showOriginalGwa = $state(false);
    const gwa = $derived(evidence?.gwa === null || evidence?.gwa === undefined ? null : evidence.gwa);
    const gwaRaw = $derived(evidence?.gwa_raw === null || evidence?.gwa_raw === undefined ? gwa : evidence.gwa_raw);
    const gwaScaleLabel = $derived(evidence?.gwa_scale_label || '0–100%');
    const isConvertedGwa = $derived(Boolean(evidence?.gwa_is_converted && gwa !== null && gwaRaw !== null));
    const isTransferee = $derived(evidence?.applicant_type === 'transferee');
    const counselorNotes = $derived(typeof evidence?.counselor_notes === 'string' ? evidence.counselor_notes.trim() : '');
    const strand = $derived(evidence?.strand || null);
    const track = $derived(evidence?.track || evidence?.shs_track || null);
    const strandDisplay = $derived(track && strand && track !== strand ? `${track} · ${strand}` : strand || track);
</script>

<div class="rounded-xl border border-border/80 bg-card p-4 shadow-xs">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-3">
        <span class="text-xs font-bold uppercase tracking-wider text-foreground">Applicant Evidence · Sealed Examination Profile</span>
        <Button type="button" variant="outline" size="sm" class="min-h-10 gap-1.5 px-3 text-sm font-semibold" onclick={onRecordSupplementalEvidence}>
            <SlidersHorizontal class="size-4" aria-hidden="true" />
            {gwa !== null || counselorNotes ? 'Edit Notes & GWA' : 'Record Notes'}
        </Button>
    </div>
    <div class="mt-4 grid grid-cols-1 gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
        <section class="flex h-full min-w-0 flex-col gap-2 rounded-xl border border-border/70 bg-muted/20 p-4" aria-label="High School GWA">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">{isTransferee ? 'Prior College / TOR GWA' : 'High School GWA'}</h3>
            </div>
            {#if gwa !== null}
                <div class="flex flex-wrap items-baseline gap-1.5">
                    {#if showOriginalGwa && isConvertedGwa}
                        <span class="font-mono text-xl font-bold text-foreground tabular-nums">{gwaRaw}</span>
                        <span class="text-xs text-muted-foreground">({gwaScaleLabel})</span>
                        <button
                            type="button"
                            class="ml-1 inline-flex items-center gap-0.5 rounded border border-border/80 bg-background/90 px-1 py-0.5 font-mono text-[10px] font-medium text-muted-foreground hover:border-foreground/40 hover:text-foreground transition-colors cursor-pointer"
                            onclick={() => { showOriginalGwa = false; }}
                            title="Switch to 0–100 scale"
                            aria-label={`Switch to 0–100 scale: ${gwa}`}
                        >
                            <span>{gwa}</span>
                            <span aria-hidden="true">⇄</span>
                        </button>
                    {:else}
                        <span class="font-mono text-xl font-bold text-foreground tabular-nums">{gwa}</span>
                        <span class="text-xs text-muted-foreground">/ 100</span>
                        {#if isConvertedGwa}
                            <button
                                type="button"
                                class="ml-1 inline-flex items-center gap-0.5 rounded border border-border/80 bg-background/90 px-1 py-0.5 font-mono text-[10px] font-medium text-muted-foreground hover:border-foreground/40 hover:text-foreground transition-colors cursor-pointer"
                                onclick={() => { showOriginalGwa = true; }}
                                title="Switch to original reported scale"
                                aria-label={`Switch to original scale: ${gwaRaw}`}
                            >
                                <span>{gwaRaw}</span>
                                <span aria-hidden="true">⇄</span>
                            </button>
                        {/if}
                    {/if}
                </div>
            {:else}
                <span class="text-sm italic text-muted-foreground">Not recorded</span>
                <p class="text-xs text-muted-foreground">Enter it with Record Notes.</p>
            {/if}
        </section>
        <section class="flex h-full min-w-0 flex-col gap-2 rounded-xl border border-border/70 bg-muted/20 p-4" aria-label="Senior High School track and strand">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">SHS Track &amp; Strand</h3>
            {#if strandDisplay}<span class="break-words text-base font-semibold text-foreground">{strandDisplay}</span>{:else}<span class="text-sm italic text-muted-foreground">Not recorded</span>{/if}
            <span class="text-xs font-semibold text-foreground">Required</span>
        </section>
        <section class="flex h-full min-w-0 flex-col gap-2 rounded-xl border border-border/70 bg-muted/20 p-4" aria-label="Counselor notes">
            <h3 class="text-xs font-bold uppercase tracking-wider text-foreground">Counselor Notes</h3>
            {#if counselorNotes}<p class="line-clamp-3 whitespace-pre-wrap break-words text-sm text-foreground">{counselorNotes}</p>{:else}<p class="text-sm italic text-muted-foreground">No counselor notes recorded</p>{/if}
        </section>
    </div>
</div>
