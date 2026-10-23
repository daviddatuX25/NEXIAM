<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Link, useForm } from '@inertiajs/svelte';

    let {
        roles = [],
        can_full_hr = false,
        default_office = 'admin',
        available_programs = [],
    } = $props();

    let selectedRoles = $state([]);
    let accountType = $state(can_full_hr ? 'permanent' : 'support');

    const form = useForm({
        name: '',
        email: '',
        roles: [],
        account_type: 'permanent',
        office: default_office,
        program_codes: [],
    });

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
        form.account_type = accountType;
        form.roles =
            can_full_hr && accountType === 'permanent' ? selectedRoles : [];
        form.post('/staff/personnel');
    }

    const breadcrumbs = [
        { label: 'Staff', href: '/staff/personnel' },
        { label: 'Create' },
    ];
</script>

<svelte:head><title>Create staff</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="max-w-5xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Create staff</h1>
            <p class="text-muted-foreground text-sm">
                Support staff are the natural role for each office. Test
                Administrator capabilities are delegated separately and are not
                granted during staff creation.
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
                        Account details
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
                            >Email (optional until setup)</label
                        >
                        <Input
                            id="email"
                            type="email"
                            bind:value={form.email}
                            aria-describedby="email-setup-help"
                        />
                        <p
                            id="email-setup-help"
                            class="text-muted-foreground text-xs"
                        >
                            Leave blank if unknown. The recipient supplies their
                            email during voucher setup.
                        </p>
                        {#if form.errors?.email}<p
                                class="text-destructive text-sm"
                            >
                                {form.errors.email}
                            </p>{/if}
                    </div>

                    <p
                        class="border-border bg-muted/30 text-muted-foreground rounded-lg border p-3 text-sm"
                    >
                        This account starts as Awaiting setup. Issue a setup
                        voucher from Personnel so the recipient can choose their
                        own password and complete required security. Creating or
                        printing a voucher does not activate access.
                    </p>
                </div>
                <div
                    class="border-border bg-card space-y-4 rounded-xl border p-6 shadow-xs"
                >
                    <div
                        class="border-border flex items-center gap-1.5 border-b pb-2"
                    >
                        <h2 class="text-foreground text-base font-semibold">
                            Office & Role Assignment
                        </h2>
                        <InfoPopover
                            title="Institutional vs. Academic taxonomy"
                            content="Central offices (Admin, Registrar, Guidance) use two-factor authentication. Academic Programs scope access strictly to assigned degree programs."
                        />
                    </div>
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
                            <option value="registrar">Registrar Office</option>
                            <option value="guidance">Guidance Office</option>
                            <option value="program">Academic Programs</option>
                        </select>
                        {#if form.errors?.office}<p
                                class="text-destructive text-sm"
                            >
                                {form.errors.office}
                            </p>{/if}
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-1.5">
                            <p class="text-sm font-medium">Account type</p>
                            <InfoPopover
                                title="Staff vs. Support staff"
                                content="Permanent staff pick explicit role helpers. Support staff inherit a bounded operational subset for their assigned office."
                            />
                        </div>
                        <div class="flex flex-wrap gap-4 pt-1">
                            <label
                                class="flex min-h-11 cursor-pointer items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    name="account_type"
                                    value="permanent"
                                    checked={accountType === 'permanent'}
                                    onchange={() => (accountType = 'permanent')}
                                    class="accent-primary h-4 w-4"
                                />
                                <span class="text-sm"
                                    >Staff (pick role below)</span
                                >
                            </label>
                            <label
                                class="flex min-h-11 cursor-pointer items-center gap-2"
                            >
                                <input
                                    type="radio"
                                    name="account_type"
                                    value="support"
                                    checked={accountType === 'support'}
                                    onchange={() => (accountType = 'support')}
                                    class="accent-primary h-4 w-4"
                                />
                                <span class="text-sm"
                                    >Support staff (role follows office)</span
                                >
                            </label>
                        </div>
                    </div>

                    {#if accountType === 'permanent'}
                        <div class="space-y-2">
                            <p class="text-sm font-medium">Roles</p>
                            <p class="text-muted-foreground text-xs">
                                Select at least one approved role helper.
                            </p>
                            <div class="flex flex-wrap gap-4 pt-2">
                                {#each roles as r}
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
                                        {#each available_programs as prog}
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
                        <p
                            class="bg-muted text-muted-foreground rounded-md px-3 py-2 text-sm"
                        >
                            {form.office} office support staff
                        </p>
                    {/if}
                </div>
            </div>
            <div class="flex gap-2 pt-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Creating…' : 'Create staff'}
                </Button>
                <Link href="/staff/personnel">
                    <Button type="button" variant="outline">Cancel</Button>
                </Link>
            </div>
        </form>
    </div>
</StaffLayout>
