<script>
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Textarea } from '@/Components/ui/textarea';
    import { Badge } from '@/Components/ui/badge';
    import { Award, Compass } from 'lucide-svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { applyCommonRange, authoredRangeError, proposeCommonRange, setAuthoredRange, setPreference } from '@/lib/tabFmRangeAuthoring';
    import { legacyShsHistory } from '@/lib/shs-history-reference-data';

    let { program, cycle, features = [], inputSchema = { subjects: [], activities: [] }, content, view = 'core', version = 0, canEdit = false, busy = false, pendingSave = false, dirty = false, reason = $bindable(''), onContentChange, onSave, onReset, recovery = null, isRecoveryProposal = false, onRecover, selectedBand = $bindable('cutoff') } = $props();
    const bands = [
        { key: 'honors', level: '1', label: 'Honors', hint: 'Upper example' },
        { key: 'regular', level: '2', label: 'Regular', hint: 'Middle example' },
        { key: 'cutoff', level: '3', label: 'Cutoff', hint: 'Lower example' },
    ];
    const HOLLAND_TARGETS = {
        BSIT: { code: 'IR', name: 'Investigative · Realistic', cmo: 'CHED CMO No. 25, s. 2015', domain: 'Computing, Software Algorithms, Systems Architecture & Hardware Engineering', track: 'STEM / TVL-ICT' },
        BSEd: { code: 'SA', name: 'Social · Artistic', cmo: 'CHED CMO No. 75, s. 2017', domain: 'Secondary Classroom Pedagogy, Instructional Design & Educational Communication', track: 'HUMSS / Academic' },
        'BSEd-Eng': { code: 'SA', name: 'Social · Artistic', cmo: 'CHED CMO No. 75, s. 2017', domain: 'English Language Pedagogy & Literary Instruction', track: 'HUMSS' },
        'BSEd-Math': { code: 'SI', name: 'Social · Investigative', cmo: 'CHED CMO No. 75, s. 2017', domain: 'Mathematics Pedagogy & Quantitative Instruction', track: 'STEM' },
        BEEd: { code: 'SA', name: 'Social · Artistic', cmo: 'CHED CMO No. 74, s. 2017', domain: 'Foundational Child Development, Primary Pedagogy & Creative Instruction', track: 'HUMSS / General Academic' },
        BPEd: { code: 'RS', name: 'Realistic · Social', cmo: 'CHED CMO No. 80, s. 2017', domain: 'Sports Kinesthetics, Athletic Coaching, Physical Conditioning & Health Leadership', track: 'Sports / TVL' },
        BSBA: { code: 'EC', name: 'Enterprising · Conventional', cmo: 'CHED CMO No. 17, s. 2017', domain: 'Business Administration, Executive Leadership, Marketing Strategy & Operations Management', track: 'ABM' },
        BSEntrep: { code: 'EC', name: 'Enterprising · Conventional', cmo: 'CHED CMO No. 18, s. 2017', domain: 'Enterprise Incubation, Venture Creation, Commercial Development & Fiscal Oversight', track: 'ABM' },
        BSMath: { code: 'IC', name: 'Investigative · Conventional', cmo: 'CHED CMO No. 48, s. 2017', domain: 'Pure and Applied Mathematics, Statistical Modeling, Numerical Computation & Data Rigor', track: 'STEM' },
        'BA-EL': { code: 'AS', name: 'Artistic · Social', cmo: 'CHED CMO No. 24, s. 2017', domain: 'Linguistic Analysis, English Discourse, Literary Exposition & Intercultural Communication', track: 'HUMSS' },
        'BA-Psych': { code: 'SI', name: 'Social · Investigative', cmo: 'CHED CMO No. 34, s. 2017', domain: 'Psychological Sciences, Clinical Assessment, Human Behavior & Psychometrics', track: 'HUMSS / STEM' },
        BASS: { code: 'SI', name: 'Social · Investigative', cmo: 'CHED Social Science Discipline Standards', domain: 'Sociological Inquiry, Community Development, Public Policy & Historical Analysis', track: 'HUMSS' },
        BPA: { code: 'ES', name: 'Enterprising · Social', cmo: 'CHED CMO No. 06, s. 2010', domain: 'Public Administration, Government Affairs, Bureaucratic Leadership & Civil Service', track: 'HUMSS / ABM' },
    };
    const hollandTarget = $derived(program ? (HOLLAND_TARGETS[program.code] ?? null) : null);
    const numericFeatures = $derived(features.filter((feature) => feature.logical_type === 'numeric' && !(inputSchema.subjects ?? []).some((subject) => subject.key === feature.key)));
    const strandsFromExamples = $derived(['1', '2', '3', '4', '5'].map((level) => content?.tiers?.[level]?.values?.shs_strand).filter((value) => typeof value === 'string' && value.trim() !== ''));
    const strandChoices = $derived([...new Set([...(content?.authoring?.preferred_strands ?? []), ...strandsFromExamples, ...legacyShsHistory.map((item) => item.aliases[0] ?? item.label)])]);
    let reasonOpen = $state(false);
    let commonRanges = $state({});
    let proposals = $state({});
    let showAdvanced = $state(false);
    const rangeFeatures = $derived(numericFeatures);
    const hasRangeErrors = $derived(bands.some((band) => rangeFeatures.some((feature) => Object.prototype.hasOwnProperty.call(content?.authoring?.ranges?.[band.key] ?? {}, feature.key) && rangeError(feature, pairFor(band.key, feature.key)) !== null)));
    function exampleCompleteness(level) {
        const requiredFeatures = features.filter((feature) => feature.is_required === true);
        const recorded = requiredFeatures.filter((feature) => {
            const value = content?.tiers?.[level]?.values?.[feature.key];
            return value !== null && value !== undefined && !(typeof value === 'string' && value.trim() === '');
        }).length;
        return { recorded, total: requiredFeatures.length };
    }
    function exampleReviewMessage(level) {
        const { recorded, total } = exampleCompleteness(level);
        if (total === 0) return 'Required-value count unavailable; review this example explicitly.';
        return recorded === total
            ? `${recorded} of ${total} required values present · review this example explicitly.`
            : `${recorded} of ${total} required values present · ${total - recorded} need review.`;
    }

    function pairFor(band, key) { return content?.authoring?.ranges?.[band]?.[key]; }
    function changeRange(band, key, endpoint, raw) { onContentChange(setAuthoredRange(content, band, key, endpoint, raw)); }
    function confirmedSource(feature) {
        return feature.source_definition ?? null;
    }
    function numericBound(value) {
        if (value === null || value === undefined || value === '') return null;
        const number = Number(value);
        return Number.isFinite(number) ? number : null;
    }
    function rangeConstraints(feature) {
        const source = confirmedSource(feature);
        return source
            ? { min_value: numericBound(source.min_value), max_value: numericBound(source.max_value) }
            : {
                min_value: numericBound(feature.min_value),
                max_value: numericBound(feature.max_value),
            };
    }
    function measurementValue(feature) {
        const source = confirmedSource(feature);
        const supported = ['percentile_rank', 'percentile', 'gwa_scale', 'count'];
        const unit = source?.unit ?? feature.declared_unit;
        const scale = source?.scale ?? feature.declared_scale;
        const declaredUnit = typeof unit === 'string' && supported.includes(unit.trim()) ? unit.trim() : null;
        const declaredScale = typeof scale === 'string' && supported.includes(scale.trim()) ? scale.trim() : null;
        return declaredUnit ?? declaredScale;
    }
    function featureUnit(feature) {
        const unit = measurementValue(feature);
        const labels = {
            percentile_rank: 'Percentile rank',
            percentile: 'Percentile',
            gwa_scale: 'GWA scale',
            count: 'Count',
        };
        return unit ? (labels[unit] ?? unit) : null;
    }
    function rangeMinimum(feature) { return rangeConstraints(feature).min_value ?? undefined; }
    function rangeMaximum(feature) { return rangeConstraints(feature).max_value ?? undefined; }
    function sliderBounds(feature) {
        const constraints = rangeConstraints(feature);
        if (measurementValue(feature) === null || constraints.min_value === null || constraints.max_value === null
            || constraints.max_value <= constraints.min_value) {
            return null;
        }
        return { minimum: constraints.min_value, maximum: constraints.max_value };
    }
    function sliderPair(feature) {
        const raw = commonRanges[feature.key] ?? ['', ''];
        const bounds = sliderBounds(feature);
        if (!bounds || raw[0] === '' || raw[1] === '') return null;
        const from = Number(raw[0]);
        const to = Number(raw[1]);
        if (!Number.isFinite(from) || !Number.isFinite(to)
            || from < bounds.minimum || to > bounds.maximum || from > to) {
            return null;
        }
        return [from, to];
    }
    function changeCommonRange(feature, endpoint, raw) {
        const current = commonRanges[feature.key] ?? ['', ''];
        commonRanges[feature.key] = endpoint === 0 ? [raw, current[1]] : [current[0], raw];
        proposals[feature.key] = null;
    }
    function changeCommonRangeSlider(feature, endpoint, raw) {
        const bounds = sliderBounds(feature);
        const pair = sliderPair(feature);
        if (!bounds || !pair) return;
        let [from, to] = pair;
        const value = Math.min(bounds.maximum, Math.max(bounds.minimum, Number(raw)));
        if (endpoint === 0) {
            from = value;
            if (from > to) to = from;
        } else {
            to = value;
            if (to < from) from = to;
        }
        commonRanges[feature.key] = [String(from), String(to)];
        proposals[feature.key] = null;
    }
    function rangeError(feature, pair) {
        const storedError = authoredRangeError(pair);
        if (storedError) return storedError;
        if (!pair) return null;
        const constraints = rangeConstraints(feature);
        for (const [label, value] of [['From', pair[0]], ['To', pair[1]]]) {
            if (value === null) continue;
            if (constraints.min_value != null && value < constraints.min_value) return `${label} must be at least ${constraints.min_value}.`;
            if (constraints.max_value != null && value > constraints.max_value) return `${label} must be at most ${constraints.max_value}.`;
        }
        return null;
    }
    function commonRangeError(feature, raw) {
        if (raw[0] === '' && raw[1] === '') return null;
        if (raw[0] === '' || raw[1] === '') return 'Set both endpoints to preview this range.';
        return rangeError(feature, [Number(raw[0]), Number(raw[1])]);
    }
    function previewRange(feature) {
        const raw = commonRanges[feature.key] ?? ['', ''];
        if (raw[0] === '' || raw[1] === '') return null;
        return proposeCommonRange(content, feature.key, [Number(raw[0]), Number(raw[1])], rangeConstraints(feature));
    }
    function changePreference(name, value, checked) { onContentChange(setPreference(content, name, value, checked)); }
    function togglePreferredStrand(strand) {
        const selected = content.authoring?.preferred_strands ?? [];
        changePreference('preferred_strands', strand, !selected.includes(strand));
    }
    function prepareRange(featureKey) {
        const [fromRaw, toRaw] = commonRanges[featureKey] ?? ['', ''];
        const pair = [fromRaw === '' ? null : Number(fromRaw), toRaw === '' ? null : Number(toRaw)];
        const feature = rangeFeatures.find((item) => item.key === featureKey);
        proposals[featureKey] = proposeCommonRange(content, featureKey, pair, rangeConstraints(feature ?? {}));
    }
    function applyPreparedRange(featureKey) {
        const proposal = proposals[featureKey] ?? null;
        if (!proposal || !proposalIsCurrent(featureKey)) return;
        onContentChange(applyCommonRange(content, proposal));
        proposals[featureKey] = null;
    }
    function proposalIsCurrent(featureKey) {
        const proposal = proposals[featureKey];
        if (!proposal) return false;
        const current = proposeCommonRange(content, featureKey, proposal.pair, proposal.constraints);
        return !!current && JSON.stringify(current.rows) === JSON.stringify(proposal.rows)
            && JSON.stringify(current.before) === JSON.stringify(proposal.before);
    }
    function setExampleValue(level, key, value) {
        const next = { ...content, tiers: Object.fromEntries(['1', '2', '3', '4', '5'].map((tier) => [tier, { values: { ...(content.tiers?.[tier]?.values ?? {}) } }])), ...(content.authoring ? { authoring: content.authoring } : {}) };
        next.tiers[level].values[key] = value;
        onContentChange(next);
    }
    function initiateSave() { reasonOpen = true; }
    async function confirmSave() {
        if (!reason.trim()) return;
        const saved = await onSave();
        if (saved) reasonOpen = false;
    }
</script>

{#snippet commonRangeEditor(feature)}
    {@const raw = commonRanges[feature.key] ?? ['', '']}
    {@const proposal = proposals[feature.key]}
    {@const preparationError = commonRangeError(feature, raw)}
    {@const preview = previewRange(feature)}
    {@const range = sliderPair(feature)}
    {@const sliderLimits = sliderBounds(feature)}
    <fieldset class="min-w-0 space-y-4 rounded-xl border border-border/70 bg-card p-4">
        <legend class="inline-flex items-center gap-1.5 px-1 text-sm font-semibold text-foreground">
            <span>{feature.label}</span>
            <InfoPopover
                title={`${feature.label} common range`}
                content="Set 'From' (Tier 3 Cutoff Floor) and 'To' (Tier 1 Honors Ceiling). The calibrator automatically computes the midpoint for Tier 2 (Regular) and lets you preview all three tier values before applying them to the draft."
            />
        </legend>
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-1.5 text-xs text-muted-foreground">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="font-medium text-foreground">Current tiers:</span>
                <span>Cutoff (T3): <strong class="font-mono text-foreground tabular-nums">{content.tiers?.['3']?.values?.[feature.key] ?? '—'}</strong></span>
                <span>·</span>
                <span>Regular (T2): <strong class="font-mono text-foreground tabular-nums">{content.tiers?.['2']?.values?.[feature.key] ?? '—'}</strong></span>
                <span>·</span>
                <span>Honors (T1): <strong class="font-mono text-foreground tabular-nums">{content.tiers?.['1']?.values?.[feature.key] ?? '—'}</strong></span>
            </div>
            {#if canEdit && !isRecoveryProposal && (content.tiers?.['3']?.values?.[feature.key] != null || content.tiers?.['1']?.values?.[feature.key] != null)}
                <button
                    type="button"
                    class="rounded border border-border/70 bg-background px-2 py-0.5 text-[11px] font-medium text-foreground hover:bg-muted cursor-pointer"
                    disabled={busy || pendingSave}
                    onclick={() => {
                        commonRanges[feature.key] = [
                            String(content.tiers?.['3']?.values?.[feature.key] ?? ''),
                            String(content.tiers?.['1']?.values?.[feature.key] ?? ''),
                        ];
                        proposals[feature.key] = null;
                    }}
                >
                    Load current range
                </button>
            {/if}
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block space-y-1 text-sm font-medium text-foreground" for={`common-${feature.key}-from`}>
                <span>From</span>
                <Input id={`common-${feature.key}-from`} type="number" min={rangeMinimum(feature)} max={rangeMaximum(feature)} step="0.01" value={raw[0]} aria-label="Common range · From" disabled={!canEdit || isRecoveryProposal || busy || pendingSave} oninput={(event) => changeCommonRange(feature, 0, event.currentTarget.value)} />
            </label>
            <label class="block space-y-1 text-sm font-medium text-foreground" for={`common-${feature.key}-to`}>
                <span>To</span>
                <Input id={`common-${feature.key}-to`} type="number" min={rangeMinimum(feature)} max={rangeMaximum(feature)} step="0.01" value={raw[1]} aria-label="Common range · To" disabled={!canEdit || isRecoveryProposal || busy || pendingSave} oninput={(event) => changeCommonRange(feature, 1, event.currentTarget.value)} />
            </label>
        </div>
        {#if sliderLimits && range}
            <div class="space-y-3 rounded-lg bg-muted/30 p-3">
                <label class="block space-y-1 text-sm font-medium text-foreground" for={`common-${feature.key}-from-slider`}>
                    <span>From · {range[0]} {featureUnit(feature)}</span>
                    <input id={`common-${feature.key}-from-slider`} class="min-h-11 w-full accent-primary" type="range" min={sliderLimits.minimum} max={Math.max(sliderLimits.minimum, range[1])} step="0.01" value={range[0]} disabled={!canEdit || isRecoveryProposal || busy || pendingSave} oninput={(event) => changeCommonRangeSlider(feature, 0, event.currentTarget.value)} />
                </label>
                <label class="block space-y-1 text-sm font-medium text-foreground" for={`common-${feature.key}-to-slider`}>
                    <span>To · {range[1]} {featureUnit(feature)}</span>
                    <input id={`common-${feature.key}-to-slider`} class="min-h-11 w-full accent-primary" type="range" min={Math.min(sliderLimits.maximum, range[0])} max={sliderLimits.maximum} step="0.01" value={range[1]} disabled={!canEdit || isRecoveryProposal || busy || pendingSave} oninput={(event) => changeCommonRangeSlider(feature, 1, event.currentTarget.value)} />
                </label>
            </div>
        {:else if sliderLimits}
            <p class="text-xs text-muted-foreground">Enter both endpoints to use the slider within the confirmed source range {sliderLimits.minimum}–{sliderLimits.maximum} {featureUnit(feature)}.</p>
        {/if}
        <p class="text-sm text-muted-foreground">{featureUnit(feature) ?? 'Unit not confirmed'}</p>
        {#if preview}
            <dl class="grid gap-2 rounded-lg border border-border/60 bg-muted/20 p-3 sm:grid-cols-3" aria-label={`Tier preview for ${feature.label}`} aria-live="polite">
                <div><dt class="text-xs font-semibold text-foreground">Cutoff Floor</dt><dd class="font-mono text-base font-bold text-foreground tabular-nums">{preview.appliedValues.cutoff}</dd></div>
                <div><dt class="text-xs font-semibold text-foreground">Regular Midpoint</dt><dd class="font-mono text-base font-bold text-foreground tabular-nums">{preview.appliedValues.regular}</dd></div>
                <div><dt class="text-xs font-semibold text-foreground">Honors Ceiling</dt><dd class="font-mono text-base font-bold text-foreground tabular-nums">{preview.appliedValues.honors}</dd></div>
            </dl>
        {:else}
            <p class="text-sm text-muted-foreground">{preparationError ?? 'Adjust the range to preview all three tier values.'}</p>
        {/if}
        <Button type="button" variant="outline" size="sm" disabled={!canEdit || isRecoveryProposal || busy || pendingSave || !preview} onclick={() => prepareRange(feature.key)}>Prepare examples</Button>
        {#if proposal}
            <div class="space-y-2 border-t border-border/60 pt-3" aria-label={`Prepared values for ${feature.label}`}>
                <p class="text-sm font-semibold text-foreground">Review before applying · local only</p>
                {#each proposal.rows as row (row.band)}
                    {@const previousRange = proposal.before[row.band]}
                    <div class="grid gap-1 border-t border-border/40 py-2 text-sm sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                        <span class="font-semibold text-foreground">{bands.find((item) => item.key === row.band)?.label}</span>
                        <span>
                            <span class="block">Range: {previousRange ? `${previousRange[0] ?? 'Missing'}–${previousRange[1] ?? 'Missing'}` : 'No authored range'} → {proposal.pair[0]}–{proposal.pair[1]}</span>
                            <span class="block">Model cell: {row.before == null ? 'Missing' : row.before} → {row.after}</span>
                        </span>
                    </div>
                {/each}
                {#if proposalIsCurrent(feature.key)}
                    <Button type="button" size="sm" disabled={!canEdit || isRecoveryProposal || busy || pendingSave} onclick={() => applyPreparedRange(feature.key)}>Apply reviewed values to this draft</Button>
                {:else}
                    <p role="status" class="text-sm text-amber-800 dark:text-amber-200">The draft changed after this review. Prepare again to confirm current before-values.</p>
                {/if}
            </div>
        {/if}
    </fieldset>
{/snippet}

<section class="min-w-0 space-y-5" aria-label="Programme benchmark editor" aria-busy={busy}>
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/50 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
        <span>Saved changes remain a draft until an authorized editor publishes them.</span>
        <span role="status" class="rounded-md bg-background/80 border border-border/60 px-2.5 py-0.5 font-medium text-foreground">{!canEdit ? 'View only' : busy ? 'Saving…' : pendingSave ? 'Save outcome unknown · retry the same change' : dirty ? 'Unsaved changes' : version ? `Draft v${version}` : isRecoveryProposal ? 'Historical set · start draft to edit' : 'No saved draft'}</span>
    </div>
    {#if recovery}
        <section class="space-y-3 border-y py-3 text-sm" aria-label="Historical benchmark recovery">
            <h3 class="font-medium">{isRecoveryProposal ? `Historical set #${recovery.source_set_id} is available to start a draft.` : recovery.status === 'draft' ? `Draft recovered from historical set #${recovery.source_set_id}.` : `Historical set #${recovery.source_set_id} is available for recovery.`}</h3>
            <p class="text-muted-foreground">Historical data stays unchanged. {Number.isFinite(recovery.remaining_program_count) && Number.isFinite(recovery.source_program_count) ? `${recovery.remaining_program_count} of ${recovery.source_program_count} source programs remain to be reviewed.` : 'Publication checks the complete source-program review.'} Missing Cross-Strand values need explicit review.</p>
            <details><summary class="cursor-pointer text-xs">Technical details</summary><dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-2 text-xs"><dt>Historical content digest</dt><dd class="break-all">{recovery.source_content_sha256 ?? 'Unavailable'}</dd><dt>Frozen source digest</dt><dd class="break-all">{recovery.source_snapshot_digest ?? 'Unavailable'}</dd></dl></details>
            {#if canEdit && isRecoveryProposal && recovery.program_in_source === true && recovery.status === 'available'}<label for="recovery-reason" class="block font-medium">Reason for change</label><Textarea id="recovery-reason" bind:value={reason} required maxlength={500} rows={2} disabled={busy} /><Button disabled={busy || !reason.trim()} onclick={onRecover}>{busy ? 'Starting draft…' : 'Start draft from historical set'}</Button>{/if}
        </section>
    {/if}
    {#if content && features.length}
        <div class="space-y-4">

            {#if view === 'core'}<div class="min-w-0 space-y-4">
                <div class="space-y-1">
                    <h3 class="flex items-center gap-1.5 text-base font-semibold text-foreground">
                        <span>Core Aptitudes &amp; GWA</span>
                        <InfoPopover
                            title="How Common Range calibration works"
                            content="Enter a lower ('From') and upper ('To') target for each metric, click 'Prepare examples' to inspect the before/after diff for Cutoff, Regular, and Honors tiers, then click 'Apply reviewed values to this draft'."
                        />
                    </h3>
                    <p class="text-sm text-muted-foreground">Adjust common ranges and review their tier values before applying. Sliders appear only when source units and numeric bounds are confirmed.</p>
                </div>
                {#if numericFeatures.length > 0}
                    <div class="grid min-w-0 gap-3 xl:grid-cols-2">
                        {#each numericFeatures as feature (feature.key)}
                            {@render commonRangeEditor(feature)}
                        {/each}
                    </div>
                {:else}
                    <p class="rounded-lg border border-border/70 p-4 text-sm text-muted-foreground">No core aptitude or GWA ranges are available in the approved schema.</p>
                {/if}
        <section class="space-y-3 rounded-lg border border-border/70 bg-muted/20 p-3" aria-label="Additional example completeness">
            <div class="space-y-1">
                <h3 class="flex items-center gap-1.5 text-sm font-semibold text-foreground">
                    <span>Additional examples · explicit review</span>
                    <InfoPopover
                        title="Other-strand & Below-cutoff anchors"
                        content="Tier 4 (Other-strand) and Tier 5 (Below-cutoff negative anchor) are never auto-filled from common ranges so you can set explicit cross-strand thresholds and rejection floors."
                    />
                </h3>
                <p class="text-xs text-muted-foreground">Other-strand and Below-cutoff values are never generated from common ranges.</p>
            </div>
            <dl class="grid gap-x-5 gap-y-2 sm:grid-cols-2">
                {#each [{ level: '4', label: 'Other-strand example' }, { level: '5', label: 'Below-cutoff example' }] as example (example.level)}
                    <div class="min-w-0 border-t border-border/50 pt-2">
                        <dt class="text-sm font-medium text-foreground">{example.label}</dt>
                        <dd role="status" class="text-xs text-muted-foreground">{exampleReviewMessage(example.level)}</dd>
                    </div>
                {/each}
            </dl>
            <Button type="button" variant="outline" size="sm" onclick={() => { showAdvanced = true; }}>Review exact examples</Button>
        </section>
        <details class="border-y py-3" bind:open={showAdvanced}>
            <summary class="cursor-pointer text-sm font-medium">Advanced · profile-specific ranges and examples</summary>
            <p class="mt-2 text-xs text-muted-foreground">Only enter values supported by reviewed evidence. Common ranges affect the first three examples only; Other-strand and Below-cutoff examples must be entered and reviewed separately.</p>
            {#each bands as advancedBand (advancedBand.key)}
                <fieldset class="grid min-w-0 gap-3 border-t py-3 md:grid-cols-2">
                    <legend class="inline-flex items-center gap-1.5 text-sm font-medium">
                        <span>{advancedBand.label} range</span>
                        <InfoPopover
                            title={`${advancedBand.label} band override`}
                            content={`Fine-tune the authored From–To interval specifically for the ${advancedBand.label} band (${advancedBand.hint}).`}
                        />
                    </legend>
                    {#each numericFeatures as feature (feature.key)}
                        {@const pair = pairFor(advancedBand.key, feature.key)}
                        {@const errorText = rangeError(feature, pair)}
                        <div class="space-y-2"><h4 class="text-sm">{feature.label}</h4><div class="grid grid-cols-[1fr_auto_1fr] items-end gap-2"><div><label for={`advanced-${advancedBand.key}-${feature.key}-from`} class="text-xs">From</label><Input id={`advanced-${advancedBand.key}-${feature.key}-from`} type="number" min={rangeMinimum(feature)} max={rangeMaximum(feature)} step="0.01" value={pair?.[0] ?? ''} disabled={!canEdit || isRecoveryProposal || busy || pendingSave} oninput={(event) => changeRange(advancedBand.key, feature.key, 0, event.currentTarget.value)} /></div><span class="pb-3">–</span><div><label for={`advanced-${advancedBand.key}-${feature.key}-to`} class="text-xs">To</label><Input id={`advanced-${advancedBand.key}-${feature.key}-to`} type="number" min={rangeMinimum(feature)} max={rangeMaximum(feature)} step="0.01" value={pair?.[1] ?? ''} disabled={!canEdit || isRecoveryProposal || busy || pendingSave} oninput={(event) => changeRange(advancedBand.key, feature.key, 1, event.currentTarget.value)} /></div></div>{#if errorText}<p role="alert" class="text-xs text-destructive">{errorText}</p>{/if}</div>
                    {/each}
                </fieldset>
            {/each}
            {#each ['4', '5'] as level}
                {@const tierLabel = level === '4' ? 'Other-strand example' : 'Below-cutoff example'}
                <fieldset class="grid min-w-0 gap-3 border-t py-3 sm:grid-cols-2"><legend class="text-sm font-medium">{tierLabel} · exact values</legend>{#each numericFeatures as feature (feature.key)}{@const value = content.tiers?.[level]?.values?.[feature.key]}<div class="min-w-0 space-y-1"><label for={`example-${level}-${feature.key}`} class="text-xs font-medium">{feature.label}</label>{#if canEdit && !isRecoveryProposal}<Input id={`example-${level}-${feature.key}`} type="number" value={value ?? ''} disabled={busy || pendingSave} oninput={(event) => setExampleValue(level, feature.key, event.currentTarget.value === '' ? null : Number(event.currentTarget.value))} />{:else}<p class="text-sm tabular-nums">{value ?? 'Missing'}</p>{/if}</div>{/each}</fieldset>
            {/each}
        </details>
            </div>{:else}<div class="min-w-0 space-y-4">
                <div class="space-y-1">
                    <h3 class="text-base font-semibold text-foreground flex items-center gap-2">
                        <Compass class="size-5 text-primary" />
                        Strand Priority &amp; Career Alignment Profile
                    </h3>
                    <p class="text-sm text-muted-foreground">Advisory preferences only. These do not change the aptitude and GWA model inputs or decide admission.</p>
                </div>

                {#if hollandTarget}
                    <div class="rounded-xl border border-primary/20 bg-primary/5 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <span class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground font-mono font-bold text-base shadow-xs">
                                    {hollandTarget.code}
                                </span>
                                <div>
                                    <h4 class="text-sm font-bold text-foreground">
                                        {program.code} Institutional Career Target: {hollandTarget.name}
                                    </h4>
                                    <p class="text-xs text-muted-foreground">{hollandTarget.domain}</p>
                                </div>
                            </div>
                            <Badge variant="outline" class="font-mono text-xs border-primary/40 text-primary bg-background">
                                CHED / DepEd OII Standard
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground pt-2 border-t border-border/40">
                            <span><strong>Regulatory Basis:</strong> {hollandTarget.cmo}</span>
                            <span>&middot;</span>
                            <span><strong>Recommended High School Track:</strong> {hollandTarget.track}</span>
                        </div>
                    </div>
                {/if}

                <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3">
                    <h4 class="text-sm font-semibold text-foreground flex items-center gap-1.5">
                        <Award class="size-4 text-primary" />
                        <span>Programme Priority Senior High School Strands</span>
                        <InfoPopover
                            title="Feeder strand preference"
                            content="Highlights applicants from aligned Senior High School tracks (e.g., STEM, ABM, HUMSS, TVL) during Release Day advising."
                        />
                    </h4>
                    <p class="text-xs text-muted-foreground">Select the feeder strands given curriculum preference in this benchmark set.</p>
                    <div class="flex flex-wrap gap-2 pt-1">
                        {#each strandChoices as choice (choice)}
                            {@const isSelected = content.authoring?.preferred_strands?.includes(choice) ?? false}
                            <button
                                type="button"
                                class={`min-h-10 rounded-full border px-4 text-xs font-semibold transition-colors cursor-pointer ${isSelected ? 'border-primary bg-primary text-primary-foreground shadow-xs' : 'border-border bg-background text-foreground hover:bg-muted/40'}`}
                                aria-pressed={isSelected}
                                disabled={!canEdit || isRecoveryProposal || busy || pendingSave}
                                onclick={() => togglePreferredStrand(choice)}
                            >
                                {choice}
                            </button>
                        {/each}
                    </div>
                    {#if !strandChoices.length}
                        <p class="text-xs text-muted-foreground">No strand choices are present in the reviewed examples.</p>
                    {/if}
                </div>
            </div>{/if}
        </div>
        <details class="border-t py-3"><summary class="cursor-pointer text-xs font-medium">Technical details</summary><ul class="mt-2 space-y-1 text-xs text-muted-foreground">{#each numericFeatures as feature (feature.key)}<li>{feature.label}: {feature.key} · {featureUnit(feature) ?? 'unit unverified'}</li>{/each}</ul></details>
            {#if reasonOpen}<div class="space-y-3 border-t pt-4"><label for="benchmark-reason" class="text-sm font-medium">Reason for this save</label><Textarea id="benchmark-reason" bind:value={reason} required maxlength={500} rows={2} disabled={busy} /><div class="flex flex-wrap gap-2"><Button type="button" disabled={busy || !reason.trim() || hasRangeErrors && !pendingSave} onclick={confirmSave}>{busy ? 'Saving…' : pendingSave ? 'Retry same save' : 'Confirm save'}</Button><Button type="button" variant="outline" disabled={busy || pendingSave} onclick={() => { reasonOpen = false; reason = ''; }}>Cancel</Button></div></div>{:else}<div class="flex flex-wrap gap-2 border-t pt-4"><Button type="button" disabled={busy || (!pendingSave && !dirty && version > 0)} onclick={initiateSave}>{pendingSave ? 'Retry same save' : version ? 'Save changes' : 'Start draft'}</Button><Button type="button" variant="outline" disabled={busy || pendingSave || !dirty} onclick={onReset}>Discard unsaved changes</Button></div>{/if}
    {:else}
        <p class="py-4 text-sm text-muted-foreground">No saved benchmark values are available for this programme and academic year. No values have been substituted.</p>
        {#if canEdit && !isRecoveryProposal}
            {#if reasonOpen || pendingSave}<div class="max-w-xl space-y-3 border-t pt-4"><label for="benchmark-start-reason" class="text-sm font-medium">Reason for starting a draft</label><Textarea id="benchmark-start-reason" bind:value={reason} required maxlength={500} rows={2} disabled={busy} /><div class="flex gap-2"><Button type="button" disabled={busy || !features.length || !reason.trim()} onclick={confirmSave}>{pendingSave ? 'Retry same start' : 'Start draft'}</Button><Button type="button" variant="outline" disabled={busy || pendingSave} onclick={() => { reasonOpen = false; reason = ''; }}>Cancel</Button></div>{#if !features.length}<p class="text-xs text-muted-foreground">A confirmed benchmark feature catalogue is required. No catalogue is available for this context.</p>{/if}</div>{:else}<Button type="button" disabled={busy || !features.length} onclick={initiateSave}>Start draft</Button>{/if}
        {/if}
    {/if}
</section>
