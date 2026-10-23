<script>
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { ChevronDown, ChevronUp, SlidersHorizontal, Info, ScrollText, CheckCircle2, AlertTriangle, ExternalLink } from 'lucide-svelte';

    let {
        vocationalProfile = null,
        selectedProgram = null,
        applicantEvidence = null,
        onOpenSupplementalEvidence,
    } = $props();

    let infoOpen = $state(false);

    const programCode = $derived(selectedProgram?.program_code ?? selectedProgram?.code ?? '');
    const programName = $derived(selectedProgram?.program_name ?? selectedProgram?.name ?? programCode);
    const programTarget = $derived(
        programCode ? (vocationalProfile?.program_targets?.[programCode] ?? null) : null
    );

    const applicantStrand = $derived(
        applicantEvidence?.strand ?? vocationalProfile?.applicant_strand ?? null
    );

    const applicantType = $derived(
        applicantEvidence?.applicant_type ?? vocationalProfile?.applicant_type ?? 'new'
    );

    const isTransferee = $derived(applicantType === 'transferee');

    const cmoUrl = $derived(programTarget?.cmo_url || 'https://ched.gov.ph/ched-memorandum-orders/');
    const cmoShort = $derived(programTarget?.cmo_short || programTarget?.cmo_reference?.split('(')[0]?.trim() || 'CHED CMO Reference');
    const alignment = $derived.by(() => {
        if (!programTarget) {
            return {
                status: 'uncalibrated',
                label: 'Uncalibrated',
                variant: 'outline',
                colorClass: 'border-border text-muted-foreground bg-muted/20',
                summary: 'Curriculum benchmark is not defined for this program.',
                cmoReference: null,
                priorityStrands: [],
                applicantStrand,
            };
        }

        const priorityStrands = Array.isArray(programTarget.priority_strands) && programTarget.priority_strands.length > 0
            ? programTarget.priority_strands
            : (programTarget.cgp_strand_alignment ? programTarget.cgp_strand_alignment.split(' / ').map((s) => s.trim().replace(/\s*\(.*\)/, '')) : []);

        if (isTransferee) {
            const priorProgram = applicantStrand && typeof applicantStrand === 'string' && applicantStrand.trim() !== ''
                ? applicantStrand.trim()
                : null;

            return {
                status: 'transferee_articulation',
                label: 'Transferee Credit',
                variant: 'secondary',
                colorClass: 'text-blue-700 dark:text-blue-300 border-blue-500/40 bg-blue-500/10',
                summary: 'Subject credit equivalency and residency are evaluated by the receiving department via official TOR (SHS bridging does not apply).',
                cmoReference: programTarget.cmo_reference ?? null,
                priorityStrands,
                applicantStrand: priorProgram,
            };
        }

        if (!applicantStrand || typeof applicantStrand !== 'string' || applicantStrand.trim() === '') {
            return {
                status: 'not_recorded',
                label: 'Strand Not Recorded',
                variant: 'secondary',
                colorClass: 'border-border text-muted-foreground bg-muted/30',
                summary: 'Applicant Senior High School strand was not recorded on this application attempt.',
                cmoReference: programTarget.cmo_reference ?? null,
                priorityStrands,
                applicantStrand: null,
            };
        }

        const normApp = applicantStrand.toUpperCase().trim();
        let isDirect = false;
        for (const strand of priorityStrands) {
            const normPri = strand.toUpperCase().trim();
            if (
                normApp === normPri
                || normApp.includes(normPri)
                || normPri.includes(normApp)
                || (normPri === 'TVL-ICT' && (normApp.includes('ICT') || normApp.includes('INFORMATION AND COMMUNICATIONS TECHNOLOGY')))
                || (normPri === 'ABM' && normApp.includes('ACCOUNTANCY'))
                || (normPri === 'STEM' && (normApp.includes('SCIENCE') || normApp.includes('STEM')))
                || (normPri === 'HUMSS' && normApp.includes('HUMANITIES'))
            ) {
                isDirect = true;
                break;
            }
        }

        if (isDirect) {
            return {
                status: 'directly_aligned',
                label: 'Directly Aligned',
                variant: 'default',
                colorClass: 'text-emerald-700 dark:text-emerald-300 border-emerald-500/40 bg-emerald-500/10',
                summary: `Applicant graduated strand (${applicantStrand}) directly satisfies the CHED CMO priority curriculum alignment for ${programCode}.`,
                cmoReference: programTarget.cmo_reference ?? null,
                priorityStrands,
                applicantStrand,
            };
        }

        if (normApp.includes('GAS') || normApp.includes('GENERAL ACADEMIC')) {
            return {
                status: 'general_academic',
                label: 'General Academic Track',
                variant: 'secondary',
                colorClass: 'text-blue-700 dark:text-blue-300 border-blue-500/40 bg-blue-500/10',
                summary: 'Applicant completed General Academic Strand (GAS); eligible for admission with standard institutional course orientation.',
                cmoReference: programTarget.cmo_reference ?? null,
                priorityStrands,
                applicantStrand,
            };
        }

        return {
            status: 'bridging_recommended',
            label: 'Bridging Recommended',
            variant: 'outline',
            colorClass: 'text-amber-700 dark:text-amber-300 border-amber-500/40 bg-amber-500/10',
            summary: `Applicant graduated from a non-aligned strand (${applicantStrand}). Cross-strand admission is accommodated under university policy; bridging foundation units may be assigned by the academic department.`,
            cmoReference: programTarget.cmo_reference ?? null,
            priorityStrands,
            applicantStrand,
        };
    });
</script>

<section class="min-w-0 rounded-lg border border-border/50 bg-background/50 p-3 space-y-2.5" aria-label="CHED Curriculum & Strand Alignment">
    <div class="border-b border-border/40 pb-2">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="text-sm font-semibold text-foreground flex items-center gap-1.5 min-w-0">
                <ScrollText class="size-4 shrink-0 text-primary" />
                <span class="truncate">
                    {#if selectedProgram}
                        Curriculum Fit: {programCode}
                    {:else}
                        CHED Curriculum &amp; Strand Alignment
                    {/if}
                </span>
            </h3>
            <Badge variant={alignment.variant} class="text-[11px] font-semibold shrink-0 {alignment.colorClass}">
                {alignment.label}
            </Badge>
        </div>
        {#if selectedProgram && programName && programName !== programCode}
            <p class="mt-0.5 text-xs font-medium text-foreground/90 break-words">{programName}</p>
        {/if}
        {#if alignment.cmoReference}
            <a href={cmoUrl} target="_blank" rel="noopener noreferrer" class="mt-0.5 inline-flex items-center gap-1 text-[11px] text-primary underline-offset-2 hover:underline" title={programTarget?.cmo_reference}>
                {cmoShort}<ExternalLink class="size-3" aria-hidden="true" />
            </a>
        {:else}
            <p class="mt-0.5 text-[11px] text-muted-foreground">Evaluated under CHED CMO and DepEd Career Guidance (CGP) frameworks.</p>
        {/if}
    </div>

    <!-- Compact 2-Row Credential Matrix -->
    <div class="rounded-lg border border-border/60 bg-card divide-y divide-border/40 text-xs">
        <div class="flex items-start justify-between gap-3 px-3 py-2">
            <span class="font-mono text-[11px] font-semibold uppercase tracking-wider text-muted-foreground shrink-0">
                {isTransferee ? 'Prior Degree' : 'Applicant SHS'}
            </span>
            <div class="text-right min-w-0">
                {#if alignment.applicantStrand}
                    <span class="font-semibold text-foreground break-words">{alignment.applicantStrand}</span>
                {:else}
                    <span class="italic text-muted-foreground">Not recorded</span>
                {/if}
            </div>
        </div>
        <div class="flex items-start justify-between gap-3 px-3 py-2">
            <span class="font-mono text-[11px] font-semibold uppercase tracking-wider text-muted-foreground shrink-0">
                {isTransferee ? 'Evaluation Basis' : 'Priority Strands'}
            </span>
            <div class="flex flex-wrap justify-end gap-1 min-w-0">
                {#if isTransferee}
                    <Badge variant="outline" class="text-[11px] font-medium">TOR Credit Equivalency</Badge>
                {:else if alignment.priorityStrands.length > 0}
                    {#each alignment.priorityStrands as strand (strand)}
                        <Badge variant="secondary" class="font-semibold text-[11px] px-2 py-0">{strand}</Badge>
                    {/each}
                {:else}
                    <span class="italic text-muted-foreground">Open track</span>
                {/if}
            </div>
        </div>
    </div>

    <!-- Compact Policy Finding -->
    <div class="rounded-lg border p-3 {alignment.colorClass}">
        <div class="flex items-center gap-1.5 mb-1">
            {#if alignment.status === 'directly_aligned'}
                <CheckCircle2 class="size-3.5 shrink-0 text-emerald-600 dark:text-emerald-400" />
            {:else if alignment.status === 'transferee_articulation' || alignment.status === 'general_academic'}
                <Info class="size-3.5 shrink-0 text-blue-600 dark:text-blue-400" />
            {:else}
                <AlertTriangle class="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" />
            {/if}
            <h4 class="text-[11px] font-bold uppercase tracking-wider">Advising &amp; Admission Policy Finding</h4>
        </div>
        <p class="text-xs leading-relaxed">{alignment.summary}</p>
    </div>

    <!-- Compact Footer Actions & Regulatory Disclosure -->
    <div class="space-y-2 border-t border-border/40 pt-2">
        <div class="flex items-center justify-between gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-1 text-[11px] font-semibold text-muted-foreground hover:text-foreground transition-colors"
                onclick={() => { infoOpen = !infoOpen; }}
                aria-expanded={infoOpen}
            >
                <Info class="size-3 text-primary" />
                <span>Regulatory Baseline (BR-024)</span>
                {#if infoOpen}
                    <ChevronUp class="size-3" />
                {:else}
                    <ChevronDown class="size-3" />
                {/if}
            </button>
            {#if typeof onOpenSupplementalEvidence === 'function'}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-7 gap-1 px-2.5 text-[11px] font-semibold"
                    onclick={onOpenSupplementalEvidence}
                >
                    <SlidersHorizontal class="size-3" />
                    Record Notes
                </Button>
            {/if}
        </div>

        {#if infoOpen}
            <div class="space-y-1.5 rounded-md bg-muted/40 p-2.5 text-[11px] text-muted-foreground">
                <p>
                    <strong class="text-foreground">Regulatory Baseline:</strong>
                    Aligned with <em>CHED Policies, Standards, and Guidelines (CMOs)</em> and <em>DepEd Career Guidance Program (CGP)</em> pathways.
                </p>
                <p class="border-t border-border/30 pt-1 text-[10px]">
                    <strong>Advisory Nature (BR-024):</strong> Curriculum alignment informs academic advising and potential bridging or credit transfer; it does not alter entrance exam percentiles.
                </p>
            </div>
        {/if}
    </div>
</section>
