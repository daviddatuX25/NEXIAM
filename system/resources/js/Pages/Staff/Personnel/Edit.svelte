<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import VoucherDialog from './VoucherDialog.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Link, router, useForm } from '@inertiajs/svelte';

    let {
        user,
        roles = [],
        can_full_hr = false,
        is_support = false,
        can_reset_password = false,
        available_programs = [],
    } = $props();

    let selectedRoles = $state([...(user.roles ?? [])]);
    let passwordVoucherOpen = $state(false);
    let busyMfa = $state(false);
    function toggleTargetMfa(action) {
        if (busyMfa) return;
        busyMfa = true;
        router.post(
            `/staff/personnel/${user.id}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    busyMfa = false;
                },
            },
        );
    }
    const form = useForm({
        name: user.name,
        email: user.email,
        roles: [...(user.roles ?? [])],
        office: user.office,
        program_codes: user.program_codes ?? [],
    });

    const canPickRoles = can_full_hr && !is_support;

    function toggleRole(name) {
        if (selectedRoles.includes(name)) {
            selectedRoles = selectedRoles.filter((r) => r !== name);
        } else {
            selectedRoles = [...selectedRoles, name];
        }
    }

    function toggleProgram(code) {
        if (form.program_codes.includes(code)) {
            form.program_codes = form.program_codes.filter((c) => c !== code);
        } else {
            form.program_codes = [...form.program_codes, code];
        }
    }
    const availableProgramCodes = $derived(
        (available_programs ?? []).map((p) => p.code),
    );
    const selectedAvailableProgramCount = $derived(
        availableProgramCodes.filter((c) => form.program_codes.includes(c))
            .length,
    );
    const allAvailableProgramsSelected = $derived(
        availableProgramCodes.length > 0 &&
            selectedAvailableProgramCount === availableProgramCodes.length,
    );

    function toggleAllPrograms() {
        if (allAvailableProgramsSelected) {
            form.program_codes = form.program_codes.filter(
                (c) => !availableProgramCodes.includes(c),
            );
        } else {
            form.program_codes = [
                ...new Set([...form.program_codes, ...availableProgramCodes]),
            ];
        }
    }

    function clearPrograms() {
        form.program_codes = [];
    }

    function changeOffice(event) {
        form.office = event.currentTarget.value;
        if (form.office !== 'program') clearPrograms();
    }
    function submitForm(e) {
        e.preventDefault();
        form.transform((data) => {
            const payload = { ...data };
            if (is_support) {
                delete payload.roles;
            } else {
                payload.roles = canPickRoles
                    ? selectedRoles
                    : [...(user.roles ?? [])];
            }
            if (form.office !== 'program') payload.program_codes = [];
            return payload;
        }).put(`/staff/personnel/${user.id}`);
    }

    const breadcrumbs = [
        { label: 'Staff', href: '/staff/personnel' },
        { label: 'Edit' },
    ];
</script>

<svelte:head><title>Edit staff</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Edit staff</h1>
            <p class="text-muted-foreground text-sm capitalize">
                Status: {user.status}
            </p>
        </div>

        <form onsubmit={submitForm} class="space-y-6">
            <div class="grid items-start gap-6 lg:grid-cols-2">
                <div
                    class="border-border bg-card space-y-4 rounded-xl border p-6 shadow-xs"
                >
                    <h2
                        class="text-foreground border-border border-b pb-2 text-base font-semibold"
                    >
                        Account Identity
                    </h2>
                    <div class="space-y-2">
                        <label for="name" class="text-sm font-medium"
                            >Name</label
                        >
                        <Input id="name" bind:value={form.name} required />
                        {#if form.errors?.name}<p
                                class="text-destructive text-sm"
                            >
                                {form.errors.name}
                            </p>{/if}
                    </div>

                    <div class="space-y-2">
                        <label for="email" class="text-sm font-medium"
                            >Email{#if user.email_pending}<span
                                    class="text-muted-foreground ml-1 text-xs"
                                    >recipient choice pending</span
                                >{/if}</label
                        >
                        <Input
                            id="email"
                            type="email"
                            bind:value={form.email}
                            required={!user.email_pending}
                            disabled={user.email_pending}
                            placeholder={user.email_pending
                                ? 'Recipient will choose during setup'
                                : ''}
                        />
                        {#if user.email_pending}<p
                                class="text-muted-foreground text-xs"
                            >
                                No address is available yet. Do not invent a
                                contact; the recipient will choose an email
                                during account setup.
                            </p>{/if}
                        {#if form.errors?.email}<p
                                class="text-destructive text-sm"
                            >
                                {form.errors.email}
                            </p>{/if}
                    </div>

                    {#if can_reset_password}
                        <div
                            class="border-border bg-muted/20 rounded-lg border p-3 text-sm"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                onclick={() => (passwordVoucherOpen = true)}
                                >Issue password recovery voucher</Button
                            >
                            <p class="mt-2">
                                The recipient chooses a new password through a
                                single-use recovery voucher.
                            </p>
                        </div>
                    {:else}
                        <div
                            class="border-border bg-muted/20 text-muted-foreground rounded-lg border border-dashed p-3 text-sm"
                        >
                            Password recovery is limited to Staffing/HR and
                            office leads. Request a recovery voucher from your
                            office lead or HR.
                        </div>
                    {/if}
                    {#if user.can_require_mfa || user.can_disable_mfa}
                        <div class="border-border/60 space-y-2 border-t pt-3">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div>
                                    <p
                                        class="text-foreground text-sm font-medium"
                                    >
                                        Two-Factor Authentication
                                    </p>
                                    <p class="text-muted-foreground text-xs">
                                        Status: <span
                                            class="text-foreground font-medium"
                                            >{user.mfa_status === 'enrolled'
                                                ? 'Active'
                                                : user.mfa_status === 'pending'
                                                  ? 'Required (Not Set Up)'
                                                  : 'Off'}</span
                                        >
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    {#if user.can_require_mfa && user.mfa_status === 'exempt'}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={busyMfa}
                                            onclick={() =>
                                                toggleTargetMfa('require-mfa')}
                                        >
                                            {busyMfa
                                                ? 'Updating…'
                                                : 'Require Two-Factor Login'}
                                        </Button>
                                    {/if}
                                    {#if user.can_disable_mfa && user.mfa_status !== 'exempt'}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            disabled={busyMfa}
                                            onclick={() =>
                                                toggleTargetMfa('disable-mfa')}
                                        >
                                            {busyMfa
                                                ? 'Updating…'
                                                : 'Turn Off Two-Factor Login'}
                                        </Button>
                                    {/if}
                                </div>
                            </div>
                        </div>
                    {/if}
                </div>
                <div
                    class="border-border bg-card space-y-4 rounded-xl border p-6 shadow-xs"
                >
                    <div
                        class="border-border flex items-center gap-1.5 border-b pb-2"
                    >
                        <h2 class="text-foreground text-base font-semibold">
                            Office & Roles
                        </h2>
                        <InfoPopover
                            title="Institutional vs. Academic taxonomy"
                            content="Central offices (Admin, Registrar, Guidance) use two-factor authentication. Academic Programs scope access strictly to assigned degree programs."
                        />
                    </div>
                    {#if can_full_hr}
                        <div class="space-y-2">
                            <label for="office" class="text-sm font-medium"
                                >Office</label
                            >
                            <select
                                id="office"
                                bind:value={form.office}
                                onchange={changeOffice}
                                required
                                class="border-input bg-background flex h-10 w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="admin">Admin Office</option>
                                <option value="registrar"
                                    >Registrar Office</option
                                >
                                <option value="guidance">Guidance Office</option
                                >
                                <option value="program"
                                    >Academic Programs</option
                                >
                            </select>
                        </div>
                    {:else}
                        <p class="text-muted-foreground text-sm">
                            Office: <span class="text-foreground font-medium"
                                >{user.office}</span
                            >
                        </p>
                    {/if}

                    {#if canPickRoles}
                        <div class="space-y-2">
                            <p class="text-sm font-medium">Roles</p>
                            <p class="text-muted-foreground text-xs">
                                Permanent role helpers only. Support staff roles
                                follow the office.
                            </p>
                            <div class="flex flex-wrap gap-4 pt-2">
                                {#each roles as r (r.name)}
                                    <label
                                        class="flex min-h-11 cursor-pointer items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedRoles.includes(
                                                r.name,
                                            )}
                                            onchange={() => toggleRole(r.name)}
                                            class="border-input accent-primary h-4 w-4 rounded"
                                        />
                                        <span class="text-sm"
                                            >{r.display_name}</span
                                        >
                                    </label>
                                {/each}
                            </div>
                            {#if form.errors?.roles}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.roles}
                                </p>{/if}
                            {#if form.office === 'program'}
                                <div
                                    class="border-border/60 space-y-2 border-t pt-3"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <p class="text-sm font-medium">
                                                Assigned Programs (Jurisdiction)
                                            </p>
                                            <InfoPopover
                                                title="Chair vs. Dean jurisdiction"
                                                content="Assigning 1 program scopes a Department Chair; assigning multiple programs across a college scopes a College Dean."
                                            />
                                        </div>
                                        {#if available_programs.length > 0}
                                            <div
                                                class="flex items-center gap-3 text-xs"
                                            >
                                                <button
                                                    type="button"
                                                    class="text-primary cursor-pointer font-medium hover:underline"
                                                    onclick={toggleAllPrograms}
                                                >
                                                    {allAvailableProgramsSelected
                                                        ? 'Deselect all'
                                                        : `Select all (${available_programs.length})`}
                                                </button>
                                                {#if selectedAvailableProgramCount > 0}
                                                    <button
                                                        type="button"
                                                        class="text-muted-foreground hover:text-destructive cursor-pointer hover:underline"
                                                        onclick={clearPrograms}
                                                    >
                                                        Clear
                                                    </button>
                                                {/if}
                                            </div>
                                        {/if}
                                    </div>
                                    <p class="text-muted-foreground text-xs">
                                        Select 1 program for a Department Chair,
                                        or multiple programs for a College Dean
                                        (e.g. BSIT + BSCS for CCIT Dean).
                                    </p>
                                    <div
                                        class="grid grid-cols-2 gap-2 pt-1 sm:grid-cols-3"
                                    >
                                        {#each available_programs as prog (prog.code)}
                                            <label
                                                class="border-border/70 hover:bg-muted/40 flex cursor-pointer items-center gap-2 rounded-lg border p-2 text-xs transition-colors"
                                                title={prog.name ?? prog.code}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.program_codes.includes(
                                                        prog.code,
                                                    )}
                                                    onchange={() =>
                                                        toggleProgram(
                                                            prog.code,
                                                        )}
                                                    class="border-input accent-primary h-3.5 w-3.5 shrink-0 rounded"
                                                />
                                                <div
                                                    class="min-w-0 flex-1 truncate"
                                                >
                                                    <span
                                                        class="text-foreground font-medium"
                                                        >{prog.code}</span
                                                    >
                                                    {#if prog.name}
                                                        <span
                                                            class="text-muted-foreground block truncate text-[10px]"
                                                            >{prog.name}</span
                                                        >
                                                    {/if}
                                                </div>
                                            </label>
                                        {/each}
                                    </div>
                                    {#if form.errors?.program_codes}<p
                                            class="text-destructive text-sm"
                                        >
                                            {form.errors.program_codes}
                                        </p>{/if}
                                </div>
                            {/if}
                        </div>
                    {:else}
                        <div class="space-y-2">
                            <p class="text-sm font-medium">Roles</p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                {#each user.roles ?? [] as roleName (typeof roleName === 'string' ? roleName : roleName.name)}
                                    <span
                                        class="bg-primary/10 text-primary rounded-full px-2.5 py-1 text-xs font-medium"
                                    >
                                        {roleName.replaceAll('_', ' ')}
                                    </span>
                                {/each}
                            </div>
                            <p class="text-muted-foreground text-xs">
                                {is_support
                                    ? 'Support staff role follows the office and is not reassigned here.'
                                    : 'Role helpers are managed by Staffing/HR only.'}
                            </p>
                        </div>
                    {/if}
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save'}
                </Button>
                <Link href="/staff/personnel">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
            </div>
        </form>
    </div>
    <VoucherDialog
        open={passwordVoucherOpen}
        {user}
        initialPurpose="password_reset"
        onOpenChange={(value) => (passwordVoucherOpen = value)}
        canIssueNonExpiring={false}
    />
</StaffLayout>
