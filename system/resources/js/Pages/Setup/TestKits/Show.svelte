<script>
    import KitCompositionEditor from '@/Components/Setup/KitCompositionEditor.svelte';
    import KitPairedPreview from '@/Components/Setup/KitPairedPreview.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { newIdempotencyKey } from '@/lib/utils.js';
    import {
        BookOpen,
        CheckCircle2,
        Circle,
        Layers,
        ScanLine,
    } from 'lucide-svelte';

    let {
        test_kit = {},
        test_kit_version = null,
        test_profile = null,
        answer_sheet = null,
        booklet = null,
        preparation = null,
        composition = null,
        component_choices = {},
        trial = {},
        recent_receipts = [],
        readiness = {},
        version_history = [],
        kit_context = null,
        actions = {},
    } = $props();

    let revokeReason = $state('');
    let retireReason = $state('');
    let selectedReceiptId = $state(
        String(test_kit_version?.selected_acceptance_receipt_id ?? ''),
    );
    let selection = $state({ ...(preparation?.selections ?? {}) });
    let selectionPreparationId = $state(preparation?.id ?? null);
    let selectionPreparationRevision = $state(preparation?.revision ?? null);
    let savingPreparation = $state(false);
    let discardConfirmationOpen = $state(false);
    let discardingPreparation = $state(false);

    $effect(() => {
        const currentPreparationId = preparation?.id ?? null;
        const currentPreparationRevision = preparation?.revision ?? null;

        if (currentPreparationId !== selectionPreparationId) {
            selection = { ...(preparation?.selections ?? {}) };
            selectionPreparationId = currentPreparationId;
            selectionPreparationRevision = currentPreparationRevision;
        } else if (
            !savingPreparation &&
            currentPreparationRevision !== selectionPreparationRevision
        ) {
            selection = { ...(preparation?.selections ?? {}) };
            selectionPreparationRevision = currentPreparationRevision;
        }
    });

    const breadcrumbs = $derived([
        { label: 'Setup', href: '/setup' },
        {
            label: 'Test Kits',
            href: actions.index ?? '/setup/exam-materials/test-kits',
        },
        { label: test_kit.code ?? 'Test Kit' },
    ]);
    const versionStatus = $derived(
        (test_kit_version?.status ?? '').toLowerCase(),
    );
    const lifecycleLabel = $derived(
        {
            draft: 'Draft',
            active: 'Active',
            history: 'History',
            revoked: 'Revoked',
        }[test_kit_version?.use_status] ?? versionStatus,
    );
    const hasExternalFinalOnlyProfile = $derived(
        test_kit_version?.scoring_method === 'final_scores_only' ||
            preparation?.selected?.test_profile?.is_final_only === true ||
            test_profile?.is_final_only === true,
    );
    const readinessTotal = $derived(Math.max(0, Number(readiness.total ?? 5)));
    const readyScore = $derived(
        Math.max(0, Math.min(readinessTotal, Number(readiness.score ?? 0))),
    );
    const readinessPercent = $derived(
        readinessTotal > 0
            ? Math.round((readyScore / readinessTotal) * 100)
            : 0,
    );
    const passingReceipts = $derived(
        recent_receipts.filter((receipt) => receipt.result === 'passed'),
    );
    const activationBlockers = $derived(
        (readiness.blocker_details ?? []).map((blocker) => blocker.message),
    );
    const assemblyBlockers = $derived(preparation?.assembly_blockers ?? []);
    const selectedSheetVersionId = $derived(
        Number(selection.template_version_id) || null,
    );
    const selectedBookletVersionId = $derived(
        Number(selection.booklet_version_id) || null,
    );
    const selectedTestProfile = $derived(
        preparation?.selected?.test_profile ?? null,
    );
    const selectedAnswerSheet = $derived(
        (component_choices.answer_sheet_versions ?? []).find(
            (choice) => choice.id === selectedSheetVersionId,
        ) ??
            (selectedSheetVersionId
                ? (preparation?.selected?.answer_sheet ?? null)
                : null),
    );
    const selectedBooklet = $derived(
        (component_choices.booklet_versions ?? []).find(
            (choice) => choice.id === selectedBookletVersionId,
        ) ??
            (selectedBookletVersionId
                ? (preparation?.selected?.booklet ?? null)
                : null),
    );
    const selectedAnswerSheetLabel = $derived(
        selectedAnswerSheet?.label ??
            (selectedAnswerSheet
                ? `${selectedAnswerSheet.name} · ${selectedAnswerSheet.status}`
                : 'No Ready Answer Sheet selected'),
    );
    const selectedBookletLabel = $derived(
        selectedBooklet?.label ??
            (selectedBooklet
                ? `${selectedBooklet.name} · Set ${selectedBooklet.set_code ?? 'A'} · ${selectedBooklet.status}`
                : 'No Finalized Booklet (Set A) selected'),
    );
    const applicableResources = $derived(
        composition?.applicable_resources ?? null,
    );
    const requirementsResolved = $derived(
        composition?.requirements !== null &&
            composition?.requirements !== undefined &&
            typeof composition?.omr_applicable === 'boolean' &&
            composition?.applicable_resources !== null &&
            composition?.applicable_resources !== undefined,
    );
    const mapApplicable = $derived(composition?.map_applicable === true);
    const requiresAnswerSheet = $derived(
        applicableResources?.answer_sheet === true,
    );
    const requiresBooklet = $derived(
        applicableResources?.booklet_form === true ||
            applicableResources?.key === true,
    );
    const requiresKey = $derived(applicableResources?.key === true);
    const paperResourceRequired = $derived(
        applicableResources !== null &&
            Object.values(applicableResources).some(
                (required) => required === true,
            ),
    );
    const allComponentsSelected = $derived(
        requirementsResolved &&
            Boolean(selectedTestProfile) &&
            (!requiresAnswerSheet || Boolean(selectedSheetVersionId)) &&
            (!requiresBooklet || Boolean(selectedBookletVersionId)),
    );
    function lifecycleVariant(status) {
        switch ((status ?? '').toLowerCase()) {
            case 'active':
                return 'default';
            case 'candidate':
                return 'outline';
            case 'retired':
                return 'secondary';
            case 'revoked':
                return 'destructive';
            default:
                return 'outline';
        }
    }

    function postAction(url, payload = {}) {
        if (!url || !test_kit_version) return;
        router.post(url, {
            ...payload,
            expected_revision: test_kit_version.revision,
            idempotency_key: newIdempotencyKey(),
        });
    }

    function selectReceipt() {
        if (selectedReceiptId)
            postAction(actions.select_receipt, {
                acceptance_receipt_id: selectedReceiptId,
            });
    }

    function activate() {
        postAction(actions.activate);
    }
    function retire() {
        postAction(actions.retire, { reason: retireReason.trim() || null });
    }
    function revoke() {
        const reason = revokeReason.trim();
        if (reason) postAction(actions.revoke, { reason });
    }

    function selectAnswerSheet(event) {
        const versionId = Number(event.currentTarget.value) || null;
        selection.template_version_id = versionId;
        const choice = (component_choices.answer_sheet_versions ?? []).find(
            (item) => item.id === versionId,
        );
        selection.answer_sheet_draft_id = choice?.draft_id ?? null;
        savePreparation();
    }

    function selectBooklet(event) {
        const versionId = Number(event.currentTarget.value) || null;
        selection.booklet_version_id = versionId;
        const choice = (component_choices.booklet_versions ?? []).find(
            (item) => item.id === versionId,
        );
        selection.booklet_set_id = choice?.set_id ?? null;
        savePreparation();
    }

    function selectTestSetup(event) {
        selection.test_instrument_version_id =
            Number(event.currentTarget.value) || null;
        savePreparation();
    }

    function savePreparation() {
        if (savingPreparation || !actions.save_preparation || !preparation)
            return;
        savingPreparation = true;
        router.put(
            actions.save_preparation,
            {
                preparation_id: preparation.id,
                expected_revision: preparation.revision,
                idempotency_key: newIdempotencyKey(),
                test_instrument_version_id:
                    selection.test_instrument_version_id ?? null,
                norm_package_version_id:
                    selection.norm_package_version_id ?? null,
                answer_sheet_draft_id: selection.answer_sheet_draft_id ?? null,
                template_version_id: selection.template_version_id ?? null,
                booklet_version_id: selection.booklet_version_id ?? null,
                booklet_set_id: selection.booklet_set_id ?? null,
            },
            {
                onFinish: () => {
                    savingPreparation = false;
                },
            },
        );
    }

    function assemble() {
        if (
            !actions.assemble ||
            !preparation ||
            !allComponentsSelected ||
            assemblyBlockers.length > 0
        )
            return;
        router.post(actions.assemble, {
            preparation_id: preparation.id,
            expected_revision: preparation.revision,
            idempotency_key: newIdempotencyKey(),
        });
    }

    function startPreparation() {
        if (!actions.start_preparation) return;
        router.post(actions.start_preparation, {
            expected_family_version: test_kit.latest_version_number ?? 0,
            idempotency_key: newIdempotencyKey(),
        });
    }

    function discardPreparation() {
        if (
            discardingPreparation ||
            !preparation ||
            !actions.discard_preparation
        ) {
            return;
        }
        discardingPreparation = true;
        router.post(
            actions.discard_preparation,
            {
                preparation_id: preparation.id,
                expected_revision: preparation.revision,
                idempotency_key: newIdempotencyKey(),
            },
            {
                onFinish: () => {
                    discardingPreparation = false;
                    discardConfirmationOpen = false;
                },
            },
        );
    }

    function acceptCompositionSave(acknowledgedComposition) {
        composition = acknowledgedComposition;
        preparation = {
            ...preparation,
            revision: acknowledgedComposition.revision,
        };
        router.reload({
            only: ['preparation', 'composition'],
            preserveScroll: true,
        });
    }
</script>

<svelte:head
    ><title>{test_kit.code ?? 'Test Kit'} · Test Kits</title></svelte:head
>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        {#snippet headerActions()}
            {#if test_kit_version}
                <Badge variant={lifecycleVariant(versionStatus)}
                    >{lifecycleLabel}</Badge
                >
            {:else}
                <Badge variant="outline">Preparing</Badge>
            {/if}
            {#if actions.start_preparation}
                <Button type="button" onclick={startPreparation}
                    >Prepare New Version</Button
                >
            {/if}
            <Link href={actions.index ?? '/setup/exam-materials/test-kits'}>
                <Button variant="outline">Back to Test Kits</Button>
            </Link>
        {/snippet}
        <PageHeaderBar
            title={`${test_kit.code ?? 'Test Kit'} · ${test_kit.name ?? ''}`}
            actions={headerActions}
        />

        {#if version_history.length > 0}
            <section aria-label="Kit version inspection" class="space-y-2">
                <h2 class="text-sm font-semibold">Kit versions</h2>
                <nav aria-label="Version history" class="flex flex-wrap gap-2">
                    {#each version_history as version (version.id)}
                        <Link
                            href={version.url}
                            aria-current={Number(test_kit_version?.id) ===
                            Number(version.id)
                                ? 'page'
                                : undefined}
                            class={`inline-flex min-h-10 items-center rounded-md border px-3 py-2 text-sm ${Number(test_kit_version?.id) === Number(version.id) ? 'border-primary bg-primary/10 text-foreground font-semibold' : 'border-border bg-card text-muted-foreground hover:text-foreground'}`}
                            >v{version.version_number} · {{
                                candidate: 'Draft',
                                active: 'Active',
                                retired: 'History',
                                revoked: 'Revoked',
                            }[version.status] ?? version.status}</Link
                        >
                    {/each}
                </nav>
                <p class="text-muted-foreground text-xs">
                    Inspect a locked version without activating it. Activation
                    and trial readiness are separate.
                </p>
            </section>
        {/if}

        {#if test_kit.is_synthetic && test_kit.code !== 'TK-ICAT-2026'}
            <p
                class="rounded-md border border-amber-500/20 bg-amber-500/5 px-3 py-2 text-sm text-amber-800 dark:text-amber-200"
            >
                Synthetic demo materials. Not approved for institutional live
                use.
            </p>
        {/if}
        <section
            class="border-border/80 bg-card space-y-3 rounded-2xl border p-5 shadow-xs sm:p-6"
            aria-label="Test Kit readiness"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span
                    class="text-foreground inline-flex items-center gap-1.5 text-sm font-medium"
                >
                    <span
                        >{readyScore} of {readinessTotal} applicable setup checks
                        ready</span
                    >
                    <InfoPopover
                        title="Conditional readiness checks"
                        content="Tracks only the components required by your selected Test Setup: no-paper setups require an approved Test Setup only, while paper-backed setups also require a compiled Answer Sheet, finalized Booklet, and item mapping."
                    />
                </span>
                <span class="text-muted-foreground text-sm"
                    >{readiness.configuration_status === 'unresolved'
                        ? 'Requirements unresolved'
                        : `${readinessPercent}%`}</span
                >
            </div>
            <div
                class="bg-muted h-2.5 overflow-hidden rounded-full"
                role="progressbar"
                aria-label="Test Kit readiness"
                aria-valuemin="0"
                aria-valuemax={readinessTotal}
                aria-valuenow={readyScore}
            >
                <div
                    class="bg-primary h-full rounded-full transition-[width]"
                    style={`width: ${readinessPercent}%`}
                ></div>
            </div>
        </section>

        {#if preparation?.state === 'preparing'}
            <section
                class="border-border bg-card space-y-5 rounded-xl border p-4 sm:p-5"
                aria-label="Test Kit preparation"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2
                        class="text-foreground flex items-center gap-1.5 text-base font-semibold"
                    >
                        <span>Preparing Kit</span>
                        <InfoPopover
                            title="Save → Lock → Edit workflow"
                            content="Save updates this open draft preparation. Lock atomically creates a numbered, immutable Test Kit version for exam sessions and scoring. Editing a locked version opens the next draft."
                        />
                    </h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">No candidate assembled</Badge>
                        {#if actions.discard_preparation}
                            {#if discardConfirmationOpen}
                                <span
                                    class="text-muted-foreground text-sm"
                                    role="status"
                                >
                                    Discard this draft? Its selections and map
                                    history will remain retained.
                                </span>
                                <Button
                                    type="button"
                                    variant="destructive"
                                    onclick={discardPreparation}
                                    disabled={discardingPreparation}
                                    >Confirm discard</Button
                                >
                                <Button
                                    type="button"
                                    variant="outline"
                                    onclick={() =>
                                        (discardConfirmationOpen = false)}
                                    disabled={discardingPreparation}
                                    >Keep draft</Button
                                >
                            {:else}
                                <Button
                                    type="button"
                                    variant="outline"
                                    onclick={() =>
                                        (discardConfirmationOpen = true)}
                                >
                                    Discard Draft
                                </Button>
                            {/if}
                        {/if}
                    </div>
                </div>
                <p class="text-muted-foreground text-sm">
                    Save keeps an editable preparation. Lock creates a numbered
                    version only when its required components are complete.
                </p>
                <div
                    class="grid min-w-0 grid-cols-1 items-start gap-5 xl:grid-cols-2"
                >
                    <article
                        class={`bg-background flex min-w-0 flex-col gap-5 rounded-xl border p-5 ${selectedTestProfile ? 'border-emerald-500/50' : 'border-border'}`}
                    >
                        <header class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                                    ><Layers
                                        class="size-5"
                                        aria-hidden="true"
                                    /></span
                                >
                                <h3
                                    class="text-foreground flex items-center gap-1.5 font-semibold"
                                >
                                    <span>Test Setup</span>
                                    <InfoPopover
                                        title="Scoring rules & subtest inventory"
                                        content="Links this Test Kit to an approved Test Setup (subtests, raw-score formulas, construct bindings, and percentile conversion tables)."
                                    />
                                </h3>
                            </div>
                            {#if selectedTestProfile}<CheckCircle2
                                    class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                    aria-label="Selected"
                                />{:else}<Circle
                                    class="text-muted-foreground size-5 shrink-0"
                                    aria-label="Not selected"
                                />{/if}
                        </header>
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="font-medium">
                                {selectedTestProfile?.name ||
                                    'No profile configured'}
                            </p>
                            <label
                                for="kit-test-setup-version"
                                class="text-muted-foreground block text-sm"
                                >Select approved Test Setup</label
                            >
                            <select
                                id="kit-test-setup-version"
                                value={selection.test_instrument_version_id ??
                                    ''}
                                onchange={selectTestSetup}
                                disabled={savingPreparation ||
                                    !actions.save_preparation}
                                class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                            >
                                <option value=""
                                    >Select an approved Test Setup</option
                                >
                                {#each component_choices.test_instruments ?? [] as choice (choice.id)}
                                    <option value={choice.id}
                                        >{choice.label}</option
                                    >
                                {/each}
                            </select>
                            <p class="text-sm font-medium">
                                Scoring method: {selectedTestProfile
                                    ? hasExternalFinalOnlyProfile
                                        ? 'Final scores only'
                                        : 'Raw scoring'
                                    : 'Select a Test Setup'}
                            </p>
                            <p class="text-muted-foreground text-sm">
                                {hasExternalFinalOnlyProfile
                                    ? 'Norms: Not applicable'
                                    : preparation.selected?.norm
                                      ? `Norm: ${preparation.selected.norm.name} · ${preparation.selected.norm.status}`
                                      : 'No norm selected'}
                            </p>
                        </div>
                        {#if actions.test_setup}<Link
                                href={actions.test_setup}
                                class="text-primary inline-flex min-h-10 items-center text-sm font-medium underline underline-offset-4"
                                >Configure Test Setup <span aria-hidden="true"
                                    >→</span
                                ></Link
                            >{/if}
                    </article>
                    <section
                        aria-label="Paired paper materials"
                        class="border-border/70 min-w-0 space-y-4 rounded-xl border p-4"
                    >
                        <header class="space-y-1">
                            <h3 class="font-semibold">
                                Paired paper materials
                            </h3>
                            <p class="text-muted-foreground text-sm">
                                Answer Sheet and Question Booklet belong to the
                                same Kit configuration.
                            </p>
                        </header>
                        <div
                            class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            {#if !hasExternalFinalOnlyProfile}
                                <article
                                    class={`bg-background flex min-w-0 flex-col gap-5 rounded-xl border p-5 ${selectedSheetVersionId ? 'border-emerald-500/50' : 'border-border'}`}
                                >
                                    <header
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
                                            <span
                                                class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                                                ><ScanLine
                                                    class="size-5"
                                                    aria-hidden="true"
                                                /></span
                                            >
                                            <h3
                                                class="text-foreground font-semibold"
                                            >
                                                Answer Sheet
                                                {#if requirementsResolved && !requiresAnswerSheet}
                                                    <Badge variant="outline"
                                                        >Optional attachment</Badge
                                                    >
                                                {/if}
                                            </h3>
                                        </div>
                                        {#if selectedSheetVersionId}<CheckCircle2
                                                class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                aria-label="Selected"
                                            />{:else}<Circle
                                                class="text-muted-foreground size-5 shrink-0"
                                                aria-label="Not selected"
                                            />{/if}
                                    </header>
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <p class="font-medium">
                                            {selectedAnswerSheetLabel}
                                        </p>
                                        <label
                                            for="answer-sheet-version"
                                            class="text-muted-foreground block text-sm"
                                            >Ready Answer Sheet</label
                                        >
                                        <select
                                            id="answer-sheet-version"
                                            value={selection.template_version_id ??
                                                ''}
                                            onchange={selectAnswerSheet}
                                            disabled={savingPreparation ||
                                                !actions.save_preparation}
                                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                                        >
                                            <option value=""
                                                >Select a ready answer sheet</option
                                            >
                                            {#each component_choices.answer_sheet_versions ?? [] as choice (choice.id)}
                                                <option value={choice.id}
                                                    >{choice.label}</option
                                                >
                                            {/each}
                                        </select>
                                    </div>
                                    {#if actions.answer_sheet}
                                        <Link
                                            href={actions.answer_sheet}
                                            class="text-primary inline-flex min-h-10 items-center text-sm font-medium underline underline-offset-4"
                                        >
                                            Manage Answer Sheets <span
                                                aria-hidden="true">→</span
                                            >
                                        </Link>
                                    {/if}
                                </article>
                                <article
                                    class={`bg-background flex min-w-0 flex-col gap-5 rounded-xl border p-5 ${selectedBookletVersionId ? 'border-emerald-500/50' : 'border-border'}`}
                                >
                                    <header
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
                                            <span
                                                class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                                                ><BookOpen
                                                    class="size-5"
                                                    aria-hidden="true"
                                                /></span
                                            >
                                            <h3
                                                class="text-foreground font-semibold"
                                            >
                                                Question Booklet (Set A)
                                                {#if requirementsResolved && !requiresBooklet}
                                                    <Badge variant="outline"
                                                        >Optional attachment</Badge
                                                    >
                                                {/if}
                                            </h3>
                                        </div>
                                        {#if selectedBookletVersionId}<CheckCircle2
                                                class="size-5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                                aria-label="Selected"
                                            />{:else}<Circle
                                                class="text-muted-foreground size-5 shrink-0"
                                                aria-label="Not selected"
                                            />{/if}
                                    </header>
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <p class="font-medium">
                                            {selectedBookletLabel}
                                        </p>
                                        <label
                                            for="booklet-version"
                                            class="text-muted-foreground block text-sm"
                                            >{requiresKey
                                                ? 'Finalized Booklet (Set A)'
                                                : 'Finalized Booklet form'}</label
                                        >
                                        <select
                                            id="booklet-version"
                                            value={selection.booklet_version_id ??
                                                ''}
                                            onchange={selectBooklet}
                                            disabled={savingPreparation ||
                                                !actions.save_preparation}
                                            class="border-input bg-background min-h-10 w-full rounded-md border px-3 text-sm"
                                        >
                                            <option value=""
                                                >Select a finalized booklet</option
                                            >
                                            {#each component_choices.booklet_versions ?? [] as choice (choice.id)}
                                                <option value={choice.id}
                                                    >{choice.label}</option
                                                >
                                            {/each}
                                        </select>
                                    </div>
                                    {#if actions.booklet}
                                        <Link
                                            href={actions.booklet}
                                            class="text-primary inline-flex min-h-10 items-center text-sm font-medium underline underline-offset-4"
                                        >
                                            Manage Question Booklets <span
                                                aria-hidden="true">→</span
                                            >
                                        </Link>
                                    {/if}
                                </article>
                            {/if}
                            {#if hasExternalFinalOnlyProfile}
                                <article
                                    class="bg-background border-border min-w-0 space-y-3 rounded-xl border p-4"
                                >
                                    <ScanLine
                                        class="text-primary size-5"
                                        aria-hidden="true"
                                    />
                                    <h4 class="font-semibold">Answer Sheet</h4>
                                    <Badge variant="outline"
                                        >Not applicable</Badge
                                    >
                                    <p class="text-muted-foreground text-sm">
                                        Final-only scores do not require an
                                        Answer Sheet.
                                    </p>
                                </article>
                                <article
                                    class="bg-background border-border min-w-0 space-y-3 rounded-xl border p-4"
                                >
                                    <BookOpen
                                        class="text-primary size-5"
                                        aria-hidden="true"
                                    />
                                    <h4 class="font-semibold">
                                        Question Booklet
                                    </h4>
                                    <Badge variant="outline"
                                        >Not applicable</Badge
                                    >
                                    <p class="text-muted-foreground text-sm">
                                        Final-only scores do not require a
                                        Booklet or answer key.
                                    </p>
                                </article>
                            {/if}
                        </div>
                    </section>
                </div>
                {#if mapApplicable}
                    <KitCompositionEditor
                        preparationId={preparation.id}
                        {composition}
                        onSave={acceptCompositionSave}
                    />
                    {#if composition?.preview_url}
                        <KitPairedPreview
                            previewUrl={composition.preview_url}
                            contextRevision={composition.revision}
                            contextSnapshot={composition.context}
                        />
                    {/if}
                {:else if requirementsResolved}
                    <p
                        class="text-muted-foreground rounded-lg border px-3 py-2 text-sm"
                    >
                        {hasExternalFinalOnlyProfile
                            ? 'Final-only Setup: Lock the exact construct, percentile-rank, and source declaration without paper, raw mapping, or norms.'
                            : paperResourceRequired
                              ? 'No item mapping is required for this declaration. Selected material requirements still apply.'
                              : 'No-paper Test Setup: Lock remains available without optional materials. Selecting either optional attachment requires the complete tuple and exact saved item map.'}
                    </p>
                {:else}
                    <p
                        class="text-muted-foreground rounded-lg border px-3 py-2 text-sm"
                    >
                        Material applicability is unresolved. Resolve the
                        selected Test Setup declarations before assembling this
                        Kit.
                    </p>
                {/if}
                <div
                    class="grid gap-2 rounded-lg border px-3 py-2 text-sm sm:grid-cols-3"
                    aria-label="Kit configuration status"
                >
                    <p>
                        Configuration: {requirementsResolved
                            ? selectedTestProfile
                                ? 'resolved'
                                : 'select a Test Setup'
                            : 'unresolved'}
                    </p>
                    <p>
                        Attachment: {composition?.attachment_state ??
                            'unresolved'}
                    </p>
                    <p>
                        OMR scoring: {composition?.omr_applicable === true
                            ? 'applicable by Test Setup'
                            : composition?.omr_applicable === false
                              ? 'not applicable'
                              : 'unresolved'}
                    </p>
                </div>

                <section
                    class="border-border bg-card flex flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"
                    aria-label="Assembly readiness"
                >
                    <div class="min-w-0 space-y-2">
                        {#if allComponentsSelected && assemblyBlockers.length === 0}
                            <p
                                class="flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300"
                                role="status"
                            >
                                <CheckCircle2
                                    class="size-5 shrink-0"
                                    aria-hidden="true"
                                />
                                Required configuration is complete. Ready to assemble
                                candidate Kit.
                            </p>
                        {:else if allComponentsSelected}
                            <p class="text-foreground text-sm font-medium">
                                Complete the remaining Kit requirements before
                                assembling.
                            </p>
                        {:else}
                            <div
                                class="flex flex-wrap gap-2"
                                aria-label="Selections needed"
                            >
                                {#if !selectedTestProfile}<Badge
                                        variant="outline"
                                        >Needs Test Setup selection</Badge
                                    >{/if}
                                {#if requiresAnswerSheet && !selectedSheetVersionId}<Badge
                                        variant="outline"
                                        >Needs Answer Sheet selection</Badge
                                    >{/if}
                                {#if requiresBooklet && !selectedBookletVersionId}<Badge
                                        variant="outline"
                                        >Needs Booklet selection</Badge
                                    >{/if}
                            </div>
                        {/if}
                        {#if assemblyBlockers.length > 0}
                            <ul
                                class="text-muted-foreground space-y-1 text-sm"
                                aria-label="Assembly requirements"
                            >
                                {#each assemblyBlockers as blocker (blocker.code)}<li
                                    >
                                        {blocker.message}
                                    </li>{/each}
                            </ul>
                        {/if}
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        onclick={savePreparation}
                        disabled={!actions.save_preparation ||
                            savingPreparation}
                    >
                        Save
                    </Button>
                    <Button
                        type="button"
                        onclick={assemble}
                        disabled={!actions.assemble ||
                            !allComponentsSelected ||
                            assemblyBlockers.length > 0 ||
                            savingPreparation}
                        class="shrink-0"
                    >
                        Lock <span aria-hidden="true">→</span>
                    </Button>
                </section>
            </section>

            <section
                class="border-border bg-card flex flex-col gap-2 rounded-xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                aria-label="Check Kit"
            >
                <div>
                    <h2 class="text-sm font-semibold">Check Kit</h2>
                    <p class="text-muted-foreground text-sm">
                        Available after candidate assembly.
                    </p>
                </div>
                <Badge variant="outline">Not assembled</Badge>
            </section>

            <details class="border-border bg-card rounded-lg border p-4">
                <summary class="text-muted-foreground cursor-pointer text-sm"
                    >More</summary
                >
                <div class="mt-3 space-y-3">
                    <p class="text-muted-foreground text-xs">
                        Preparation revision {preparation.revision}
                    </p>
                    {#if version_history.length > 0}
                        <div
                            class="flex flex-wrap gap-2"
                            aria-label="Version history"
                        >
                            {#each version_history as version (version.id)}<Link
                                    href={version.url}
                                    class="text-primary text-sm underline underline-offset-4"
                                    >v{version.version_number} · {version.status}</Link
                                >{/each}
                        </div>
                    {/if}
                </div>
            </details>
        {:else if test_kit_version}
            <div
                class="grid min-w-0 grid-cols-1 items-start gap-5 xl:grid-cols-2"
            >
                <article
                    class="border-border/80 bg-card flex min-w-0 flex-col gap-4 rounded-2xl border p-5 shadow-xs"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                            >
                                <Layers class="size-5" aria-hidden="true" />
                            </span>
                            <h2 class="text-foreground font-semibold">
                                Test Setup
                            </h2>
                        </div>
                        <Badge
                            variant={test_profile?.status === 'approved'
                                ? 'default'
                                : 'outline'}
                        >
                            {test_profile?.status ?? 'Action Needed'}
                        </Badge>
                    </div>
                    <div class="min-w-0 flex-1 space-y-1 text-sm">
                        <p class="text-foreground truncate font-medium">
                            {test_profile?.display_name ?? 'Test Profile'}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            {#if test_profile?.is_final_only}
                                {test_profile.required_final_metric_count ?? 0} required
                                aptitude final-score metrics
                            {:else}
                                {test_profile?.subtests_count ?? 0} Subtests {test_profile?.subtests_summary
                                    ? `(${test_profile.subtests_summary})`
                                    : ''}
                            {/if}
                        </p>
                        <p class="text-muted-foreground text-xs">
                            Scoring method: {test_profile?.is_final_only
                                ? 'Final scores only'
                                : 'Raw scoring'}
                        </p>
                    </div>
                    {#if actions.test_setup}
                        <Link href={actions.test_setup} class="mt-auto block">
                            <Button variant="outline" size="sm" class="w-full"
                                >Configure Test Setup</Button
                            >
                        </Link>
                    {/if}
                </article>

                <section
                    aria-label="Paired paper materials"
                    class="border-border/70 min-w-0 space-y-4 rounded-xl border p-4"
                >
                    <header class="space-y-1">
                        <h2 class="font-semibold">Paired paper materials</h2>
                        <p class="text-muted-foreground text-sm">
                            Answer Sheet and Question Booklet are inspected
                            together for this exact Kit version.
                        </p>
                    </header>
                    <div class="grid min-w-0 grid-cols-1 gap-4 sm:grid-cols-2">
                        {#if !hasExternalFinalOnlyProfile}
                            <article
                                class="border-border/80 bg-card flex min-w-0 flex-col gap-4 rounded-2xl border p-5 shadow-xs"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span
                                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                                        >
                                            <ScanLine
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <h2
                                            class="text-foreground font-semibold"
                                        >
                                            Answer Sheet
                                        </h2>
                                    </div>
                                    <Badge
                                        variant={answer_sheet?.status ===
                                            'active' ||
                                        answer_sheet?.status === 'compiled'
                                            ? 'default'
                                            : 'outline'}
                                    >
                                        {answer_sheet?.status ?? 'Not attached'}
                                    </Badge>
                                </div>
                                <div class="min-w-0 flex-1 space-y-1 text-sm">
                                    <p
                                        class="text-foreground truncate font-medium"
                                    >
                                        {answer_sheet?.title ||
                                            'No Answer Sheet attached'}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        {answer_sheet?.total_pages ?? 0} Pages · {answer_sheet?.physical_item_count ??
                                            0} Items
                                    </p>
                                </div>
                                {#if composition?.applicable_resources?.answer_sheet === false}
                                    <p class="text-muted-foreground text-xs">
                                        Optional attachment. A no-paper Kit can
                                        be locked without it.
                                    </p>
                                {/if}
                                <div class="mt-auto flex flex-col gap-2">
                                    {#if answer_sheet?.preview_url}
                                        <a
                                            href={answer_sheet.preview_url}
                                            class="block"
                                        >
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="w-full"
                                                >Preview Answer Sheet</Button
                                            >
                                        </a>
                                    {/if}
                                    {#if actions.answer_sheet}
                                        <Link
                                            href={actions.answer_sheet}
                                            class="text-primary text-center text-xs font-medium underline underline-offset-4"
                                        >
                                            Manage Answer Sheets <span
                                                aria-hidden="true">→</span
                                            >
                                        </Link>
                                    {/if}
                                </div>
                            </article>
                            <article
                                class="border-border/80 bg-card flex min-w-0 flex-col gap-4 rounded-2xl border p-5 shadow-xs"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-3"
                                    >
                                        <span
                                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                                        >
                                            <BookOpen
                                                class="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <h2
                                            class="text-foreground font-semibold"
                                        >
                                            Question Booklet
                                        </h2>
                                    </div>
                                    <Badge
                                        variant={booklet?.status === 'finalized'
                                            ? 'default'
                                            : 'outline'}
                                    >
                                        {booklet?.status ?? 'Not attached'}
                                    </Badge>
                                </div>
                                <div class="min-w-0 flex-1 space-y-1 text-sm">
                                    <p
                                        class="text-foreground truncate font-medium"
                                    >
                                        {booklet?.title ||
                                            'No Question Booklet attached'}
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        Set {booklet?.set_code ?? 'A'} · {booklet?.total_keys ??
                                            0} Answer Keys
                                    </p>
                                </div>
                                {#if composition?.applicable_resources?.booklet_form === false}
                                    <p class="text-muted-foreground text-xs">
                                        Optional attachment. A no-paper Kit can
                                        be locked without it.
                                    </p>
                                {/if}
                                <div class="mt-auto flex flex-col gap-2">
                                    {#if booklet?.workspace_url}
                                        <Link
                                            href={booklet.workspace_url}
                                            class="block"
                                        >
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="w-full"
                                                >Edit Answer Keys</Button
                                            >
                                        </Link>
                                    {/if}
                                    {#if actions.booklet}
                                        <Link
                                            href={actions.booklet}
                                            class="text-primary text-center text-xs font-medium underline underline-offset-4"
                                        >
                                            Manage Question Booklets <span
                                                aria-hidden="true">→</span
                                            >
                                        </Link>
                                    {/if}
                                </div>
                            </article>
                        {/if}
                        {#if hasExternalFinalOnlyProfile}
                            <article
                                class="bg-card border-border min-w-0 space-y-3 rounded-xl border p-4"
                            >
                                <ScanLine
                                    class="text-primary size-5"
                                    aria-hidden="true"
                                />
                                <h3 class="font-semibold">Answer Sheet</h3>
                                <Badge variant="outline">Not applicable</Badge>
                                <p class="text-muted-foreground text-sm">
                                    No Answer Sheet is required by this
                                    final-only Setup.
                                </p>
                            </article>
                            <article
                                class="bg-card border-border min-w-0 space-y-3 rounded-xl border p-4"
                            >
                                <BookOpen
                                    class="text-primary size-5"
                                    aria-hidden="true"
                                />
                                <h3 class="font-semibold">Question Booklet</h3>
                                <Badge variant="outline">Not applicable</Badge>
                                <p class="text-muted-foreground text-sm">
                                    No Booklet or answer key is required by this
                                    final-only Setup.
                                </p>
                            </article>
                        {/if}
                    </div>
                    <section
                        class="border-border/80 bg-card flex flex-col gap-3 rounded-2xl border p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between"
                        aria-label="Check Kit"
                    >
                        <div>
                            <h2 class="text-foreground text-base font-semibold">
                                {hasExternalFinalOnlyProfile ||
                                trial.verification_status === 'not_applicable'
                                    ? 'OMR readiness: Not applicable'
                                    : 'Pre-Exam Trial Scan'}
                            </h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                {hasExternalFinalOnlyProfile
                                    ? 'The final-only Test Setup uses no paper, answer key, or OMR capture, so OMR qualification is not applicable.'
                                    : trial.verification_status ===
                                        'not_applicable'
                                      ? 'Approved metric declarations do not require automated item recognition for this Kit.'
                                      : trial.verification_status ===
                                          'unresolved'
                                        ? 'Verification applicability is unresolved.'
                                        : trial.current_verification
                                          ? 'Software verification passed'
                                          : trial.historical_result === 'passed'
                                            ? 'Historical pass · current verification required'
                                            : trial.historical_result ===
                                                'failed'
                                              ? 'Verification failed'
                                              : trial.verification_blockers
                                                      ?.length
                                                ? 'Verification incomplete · retake required'
                                                : 'Not verified'}
                            </p>
                            {#if readiness.operational_holds?.length}
                                <p class="mt-1 text-xs text-amber-700">
                                    Independent source / physical holds: {readiness.operational_holds.join(
                                        ', ',
                                    )}
                                </p>
                            {/if}
                        </div>
                        {#if trial.trial_url}
                            <a href={trial.trial_url}
                                ><Button size="sm">Launch Trial Scan</Button></a
                            >
                        {/if}
                    </section>
                </section>
            </div>

            {#if composition?.preview_url}
                <KitPairedPreview
                    previewUrl={composition.preview_url}
                    contextRevision={composition.preparation_revision ??
                        composition.composition_revision ??
                        composition.revision}
                    contextSnapshot={composition.context}
                />
            {/if}

            {#if versionStatus === 'candidate'}
                <section
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    aria-label="Activation"
                >
                    {#if readiness.is_ready_for_activation}
                        <Button
                            onclick={activate}
                            class="min-h-11 w-full font-semibold sm:w-auto"
                            >Activate Test Kit for Live Exams</Button
                        >
                    {:else}<p class="text-muted-foreground text-sm">
                            Blocked: {activationBlockers.length > 0
                                ? activationBlockers.join(' ')
                                : 'Eligibility requirements are not satisfied.'}
                        </p>{/if}
                </section>
            {/if}

            <details class="border-border bg-card rounded-lg border p-4">
                <summary class="text-muted-foreground cursor-pointer text-sm"
                    >More</summary
                >
                <div class="mt-4 space-y-5">
                    <div
                        class="text-muted-foreground grid gap-2 text-xs sm:grid-cols-2"
                    >
                        <p>
                            Kit version {test_kit_version.version_number} · Revision
                            {test_kit_version.revision}
                        </p>
                        <p>
                            Test Setup v{test_profile?.version_number ?? '—'} · Form
                            {test_profile?.form_code ?? 'unspecified'}
                        </p>
                        {#if answer_sheet}
                            <p>
                                Answer Sheet v{answer_sheet.version_number} ·
                                {answer_sheet.total_pages} pages · {answer_sheet.total_bubbles ??
                                    '—'} bubbles
                            </p>
                        {:else if composition?.applicable_resources?.answer_sheet === true}
                            <p>Answer Sheet · required but unavailable</p>
                        {:else if composition?.state === 'unavailable'}
                            <p>Answer Sheet applicability unresolved</p>
                        {:else}
                            <p>Answer Sheet · Not applicable</p>
                        {/if}
                        {#if booklet}
                            <p>
                                Answer Keys v{booklet.version_number} · {booklet.total_keys}
                                keys
                            </p>
                        {:else if composition?.applicable_resources?.booklet_form === true || composition?.applicable_resources?.key === true}
                            <p>Booklet / key · required but unavailable</p>
                        {:else if composition?.state === 'unavailable'}
                            <p>Booklet / key applicability unresolved</p>
                        {:else}
                            <p>Booklet / key · Not applicable</p>
                        {/if}
                    </div>
                    {#if actions.start_preparation}<Button
                            variant="outline"
                            onclick={startPreparation}>Edit</Button
                        >{/if}
                    {#if versionStatus === 'candidate'}
                        {#if passingReceipts.length > 0}
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-end"
                            >
                                <div class="grid flex-1 gap-2">
                                    <Label for="acceptance-receipt"
                                        >Select passing receipt</Label
                                    >
                                    <select
                                        id="acceptance-receipt"
                                        bind:value={selectedReceiptId}
                                        class="border-input bg-background text-foreground h-10 rounded-md border px-3 text-sm"
                                    >
                                        <option value="">Select receipt</option>
                                        {#each passingReceipts as receipt (receipt.id)}<option
                                                value={String(receipt.id)}
                                                >Receipt #{receipt.id}</option
                                            >{/each}
                                    </select>
                                </div>
                                <Button
                                    variant="outline"
                                    disabled={!selectedReceiptId}
                                    onclick={selectReceipt}
                                    >Select Receipt</Button
                                >
                            </div>
                        {/if}
                        <div
                            class="border-border grid gap-3 border-t pt-4 sm:grid-cols-[1fr_auto] sm:items-end"
                        >
                            <div class="grid gap-2">
                                <Label for="revoke-reason"
                                    >Revoke candidate</Label
                                ><Input
                                    id="revoke-reason"
                                    bind:value={revokeReason}
                                    maxlength="255"
                                    placeholder="Reason for revocation"
                                />
                            </div>
                            <Button
                                variant="destructive"
                                disabled={!revokeReason.trim()}
                                onclick={revoke}>Revoke Test Kit</Button
                            >
                        </div>
                    {:else if versionStatus === 'active'}
                        <div
                            class="border-border grid gap-3 border-t pt-4 sm:grid-cols-[1fr_auto] sm:items-end"
                        >
                            <div class="grid gap-2">
                                <Label for="retire-reason"
                                    >Retirement reason (optional)</Label
                                ><Input
                                    id="retire-reason"
                                    bind:value={retireReason}
                                    maxlength="255"
                                    placeholder="Optional retirement reason"
                                />
                            </div>
                            <Button variant="outline" onclick={retire}
                                >Retire Test Kit</Button
                            >
                        </div>
                    {/if}
                </div>
            </details>
        {:else}
            <section class="border-border bg-card rounded-xl border p-5">
                <h2 class="font-semibold">No open preparation</h2>
                <p class="text-muted-foreground mt-1 text-sm">
                    Start a new preparation to select components.
                </p>
                {#if actions.start_preparation}<Button
                        class="mt-3"
                        onclick={startPreparation}>Edit</Button
                    >{/if}
                {#if version_history.length > 0}
                    <div
                        class="mt-4 flex flex-wrap gap-2"
                        aria-label="Version history"
                    >
                        {#each version_history as version (version.id)}<Link
                                href={version.url}
                                class="text-primary text-sm underline underline-offset-4"
                                >v{version.version_number} · {version.status}</Link
                            >{/each}
                    </div>
                {/if}
            </section>
        {/if}
    </div>
</StaffLayout>
