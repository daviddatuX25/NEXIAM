<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import SubtestsTableCard from '@/Components/Setup/SubtestsTableCard.svelte';
    import AptitudeAreasTableCard from '@/Components/Setup/AptitudeAreasTableCard.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';
    import { Plus, ShieldCheck, ArrowLeft, Pencil } from 'lucide-svelte';
    import { onMount } from 'svelte';

    let {
        instrument,
        request_id,
        constructs = [],
        tables = {},
    } = $props();

    let fromKit = $state(null);
    let fromVersion = $state(null);
    onMount(() => {
        const params = new URLSearchParams(window.location.search);
        fromKit = params.get('from_kit');
        fromVersion = params.get('version');
    });

    let draft = $derived(instrument.draft_version);
    let approved = $derived(instrument.current_approved_version);
    let selectedVersionId = $state(null);

    const activeVersion = $derived.by(() => {
        const versions = instrument.versions ?? [];
        if (selectedVersionId !== null) {
            const matched = versions.find((v) => Number(v.id) === Number(selectedVersionId));
            if (matched) return matched;
        }
        if (draft) {
            return versions.find((v) => Number(v.id) === Number(draft.id)) ?? draft;
        }
        if (approved) {
            return versions.find((v) => Number(v.id) === Number(approved.id)) ?? approved;
        }
        return versions[0] ?? null;
    });
    const activeFinalOnly = $derived(Boolean(activeVersion?.profile_metadata?.is_final_only));

    let previewSubtests = $derived((activeVersion?.subtests ?? []).map((subtest) => ({
        ...subtest,
        items: (subtest.items ?? []).map((item) => ({ ...item, option_tokens: [...(item.option_tokens ?? [])] })),
    })));
    let previewMetrics = $derived((activeVersion?.metrics ?? []).map((metric) => ({
        ...metric,
        input_codes: [...(metric.input_codes ?? [])],
        required_output_scales: [...(metric.required_output_scales ?? [])],
    })));
    let previewTables = $derived({ ...(tables ?? {}) });

    const approvalForm = useForm({
        request_id,
        expected_revision: draft?.lock_version ?? null,
        source_review_attestation: false,
    });
    const successorForm = useForm({
        request_id,
        expected_revision: approved?.lock_version ?? null,
    });
    const retirementForm = useForm({
        request_id,
        expected_revision: approved?.lock_version ?? null,
        reason: '',
    });

    const materialRequirementLabel = (requirement, finalOnly = false) =>
        finalOnly
            ? 'No-paper Test Setup · external final scores'
            : ({
                unresolved: 'Unresolved',
                paper_backed: 'Paper-backed materials required',
                no_paper: 'No-paper, direct-manual only',
            })[requirement] ?? 'Unresolved';

    const breadcrumbs = $derived([
        { label: 'Setup', href: '/setup' },
        { label: 'Test instruments', href: '/setup/test-instruments' },
        { label: instrument.code },
    ]);

    function approve(event) {
        event.preventDefault();
        approvalForm.post(
            `/setup/test-instruments/${instrument.id}/versions/${draft.id}/approve`,
            { preserveScroll: true, preserveState: 'errors' },
        );
    }

    function createSuccessor(event) {
        event.preventDefault();
        successorForm.post(
            `/setup/test-instruments/${instrument.id}/versions/${approved.id}/successor`,
            { preserveState: 'errors' },
        );
    }

    function retire(event) {
        event.preventDefault();
        retirementForm.post(
            `/setup/test-instruments/${instrument.id}/versions/${approved.id}/retire`,
            { preserveScroll: true, preserveState: 'errors' },
        );
    }
</script>

<svelte:head><title>{instrument.code} · Test Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto min-w-0 max-w-7xl space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={`Test Setup: ${activeVersion?.display_name ?? draft?.display_name ?? approved?.display_name ?? instrument.code}`}
            description={activeFinalOnly ? 'Six required aptitude constructs with externally supplied percentile ranks; raw formulas and local norm tables are not applicable.' : 'Subtest inventory, raw scoring formulas, and percentile conversion tables.'}
        >
            {#snippet actions()}
                {#if fromKit}
                    <a
                        href={`/setup/exam-materials/test-kits/${fromKit}${fromVersion ? `/${fromVersion}` : ''}`}
                    >
                        <Button variant="outline" size="sm" class="min-h-10 gap-1.5">
                            <ArrowLeft class="size-4" /> Back to Test Kit
                        </Button>
                    </a>
                {/if}
                <Link href="/setup/test-instruments">
                    <Button variant="outline" class="min-h-10 gap-1.5">
                        <ArrowLeft class="size-4" /> Back to Instruments
                    </Button>
                </Link>
                {#if draft}
                    <Link href={`/setup/test-instruments/${instrument.id}/edit`}>
                        <Button class="min-h-10 gap-2">
                            <Pencil class="size-4" /> Edit Draft Setup
                        </Button>
                    </Link>
                {:else if approved}
                    <form onsubmit={createSuccessor} class="inline-flex">
                        <Button
                            type="submit"
                            disabled={successorForm.processing}
                            class="min-h-10 gap-2"
                        >
                            <Plus class="size-4" /> Edit / New Draft Version
                        </Button>
                    </form>
                {/if}
            {/snippet}
        </PageHeaderBar>

        {#if instrument.is_synthetic}
            <div
                class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-3.5 text-sm"
                role="status"
            >
                <strong>Synthetic software fixture.</strong> This profile is for rehearsal/testing only.
            </div>
        {/if}

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/70 bg-card p-3.5 text-sm shadow-xs">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">Viewing:</span>
                {#if activeVersion}
                    <Badge variant={activeVersion.status === 'approved' ? 'default' : 'secondary'}>
                        v{activeVersion.version_number} · {activeVersion.status}
                    </Badge>
                    <Badge variant="outline">
                        {materialRequirementLabel(activeVersion.material_requirement, activeFinalOnly)}
                    </Badge>
                {/if}
                <span class="text-muted-foreground text-xs">
                    Read-only preview · {activeFinalOnly ? 'Six required final aptitude scores are supplied in percentile-rank scale; no subtests or local norm tables apply.' : draft ? 'Click "Edit Draft Setup" to modify subtests, formulas, or conversion tables.' : 'Click "Edit / New Draft Version" to open an editable successor draft while keeping the approved version intact.'}
                </span>
            </div>

            {#if (instrument.versions ?? []).length > 1}
                <div class="flex flex-wrap items-center gap-1.5" aria-label="Version selector">
                    {#each instrument.versions as ver (ver.id)}
                        <button
                            type="button"
                            onclick={() => (selectedVersionId = ver.id)}
                            class={`rounded-md border px-2.5 py-1 text-xs font-medium transition-colors ${Number(activeVersion?.id) === Number(ver.id) ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-background hover:bg-muted'}`}
                        >
                            v{ver.version_number} ({ver.status})
                        </button>
                    {/each}
                </div>
            {/if}
        </div>

        {#if successorForm.errors.version}
            <p class="rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm text-destructive" role="alert">
                {successorForm.errors.version}
            </p>
        {/if}

        {#if draft}
            <section
                class="border-border bg-card space-y-3 rounded-xl border p-4"
                aria-labelledby="draft-heading"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="draft-heading" class="text-sm font-semibold">
                            Draft version {draft.version_number} ready for editing or approval
                        </h2>
                        <p class="text-muted-foreground text-xs">
                            Revision {draft.lock_version} · Material requirement: {materialRequirementLabel(draft.material_requirement, Boolean(draft.profile_metadata?.is_final_only))} · Procedure: {draft.administration_procedure_reference ?? 'Not recorded'}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge variant="secondary">Draft</Badge>
                        <Link href={`/setup/test-instruments/${instrument.id}/edit`}>
                            <Button size="sm" variant="outline" class="gap-1.5">
                                <Pencil class="size-3.5" /> Edit Draft
                            </Button>
                        </Link>
                    </div>
                </div>

                {#if draft.profile_metadata?.is_final_only}
                    <p class="text-muted-foreground text-xs">
                        This definition requires externally supplied percentile ranks from 0–100 for its selected aptitude constructs. NEXIAM does not calculate raw scores or require local norm tables; scoring still needs an active eligible Test Kit.
                    </p>
                {:else if draft.material_requirement === 'no_paper'}
                    <p class="text-muted-foreground text-xs">
                        This is a declared direct-manual procedure, not a schedule-free route. Direct-manual use still requires this approved procedure.
                    </p>
                {:else if draft.material_requirement === 'unresolved'}
                    <p class="text-muted-foreground text-xs">
                        Unresolved does not establish no-paper applicability. Approval requires an explicit material choice and procedure reference.
                    </p>
                {/if}

                {#if !instrument.is_synthetic}
                    <form
                        class="border-border space-y-3 border-t pt-3"
                        onsubmit={approve}
                    >
                        {#if approvalForm.errors['profile.material_requirement'] ||
                            approvalForm.errors['profile.administration_procedure_reference']}
                            <div
                                class="border-destructive/40 bg-destructive/5 rounded-lg border p-3 text-sm"
                                role="alert"
                            >
                                {#if approvalForm.errors['profile.material_requirement']}
                                    <p>{approvalForm.errors['profile.material_requirement']}</p>
                                {/if}
                                {#if approvalForm.errors['profile.administration_procedure_reference']}
                                    <p>{approvalForm.errors['profile.administration_procedure_reference']}</p>
                                {/if}
                            </div>
                        {/if}
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <label class="flex items-start gap-2.5 text-xs sm:items-center">
                                <input
                                    type="checkbox"
                                    bind:checked={approvalForm.source_review_attestation}
                                    class="accent-primary mt-0.5 size-4 sm:mt-0"
                                />
                                <span>
                                    {draft.profile_metadata?.is_final_only
                                        ? 'I reviewed the external assessment source, confirmed that it reports percentile rank, and reviewed permitted-use evidence and the intake procedure.'
                                        : 'I reviewed the official instrument/form/scoring source and permitted-use evidence.'}
                                </span>
                            </label>
                            <Button
                                type="submit"
                                size="sm"
                                disabled={approvalForm.processing || !approvalForm.source_review_attestation}
                                class="min-h-9 gap-2"
                            >
                                <ShieldCheck class="h-4 w-4" /> Approve version
                            </Button>
                        </div>
                        {#if approvalForm.errors.source_review_attestation}
                            <p class="text-destructive text-sm" role="alert">
                                {approvalForm.errors.source_review_attestation}
                            </p>
                        {/if}
                        {#if approvalForm.errors.version}
                            <p class="text-destructive text-sm" role="alert">
                                {approvalForm.errors.version}
                            </p>
                        {/if}
                    </form>
                {/if}
            </section>
        {/if}

        <div class="grid min-w-0 gap-6">
            {#if !activeFinalOnly}
            <SubtestsTableCard
                bind:subtests={previewSubtests}
                bind:metrics={previewMetrics}
                readOnly={true}
            />
            {/if}
            <AptitudeAreasTableCard
                bind:metrics={previewMetrics}
                bind:tables={previewTables}
                subtests={previewSubtests}
                {constructs}
                finalOnlyMode={activeFinalOnly}
                readOnly={true}
            />
        </div>

        <details class="group rounded-xl border border-border/70 bg-card p-4 shadow-xs">
            <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold select-none [&::-webkit-details-marker]:hidden">
                <span>Show Test Details, Source References &amp; Version Lifecycle</span>
                <span aria-hidden="true" class="size-2 rotate-45 border-b-2 border-r-2 border-muted-foreground/70 transition-transform group-open:-rotate-[135deg]"></span>
            </summary>

            <div class="mt-4 space-y-6 border-t border-border/60 pt-4">
                {#if activeVersion}
                    <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-muted-foreground text-xs">Instrument Code</dt>
                            <dd class="font-mono">{instrument.code}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Publisher</dt>
                            <dd>{activeVersion.publisher_name ?? 'Unverified'}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Form / Edition / Language</dt>
                            <dd>
                                {activeVersion.form_code ?? 'Unspecified'} · {activeVersion.edition ?? 'Unspecified'} · {activeVersion.language ?? 'Unspecified'}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Source reference</dt>
                            <dd class="break-words">{activeVersion.source_reference ?? 'Not recorded'}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Permitted-use reference</dt>
                            <dd class="break-words">{activeVersion.permitted_use_reference ?? 'Not recorded'}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Material requirement</dt>
                            <dd>{materialRequirementLabel(activeVersion.material_requirement)}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-muted-foreground text-xs">Administration procedure reference</dt>
                            <dd class="break-words">{activeVersion.administration_procedure_reference ?? 'Not recorded'}</dd>
                        </div>
                        {#if activeVersion.content_sha256}
                            <div class="sm:col-span-3">
                                <dt class="text-muted-foreground text-xs">Immutable content SHA-256</dt>
                                <dd class="font-mono text-xs break-all">{activeVersion.content_sha256}</dd>
                            </div>
                        {/if}
                    </dl>
                {/if}

                {#if approved}
                    <div class="space-y-4 border-t border-border/60 pt-4">
                        <h3 id="lifecycle-heading" class="text-sm font-semibold">Version lifecycle</h3>
                        {#if !draft}
                            <form class="flex flex-wrap items-end gap-2" onsubmit={createSuccessor}>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    disabled={successorForm.processing}
                                    class="min-h-10 gap-2"
                                >
                                    <Plus class="h-4 w-4" /> Create successor draft
                                </Button>
                            </form>
                        {/if}
                        <form class="grid gap-3 sm:grid-cols-2 sm:items-end" onsubmit={retire}>
                            <div class="space-y-1.5">
                                <Label for="retirement-reason">Retirement reason</Label>
                                <Input
                                    id="retirement-reason"
                                    bind:value={retirementForm.reason}
                                    maxlength="500"
                                    required
                                />
                                {#if retirementForm.errors.reason}
                                    <p class="text-destructive text-sm" role="alert">
                                        {retirementForm.errors.reason}
                                    </p>
                                {/if}
                            </div>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={retirementForm.processing}
                                class="min-h-10"
                            >
                                Retire current version
                            </Button>
                        </form>
                    </div>
                {/if}
            </div>
        </details>
    </div>
</StaffLayout>
