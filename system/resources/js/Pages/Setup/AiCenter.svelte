<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import * as Dialog from '@/Components/ui/dialog';
    import { router } from '@inertiajs/svelte';
    import { Activity, AlertTriangle, Cpu, Loader2, ShieldAlert, ShieldCheck, Users, Wallet, X, Zap } from 'lucide-svelte';

    let {
        is_umbrella_admin = false,
        actor_office = 'guidance',
        usage = { total_used: 0, extensions: 0, limit: 200, percentage: 0, tokens: 0, cost_micro: 0, breakdown: {} },
        model_info = { gateway: 'fake', provider: 'openai', model: 'gpt-4o-mini', timeout: 30, emergency_stop: false, emergency_stop_reason: null },
        provider_config = null,
        model_tariffs = [],
        policy = {},
        staff_quotas = [],
        switchboard = [],
        policy_version = 1,
        proposals = [],
        interventions = [],
        unknown_holds = [],
    } = $props();

    const tabs = ['Overview & Budgets', 'Capability Switchboard', 'Activity & Holds'];
    const drivers = ['gemini', 'openai', 'anthropic', 'openrouter', 'deepseek', 'groq', 'ollama', 'openai-compatible'];
    const groupOrder = ['Applicant Portal', 'Institutional Setup', 'Registrar Office', 'Guidance & Testing Office', 'Institutional Reports', 'Shared Staff & Knowledge'];
    let activeTab = $state(tabs[0]);
    let search = $state('');
    let openGroups = $state(Object.fromEntries(groupOrder.map((group) => [group, true])));
    let providerOpen = $state(false);
    let stopOpen = $state(false);
    let extensionOpen = $state(false);
    let extensionTarget = $state(null);
    let extensionAmount = $state(1);
    let extensionReason = $state('');
    let stopReason = $state('');
    let providerStatus = $state('');
    let providerBusy = $state(false);
    let discoveredModels = $state(provider_config?.discovered_models ?? []);
    let tariffModel = $state(null);
    let togglingTool = $state(null);
    let providerForm = $state({
        driver: provider_config?.active_driver ?? 'openai',
        base_url: provider_config?.base_url ?? '',
        key_mode: provider_config?.key_mode ?? 'env',
        custom_api_key: '',
        active_model_id: provider_config?.active_model_id ?? '',
        fallback_model_id: provider_config?.fallback_model_id ?? '',
        timeout_seconds: provider_config?.timeout_seconds ?? 30,
        temperature: provider_config?.temperature ?? 0.2,
        is_on_premises: provider_config?.is_on_premises ?? false,
    });
    let tariffForm = $state({
        model_id: '', provider_driver: providerForm.driver, name: '', supports_tools: true, context_window: 128000,
        prompt_micro_per_token: 0, completion_micro_per_token: 0, cache_read_micro_per_token: 0,
        cache_write_micro_per_token: 0, reasoning_micro_per_token: 0, pricing_source: 'manual',
    });
    let applicantForm = $state({
        applicant_inquiry_enabled: policy.applicant_inquiry_enabled ?? true,
        applicant_self_booking_enabled: policy.applicant_self_booking_enabled ?? true,
        daily_applicant_pool_asks: policy.daily_applicant_pool_asks ?? 0,
        per_applicant_daily_ask_limit: policy.per_applicant_daily_ask_limit ?? 0,
    });
    let budgetForm = $state({
        daily_institution_ask_ceiling: policy.daily_institution_ask_ceiling ?? 0,
        office_ask_allocations: policy.office_ask_allocations ?? {},
        daily_institution_budget_micro: policy.daily_institution_budget_micro ?? 0,
        office_budget_allocations_micro: policy.office_budget_allocations_micro ?? {},
        per_staff_daily_ask_limit: policy.per_staff_daily_ask_limit ?? 200,
    });

    const groupedSwitchboard = $derived.by(() => {
        const filtered = switchboard.filter((item) => {
            const query = search.trim().toLowerCase();
            return !query || `${item.display_title} ${item.description} ${item.id} ${item.display_group}`.toLowerCase().includes(query);
        });
        const known = groupOrder.map((name) => ({ name, items: filtered.filter((item) => item.display_group === name) })).filter((group) => group.items.length > 0);
        const knownNames = new Set(groupOrder);
        const extraNames = [...new Set(filtered.map((item) => item.display_group).filter((g) => g && !knownNames.has(g)))];
        const extra = extraNames.map((name) => ({ name, items: filtered.filter((item) => item.display_group === name) })).filter((group) => group.items.length > 0);
        return [...known, ...extra];
    });
    const todayUsd = $derived((Number(usage.cost_micro ?? 0) / 1_000_000).toFixed(4));

    function postToggle(item, enabled = !item.enabled, lockForOffice = item.locked_by_admin) {
        togglingTool = item.id;
        router.post('/setup/ai-center/tools/toggle', {
            tool_id: item.id,
            enabled,
            lock_for_office: lockForOffice,
        }, { preserveScroll: true, onFinish: () => (togglingTool = null) });
    }

    function submitStop() {
        router.post('/setup/ai-center/emergency-stop', { reason: stopReason }, {
            preserveScroll: true,
            onSuccess: () => { stopOpen = false; stopReason = ''; },
        });
    }

    function openExtension(staff) {
        extensionTarget = staff;
        extensionAmount = 1;
        extensionReason = '';
        extensionOpen = true;
    }

    function grantExtension(event) {
        event.preventDefault();
        if (!extensionTarget) return;
        router.post('/setup/ai-center/extensions', {
            target_user_id: extensionTarget.id,
            amount: Number(extensionAmount),
            reason: extensionReason,
        }, { preserveScroll: true, onSuccess: () => (extensionOpen = false) });
    }

    async function discoverModels() {
        providerBusy = true;
        providerStatus = 'Contacting provider safely…';
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const response = await fetch('/setup/ai-center/models/discover', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ driver: providerForm.driver, base_url: providerForm.base_url || null, key_mode: providerForm.key_mode, custom_api_key: providerForm.custom_api_key || null }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message ?? 'Model discovery failed. Check the provider settings.');
            discoveredModels = result.models ?? [];
            providerStatus = `${discoveredModels.length} available model${discoveredModels.length === 1 ? '' : 's'} discovered.`;
        } catch (error) {
            providerStatus = error instanceof Error ? error.message : 'Model discovery failed.';
        } finally {
            providerBusy = false;
        }
    }

    function saveProvider(event) {
        event.preventDefault();
        router.post('/setup/ai-center/provider', { ...providerForm, discovered_models: discoveredModels }, {
            preserveScroll: true,
            onSuccess: () => { providerOpen = false; },
        });
    }

    function selectModel(model) {
        providerForm.active_model_id = model.id;
        if (!model.has_tariff && !hasTariff(model.id, providerForm.driver)) {
            tariffModel = model;
            tariffForm = { ...tariffForm, model_id: model.id, provider_driver: providerForm.driver, name: model.name ?? model.id };
        }
    }

    function hasTariff(modelId, driver) {
        if (!modelId) return false;
        if (modelId.endsWith(':free') || providerForm.is_on_premises) return true;
        return model_tariffs.some((tariff) => tariff.model_id === modelId && tariff.provider_driver === driver && tariff.is_active);
    }

    function checkCustomModelTariff(modelId) {
        if (!modelId || providerForm.is_on_premises || modelId.endsWith(':free') || hasTariff(modelId, providerForm.driver)) {
            return;
        }
        tariffModel = { id: modelId, name: modelId };
        tariffForm = { ...tariffForm, model_id: modelId, provider_driver: providerForm.driver, name: modelId };
    }

    function saveTariff(event) {
        event.preventDefault();
        router.post('/setup/ai-center/tariffs', tariffForm, {
            preserveScroll: true,
            onSuccess: () => { tariffModel = null; },
        });
    }

    function saveApplicantPolicy(event) {
        event.preventDefault();
        router.post('/setup/ai-center/applicant-policy', applicantForm, { preserveScroll: true });
    }

    function saveBudget(event) {
        event.preventDefault();
        router.post('/setup/ai-center/budget', budgetForm, { preserveScroll: true });
    }

    function reconcile(hold, action) {
        router.post(`/setup/ai-center/holds/${encodeURIComponent(hold.id)}/reconcile`, { action }, { preserveScroll: true });
    }

    function formatMoney(micro) {
        return `$${(Number(micro ?? 0) / 1_000_000).toFixed(4)}`;
    }

    function officeLabel(office) {
        return ({ guidance: 'Guidance', registrar: 'Registrar', program: 'Program', admin: 'Admin', applicant: 'Applicants' })[office] ?? office;
    }

    const breadcrumbs = [{ label: 'Setup', href: '/setup' }, { label: 'AI Control Center' }];
</script>

<svelte:head><title>AI Control Center · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <main class="min-w-0 max-w-full space-y-6 overflow-x-clip p-4 sm:p-6">
        <PageHeaderBar title="AI Control Center" description="A clear view of AI usage, office capabilities, and safety activity.">
            {#snippet actions()}
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <Badge variant="outline" class="min-h-8 px-3">{is_umbrella_admin ? 'Institutional Umbrella' : 'Office Governance'}</Badge>
                    {#if is_umbrella_admin}
                        <Button variant="outline" class="min-h-11" onclick={() => (providerOpen = true)}>Configure Provider &amp; Model</Button>
                        <Button variant={model_info.emergency_stop ? 'default' : 'destructive'} class="min-h-11" onclick={() => (stopOpen = true)}>
                            {model_info.emergency_stop ? 'Disarm Emergency Stop' : 'Trip Emergency Stop'}
                        </Button>
                    {/if}
                </div>
            {/snippet}
        </PageHeaderBar>

        {#if !is_umbrella_admin}
            <section class="flex min-w-0 flex-wrap items-center gap-3 rounded-xl border border-border bg-muted/30 p-4" aria-label="Campus AI status">
                {#if model_info.emergency_stop}<ShieldAlert class="size-5 text-destructive" />{:else}<ShieldCheck class="size-5 text-emerald-600" />{/if}
                <strong>Campus Status</strong>
                <span class="text-sm text-muted-foreground">Circuit Breaker: {model_info.emergency_stop ? 'Tripped' : 'Normal'} · Central Applicant AI: {policy.applicant_inquiry_enabled === false ? 'Paused' : 'Active'}</span>
            </section>
        {/if}

        <nav class="grid grid-cols-1 gap-2 rounded-xl border border-border bg-muted/20 p-1 sm:grid-cols-3" aria-label="AI governance sections" role="tablist">
            {#each tabs as tab (tab)}
                <button type="button" role="tab" aria-selected={activeTab === tab} class={`min-h-11 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${activeTab === tab ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`} onclick={() => (activeTab = tab)}>{tab}</button>
            {/each}
        </nav>

        {#if activeTab === 'Overview & Budgets'}
            <section class="min-w-0 space-y-5" aria-label="Overview and budgets">
                <div class="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <Card.Root class="min-w-0"><Card.Header class="pb-2"><Card.Title class="flex items-center gap-2 text-sm"><Zap class="size-4" /> Today's Asks</Card.Title></Card.Header><Card.Content><p class="text-2xl font-semibold">{usage.total_used} <span class="text-sm font-normal text-muted-foreground">asks</span></p><p class="mt-1 break-words text-xs text-muted-foreground">{usage.extensions} additional asks granted today · limit {usage.limit}</p><div class="mt-3 h-2 overflow-hidden rounded-full bg-muted"><div class="h-full rounded-full bg-primary" style={`width:${usage.percentage}%`}></div></div></Card.Content></Card.Root>
                    <Card.Root class="min-w-0"><Card.Header class="pb-2"><Card.Title class="flex items-center gap-2 text-sm"><Wallet class="size-4" /> Today's Spend</Card.Title></Card.Header><Card.Content><p class="text-2xl font-semibold">${todayUsd} USD</p><p class="mt-1 break-words text-xs text-muted-foreground">₱— PHP conversion rate not configured · {Number(usage.tokens ?? 0).toLocaleString()} settled tokens</p></Card.Content></Card.Root>
                    <Card.Root class="min-w-0"><Card.Header class="pb-2"><Card.Title class="flex items-center gap-2 text-sm"><Cpu class="size-4" /> Model &amp; Driver</Card.Title></Card.Header><Card.Content><p class="break-all text-lg font-semibold">{model_info.model}</p><p class="mt-1 break-words text-xs text-muted-foreground">{model_info.provider} · {model_info.timeout}s timeout · Policy v{policy_version}</p></Card.Content></Card.Root>
                    <Card.Root class="min-w-0"><Card.Header class="pb-2"><Card.Title class="flex items-center gap-2 text-sm"><Activity class="size-4" /> Proposal &amp; Safety Ledger</Card.Title></Card.Header><Card.Content><p class="text-2xl font-semibold">{proposals.length} <span class="text-sm font-normal text-muted-foreground">proposals</span></p><p class="mt-1 break-words text-xs text-muted-foreground">{interventions.length} recent safety interventions</p></Card.Content></Card.Root>
                </div>

                {#if is_umbrella_admin}
                    <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                        <Card.Root class="min-w-0"><Card.Header><Card.Title class="flex items-center gap-1.5"><span>Central Applicant AI Governance</span><InfoPopover title="Applicant AI policy" content="Controls public and applicant-portal Xiam inquiries and reserves a dedicated daily ask pool so staff activity cannot exhaust applicant assistance." /></Card.Title><Card.Description>Manage public applicant inquiry and self-booking policy.</Card.Description></Card.Header><Card.Content><form class="space-y-4" onsubmit={saveApplicantPolicy}>
                            <label class="flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 text-sm"><span>Applicant inquiry enabled</span><input type="checkbox" bind:checked={applicantForm.applicant_inquiry_enabled} class="size-5 accent-primary" /></label>
                            <label class="flex min-h-11 items-center justify-between gap-3 rounded-lg border p-3 text-sm"><span>Applicant self-booking enabled</span><input type="checkbox" bind:checked={applicantForm.applicant_self_booking_enabled} class="size-5 accent-primary" /></label>
                            <label class="grid gap-1 text-sm"><span class="inline-flex items-center gap-1.5"><span>Ring-fenced applicant pool asks</span><InfoPopover title="Applicant pool reserve" content="Daily ask quota reserved exclusively for public/applicant inquiries so staff usage cannot exhaust applicant help." /></span><input type="number" min="0" bind:value={applicantForm.daily_applicant_pool_asks} class="min-h-11 w-full rounded-md border bg-background px-3" /></label>
                            <label class="grid gap-1 text-sm">Per-applicant daily ask limit<input type="number" min="0" bind:value={applicantForm.per_applicant_daily_ask_limit} class="min-h-11 w-full rounded-md border bg-background px-3" /></label>
                            <Button type="submit" class="min-h-11 w-full sm:w-auto">Save Applicant Policy</Button>
                        </form></Card.Content></Card.Root>
                        <Card.Root class="min-w-0"><Card.Header><Card.Title class="flex items-center gap-1.5"><span>Office Pool Allocations</span><InfoPopover title="Fail-closed budget ceilings" content="Enforces daily ask and micro-USD ceilings across the institution and per office. Requests stop automatically once a ceiling is reached unless extended." /></Card.Title><Card.Description>Ask and cost ceilings for the institution and each office.</Card.Description></Card.Header><Card.Content><form class="space-y-4" onsubmit={saveBudget}>
                            <label class="grid gap-1 text-sm">Institution daily ask ceiling<input type="number" min="0" bind:value={budgetForm.daily_institution_ask_ceiling} class="min-h-11 w-full rounded-md border bg-background px-3" /></label>
                            <label class="grid gap-1 text-sm">Institution daily budget (micro-USD)<input type="number" min="0" bind:value={budgetForm.daily_institution_budget_micro} class="min-h-11 w-full rounded-md border bg-background px-3" /></label>
                            <label class="grid gap-1 text-sm">Per-staff daily ask limit<input type="number" min="0" bind:value={budgetForm.per_staff_daily_ask_limit} class="min-h-11 w-full rounded-md border bg-background px-3" /></label>
                            <div class="grid gap-3 sm:grid-cols-2">{#each ['guidance', 'registrar', 'program', 'admin'] as office (office)}<label class="grid min-w-0 gap-1 text-sm">{officeLabel(office)} pool asks<input type="number" min="0" value={budgetForm.office_ask_allocations[office] ?? 0} oninput={(event) => (budgetForm.office_ask_allocations[office] = Number(event.currentTarget.value))} class="min-h-11 w-full min-w-0 rounded-md border bg-background px-3" /></label>{/each}</div>
                            <div class="grid gap-3 sm:grid-cols-2">{#each ['guidance', 'registrar', 'program', 'admin'] as office (office)}<label class="grid min-w-0 gap-1 text-sm">{officeLabel(office)} pool budget (micro-USD)<input type="number" min="0" value={budgetForm.office_budget_allocations_micro[office] ?? 0} oninput={(event) => (budgetForm.office_budget_allocations_micro[office] = Number(event.currentTarget.value))} class="min-h-11 w-full min-w-0 rounded-md border bg-background px-3" /></label>{/each}</div>
                            <Button type="submit" class="min-h-11 w-full sm:w-auto">Save Pool Allocations</Button>
                        </form></Card.Content></Card.Root>
                    </div>
                    <div><h2 class="mb-3 flex items-center gap-2 text-lg font-semibold"><Users class="size-5" /> Staff Quota Ledger</h2>{@render QuotaTable(staff_quotas, openExtension)}</div>
                    <Card.Root class="min-w-0"><Card.Header><Card.Title>Today's Office Usage</Card.Title></Card.Header><Card.Content><div class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-5">{#each Object.entries(usage.breakdown ?? {}) as [office, values] (office)}<div class="min-w-0 rounded-lg border p-3"><p class="text-sm font-medium">{officeLabel(office)}</p><p class="mt-1 text-sm">{values.asks} asks</p><p class="break-words text-xs text-muted-foreground">{Number(values.tokens ?? 0).toLocaleString()} tokens · {formatMoney(values.cost_micro)}</p></div>{/each}</div></Card.Content></Card.Root>
                {:else}
                    <Card.Root class="min-w-0"><Card.Header><Card.Title>Own-Office Pool Meter</Card.Title><Card.Description>{officeLabel(actor_office)} usage and daily capacity.</Card.Description></Card.Header><Card.Content><div class="grid gap-4 sm:grid-cols-3"><div><p class="text-xs text-muted-foreground">Asks used</p><p class="text-xl font-semibold">{usage.breakdown?.[actor_office]?.asks ?? usage.total_used}</p></div><div><p class="text-xs text-muted-foreground">Tokens settled</p><p class="text-xl font-semibold">{Number(usage.breakdown?.[actor_office]?.tokens ?? usage.tokens).toLocaleString()}</p></div><div><p class="text-xs text-muted-foreground">Spend</p><p class="text-xl font-semibold">{formatMoney(usage.breakdown?.[actor_office]?.cost_micro ?? usage.cost_micro)}</p></div></div></Card.Content></Card.Root>
                    <div><h2 class="mb-3 flex items-center gap-2 text-lg font-semibold"><Users class="size-5" /> Own-Office Staff Quota Ledger</h2>{@render QuotaTable(staff_quotas, openExtension)}</div>
                {/if}
            </section>
        {:else if activeTab === 'Capability Switchboard'}
            <section class="min-w-0 space-y-4" aria-label="Capability switchboard">
                <div><h2 class="text-lg font-semibold">Capability Switchboard</h2><p class="text-sm text-muted-foreground">Search by capability, domain, or tool ID. Changes take effect immediately.</p></div>
                <label class="grid gap-1 text-sm">Search capabilities<input type="search" bind:value={search} placeholder="Search tools…" class="min-h-11 w-full rounded-lg border bg-background px-3" /></label>
                {#each groupedSwitchboard as group (group.name)}
                    <section class="min-w-0 overflow-hidden rounded-xl border border-border">
                        <button type="button" class="flex min-h-12 w-full min-w-0 items-center justify-between gap-3 bg-muted/30 px-4 text-left" aria-expanded={openGroups[group.name]} onclick={() => (openGroups[group.name] = !openGroups[group.name])}>
                            <span class="truncate font-semibold">{group.name}</span><span class="shrink-0 text-xs text-muted-foreground">{group.items.filter((item) => item.enabled).length} / {group.items.length} active</span>
                        </button>
                        {#if openGroups[group.name]}
                            <div class="divide-y divide-border">
                                {#each group.items as item (item.id)}
                                    <article class="flex min-w-0 flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div class="min-w-0 space-y-1"><div class="flex min-w-0 flex-wrap items-center gap-2"><strong class="break-words text-sm">{item.display_title}</strong><Badge variant="secondary" class="font-mono text-[10px]">@{item.version ?? 1}</Badge>{#if item.locked_by_admin && !is_umbrella_admin}<Badge variant="outline">Locked by Admin</Badge>{/if}</div><p class="break-words text-xs leading-relaxed text-muted-foreground">{item.description}</p><p class="break-all font-mono text-[10px] text-muted-foreground/70">{item.id}</p></div>
                                        <div class="flex min-w-0 flex-wrap items-center gap-3 sm:shrink-0">
                                            <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" checked={item.enabled} disabled={!item.can_toggle || togglingTool === item.id} onchange={(event) => postToggle(item, event.currentTarget.checked)} class="size-5 accent-primary" />{item.enabled ? 'Active' : 'Inactive'}</label>
                                            {#if is_umbrella_admin}<label class="flex min-h-11 items-center gap-2 text-xs text-muted-foreground"><input type="checkbox" checked={item.locked_by_admin} disabled={togglingTool === item.id} onchange={(event) => postToggle(item, item.enabled, event.currentTarget.checked)} class="size-4 accent-primary" />Lock for Office</label>{/if}
                                        </div>
                                    </article>
                                {/each}
                            </div>
                        {/if}
                    </section>
                {:else}<p class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">No capabilities match this search.</p>{/each}
            </section>
        {:else}
            <section class="min-w-0 space-y-5" aria-label="Activity and holds">
                {#if is_umbrella_admin}<Card.Root class="min-w-0"><Card.Header><Card.Title>Unknown Reservation Holds</Card.Title><Card.Description>Resolve provider requests with uncertain billing after confirming their outcome.</Card.Description></Card.Header><Card.Content class="space-y-3">
                    {#if unknown_holds.length === 0}<p class="text-sm text-muted-foreground">No unresolved usage holds.</p>{:else}{#each unknown_holds as hold (hold.id)}<div class="flex min-w-0 flex-col gap-3 rounded-lg border p-3 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><p class="break-all text-sm font-medium">Run {hold.run_id ?? hold.id}</p><p class="break-words text-xs text-muted-foreground">{officeLabel(hold.office ?? 'unknown')} · {hold.amount} asks · {Number(hold.reserved_tokens ?? 0).toLocaleString()} tokens · {formatMoney(hold.reserved_cost_micro)}</p></div><div class="flex flex-wrap gap-2"><Button variant="outline" class="min-h-11" onclick={() => reconcile(hold, 'release')}>Release</Button><Button class="min-h-11" onclick={() => reconcile(hold, 'settle')}>Settle</Button></div></div>{/each}{/if}
                </Card.Content></Card.Root>{/if}
                <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                    <Card.Root class="min-w-0"><Card.Header><Card.Title>Recent Proposals</Card.Title><Card.Description>Assistant actions awaiting or recording staff confirmation.</Card.Description></Card.Header><Card.Content class="space-y-3">{#if proposals.length === 0}<p class="text-sm text-muted-foreground">No recent proposals.</p>{:else}{#each proposals as proposal (proposal.id)}<div class="min-w-0 rounded-lg border p-3"><div class="flex flex-wrap items-center justify-between gap-2"><strong class="break-all text-sm">{proposal.capability_id}</strong><Badge variant="outline">{proposal.status}</Badge></div><p class="mt-1 text-xs text-muted-foreground">{proposal.created_at} · Expires {proposal.expires_at}</p></div>{/each}{/if}</Card.Content></Card.Root>
                    <Card.Root class="min-w-0"><Card.Header><Card.Title>Safety Interventions</Card.Title><Card.Description>Recent refusals, failed runs, and rejected skills.</Card.Description></Card.Header><Card.Content class="space-y-3">{#if interventions.length === 0}<p class="text-sm text-muted-foreground">No recent safety interventions.</p>{:else}{#each interventions as event (event.id)}<div class="min-w-0 rounded-lg border p-3"><div class="flex flex-wrap items-center justify-between gap-2"><strong class="text-sm">{event.type}</strong><span class="text-xs text-muted-foreground">{event.created_at}</span></div><p class="mt-1 break-words text-sm text-muted-foreground">{event.reason}</p></div>{/each}{/if}</Card.Content></Card.Root>
                </div>
            </section>
        {/if}
    </main>

    <Dialog.Root bind:open={providerOpen}>
        <Dialog.Content class="flex max-h-[88vh] w-[calc(100vw-2rem)] sm:max-w-3xl lg:max-w-4xl flex-col overflow-hidden rounded-2xl border border-border bg-card p-0 shadow-2xl" showCloseButton={false}>
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border/70 bg-muted/20 px-5 py-4 sm:px-6">
                <div class="min-w-0">
                    <Dialog.Title class="text-lg font-semibold tracking-tight text-foreground">Configure Provider &amp; Model</Dialog.Title>
                    <Dialog.Description class="mt-0.5 text-xs text-muted-foreground">Connection settings, model discovery, and approved token pricing.</Dialog.Description>
                </div>
                <Button variant="ghost" size="sm" class="size-8 rounded-lg p-0 text-muted-foreground hover:text-foreground" onclick={() => (providerOpen = false)} aria-label="Close dialog">
                    <X class="size-4" />
                </Button>
            </div>
            <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5 sm:px-6">
                <form id="provider-form" class="space-y-6" onsubmit={saveProvider}>
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Left Column: Provider Connection -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-1.5 border-b border-border/50 pb-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Provider Connection</span>
                            </div>

                            <label class="grid gap-1 text-sm font-medium text-foreground">Provider driver
                                <select bind:value={providerForm.driver} onchange={() => (providerForm.active_model_id = '')} class="min-h-11 w-full rounded-md border bg-background px-3 text-sm">
                                    {#each drivers as driver (driver)}<option value={driver}>{driver}</option>{/each}
                                </select>
                            </label>

                            <label class="grid gap-1 text-sm font-medium text-foreground">Base URL
                                <input type="url" bind:value={providerForm.base_url} placeholder="Use provider default" class="min-h-11 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>

                            <fieldset class="space-y-2 rounded-lg border border-border/60 bg-muted/10 p-3">
                                <legend class="flex items-center gap-1.5 text-xs font-medium text-foreground px-1">
                                    <span>API key mode</span>
                                    <InfoPopover title="Environment vs. Custom key" content="Environment uses the server's .env secret. Custom stores an encrypted provider key in the database." />
                                </legend>
                                <label class="flex min-h-9 items-center gap-2 text-sm"><input type="radio" bind:group={providerForm.key_mode} value="env" class="size-4 accent-primary" />Use environment key</label>
                                <label class="flex min-h-9 items-center gap-2 text-sm"><input type="radio" bind:group={providerForm.key_mode} value="custom" class="size-4 accent-primary" />Use saved or custom key</label>
                            </fieldset>

                            {#if providerForm.key_mode === 'custom'}
                                <label class="grid gap-1 text-sm font-medium text-foreground">Custom API key
                                    <input type="password" bind:value={providerForm.custom_api_key} autocomplete="new-password" placeholder={provider_config?.has_custom_key ? 'Saved key is configured; enter to replace it' : 'Enter API key'} class="min-h-11 w-full min-w-0 rounded-md border bg-background px-3 font-mono text-sm" />
                                </label>
                            {/if}

                            <div class="space-y-2 pt-1">
                                <Button type="button" variant="outline" class="flex min-h-11 w-full items-center justify-center gap-2" disabled={providerBusy} onclick={discoverModels}>
                                    {#if providerBusy}
                                        <Loader2 class="size-4 animate-spin" />
                                        <span>Discovering models…</span>
                                    {:else}
                                        <span>Discover Available Models</span>
                                    {/if}
                                </Button>
                                {#if providerStatus}
                                    <p class="text-xs text-muted-foreground" aria-live="polite">{providerStatus}</p>
                                {/if}
                            </div>
                        </div>

                        <!-- Right Column: Model Selection & Parameters -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-1.5 border-b border-border/50 pb-2">
                                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Model &amp; Execution</span>
                            </div>

                            <div class="space-y-3 rounded-xl border border-border bg-muted/15 p-3.5">
                                <div class="flex items-center justify-between gap-2">
                                    <label for="active-model-input" class="text-sm font-medium text-foreground">Primary Active Model ID *</label>
                                    {#if providerForm.active_model_id}
                                        <span class="rounded bg-primary/10 px-2 py-0.5 font-mono text-xs text-primary">
                                            {hasTariff(providerForm.active_model_id, providerForm.driver) || providerForm.is_on_premises ? '✓ Pricing ready' : '⚠ Tariff needed'}
                                        </span>
                                    {/if}
                                </div>
                                <input
                                    id="active-model-input"
                                    list="discovered-active-models"
                                    bind:value={providerForm.active_model_id}
                                    placeholder="e.g. gpt-4o-mini, deepseek-chat, or custom model ID"
                                    required
                                    class="min-h-11 w-full rounded-md border bg-background px-3 font-mono text-sm"
                                    onchange={(e) => {
                                        const val = e.currentTarget.value.trim();
                                        const found = discoveredModels.find((candidate) => candidate.id === val);
                                        if (found) selectModel(found);
                                        else checkCustomModelTariff(val);
                                    }}
                                />
                                <datalist id="discovered-active-models">
                                    {#each discoveredModels as model (model.id)}
                                        <option value={model.id}>{model.name ?? model.id} ({model.has_tariff || hasTariff(model.id, providerForm.driver) ? 'Pricing ready' : 'Pricing required'})</option>
                                    {/each}
                                </datalist>
                                <p class="text-xs text-muted-foreground">Type any custom model ID, or pick from discovered below.</p>

                                {#if discoveredModels.length > 0}
                                    <div class="pt-1">
                                        <span class="text-xs font-medium text-muted-foreground">Discovered suggestions:</span>
                                        <div class="mt-1.5 flex max-h-36 flex-wrap gap-1.5 overflow-y-auto p-1">
                                            {#each discoveredModels.slice(0, 18) as model (model.id)}
                                                <button
                                                    type="button"
                                                    class={`rounded-md border px-2 py-1 font-mono text-xs transition-colors ${providerForm.active_model_id === model.id ? 'border-primary bg-primary/15 font-semibold text-primary' : 'border-border bg-background text-foreground hover:bg-muted'}`}
                                                    onclick={() => selectModel(model)}
                                                >
                                                    {model.id}
                                                    {#if model.supports_tools}<span class="text-[10px] text-emerald-600">·tools</span>{/if}
                                                </button>
                                            {/each}
                                        </div>
                                    </div>
                                {/if}
                            </div>

                            <div class="space-y-2 rounded-xl border border-border bg-muted/15 p-3.5">
                                <div class="flex items-center justify-between gap-2">
                                    <label for="fallback-model-input" class="text-sm font-medium text-foreground">Fallback Model ID (Failover)</label>
                                    {#if providerForm.fallback_model_id}
                                        <span class="rounded bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground">
                                            {hasTariff(providerForm.fallback_model_id, providerForm.driver) || providerForm.is_on_premises ? '✓ Pricing ready' : '⚠ Tariff needed'}
                                        </span>
                                    {/if}
                                </div>
                                <input
                                    id="fallback-model-input"
                                    list="discovered-fallback-models"
                                    bind:value={providerForm.fallback_model_id}
                                    placeholder="e.g. gpt-4o, deepseek-chat, or leave empty"
                                    class="min-h-11 w-full rounded-md border bg-background px-3 font-mono text-sm"
                                    onchange={(e) => {
                                        const val = e.currentTarget.value.trim();
                                        if (val && !hasTariff(val, providerForm.driver) && !providerForm.is_on_premises) {
                                            checkCustomModelTariff(val);
                                        }
                                    }}
                                />
                                <datalist id="discovered-fallback-models">
                                    {#each discoveredModels as model (model.id)}
                                        <option value={model.id}>{model.name ?? model.id}</option>
                                    {/each}
                                </datalist>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="grid gap-1 text-sm font-medium text-foreground">Timeout (seconds)
                                    <input type="number" min="1" max="300" bind:value={providerForm.timeout_seconds} class="min-h-11 w-full rounded-md border bg-background px-3 text-sm" />
                                </label>
                                <label class="grid gap-1 text-sm font-medium text-foreground">Temperature
                                    <input type="number" min="0" max="2" step="0.1" bind:value={providerForm.temperature} class="min-h-11 w-full rounded-md border bg-background px-3 text-sm" />
                                </label>
                            </div>

                            <label class="flex min-h-10 items-center gap-2 text-sm font-medium text-foreground">
                                <input type="checkbox" bind:checked={providerForm.is_on_premises} class="size-4 accent-primary" />
                                On-premises model (zero-cost pricing allowed)
                            </label>
                        </div>
                    </div>
                </form>

                {#if tariffModel}
                    <div class="mt-4 rounded-xl border border-amber-500/40 bg-amber-500/5 p-4 sm:p-5">
                        <div class="mb-3">
                            <h3 class="font-semibold text-foreground">Set Model Tariff: {tariffModel.name ?? tariffModel.id}</h3>
                            <p class="break-words text-xs text-muted-foreground">This model requires an active pricing tariff before activation.</p>
                        </div>
                        <form id="tariff-form" class="grid gap-3 sm:grid-cols-3" onsubmit={saveTariff}>
                            <label class="grid gap-1 text-xs font-medium text-foreground">Prompt micro-USD/token
                                <input type="number" min="0" step="0.000001" bind:value={tariffForm.prompt_micro_per_token} class="min-h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>
                            <label class="grid gap-1 text-xs font-medium text-foreground">Completion micro-USD/token
                                <input type="number" min="0" step="0.000001" bind:value={tariffForm.completion_micro_per_token} class="min-h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>
                            <label class="grid gap-1 text-xs font-medium text-foreground">Context window
                                <input type="number" min="1" bind:value={tariffForm.context_window} class="min-h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>
                            <label class="grid gap-1 text-xs font-medium text-foreground">Cache read micro-USD
                                <input type="number" min="0" step="0.000001" bind:value={tariffForm.cache_read_micro_per_token} class="min-h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>
                            <label class="grid gap-1 text-xs font-medium text-foreground">Cache write micro-USD
                                <input type="number" min="0" step="0.000001" bind:value={tariffForm.cache_write_micro_per_token} class="min-h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>
                            <label class="grid gap-1 text-xs font-medium text-foreground">Reasoning micro-USD
                                <input type="number" min="0" step="0.000001" bind:value={tariffForm.reasoning_micro_per_token} class="min-h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm" />
                            </label>
                            <div class="sm:col-span-3 pt-2">
                                <Button type="submit" variant="secondary" class="min-h-10 w-full">Save Model Tariff</Button>
                            </div>
                        </form>
                    </div>
                {/if}
            </div>

            <!-- Pinned Footer -->
            <div class="flex shrink-0 items-center justify-end gap-2 border-t border-border/70 bg-muted/10 px-5 py-3.5 sm:px-6">
                <Button type="button" variant="outline" class="min-h-10" onclick={() => (providerOpen = false)}>Cancel</Button>
                <Button type="submit" form="provider-form" class="min-h-10">Save Provider Configuration</Button>
            </div>
        </Dialog.Content>
    </Dialog.Root>

    <Dialog.Root bind:open={stopOpen}>
        <Dialog.Content class="sm:max-w-lg p-5 sm:p-6" showCloseButton={false}>
            <Dialog.Header>
                <Dialog.Title class="flex items-center gap-2 text-lg font-semibold text-foreground">
                    <AlertTriangle class="size-5 text-destructive" />
                    {model_info.emergency_stop ? 'Disarm Emergency Stop?' : 'Trip Emergency Stop?'}
                </Dialog.Title>
                <Dialog.Description class="mt-1 text-sm text-muted-foreground">
                    {model_info.emergency_stop ? 'Restore normal assistant operations for this institution.' : 'Block assistant operations and revoke active task grants for this institution.'}
                </Dialog.Description>
            </Dialog.Header>
            <label class="mt-4 grid gap-1 text-sm font-medium text-foreground">Reason (optional)
                <textarea bind:value={stopReason} rows="3" class="w-full min-w-0 resize-y rounded-md border bg-background p-3 text-sm" />
            </label>
            <Dialog.Footer class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button type="button" variant="outline" class="min-h-10" onclick={() => (stopOpen = false)}>Cancel</Button>
                <Button type="button" variant="destructive" class="min-h-10" onclick={submitStop}>
                    {model_info.emergency_stop ? 'Disarm Stop' : 'Trip Stop'}
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>

    {#if extensionTarget}
        <Dialog.Root bind:open={extensionOpen}>
            <Dialog.Content class="sm:max-w-md p-5 sm:p-6" showCloseButton={false}>
                <Dialog.Header>
                    <Dialog.Title class="text-lg font-semibold text-foreground">Extend Daily Ask Quota</Dialog.Title>
                    <Dialog.Description class="mt-1 text-sm text-muted-foreground">
                        {extensionTarget.name} · {officeLabel(extensionTarget.office)}
                    </Dialog.Description>
                </Dialog.Header>
                <form class="mt-4 space-y-4" onsubmit={grantExtension}>
                    <label class="grid gap-1 text-sm font-medium text-foreground">Additional asks (1–500)
                        <input type="number" min="1" max="500" bind:value={extensionAmount} required class="min-h-11 w-full rounded-md border bg-background px-3 text-sm" />
                    </label>
                    <label class="grid gap-1 text-sm font-medium text-foreground">Reason
                        <textarea bind:value={extensionReason} required rows="3" class="w-full resize-y rounded-md border bg-background p-3 text-sm" />
                    </label>
                    <Dialog.Footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" class="min-h-10" onclick={() => (extensionOpen = false)}>Cancel</Button>
                        <Button type="submit" class="min-h-10">Grant Extension</Button>
                    </Dialog.Footer>
                </form>
            </Dialog.Content>
        </Dialog.Root>
    {/if}
</StaffLayout>

{#snippet QuotaTable(staff_quotas, openExtension)}
    <Card.Root class="min-w-0"><Card.Content class="p-0"><div class="overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="border-b bg-muted/30"><tr><th class="p-3 font-medium">Staff member</th><th class="p-3 font-medium">Office</th><th class="p-3 font-medium">Asks used / limit</th><th class="p-3 font-medium">Extensions today</th><th class="p-3 text-right font-medium">Action</th></tr></thead><tbody class="divide-y">{#each staff_quotas as staff (staff.id)}<tr><td class="p-3"><p class="font-medium">{staff.name}</p><p class="text-xs text-muted-foreground">{staff.email}</p></td><td class="p-3">{officeLabel(staff.office)}</td><td class="p-3">{staff.used_asks} / {staff.limit}</td><td class="p-3">{staff.granted_extensions_today}</td><td class="p-3 text-right">{#if staff.can_extend}<Button variant="outline" size="sm" class="min-h-11" onclick={() => openExtension(staff)}>+ Extend</Button>{:else}<span class="text-xs text-muted-foreground">—</span>{/if}</td></tr>{/each}</tbody></table></div></Card.Content></Card.Root>
{/snippet}
