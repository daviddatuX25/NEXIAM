<script>
    import { onMount } from 'svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Label } from '@/Components/ui/label';
    import { router, usePage } from '@inertiajs/svelte';
    import { ArrowLeft } from 'lucide-svelte';

    let { academic_cycles = [], selected_cycle_id = null, policy = null, return_to = null } = $props();
    const page = usePage();
    const status = $derived(page.props.flash?.status ?? '');
    const errors = $derived(page.props.errors ?? {});
    let normalMethod = $state(policy?.normal_method ?? 'online');
    let allowOnlineExceptions = $state(Boolean(policy?.allow_online_exceptions));
    let saving = $state(false);
    let saveError = $state('');
    let saved = $state(false);
    let allowNavigation = false;
    const dirty = $derived(
        normalMethod !== (policy?.normal_method ?? 'online')
            || (normalMethod === 'f2f' && allowOnlineExceptions !== Boolean(policy?.allow_online_exceptions)),
    );

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Consultation Policy' },
    ];

    onMount(() => {
        const removeBefore = router.on('before', (event) => {
            const visit = event.detail.visit;
            const target = new URL(visit.url, window.location.origin);
            const isPolicySave = saving && visit.method === 'post' && target.pathname === '/setup/consultation-policy';

            if (allowNavigation || isPolicySave) {
                allowNavigation = false;
                return;
            }

            if (saving || (dirty && !window.confirm('Discard unsaved consultation policy changes and leave this page?'))) {
                event.preventDefault();
                saveError = saving
                    ? 'Wait for the policy save to finish before leaving.'
                    : 'Unsaved policy changes were kept.';
            }
        });
        const beforeUnload = (event) => {
            if (!saving && !dirty) return;
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', beforeUnload);

        return () => {
            removeBefore();
            window.removeEventListener('beforeunload', beforeUnload);
        };
    });

    $effect(() => {
        // A cycle navigation replaces policy props; discard drafts and trust its revision.
        selected_cycle_id;
        policy?.normal_method;
        policy?.allow_online_exceptions;
        policy?.version;
        normalMethod = policy?.normal_method ?? 'online';
        allowOnlineExceptions = Boolean(policy?.allow_online_exceptions);
        saved = false;
        saveError = '';
    });

    function updatePolicy(event) {
        event.preventDefault();
        if (saving || !selected_cycle_id) return;
        saving = true;
        saved = false;
        saveError = '';
        router.post('/setup/consultation-policy', {
            academic_cycle_id: selected_cycle_id,
            normal_method: normalMethod,
            allow_online_exceptions: normalMethod === 'f2f' && allowOnlineExceptions,
            expected_version: policy?.version ?? 0,
        }, {
            preserveScroll: true,
            onSuccess: () => { saved = true; },
            onError: (validationErrors) => {
                saveError = Object.values(validationErrors).join(' ')
                    || 'The consultation policy changed or could not be saved. Reload the cycle and try again.';
            },
            onFinish: () => { saving = false; },
        });
    }

    function canLeave() {
        if (saving) {
            saveError = 'Wait for the policy save to finish before leaving.';
            return false;
        }
        if (dirty && !window.confirm('Discard unsaved consultation policy changes and leave this page?')) {
            saveError = 'Unsaved policy changes were kept.';
            return false;
        }
        return true;
    }

    function navigate(url, onBlocked = null) {
        if (!url || !canLeave()) {
            onBlocked?.();
            return;
        }
        allowNavigation = true;
        router.visit(url, { onFinish: () => { allowNavigation = false; } });
    }

    function selectCycle(event) {
        const select = event.currentTarget;
        const params = new URLSearchParams();
        const cycleId = select.value;
        if (cycleId) params.set('cycle_id', cycleId);
        if (return_to) params.set('return_to', return_to);
        const query = params.toString();
        navigate(`/setup/consultation-policy${query ? `?${query}` : ''}`, () => {
            select.value = String(selected_cycle_id ?? '');
        });
    }
</script>


<svelte:head><title>Consultation Policy · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 pb-40 sm:p-6 sm:pb-40">
        <PageHeaderBar
            title="Consultation Policy"
            description="Choose the normal consultation method for this academic cycle."
        >
            {#snippet actions()}
                {#if return_to}
                    <Button variant="outline" class="min-h-10 gap-2" onclick={() => navigate(return_to)}>
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        <span>Return to Consultation</span>
                    </Button>
                {/if}
            {/snippet}
        </PageHeaderBar>

        {#if status}
            <p role="status" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900">{status}</p>
        {/if}
        {#if saveError || Object.keys(errors).length > 0}
            <div role="alert" class="rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive">
                {#if saveError}<p>{saveError}</p>{/if}
                {#each Object.values(errors) as error}<p>{error}</p>{/each}
            </div>
        {/if}
        {#if saved}
            <p role="status" class="text-sm text-muted-foreground">Consultation policy saved.</p>
        {/if}

        {#if academic_cycles.length > 0}
            <div class="space-y-2">
                <Label for="cycle-select">Academic cycle</Label>
                <select id="cycle-select" value={selected_cycle_id ?? ''} onchange={selectCycle} class="h-10 min-w-64 rounded-md border border-input bg-background px-3 text-sm text-foreground shadow-xs">
                    {#each academic_cycles as cycle (cycle.id)}
                        <option value={cycle.id}>{cycle.label}{cycle.is_active ? ' · Active' : ''}</option>
                    {/each}
                </select>
            </div>

            <section aria-labelledby="consultation-policy-heading" class="overflow-hidden rounded-xl border border-border/70 bg-card shadow-xs">
                <div class="border-b border-border/60 bg-muted/20 p-5 sm:p-6">
                    <h2 id="consultation-policy-heading" class="text-base font-semibold text-foreground">Consultation method</h2>
                    <p class="mt-1 text-sm text-muted-foreground">The default applies the consultation preference for the cycle. Staff explicitly start each online encounter; saving this policy never converts bookings or changes results.</p>
                </div>
                <form onsubmit={updatePolicy} class="space-y-5 p-5 sm:p-6">
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-medium text-foreground">Normal method</legend>
                        <label for="normal-method-online" class="flex items-start gap-3 text-sm text-foreground">
                            <input id="normal-method-online" type="radio" name="normal-method" value="online" bind:group={normalMethod} class="mt-0.5 size-4 accent-primary" />
                            <span>
                                <span class="font-medium">Online</span>
                                <span class="mt-1 block text-muted-foreground">Online is the default. Staff explicitly start each online encounter when appropriate.</span>
                            </span>
                        </label>
                        <label for="normal-method-f2f" class="flex items-start gap-3 text-sm text-foreground">
                            <input id="normal-method-f2f" type="radio" name="normal-method" value="f2f" bind:group={normalMethod} class="mt-0.5 size-4 accent-primary" />
                            <span>
                                <span class="font-medium">Face-to-face</span>
                                <span class="mt-1 block text-muted-foreground">Face-to-face is the normal method. Existing bookings and results are not changed.</span>
                            </span>
                        </label>
                    </fieldset>
                    {#if normalMethod === 'f2f'}
                        <label for="allow-online-exceptions" class="flex max-w-2xl items-start gap-3 text-sm text-foreground">
                            <input id="allow-online-exceptions" type="checkbox" bind:checked={allowOnlineExceptions} class="mt-0.5 size-4 rounded border-input accent-primary" />
                            <span>
                                <span class="font-medium">Allow online exceptions</span>
                                <span class="mt-1 block text-muted-foreground">When enabled, staff may explicitly start an online encounter for an individual applicant.</span>
                            </span>
                        </label>
                    {/if}
                    <Button type="submit" disabled={saving} aria-busy={saving}>{saving ? 'Saving…' : 'Save'}</Button>
                </form>
            </section>

            {#if !policy?.media_ready}
                <p role="note" class="text-sm text-muted-foreground" title={policy?.media_unavailable_reason ?? undefined}>
                    Online consultation is temporarily unavailable. Face-to-face consultation remains available.
                </p>
            {/if}
        {:else}
            <section class="rounded-xl border border-border/70 bg-card p-6 text-sm text-muted-foreground">Create an academic cycle before configuring its consultation method.</section>
        {/if}
    </div>
</StaffLayout>
