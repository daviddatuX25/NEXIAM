<script>
    /**
     * P-REG-01 — Registrar Create / Walk-in Application.
     * Aligned with public /apply form inputs (PSGC address selector, school/strand
     * reference suggestions, MM-DD-YYYY birthdate, draft persistence, conflict modal)
     * plus Application Stage & Initial Status with Immediate Walk-in document completion.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import IntakeCorrectionFields from '@/Pages/Admission/IntakeCorrectionFields.svelte';
    import ExistingApplicationConflictModal from '@/Components/Admission/ExistingApplicationConflictModal.svelte';
    import { usePersistentForm } from '@/lib/persistent-form.svelte';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import {
        UserPlus,
        ArrowLeft,
        CheckCircle2,
        FileCheck2,
        FileText,
        Folder,
    } from 'lucide-svelte';

    let {
        page_state = 'ready',
        programs = [],
        requirements = [],
        can_verify_documents = true,
        application_conflict = null,
        cycle = null,
        intake_suggestions = { schools: [], shs: [], programs: [] },
    } = $props();

    let conflictModalOpen = $state(false);
    $effect(() => {
        if (application_conflict !== null) {
            conflictModalOpen = true;
        }
    });

    let birthdateFormatError = $state('');

    const persistentForm = usePersistentForm({
        formId: 'admission_create',
        getContext: () => ({
            cycle_id: cycle?.id ?? '',
        }),
        initialData: {
            first_name: '',
            middle_name: '',
            last_name: '',
            suffix: '',
            identity_first_name: '',
            identity_middle_name: '',
            identity_last_name: '',
            identity_suffix: '',
            birthdate: '',
            identity_birthdate: '',
            sex: '',
            identity_sex: '',
            applicant_type: 'new',
            last_school_enrolled: '',
            strand: '',
            contact_email: '',
            identity_email: '',
            contact_phone: '',
            address_line: '',
            city: '',
            province: '',
            zip_code: '',
            program_preference_1_code: '',
            program_preference_2_code: '',
            program_preference_3_code: '',
            status: 'accepted',
            complete_documents: true,
            verified_requirement_ids: [],
            correction_notes: '',
        },
    });
    const { form } = persistentForm;

    const targetTrack = $derived(
        form.applicant_type === 'transferee' ? 'transferee' : 'freshman',
    );
    const applicableRequirements = $derived(
        (requirements ?? []).filter(
            (req) =>
                req.applicant_track === 'all' ||
                req.applicant_track === targetTrack,
        ),
    );

    // Prune any selected requirement IDs that do not belong to the active track
    $effect(() => {
        const validIds = new Set(applicableRequirements.map((r) => Number(r.id)));
        const current = Array.isArray(form.verified_requirement_ids)
            ? form.verified_requirement_ids
            : [];
        const filtered = current.filter((id) => validIds.has(Number(id)));
        if (filtered.length !== current.length) {
            form.verified_requirement_ids = filtered;
        }
    });

    function toggleRequirement(requirementId, checked) {
        const id = Number(requirementId);
        const current = Array.isArray(form.verified_requirement_ids)
            ? form.verified_requirement_ids.map(Number)
            : [];
        if (checked) {
            if (!current.includes(id)) {
                form.verified_requirement_ids = [...current, id];
            }
        } else {
            form.verified_requirement_ids = current.filter((item) => item !== id);
        }
    }

    function selectAllRequirements() {
        form.verified_requirement_ids = applicableRequirements.map((r) =>
            Number(r.id),
        );
    }

    function clearSelectedRequirements() {
        form.verified_requirement_ids = [];
    }

    // Synchronize identity fields with top-level StoreAdminAdmissionRequest fields
    $effect(() => {
        form.first_name = form.identity_first_name || '';
        form.last_name = form.identity_last_name || '';
        form.middle_name = form.identity_middle_name || '';
        form.suffix = form.identity_suffix || '';
        form.birthdate = form.identity_birthdate || '';
        form.sex = form.identity_sex || '';
    });

    function submit(event) {
        event.preventDefault();

        form.first_name = form.identity_first_name || form.first_name;
        form.last_name = form.identity_last_name || form.last_name;
        form.middle_name = form.identity_middle_name || form.middle_name;
        form.suffix = form.identity_suffix || form.suffix;
        form.birthdate = form.identity_birthdate || form.birthdate;
        form.sex = form.identity_sex || form.sex;
        form.contact_email = form.contact_email || form.identity_email;

        if (!form.birthdate) {
            birthdateFormatError = 'Enter a valid birthdate as MM-DD-YYYY.';
            return;
        }

        birthdateFormatError = '';
        form.post('/admissions');
    }

    const breadcrumbs = [
        { label: 'Admissions', href: '/admissions' },
        { label: 'New application' },
    ];
</script>

<svelte:head>
    <title>New application · Admissions · NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-4xl space-y-6 pb-16">
        <PageHeaderBar
            title="New Application"
            description="Register an in-person walk-in applicant directly into the active academic cycle."
        >
            {#snippet actions()}
                <Button variant="outline" href="/admissions" class="gap-2">
                    <ArrowLeft class="size-4" />
                    Back to list
                </Button>
            {/snippet}
        </PageHeaderBar>

        {#if cycle}
            <div class="rounded-lg border border-primary/20 bg-primary/5 p-4 text-sm text-foreground flex items-center justify-between">
                <div>
                    <span class="font-semibold text-primary">Active Academic Cycle:</span>
                    <span class="ml-1.5">{cycle.name} ({cycle.school_year} {cycle.semester})</span>
                </div>
                <span class="text-xs text-muted-foreground uppercase tracking-wider font-mono font-medium">Cycle {cycle.code}</span>
            </div>
        {/if}

        {#if application_conflict}
            <div
                class="border-destructive/40 bg-destructive/5 text-destructive rounded-md border p-3 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
            >
                <span>
                    {form.errors.first_name ??
                        'An application for this person already exists for the current admission period.'}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-destructive/30 text-destructive hover:bg-destructive/10 shrink-0 w-fit"
                    onclick={() => (conflictModalOpen = true)}
                >
                    View Details
                </Button>
            </div>
        {/if}

        <form onsubmit={submit} class="space-y-6" novalidate>
            {#if persistentForm.isRestored}
                <div class="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-4 py-2.5 text-xs text-primary">
                    <span>Draft restored from your recent session in this browser tab.</span>
                    <button
                        type="button"
                        onclick={() => {
                            persistentForm.clearSavedDraft();
                            form.reset();
                            birthdateFormatError = '';
                        }}
                        class="font-medium underline hover:text-primary/80"
                    >
                        Start over
                    </button>
                </div>
            {/if}

            <Card.Root>
                <Card.Header>
                    <Card.Title class="text-lg font-semibold flex items-center gap-2">
                        <UserPlus class="size-5 text-primary" />
                        Application Details
                    </Card.Title>
                    <Card.Description>
                        Enter applicant identity, academic background, contact info, and program preferences.
                    </Card.Description>
                </Card.Header>
                <Card.Content>
                    <IntakeCorrectionFields
                        {form}
                        {programs}
                        intake_suggestions={intake_suggestions}
                        identityEditable={true}
                        birthdateEditable={true}
                        allowManualAddress={false}
                        showInternalNotes={true}
                        idPrefix="create-app"
                        bind:birthdateFormatError
                    />
                </Card.Content>
            </Card.Root>

            <Card.Root>
                <Card.Header>
                    <Card.Title class="text-base font-semibold">Application Stage & Initial Status</Card.Title>
                    <Card.Description>
                        Determine whether this walk-in application enters the standard review queue or is accepted immediately.
                    </Card.Description>
                </Card.Header>
                <Card.Content class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors {form.status === 'pending' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'}"
                        >
                            <input
                                type="radio"
                                name="status"
                                value="pending"
                                bind:group={form.status}
                                class="mt-1 text-primary focus:ring-primary"
                            />
                            <div class="space-y-1">
                                <span class="font-medium text-foreground">Pending (Review Queue)</span>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Enters the admissions queue for standard credential review, document verification, and scheduling.
                                </p>
                            </div>
                        </label>

                        <label
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition-colors {form.status === 'accepted' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/50'}"
                        >
                            <input
                                type="radio"
                                name="status"
                                value="accepted"
                                bind:group={form.status}
                                class="mt-1 text-primary focus:ring-primary"
                            />
                            <div class="space-y-1">
                                <span class="font-medium text-foreground">Immediate Walk-in Acceptance</span>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Pre-verified credentials. Qualifies directly for exam slot booking and direct assessment.
                                </p>
                            </div>
                        </label>
                    </div>

                    {#if form.status === 'accepted' && can_verify_documents}
                        <div
                            class="rounded-xl border border-primary/25 bg-primary/5 p-4 space-y-4"
                            data-testid="walkin-document-verification"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <FileCheck2 class="size-4 text-primary shrink-0" />
                                        <label
                                            for="complete-documents-toggle"
                                            class="text-sm font-semibold text-foreground cursor-pointer"
                                        >
                                            Complete documents
                                        </label>
                                    </div>
                                    <p class="text-xs text-muted-foreground leading-relaxed">
                                        Mark all document requirements for this applicant classification as verified upon creation. Turn off to select individual requirements.
                                    </p>
                                </div>
                                <button
                                    id="complete-documents-toggle"
                                    type="button"
                                    role="switch"
                                    aria-checked={form.complete_documents}
                                    aria-label="Complete documents"
                                    onclick={() =>
                                        (form.complete_documents =
                                            !form.complete_documents)}
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {form.complete_documents
                                        ? 'bg-primary'
                                        : 'bg-muted-foreground/30'}"
                                >
                                    <span
                                        class="pointer-events-none inline-block size-5 transform rounded-full bg-background shadow-sm transition-transform {form.complete_documents
                                            ? 'translate-x-5'
                                            : 'translate-x-0'}"
                                    ></span>
                                </button>
                            </div>

                            {#if form.complete_documents}
                                <div class="rounded-lg border border-primary/20 bg-background/80 px-3.5 py-2.5 text-xs text-muted-foreground flex items-center justify-between gap-2">
                                    <span>
                                        All <strong class="text-foreground">{applicableRequirements.length}</strong> active document requirements for
                                        <strong class="text-foreground">{form.applicant_type === 'transferee' ? 'Transferee' : 'New (Freshman)'}</strong>
                                        will be marked as verified.
                                    </span>
                                    <span class="shrink-0 rounded-full bg-emerald-500/10 border border-emerald-500/25 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                                        All verified
                                    </span>
                                </div>
                            {:else}
                                <div
                                    class="space-y-3 rounded-lg border border-border bg-background p-3.5"
                                    data-testid="walkin-individual-requirements"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <span class="text-xs font-medium text-foreground">
                                            Select verified document requirements ({form.verified_requirement_ids.length} of {applicableRequirements.length})
                                        </span>
                                        {#if applicableRequirements.length > 0}
                                            <div class="flex items-center gap-2 text-xs">
                                                <button
                                                    type="button"
                                                    onclick={selectAllRequirements}
                                                    class="text-primary font-medium hover:underline"
                                                >
                                                    Select all
                                                </button>
                                                <span class="text-muted-foreground">·</span>
                                                <button
                                                    type="button"
                                                    onclick={clearSelectedRequirements}
                                                    class="text-muted-foreground hover:text-foreground hover:underline"
                                                >
                                                    Clear
                                                </button>
                                            </div>
                                        {/if}
                                    </div>

                                    {#if applicableRequirements.length === 0}
                                        <p class="text-xs text-muted-foreground py-2">
                                            No active document requirements configured for this applicant classification.
                                        </p>
                                    {:else}
                                        <div class="grid gap-2">
                                            {#each applicableRequirements as req (req.id)}
                                                {@const isChecked = form.verified_requirement_ids.includes(Number(req.id))}
                                                <label
                                                    class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-xs sm:text-sm transition-colors {isChecked
                                                        ? 'border-primary/40 bg-primary/5'
                                                        : 'border-border/70 hover:bg-muted/40'}"
                                                >
                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                        <input
                                                            type="checkbox"
                                                            checked={isChecked}
                                                            onchange={(e) =>
                                                                toggleRequirement(
                                                                    req.id,
                                                                    e.currentTarget
                                                                        .checked,
                                                                )}
                                                            class="size-4 rounded border-border accent-primary shrink-0"
                                                        />
                                                        {#if req.item_kind === 'container'}
                                                            <Folder class="size-4 shrink-0 text-amber-500" />
                                                        {:else}
                                                            <FileText class="size-4 shrink-0 text-primary" />
                                                        {/if}
                                                        <span class="font-medium text-foreground truncate">
                                                            {req.label}
                                                        </span>
                                                    </div>
                                                    <span
                                                        class="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                                    >
                                                        {req.is_mandatory
                                                            ? 'Required'
                                                            : 'Optional'}
                                                    </span>
                                                </label>
                                            {/each}
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/if}

                    {#if form.errors.status}
                        <p class="text-sm text-destructive">{form.errors.status}</p>
                    {/if}
                </Card.Content>
            </Card.Root>

            <div class="flex items-center justify-end gap-3 pt-2">
                <Button variant="outline" href="/admissions">Cancel</Button>
                <Button type="submit" class="min-w-36 gap-2" disabled={form.processing}>
                    <CheckCircle2 class="size-4" />
                    {form.processing ? 'Creating…' : 'Create application'}
                </Button>
            </div>
        </form>
    </div>

    <ExistingApplicationConflictModal
        bind:open={conflictModalOpen}
        conflict={application_conflict}
        mode="staff"
        onClose={() => (conflictModalOpen = false)}
    />
</StaffLayout>
