<script>
    import { Link, router } from '@inertiajs/svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Button } from '@/Components/ui/button';
    import BatchVouchers from './BatchVouchers.svelte';

    let {
        readiness,
        can_create = false,
        roles = [],
        demo_mode_available = false,
        default_office = 'admin',
        can_full_hr = false,
        can_assign_department_dean = false,
        eligible_existing_heads = [],
    } = $props();
    let selected = $state([]);
    let name = $state('');
    let email = $state('');
    let office = $state(default_office);
    let role = $state(roles[0]?.name ?? roles[0] ?? 'program_head');
    let programCodes = $state([]);
    let demoMode = $state(false);
    let mode = $state('create');
    let targetUserId = $state('');
    let assignmentProgramCode = $state('');
    let departmentId = $state(null);
    let batchMode = $state(false);
    let batchRecipients = $state([]);
    let batchRole = $state(roles[0]?.name ?? roles[0] ?? 'program_head');
    let batchOffice = $state(default_office);
    let batchCount = $state(1);
    let batchReview = $state(null);
    let batchResults = $state([]);
    let review = $state(null);
    let busy = $state(false);
    let error = $state('');
    let resetReason = $state('');
    let currentPassword = $state('');
    let operationId = '';
    let totpCode = $state('');
    let resetResults = $state([]);
    let showReset = $state(false);
    let resetOperationIds = $state({});
    const rows = $derived(readiness?.rows ?? []);
    const voucherTargets = $derived(
        Array.from(
            new Map(
                rows
                    .filter(
                        (row) =>
                            selected.includes(row.user_id) &&
                            row.user_id &&
                            row.permitted_actions.includes('issue_voucher'),
                    )
                    .map((row) => [
                        row.user_id,
                        {
                            id: row.user_id,
                            name: row.name,
                            email: row.email,
                            office: row.office,
                            program_codes: row.program_codes,
                        },
                    ]),
            ).values(),
        ),
    );

    function toggle(id) {
        selected = selected.includes(id)
            ? selected.filter((value) => value !== id)
            : [...selected, id];
    }
    function recipientPayload() {
        if (mode === 'assign_existing') {
            const target = eligible_existing_heads.find(
                (item) => item.id === Number(targetUserId),
            );
            return {
                mode,
                target_user_id: Number(targetUserId),
                name: target?.name,
                email: target?.email ?? null,
                office: 'program',
                program_codes: [assignmentProgramCode],
                demo_mode: false,
            };
        }
        if (mode === 'prepare_dean') {
            return {
                mode,
                department_id: departmentId,
                name,
                email: email || null,
                office: 'program',
                demo_mode: false,
            };
        }
        return {
            mode,
            name,
            email: email || null,
            office,
            roles: [role],
            program_codes: programCodes,
            demo_mode: demoMode,
        };
    }
    function prepareDean(row) {
        departmentId = row.department_id;
        name = '';
        email = '';
        mode = 'prepare_dean';
        office = 'program';
        role = 'program_head';
        review = null;
    }
    function addBatchRecipientRows() {
        const count = Math.min(30, Math.max(1, Number(batchCount) || 1));
        batchRecipients = [
            ...batchRecipients,
            ...Array.from({ length: count }, () => ({
                operation_id: crypto.randomUUID(),
                name: '',
                email: null,
                office: batchOffice,
                roles: [batchRole],
                program_codes: [],
                demo_mode: false,
            })),
        ];
        batchReview = null;
    }

    async function previewBatch() {
        error = '';
        busy = true;
        try {
            const response = await fetch('/staff/personnel/prepare/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({ recipients: batchRecipients }),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    data.message ?? 'Batch review could not be prepared.',
                );
            batchReview = data;
        } catch (cause) {
            error = cause.message;
        } finally {
            busy = false;
        }
    }

    function commitBatch() {
        if (!batchReview) return;
        busy = true;
        router.post(
            '/staff/personnel/prepare/commit',
            {
                recipients: batchRecipients,
                review_token: batchReview.review_token,
            },
            {
                onSuccess: (page) => {
                    batchResults = page.props.flash?.preparation_results ?? [];
                },
                onError: (errors) => {
                    error = Object.values(errors).join(' ');
                    batchReview = null;
                },
                onFinish: () => {
                    busy = false;
                },
            },
        );
    }

    async function preview() {
        error = '';
        busy = true;
        operationId = crypto.randomUUID();
        try {
            const response = await fetch('/staff/personnel/prepare/preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN':
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content ?? '',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    ...recipientPayload(),
                    operation_id: operationId,
                }),
            });
            const data = await response.json();
            if (!response.ok)
                throw new Error(
                    data.message ?? 'Review could not be prepared.',
                );
            review = data;
        } catch (cause) {
            error = cause.message;
        } finally {
            busy = false;
        }
    }

    function commit() {
        if (!review) return;
        busy = true;
        router.post(
            '/staff/personnel/prepare/commit',
            {
                ...recipientPayload(),
                operation_id: operationId,
                review_token: review.review_token,
            },
            {
                onError: (errors) => {
                    error = Object.values(errors).join(' ');
                    review = null;
                },
                onFinish: () => {
                    busy = false;
                },
            },
        );
    }
    function resetSelected() {
        error = '';
        busy = true;
        const operationIds = {};
        for (const id of selected) {
            if (!resetOperationIds[id])
                resetOperationIds = {
                    ...resetOperationIds,
                    [id]: crypto.randomUUID(),
                };
            operationIds[String(id)] = resetOperationIds[id];
        }
        const expectedGenerations = Object.fromEntries(
            voucherTargets.map((target) => [
                String(target.id),
                rows.find((row) => row.user_id === target.id)
                    ?.expected_generation,
            ]),
        );
        router.post(
            '/staff/personnel/full-reset/batch',
            {
                targets: selected,
                operation_ids: operationIds,
                expected_generations: expectedGenerations,
                reason: resetReason,
                current_password: currentPassword,
                totp_code: totpCode,
            },
            {
                onSuccess: (page) => {
                    resetResults = page.props.flash?.reset_results ?? [];
                    selected = resetResults
                        .filter((result) =>
                            ['failed', 'conflicted'].includes(result.outcome),
                        )
                        .map((result) => result.user_id);
                    const retainedIds = new Set(selected.map(String));
                    resetOperationIds = Object.fromEntries(
                        Object.entries(resetOperationIds).filter(([id]) =>
                            retainedIds.has(id),
                        ),
                    );
                    showReset = selected.length > 0;
                    currentPassword = '';
                    totpCode = '';
                },
                onError: (errors) => {
                    error = Object.values(errors).join(' ');
                },
                onFinish: () => {
                    busy = false;
                },
            },
        );
    }

    const breadcrumbs = [
        { label: 'Staff Directory', href: '/staff/personnel' },
        { label: 'Prepare staff accounts' },
    ];
</script>

<svelte:head><title>Prepare staff accounts</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <main class="min-w-0 space-y-8 p-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Prepare staff accounts
                </h1>
                <p class="text-muted-foreground mt-2 max-w-3xl text-sm">
                    Review current program and department coverage, prepare
                    identified recipients, and manage setup access without
                    activating accounts.
                </p>
            </div>
            <Link href="/staff/personnel"
                ><Button variant="outline">Back to staff directory</Button
                ></Link
            >
        </header>

        {#if error}<p
                role="alert"
                class="border-destructive/40 bg-destructive/5 text-destructive rounded-lg border p-3 text-sm"
            >
                {error}
            </p>{/if}
        <section aria-labelledby="coverage-title" class="space-y-3">
            <h2 id="coverage-title" class="text-lg font-semibold">
                Current coverage
            </h2>
            <div class="border-border overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead class="bg-muted/50"
                        ><tr
                            ><th class="p-3">Need</th><th class="p-3"
                                >Office / scope</th
                            ><th class="p-3">Assigned person</th><th class="p-3"
                                >State</th
                            ><th class="p-3">Action</th></tr
                        ></thead
                    ><tbody>
                        {#each rows as row (row.key)}<tr
                                class="border-border border-t"
                                ><td class="p-3">{row.label}</td><td class="p-3"
                                    >{row.office ?? 'Academic'}{row.scope_code
                                        ? ` · ${row.scope_code}`
                                        : ''}{row.department_id
                                        ? ` · Department ${row.department_id}`
                                        : ''}</td
                                ><td class="p-3"
                                    >{row.name ??
                                        (row.state === 'missing'
                                            ? 'Unfilled'
                                            : 'Details restricted')}{#if row.email === null && row.name}<span
                                            class="text-muted-foreground block text-xs"
                                            >Email to be chosen by recipient</span
                                        >{/if}</td
                                ><td class="p-3"
                                    ><span
                                        >{row.state.replaceAll('_', ' ')}</span
                                    >{#if row.employment_state}<span
                                            class="text-muted-foreground block text-xs"
                                            >Employment: {row.employment_state}</span
                                        >{/if}{#if row.setup_state}<span
                                            class="text-muted-foreground block text-xs"
                                            >Setup: {row.setup_state.replaceAll(
                                                '_',
                                                ' ',
                                            )}</span
                                        >{/if}{#if row.voucher_state && row.voucher_state !== 'none'}<span
                                            class="text-muted-foreground block text-xs"
                                            >Voucher: {row.voucher_state ===
                                                'expired' &&
                                            row.permitted_actions.includes(
                                                'issue_voucher',
                                            )
                                                ? 'expired — issue a replacement'
                                                : row.voucher_state}</span
                                        >{/if}</td
                                ><td class="p-3"
                                    >{#if row.user_id && row.permitted_actions.includes('reset')}<label
                                            class="inline-flex items-center gap-2"
                                            ><input
                                                type="checkbox"
                                                checked={selected.includes(
                                                    row.user_id,
                                                )}
                                                onchange={() =>
                                                    toggle(row.user_id)}
                                                aria-label={`Select ${row.name} for staff access operations`}
                                            />Select</label
                                        >{:else if row.state === 'missing' && row.role === 'dean' && can_full_hr}<Button
                                            type="button"
                                            variant="outline"
                                            onclick={() => prepareDean(row)}
                                            >Prepare recipient for Dean
                                            assignment</Button
                                        >{:else if row.state === 'missing' && row.role === 'dean'}<span
                                            class="text-muted-foreground"
                                            >Ask HR to prepare a recipient; an
                                            authorized Admin Office Setup user
                                            must assign the Dean in Departments.</span
                                        >{:else if row.state === 'missing' && can_create}<span
                                            class="text-muted-foreground"
                                            >Prepare an identified recipient
                                            below</span
                                        >{:else}<span
                                            class="text-muted-foreground"
                                            >{row.state === 'missing' &&
                                            row.role === 'program_head'
                                                ? 'Escalate to HR for assignment.'
                                                : row.state === 'conflict'
                                                  ? 'Review duplicate ownership'
                                                  : 'No permitted action'}</span
                                        >{/if}</td
                                ></tr
                            >{/each}
                    </tbody>
                </table>
            </div>
        </section>

        {#if can_create}<section
                class="border-border max-w-3xl space-y-4 border-t pt-6"
                aria-labelledby="recipient-title"
            >
                <h2 id="recipient-title" class="text-lg font-semibold">
                    Identify a recipient
                </h2>
                <p class="text-muted-foreground text-sm">
                    Email is optional. A missing position cannot be claimed by
                    an anonymous account. Role and program assignment are
                    checked again when committed.
                </p>
                <label class="inline-flex items-center gap-2 text-sm"
                    ><input
                        type="checkbox"
                        bind:checked={batchMode}
                        onchange={() => {
                            review = null;
                            batchReview = null;
                        }}
                    />Prepare several identified recipients in one reviewed
                    batch</label
                >
                {#if !batchMode}
                    {#if mode !== 'prepare_dean' && eligible_existing_heads.length > 0}<label
                            class="block max-w-md space-y-1 text-sm"
                            >Preparation method<select
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                bind:value={mode}
                                onchange={() => (review = null)}
                                ><option value="create"
                                    >Prepare a new identified recipient</option
                                ><option value="assign_existing"
                                    >Assign an existing Program Head to one
                                    missing program</option
                                ></select
                            ></label
                        >{/if}
                    {#if mode === 'create' || mode === 'prepare_dean'}
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="space-y-1 text-sm"
                                >Full name<input
                                    class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                    bind:value={name}
                                    autocomplete="name"
                                /></label
                            ><label class="space-y-1 text-sm"
                                >Email (optional)<input
                                    class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                    type="email"
                                    bind:value={email}
                                    autocomplete="email"
                                /></label
                            >{#if mode === 'create'}<label
                                    class="space-y-1 text-sm"
                                    >Office<select
                                        class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                        bind:value={office}
                                    >
                                        {#if can_full_hr}
                                            <option value="program">Academic Programs</option>
                                            <option value="admin">Admin Office</option>
                                            <option value="guidance">Guidance Office</option>
                                            <option value="registrar">Registrar Office</option>
                                        {:else}
                                            <option value={default_office}>{default_office === 'guidance' ? 'Guidance Office' : 'Registrar Office'}</option>
                                        {/if}
                                    </select
                                    ></label
                                ><label class="space-y-1 text-sm"
                                    >Role<select
                                        class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                        bind:value={role}
                                        >{#each roles as item (typeof item === 'string' ? item : item.name)}<option
                                                value={typeof item === 'string'
                                                    ? item
                                                    : item.name}
                                                >{typeof item === 'string'
                                                    ? item.replaceAll('_', ' ')
                                                    : item.display_name}</option
                                            >{/each}</select
                                    ></label
                                >{/if}
                        </div>
                        {#if mode === 'prepare_dean'}<p
                                class="text-muted-foreground text-sm"
                            >
                                Prepared as a Program Head helper for {rows.find(
                                    (row) =>
                                        row.department_id === departmentId &&
                                        row.role === 'dean',
                                )?.label ?? 'this department'}. This does not
                                assign Dean ownership or mark coverage complete.
                                An authorized Admin Office Setup user must
                                assign the person in Departments.{#if can_assign_department_dean}
                                    <Link
                                        href="/setup/departments"
                                        class="text-primary underline"
                                        >Open Departments</Link
                                    >{/if}
                            </p>{/if}
                        {#if mode === 'create' && office === 'program' && role === 'program_head'}<fieldset
                                class="space-y-2"
                            >
                                <legend class="text-sm font-medium"
                                    >Active program assignments</legend
                                >{#each rows.filter((row) => row.role === 'program_head' && row.state === 'missing') as row (row.key)}<label
                                        class="flex items-center gap-2 text-sm"
                                        ><input
                                            type="checkbox"
                                            checked={programCodes.includes(
                                                row.scope_code,
                                            )}
                                            onchange={() => {
                                                programCodes =
                                                    programCodes.includes(
                                                        row.scope_code,
                                                    )
                                                        ? programCodes.filter(
                                                              (code) =>
                                                                  code !==
                                                                  row.scope_code,
                                                          )
                                                        : [
                                                              ...programCodes,
                                                              row.scope_code,
                                                          ];
                                            }}
                                        />{row.scope_code} — {row.label}</label
                                    >{/each}
                            </fieldset>{/if}
                        {#if demo_mode_available}<label
                                class="inline-flex items-center gap-2 text-sm"
                                ><input
                                    type="checkbox"
                                    bind:checked={demoMode}
                                />Generate an explicitly fictional local demo
                                recipient</label
                            >{/if}
                    {/if}
                    {#if mode === 'assign_existing'}
                        <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
                            <label class="space-y-1 text-sm"
                                >Existing Program Head<select
                                    class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                    bind:value={targetUserId}
                                    ><option value=""
                                        >Choose a staff account</option
                                    >{#each eligible_existing_heads as target (target.id)}<option
                                            value={target.id}
                                            >{target.name}{target.program_codes
                                                .length
                                                ? ` · ${target.program_codes.join(', ')}`
                                                : ''}</option
                                        >{/each}</select
                                ></label
                            >
                            <label class="space-y-1 text-sm"
                                >Missing program<select
                                    class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                    bind:value={assignmentProgramCode}
                                    ><option value=""
                                        >Choose a current missing need</option
                                    >{#each rows.filter((row) => row.role === 'program_head' && row.state === 'missing') as row (row.key)}<option
                                            value={row.scope_code}
                                            >{row.scope_code} — {row.label}</option
                                        >{/each}</select
                                ></label
                            >
                        </div>
                        <p class="text-muted-foreground max-w-2xl text-sm">
                            This operation adds one active program to an
                            existing Program Head without changing identity,
                            email, password, or roles. Deans and office leads
                            without assignment authority must escalate to HR.
                        </p>
                    {/if}
                    <div class="flex flex-wrap gap-2">
                        <Button
                            onclick={preview}
                            disabled={busy ||
                                (mode === 'create' || mode === 'prepare_dean'
                                    ? !name.trim()
                                    : !targetUserId || !assignmentProgramCode)}
                            >{busy
                                ? 'Preparing review…'
                                : 'Review recipient'}</Button
                        >{#if review}<Button onclick={commit} disabled={busy}
                                >Commit reviewed preparation</Button
                            >
                            <p class="text-muted-foreground basis-full text-sm">
                                Review expires {new Date(
                                    review.expires_at,
                                ).toLocaleString()}. A changed review must be
                                prepared again.
                            </p>{/if}
                    </div>
                {/if}
                {#if batchMode}
                    <div class="grid max-w-3xl gap-3 sm:grid-cols-3">
                        <label class="space-y-1 text-sm"
                            >Office<select
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                bind:value={batchOffice}
                            >
                                {#if can_full_hr}
                                    <option value="program">Academic Programs</option>
                                    <option value="admin">Admin Office</option>
                                    <option value="guidance">Guidance Office</option>
                                    <option value="registrar">Registrar Office</option>
                                {:else}
                                    <option value={default_office}>{default_office === 'guidance' ? 'Guidance Office' : 'Registrar Office'}</option>
                                {/if}
                            </select
                            ></label
                        >
                        <label class="space-y-1 text-sm"
                            >Assignable role<select
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                bind:value={batchRole}
                                >{#each roles as item (typeof item === 'string' ? item : item.name)}<option
                                        value={typeof item === 'string'
                                            ? item
                                            : item.name}
                                        >{typeof item === 'string'
                                            ? item.replaceAll('_', ' ')
                                            : item.display_name}</option
                                    >{/each}</select
                            ></label
                        >
                        <label class="space-y-1 text-sm"
                            >Recipient count<input
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                type="number"
                                min="1"
                                max="30"
                                bind:value={batchCount}
                            /></label
                        >
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onclick={addBatchRecipientRows}
                            >Add identified recipient rows</Button
                        ><Button
                            variant="outline"
                            onclick={() => {
                                batchRecipients = [];
                                batchReview = null;
                            }}>Clear all recipient rows</Button
                        >
                    </div>
                    <p class="text-muted-foreground text-sm">
                        The count creates editable, uncommitted rows only; it
                        does not save a staffing quota. Every recipient must be
                        identified before commit.
                    </p>
                    {#each batchRecipients as recipient, index (recipient.operation_id)}
                        <div
                            class="border-border grid max-w-5xl gap-3 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <label class="space-y-1 text-sm"
                                >Recipient {index + 1} name<input
                                    class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                    bind:value={recipient.name}
                                /></label
                            >
                            <label class="space-y-1 text-sm"
                                >Email (optional)<input
                                    class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                    type="email"
                                    bind:value={recipient.email}
                                /></label
                            >
                            {#if batchRole === 'program_head'}<fieldset
                                    class="space-y-1"
                                >
                                    <legend class="text-sm"
                                        >Active programs</legend
                                    >{#each rows.filter((row) => row.role === 'program_head' && row.state === 'missing') as row (row.scope_code)}<label
                                            class="flex items-center gap-2 text-sm"
                                            ><input
                                                type="checkbox"
                                                checked={recipient.program_codes.includes(
                                                    row.scope_code,
                                                )}
                                                onchange={() => {
                                                    recipient.program_codes =
                                                        recipient.program_codes.includes(
                                                            row.scope_code,
                                                        )
                                                            ? recipient.program_codes.filter(
                                                                  (code) =>
                                                                      code !==
                                                                      row.scope_code,
                                                              )
                                                            : [
                                                                  ...recipient.program_codes,
                                                                  row.scope_code,
                                                              ];
                                                }}
                                            />{row.scope_code} — {row.label}</label
                                        >{/each}
                                </fieldset>{/if}
                            {#if demo_mode_available}<label
                                    class="inline-flex items-center gap-2 text-sm"
                                    ><input
                                        type="checkbox"
                                        bind:checked={recipient.demo_mode}
                                    />Fictional local demo recipient</label
                                >{/if}
                            <Button
                                variant="outline"
                                onclick={() => {
                                    batchRecipients = batchRecipients.filter(
                                        (_, rowIndex) => rowIndex !== index,
                                    );
                                    batchReview = null;
                                }}>Remove recipient</Button
                            >
                        </div>
                    {/each}
                    <div class="flex flex-wrap gap-2">
                        <Button
                            onclick={previewBatch}
                            disabled={busy ||
                                batchRecipients.length === 0 ||
                                batchRecipients.some(
                                    (recipient) => !recipient.name.trim(),
                                )}
                            >{busy
                                ? 'Preparing batch review…'
                                : 'Review recipients'}</Button
                        >{#if batchReview}<Button
                                onclick={commitBatch}
                                disabled={busy}>Commit reviewed batch</Button
                            >
                            <p class="text-muted-foreground basis-full text-sm">
                                Batch review expires {new Date(
                                    batchReview.expires_at,
                                ).toLocaleString()}.
                            </p>{/if}
                    </div>
                    {#if batchResults.length}<ul
                            aria-live="polite"
                            class="space-y-1 text-sm"
                        >
                            {#each batchResults as result (result.operation_id)}<li
                                >
                                    {result.operation_id}: {result.outcome} — {result.message}
                                </li>{/each}
                        </ul>{/if}
                {/if}
            </section>{/if}

        {#if selected.length > 0}<section
                class="border-border space-y-4 border-t pt-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">
                        Selected accounts ({selected.length})
                    </h2>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            onclick={() => (showReset = !showReset)}
                            >Full reset selected</Button
                        ><Button
                            variant="outline"
                            onclick={() => (selected = [])}
                            >Clear selection</Button
                        >
                    </div>
                </div>
                {#if showReset}<div
                        class="border-destructive/30 max-w-2xl space-y-3 rounded-lg border p-4"
                    >
                        <p class="text-sm">
                            Full reset revokes existing login methods and
                            returns each eligible account to Awaiting setup.
                            This does not reactivate inactive employment.
                            Protected, self, out-of-scope, stale, and
                            last-HR-route targets will not be reset.
                        </p>
                        <label class="block space-y-1 text-sm"
                            >Reason<input
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                bind:value={resetReason}
                            /></label
                        ><label class="block space-y-1 text-sm"
                            >Your current password<input
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                type="password"
                                autocomplete="current-password"
                                bind:value={currentPassword}
                            /></label
                        ><label class="block space-y-1 text-sm"
                            >Authenticator code<input
                                class="border-input bg-background block w-full rounded-md border px-3 py-2"
                                inputmode="numeric"
                                autocomplete="one-time-code"
                                bind:value={totpCode}
                            /></label
                        ><Button
                            variant="destructive"
                            disabled={busy ||
                                resetReason.trim().length < 10 ||
                                !currentPassword ||
                                !totpCode}
                            onclick={resetSelected}
                            >{busy
                                ? 'Resetting…'
                                : 'Confirm full reset'}</Button
                        >
                    </div>{/if}
                <BatchVouchers
                    targets={voucherTargets}
                    canIssueNonExpiring={can_full_hr}
                />
            </section>{/if}
        {#if resetResults.length}<section aria-live="polite">
                <h2 class="text-lg font-semibold">Reset outcomes</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    {#each resetResults as result (result.user_id ?? result.operation_id)}<li
                        >
                            Account {result.user_id}: {result.outcome} — {result.message}
                        </li>{/each}
                </ul>
            </section>{/if}
    </main>
</StaffLayout>
