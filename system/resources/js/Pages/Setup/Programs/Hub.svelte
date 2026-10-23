<script>
    import { onMount } from 'svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import GovernanceDiscussionPanel from '@/Components/admission-support/GovernanceDiscussionPanel.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Table from '@/Components/ui/table';
    import { Link, router, useForm } from '@inertiajs/svelte';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    import { newIdempotencyKey } from '@/lib/utils.js';
    import { ArrowUpRight, BarChart3, BookOpen, Megaphone, Settings2, Users } from 'lucide-svelte';
    let {
        program,
        cycles = [],
        selected_cycle_id = null,
        quota = null,
        can_manage_quota = false,
        quota_settings_url = null,
        announcements = [],
        policies = [],
        benchmarks = null,
        registrar_grants = [],
        registrar_candidates = [],
        can_manage_registrar_grants = false,
    } = $props();

    const tabs = [
        { id: 'overview', label: 'Overview & description', icon: BookOpen },
        { id: 'quotas', label: 'Quota governance', icon: Users },
        { id: 'announcements', label: 'Announcements', icon: Megaphone },
        { id: 'policies', label: 'Policies', icon: Settings2 },
        { id: 'benchmarks', label: 'Benchmarks / TabFM', icon: BarChart3 },
    ];
    const quotaNoticeSuggestions = [
        'Approved academic plan',
        'Laboratory seat capacity bounds',
        'Faculty workload limit',
        'Board resolution update',
    ];
    let grantRecipient = $state('');
    let grantReason = $state('');
    let grantError = $state('');
    let activeTab = $state('overview');
    let cycleLoading = $state(false);
    let cycleError = $state('');
    let selectedCycleId = $state(selected_cycle_id === null ? '' : String(selected_cycle_id));
    let quotaFormScope = '';

    const selectedCycle = $derived(cycles.find((cycle) => String(cycle.id) === selectedCycleId) ?? null);
    const cycleLabel = $derived(selectedCycle?.staff_label ?? selectedCycle?.name ?? selectedCycle?.code ?? 'Academic year and semester');
    const quotaRow = $derived(quota ?? {});
    const capacity = $derived(quotaRow.slots ?? null);
    const commitments = $derived(quotaRow.commitments ?? null);
    const remaining = $derived(quotaRow.remaining ?? null);
    const overCapacity = $derived(quotaRow.over_capacity ?? 0);
    const quotaChanges = $derived(quotaRow.changes ?? []);
    const quotaDiscussion = $derived(quotaRow.discussion ?? null);
    const announcementRows = $derived(Array.isArray(announcements) ? announcements : (announcements?.data ?? []));
    const policyRows = $derived(Array.isArray(policies) ? policies : (policies?.data ?? []));
    const programDescription = $derived(program.description ?? program.public_profile?.description ?? '');
    const majorCodes = $derived(Array.isArray(program.major_codes) ? program.major_codes : []);
    const benchmarkCutoffs = $derived(Array.isArray(benchmarks?.cutoffs) ? benchmarks.cutoffs : []);
    const activeBenchmark = $derived(benchmarks?.available ? benchmarks : null);

    const form = useForm({
        program_id: String(program.id),
        academic_cycle_id: selectedCycleId,
        slots: capacity ?? '',
        notice: '',
    });

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Programs', href: '/setup/programs' },
        { label: `${program.code} Hub` },
    ];

    $effect(() => {
        const nextCycleId = selected_cycle_id === null ? '' : String(selected_cycle_id);
        if (nextCycleId !== selectedCycleId) {
            selectedCycleId = nextCycleId;
        }
    });

    $effect(() => {
        const nextScope = `${program.id}:${selectedCycleId}:${capacity ?? ''}`;
        if (nextScope === quotaFormScope) return;
        quotaFormScope = nextScope;
        form.program_id = String(program.id);
        form.academic_cycle_id = selectedCycleId;
        form.slots = capacity ?? '';
        form.notice = '';
        form.clearErrors();
    });
    onMount(() => {
        const tabParam = new URL(window.location.href).searchParams.get('tab');
        if (tabs.some((tab) => tab.id === tabParam)) {
            activeTab = tabParam;
        }
    });


    function selectTab(tabId) {
        activeTab = tabId;
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabId);
        window.history.replaceState(window.history.state, '', url);
    }

    function changeRegistrarGrant(event) {
        event.preventDefault();
        if (!can_manage_registrar_grants || !selectedCycle || !grantRecipient || !grantReason.trim()) return;

        const currentGrant = registrar_grants.find((grant) =>
            String(grant.program_id) === String(program.id)
            && String(grant.cycle_id) === String(selectedCycle.id)
            && String(grant.recipient_id) === grantRecipient,
        );
        const operation = currentGrant?.status === 'active' ? 'revoke' : 'issue';
        const url = typeof route === 'function'
            ? route(`setup.quotas.registrar-grants.${operation}`, { program: program.id, cycle: selectedCycle.id })
            : `/setup/quotas/programs/${program.id}/cycles/${selectedCycle.id}/registrar-grants${operation === 'revoke' ? '/revoke' : ''}`;

        grantError = '';
        const opLabel = operation === 'revoke' ? 'revoked' : 'granted';
        router.post(url, {
            recipient_id: Number(grantRecipient),
            expected_version: currentGrant?.version ?? 0,
            reason: grantReason.trim(),
            idempotency_key: newIdempotencyKey(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                grantReason = '';
                toastSuccess(`Registrar quota access ${opLabel} successfully.`);
            },
            onError: (errors) => {
                grantError = Object.values(errors).flat().join(' ') || 'The Registrar access change could not be saved.';
                toastError(grantError);
            },
        });
    }

    function handleTabKeydown(event, index) {
        let nextIndex = index;
        if (event.key === 'ArrowRight') nextIndex = (index + 1) % tabs.length;
        else if (event.key === 'ArrowLeft') nextIndex = (index - 1 + tabs.length) % tabs.length;
        else if (event.key === 'Home') nextIndex = 0;
        else if (event.key === 'End') nextIndex = tabs.length - 1;
        else return;

        event.preventDefault();
        const nextTab = tabs[nextIndex];
        selectTab(nextTab.id);
        document.getElementById(`program-hub-tab-${nextTab.id}`)?.focus();
    }

    function changeCycle(event) {
        const nextCycleId = event.currentTarget.value;
        cycleError = '';
        router.get(
            `/setup/programs/${program.id}/hub`,
            { cycle_id: nextCycleId, tab: activeTab },
            {
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    cycleLoading = true;
                },
                onError: () => {
                    cycleError = 'This quota context could not be opened. Select an authorized academic year and semester, then try again.';
                },
                onFinish: () => {
                    cycleLoading = false;
                },
            },
        );
    }

    function quotaStoreUrl() {
        return typeof route === 'function' ? route('setup.quotas.store') : '/setup/quotas';
    }

    function saveQuota(event) {
        event.preventDefault();
        if (!can_manage_quota || !selectedCycleId || form.processing) return;

        form.program_id = String(program.id);
        form.academic_cycle_id = selectedCycleId;
        form.post(quotaStoreUrl(), {
            preserveScroll: true,
            onSuccess: () => form.reset('notice'),
            onError: (errors) => {
                const msg = Object.values(errors).flat().join(' ') || 'Program quota could not be saved.';
                toastError(msg);
            },
        });
    }
    function formatDate(value) {
        if (!value) return '—';
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
    }

    function displayCutoffValue(cutoff) {
        if (cutoff.is_missing || cutoff.value === null || cutoff.value === undefined || cutoff.value === '') return 'Missing';
        return String(cutoff.value);
    }
</script>

<svelte:head><title>{program.code} Hub · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto grid w-full max-w-7xl gap-6 p-4 sm:p-6">
        <PageHeaderBar
            title={`${program.code} — ${program.name}`}
            description={`Department of ${program.department?.name ?? '—'}. Program overview, admissions quota governance, announcements, policies, and benchmarks.`}
        >
            {#snippet actions()}
                <PrintDocumentsSlot
                    slotKey="program_workspace_show"
                    cycleId={selected_cycle_id}
                    programCode={program?.code}
                />
            {/snippet}
        </PageHeaderBar>

        <div class="min-w-0 space-y-5">
            <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                <div class="inline-flex min-w-full gap-1 rounded-xl border border-border/70 bg-muted/30 p-1 sm:min-w-0" role="tablist" aria-label="Program hub sections">
                    {#each tabs as tab, index (tab.id)}
                        <button
                            id={`program-hub-tab-${tab.id}`}
                            type="button"
                            role="tab"
                            aria-selected={activeTab === tab.id}
                            aria-controls="program-hub-panel"
                            tabindex={activeTab === tab.id ? 0 : -1}
                            class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium transition-colors {activeTab === tab.id ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:bg-background/70 hover:text-foreground'}"
                            onclick={() => selectTab(tab.id)}
                            onkeydown={(event) => handleTabKeydown(event, index)}
                        >
                            <tab.icon class="size-4" aria-hidden="true" />
                            {tab.label}
                        </button>
                    {/each}
                </div>
            </div>

            <section
                id="program-hub-panel"
                role="tabpanel"
                aria-labelledby={`program-hub-tab-${activeTab}`}
                tabindex="0"
                class="min-w-0 space-y-5 outline-none"
            >
                {#if activeTab === 'overview'}
                    <section class="rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="program-overview-heading">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 space-y-4">
                                <div>
                                    <h2 id="program-overview-heading" class="text-base font-semibold">Program overview</h2>
                                    <p class="mt-1 text-sm text-muted-foreground">{programDescription || 'No public description has been added for this program.'}</p>
                                </div>
                                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                                    <div class="space-y-1">
                                        <dt class="text-muted-foreground">Department</dt>
                                        <dd class="font-medium">{program.department?.name ?? '—'}</dd>
                                    </div>
                                    <div class="space-y-1">
                                        <dt class="text-muted-foreground">Major codes</dt>
                                        <dd class="flex flex-wrap gap-1.5">
                                            {#if majorCodes.length}
                                                {#each majorCodes as code (code)}
                                                    <Badge variant="outline" class="font-mono">{code}</Badge>
                                                {/each}
                                            {:else}
                                                <span>—</span>
                                            {/if}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <Link href={`/setup/programs/${program.id}/public-details`}>
                                <Button variant="outline" class="min-h-11 gap-2">
                                    Edit public details
                                    <ArrowUpRight class="size-4" aria-hidden="true" />
                                </Button>
                            </Link>
                        </div>
                    </section>
                {:else if activeTab === 'quotas'}
                    <div class="space-y-5">
                        <section class="space-y-4 rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="quota-governance-heading">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-1.5">
                                        <h2 id="quota-governance-heading" class="text-base font-semibold">Quota governance</h2>
                                        <InfoPopover
                                            title="Program slot enforcement"
                                            content="Caps how many examinees can be endorsed and cleared into this degree program for the selected academic year."
                                        />
                                    </div>
                                    <p class="mt-1 text-sm text-muted-foreground">Capacity and commitments for {program.code}.</p>
                                </div>
                                {#if cycles.length > 0}
                                    <div class="w-full space-y-1.5 sm:w-auto sm:min-w-64">
                                        <Label for="program-hub-cycle">Academic year · Semester</Label>
                                        <select
                                            id="program-hub-cycle"
                                            value={selectedCycleId}
                                            onchange={changeCycle}
                                            disabled={cycleLoading}
                                            class="flex min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60 sm:min-w-64"
                                        >
                                            {#each cycles as cycle (cycle.id)}
                                                <option value={String(cycle.id)}>{cycle.staff_label ?? cycle.name ?? cycle.code}</option>
                                            {/each}
                                        </select>
                                    </div>
                                {/if}
                            </div>
                            {#if quota_settings_url && !cycleLoading}
                                <Link href={quota_settings_url} class="inline-flex min-h-11 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                    Open quota settings
                                    <ArrowUpRight class="size-4" aria-hidden="true" />
                                </Link>
                            {/if}
                            {#if cycleError}<p role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">{cycleError}</p>{/if}

                            {#if !selectedCycle}
                                <p class="rounded-lg border border-border bg-muted/30 p-4 text-sm text-muted-foreground">No authorized academic year and semester is available for this program.</p>
                            {:else}
                                <p class="text-xs text-muted-foreground">{cycleLabel}</p>
                                <dl class="grid gap-3 sm:grid-cols-3">
                                    <div class="rounded-lg border border-border/70 bg-background p-4">
                                        <dt class="text-sm text-muted-foreground">Capacity slots</dt>
                                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{capacity ?? 'Not set'}</dd>
                                    </div>
                                    <div class="rounded-lg border border-border/70 bg-background p-4">
                                        <dt class="text-sm text-muted-foreground">Committed examinees</dt>
                                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{commitments ?? '—'}</dd>
                                    </div>
                                    <div class="rounded-lg border border-border/70 bg-background p-4">
                                        <dt class="text-sm text-muted-foreground">Remaining seats</dt>
                                        <dd class="mt-1 text-2xl font-semibold tabular-nums">{remaining ?? '—'}</dd>
                                    </div>
                                </dl>
                                {#if overCapacity > 0}
                                    <p class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">Existing commitments exceed capacity by {overCapacity}. Current commitments are preserved.</p>
                                {/if}

                                {#if can_manage_quota}
                                    <form class="grid gap-3 rounded-xl border border-border bg-background p-4 sm:grid-cols-2" onsubmit={saveQuota}>
                                        <div class="space-y-1.5">
                                            <Label for="quota-slots">Slots</Label>
                                            <Input id="quota-slots" type="number" min="0" max="100000" step="1" required bind:value={form.slots} disabled={form.processing || cycleLoading} aria-invalid={!!form.errors.slots} aria-describedby={form.errors.slots ? 'quota-slots-error' : undefined} class="min-h-11" />
                                            {#if form.errors.slots}<p id="quota-slots-error" role="alert" class="text-sm text-destructive">{form.errors.slots}</p>{/if}
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label for="quota-notice">Notice</Label>
                                            <textarea id="quota-notice" bind:value={form.notice} rows="3" maxlength="2000" disabled={form.processing || cycleLoading} aria-invalid={!!form.errors.notice} class="min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-60" placeholder="Explain the quota adjustment."></textarea>
                                            <div class="flex flex-wrap gap-1.5 pt-1">
                                                {#each quotaNoticeSuggestions as chip}
                                                    <button
                                                        type="button"
                                                        class="rounded-md border border-border/70 bg-muted/30 px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground cursor-pointer"
                                                        onclick={() => { form.notice = form.notice ? `${form.notice} · ${chip}` : chip; }}
                                                    >
                                                        + {chip}
                                                    </button>
                                                {/each}
                                            </div>
                                            {#if form.errors.notice}<p role="alert" class="text-sm text-destructive">{form.errors.notice}</p>{/if}
                                        </div>
                                        {#if form.errors.program_id || form.errors.academic_cycle_id}<p class="text-sm text-destructive sm:col-span-2" role="alert">{form.errors.program_id || form.errors.academic_cycle_id}</p>{/if}
                                        <div class="sm:col-span-2">
                                            <Button type="submit" class="min-h-11" disabled={form.processing || cycleLoading}>{form.processing ? 'Saving…' : 'Save quota'}</Button>
                                            {#if form.recentlySuccessful}<p class="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-400" role="status">✓ Program quota saved.</p>{/if}
                                    </form>
                                {:else}
                                    <p class="rounded-lg border border-border bg-muted/30 p-4 text-sm text-muted-foreground">Quota editing is not available for your current access.</p>
                                {/if}
                            {/if}
                        </section>
                        {#if can_manage_registrar_grants && selectedCycle}
                            {@const scopedGrants = registrar_grants.filter((grant) => String(grant.program_id) === String(program.id) && String(grant.cycle_id) === String(selectedCycle.id))}
                            {@const selectedGrant = scopedGrants.find((grant) => String(grant.recipient_id) === grantRecipient) ?? null}
                            <section class="space-y-4 rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="registrar-access-heading">
                                <div>
                                    <h2 id="registrar-access-heading" class="text-base font-semibold">Registrar access</h2>
                                    <p class="mt-1 text-sm text-muted-foreground">Manage scoped quota access for {program.code} · {cycleLabel}.</p>
                                </div>
                                {#if scopedGrants.length}
                                    <ul class="divide-y divide-border text-sm">
                                        {#each scopedGrants as grant (grant.recipient_id)}
                                            <li class="space-y-1 py-2">
                                                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-medium">{grant.recipient_name || 'Registrar'}</span><Badge variant="secondary">{grant.status} · v{grant.version}</Badge></div>
                                                <p class="whitespace-pre-wrap break-words text-xs text-muted-foreground">{grant.reason}</p>
                                                {#if grant.actor_name}<p class="text-xs text-muted-foreground">By {grant.actor_name}</p>{/if}
                                            </li>
                                        {/each}
                                    </ul>
                                {:else}
                                    <p class="text-sm text-muted-foreground">No Registrar grants have been issued for this academic year and semester.</p>
                                {/if}
                                {#if registrar_candidates.length}
                                    <form class="grid gap-3 border-t border-border pt-4" onsubmit={changeRegistrarGrant}>
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-1.5">
                                                <Label for="quota-grant-recipient">Registrar recipient</Label>
                                                <InfoPopover
                                                    title="Quota grant delegation"
                                                    content="Authorizes the selected Registrar staff member to adjust slot counts for this program."
                                                />
                                            </div>
                                            <select id="quota-grant-recipient" bind:value={grantRecipient} required class="flex min-h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                                                <option value="">Select a Registrar</option>
                                                {#each registrar_candidates as candidate (candidate.id)}
                                                    <option value={String(candidate.id)}>{candidate.name}</option>
                                                {/each}
                                            </select>
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label for="quota-grant-reason">Reason</Label>
                                            <textarea id="quota-grant-reason" bind:value={grantReason} rows="2" maxlength="1000" required class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"></textarea>
                                        </div>
                                        {#if grantError}<p role="alert" class="text-sm text-destructive">{grantError}</p>{/if}
                                        <Button type="submit" class="min-h-11 w-fit" disabled={!grantRecipient || !grantReason.trim()}>{selectedGrant?.status === 'active' ? 'Revoke grant' : 'Issue grant'}</Button>
                                    </form>
                                {:else}
                                    <p class="text-sm text-muted-foreground">No active Registrar recipients are available in this institution.</p>
                                {/if}
                            </section>
                        {/if}

                        {#if selectedCycle && quotaDiscussion}
                            {#key `${program.id}:${selectedCycleId}`}
                                <GovernanceDiscussionPanel program={program} cycle={selectedCycle} topic="quota" initialList={quotaDiscussion} />
                            {/key}
                        {/if}

                        <section class="min-w-0 space-y-3 rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="quota-history-heading">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 id="quota-history-heading" class="text-base font-semibold">Quota change history</h2>
                                <span class="text-xs text-muted-foreground">{cycleLabel}</span>
                            </div>
                            {#if quotaChanges.length}
                                <div class="overflow-x-auto">
                                    <Table.Root class="min-w-[640px]">
                                        <Table.Header>
                                            <Table.Row>
                                                <Table.Head>Changed</Table.Head>
                                                <Table.Head>Capacity</Table.Head>
                                                <Table.Head>Changed by</Table.Head>
                                                <Table.Head>Notice</Table.Head>
                                            </Table.Row>
                                        </Table.Header>
                                        <Table.Body>
                                            {#each quotaChanges as change (change.id)}
                                                <Table.Row>
                                                    <Table.Cell class="whitespace-nowrap text-sm">{formatDate(change.created_at)}</Table.Cell>
                                                    <Table.Cell class="whitespace-nowrap font-medium tabular-nums">{change.from_slots ?? 'Not set'} → {change.to_slots} slots</Table.Cell>
                                                    <Table.Cell class="text-sm">{change.actor ?? 'Unrecorded staff'}</Table.Cell>
                                                    <Table.Cell class="max-w-md whitespace-pre-wrap text-sm">{change.notice || '—'}</Table.Cell>
                                                </Table.Row>
                                            {/each}
                                        </Table.Body>
                                    </Table.Root>
                                </div>
                            {:else}
                                <p class="text-sm text-muted-foreground">No quota changes have been recorded for this academic year and semester.</p>
                            {/if}
                        </section>
                    </div>
                {:else if activeTab === 'announcements'}
                    <section class="space-y-4 rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="program-announcements-heading">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 id="program-announcements-heading" class="text-base font-semibold">Program announcements</h2>
                                <p class="mt-1 text-sm text-muted-foreground">Announcements scoped to {program.code}.</p>
                            </div>
                            <Link href="/communications/create"><Button class="min-h-11">Create announcement</Button></Link>
                        </div>
                        {#if announcementRows.length}
                            <ul class="divide-y divide-border">
                                {#each announcementRows as announcement (announcement.id)}
                                    <li class="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
                                        <div class="min-w-0 space-y-1">
                                            <Link href={announcement.href ?? `/announcements/${announcement.id}`} class="font-medium underline-offset-4 hover:underline">{announcement.title}</Link>
                                            {#if announcement.summary}<p class="text-sm text-muted-foreground">{announcement.summary}</p>{/if}
                                            <p class="text-xs text-muted-foreground">{announcement.publisher_label ?? program.code}{announcement.published_at ? ` · ${formatDate(announcement.published_at)}` : ''}</p>
                                        </div>
                                        <Badge variant={announcement.effective_status === 'published' || announcement.status === 'published' ? 'default' : 'secondary'}>{announcement.effective_status ?? announcement.status ?? 'Unavailable'}</Badge>
                                    </li>
                                {/each}
                            </ul>
                        {:else}
                            <p class="rounded-lg border border-dashed border-border p-5 text-sm text-muted-foreground">No program announcements are available.</p>
                        {/if}
                    </section>
                {:else if activeTab === 'policies'}
                    <section class="space-y-4 rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="program-policies-heading">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 id="program-policies-heading" class="text-base font-semibold">Program policies</h2>
                                <p class="mt-1 text-sm text-muted-foreground">Public policies scoped to {program.code}.</p>
                            </div>
                            <Link href="/communications/policies/create"><Button class="min-h-11">Create policy</Button></Link>
                        </div>
                        {#if policyRows.length}
                            <ul class="divide-y divide-border">
                                {#each policyRows as policy (policy.id)}
                                    <li class="flex flex-wrap items-start justify-between gap-3 py-4 first:pt-0 last:pb-0">
                                        <div class="min-w-0 space-y-1">
                                            <Link href={policy.href ?? `/policies/${policy.id}`} class="font-medium underline-offset-4 hover:underline">{policy.title}</Link>
                                            {#if policy.excerpt}<p class="text-sm text-muted-foreground">{policy.excerpt}</p>{/if}
                                            <p class="text-xs text-muted-foreground">{policy.placement} · {policy.reference}{policy.published_at ? ` · ${formatDate(policy.published_at)}` : ''}</p>
                                        </div>
                                        <Badge variant="secondary">Published</Badge>
                                    </li>
                                {/each}
                            </ul>
                        {:else}
                            <p class="rounded-lg border border-dashed border-border p-5 text-sm text-muted-foreground">No program policies are available.</p>
                        {/if}
                    </section>
                {:else if activeTab === 'benchmarks'}
                    <section class="space-y-4 rounded-xl border border-border bg-card p-5 sm:p-6" aria-labelledby="program-benchmarks-heading">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 id="program-benchmarks-heading" class="text-base font-semibold">Active benchmarks</h2>
                                <p class="mt-1 text-sm text-muted-foreground">Current GPA and score cutoff summary for {program.code}.</p>
                            </div>
                            <Link href={benchmarks?.studio_url ?? '/setup/decision-support'}>
                                <Button variant="outline" class="min-h-11 gap-2">Open TabFM Studio<ArrowUpRight class="size-4" aria-hidden="true" /></Button>
                            </Link>
                        </div>
                        {#if activeBenchmark}
                            <div class="flex flex-wrap gap-x-6 gap-y-2 border-y border-border py-3 text-sm">
                                {#if activeBenchmark.version !== undefined}<p><span class="text-muted-foreground">Active version:</span> <span class="font-medium">v{activeBenchmark.version}</span></p>{/if}
                                {#if activeBenchmark.set_name}<p><span class="text-muted-foreground">Benchmark set:</span> <span class="font-medium">{activeBenchmark.set_name}</span></p>{/if}
                                {#if activeBenchmark.activated_at}<p><span class="text-muted-foreground">Activated:</span> <span class="font-medium">{formatDate(activeBenchmark.activated_at)}</span></p>{/if}
                            </div>
                            {#if benchmarkCutoffs.length}
                                <dl class="grid gap-3 sm:grid-cols-2">
                                    {#each benchmarkCutoffs as cutoff (cutoff.feature_key)}
                                        <div class="rounded-lg border border-border/70 bg-background p-4">
                                            <dt class="text-sm text-muted-foreground">{cutoff.label ?? cutoff.feature_key}</dt>
                                            <dd class="mt-1 text-xl font-semibold tabular-nums">{displayCutoffValue(cutoff)}{cutoff.unit ? ` ${cutoff.unit}` : ''}</dd>
                                        </div>
                                    {/each}
                                </dl>
                            {:else}
                                <p class="text-sm text-muted-foreground">No active GPA or score cutoffs are available for this program.</p>
                            {/if}
                        {:else}
                            <p class="rounded-lg border border-dashed border-border p-5 text-sm text-muted-foreground">No active benchmark summary is available for this program.</p>
                        {/if}
                    </section>
                {/if}
            </section>
        </div>
    </div>
</StaffLayout>
