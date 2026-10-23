<script>
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import { usePersistentForm } from '@/lib/persistent-form.svelte';
    import OpeningAlertSignup from '@/Components/public/OpeningAlertSignup.svelte';
    import {
        forgetRememberedOpeningAlertEmail,
        loadRememberedOpeningAlertEmail,
    } from '@/lib/opening-alert-email';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import ApplicantBirthdateInput from '@/Components/ApplicantBirthdateInput.svelte';
    import ApplicantIntakeSuggestionInput from '@/Components/ApplicantIntakeSuggestionInput.svelte';
    import { Label } from '@/Components/ui/label';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import * as Card from '@/Components/ui/card';
    import * as Dialog from '@/Components/ui/dialog';
    import PhilippineAddressSelector from '@/Components/PhilippineAddressSelector.svelte';
    import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
    import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
    import {
        CheckCircle2,
        Lock,
        ShieldCheck,
    } from 'lucide-svelte';
    import { parseMmDdYyyyBirthdate } from '@/lib/applicant-birthdate';
    import { GWA_SCALES, getGwaScaleConfig, normalizeGwaValue } from '@/lib/gwa-scale.js';

    import ExistingApplicationConflictModal from '@/Components/Admission/ExistingApplicationConflictModal.svelte';
    import ApplicantStrandSelect from '@/Components/Admission/ApplicantStrandSelect.svelte';
    import { buildShsStrandOptions } from '@/lib/shs-strand-options';

    let {
        page_state = 'ready',
        institution = null,
        cycle = null,
        window = null,
        programs = [],
        privacy_policy = null,
        application_conflict = null,
        intake_suggestions = { schools: [], shs: [], programs: [] },
        prefilled_email = '',
        opening_alerts_available = false,
    } = $props();

    let conflictModalOpen = $state(false);
    $effect(() => {
        if (application_conflict !== null) {
            conflictModalOpen = true;
        }
    });

    let birthdateInput = $state('');

    // Inertia Svelte 5 useForm returns a reactive object, not a store — do not use $form.
    const rememberedOpeningAlertEmail = loadRememberedOpeningAlertEmail();
    let hasRememberedEmail = $state(Boolean(rememberedOpeningAlertEmail));
    const initialApplicationEmail = prefilled_email || rememberedOpeningAlertEmail;
    const persistentForm = usePersistentForm({
        formId: 'admission_apply',
        getContext: () => ({
            cycle_id: cycle?.id ?? '',
            window_id: window?.id ?? '',
            policy_digest: privacy_policy?.digest ?? '',
        }),
        initialData: {
            academic_cycle_id: cycle?.id ?? '',
            application_window_id: window?.id ?? '',
            public_policy_id: privacy_policy?.id ?? '',
            public_policy_revision_id: privacy_policy?.revision_id ?? '',
            public_policy_revision_digest: privacy_policy?.digest ?? '',
            first_name: '',
            middle_name: '',
            last_name: '',
            suffix: '',
            email: initialApplicationEmail,
            birthdate: '',
            birthdate_input: '',
            sex: '',
            applicant_type: 'new',
            last_school_enrolled: '',
            strand: '',
            gwa_scale: 'percentage_100',
            gwa: '',
            phone: '',
            address_line: '',
            city: '',
            province: '',
            zip_code: '',
            program_preference_1: '',
            program_preference_2: '',
            program_preference_3: '',
            terms_accepted: false,
        },
        excludeKeys: [
            'terms_accepted',
            'academic_cycle_id',
            'application_window_id',
            'public_policy_id',
            'public_policy_revision_id',
            'public_policy_revision_digest',
        ],
        onRestored: (data) => {
            if (data.birthdate_input) {
                birthdateInput = data.birthdate_input;
            }
        },
    });
    const { form } = persistentForm;
    function forgetRememberedEmail() {
        forgetRememberedOpeningAlertEmail();
        hasRememberedEmail = false;
    }

    $effect(() => {
        form.birthdate_input = birthdateInput;
    });

    let birthdateFormatError = $state('');
    let policyDialogOpen = $state(false);
    let tourOpen = $state(false);
    const policySections = $derived.by(() => {
        const raw = privacy_policy?.content ?? '';
        if (!raw) return [];
        const parts = raw.split(/\n\n(?=\d+\.\s+[A-Z\s&]+)/);
        return parts.map((part) => {
            const match = part.match(/^(\d+\.\s+[A-Z\s&]+)\r?\n+([\s\S]+)$/);
            if (match) {
                return { heading: match[1], body: match[2].trim() };
            }
            return { heading: null, body: part.trim() };
        });
    });
    const academicHistorySuggestions = $derived(
        form.applicant_type === 'transferee'
            ? intake_suggestions.programs
            : intake_suggestions.shs,
    );
    const academicHistoryLabel = $derived(
        form.applicant_type === 'transferee'
            ? 'Previous course or program'
            : 'SHS strand / track',
    );
    const shsStrandOptions = $derived(
        buildShsStrandOptions(intake_suggestions?.shs),
    );
    const suggestionHelperText = 'Optional: choose a suggestion or enter your own.';
    const activeGwaScale = $derived(getGwaScaleConfig(form.gwa_scale));
    const convertedGwaPreview = $derived(
        form.gwa_scale !== 'percentage_100'
            ? normalizeGwaValue(form.gwa, form.gwa_scale)
            : null,
    );


    const tourContext = $derived({
        hasCycle: Boolean(cycle),
        hasWindow: Boolean(window),
        programCount: programs.length,
    });

    const optionsFor2 = $derived(
        programs.filter((p) => p.code !== form.program_preference_1),
    );
    const optionsFor3 = $derived(
        programs.filter(
            (p) =>
                p.code !== form.program_preference_1 &&
                p.code !== form.program_preference_2,
        ),
    );

    $effect(() => {
        const p1 = form.program_preference_1;
        const p2 = form.program_preference_2;
        const p3 = form.program_preference_3;
        if (p2 && p2 === p1) {
            form.program_preference_2 = '';
        }
        if (p3 && (p3 === p1 || p3 === p2)) {
            form.program_preference_3 = '';
        }
    });

    function clearBirthdateError() {
        birthdateFormatError = '';
        form.clearErrors('birthdate');
    }

    function submit(event) {
        event.preventDefault();
        const birthdate = parseMmDdYyyyBirthdate(birthdateInput);

        if (!birthdate) {
            birthdateFormatError = 'Enter a valid birthdate as MM-DD-YYYY.';
            return;
        }

        birthdateFormatError = '';
        form.transform((data) => {
            const { birthdate_input, ...payload } = data;
            return { ...payload, birthdate };
        }).post('/apply');
    }
</script>

<svelte:head>
    <title>Admission application</title>
</svelte:head>

<HomeLayout {institution}>
    <div class="mx-auto max-w-3xl space-y-8 px-4 py-8 sm:px-6 sm:py-10">
        <header
            class="border-border/60 space-y-2 border-b pb-5"
            data-tour="apply-header"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-3xl font-semibold tracking-tight">
                    Admission application
                </h1>
                <div class="flex flex-wrap items-center gap-2">
                    <TourTriggerGlyph
                        tourKey="applicant-apply-guide"
                        label="Need help with your application? Ask Kuya Xiam!"
                        onclick={() => (tourOpen = true)}
                    />
                </div>
            </div>
        </header>

        {#if page_state === 'empty' || !window}
            <Card.Root>
                <Card.Content class="text-muted-foreground pt-6 text-sm">
                    No admission period is currently open. Please contact the
                    Registrar’s office for details.
                </Card.Content>
            </Card.Root>
            <OpeningAlertSignup available={opening_alerts_available} prefilledEmail={prefilled_email} />
        {:else}
            {#if form.errors.identity || form.errors.public_policy_id || form.errors.public_policy_revision_id || form.errors.public_policy_revision_digest || form.errors.application_window_id || form.errors.academic_cycle_id}
                <div
                    class="border-destructive/40 bg-destructive/5 text-destructive rounded-md border p-3 text-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
                >
                    <span>
                        {form.errors.identity ??
                            form.errors.public_policy_id ??
                            form.errors.public_policy_revision_id ??
                            form.errors.public_policy_revision_digest ??
                            form.errors.application_window_id ??
                            form.errors.academic_cycle_id}
                    </span>
                    {#if application_conflict}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="border-destructive/30 text-destructive hover:bg-destructive/10 shrink-0 w-fit"
                            onclick={() => (conflictModalOpen = true)}
                        >
                            View Details
                        </Button>
                    {/if}
                </div>
            {/if}

            <form onsubmit={submit} class="space-y-8" novalidate>
                {#if persistentForm.isRestored}
                    <div class="flex items-center justify-between rounded-lg border border-primary/20 bg-primary/5 px-4 py-2.5 text-xs text-primary">
                        <span>Draft restored from your recent session in this browser tab.</span>
                        <button
                            type="button"
                            onclick={() => {
                                persistentForm.clearSavedDraft();
                                form.reset();
                                birthdateInput = '';
                            }}
                            class="font-medium underline hover:text-primary/80"
                        >
                            Start over
                        </button>
                    </div>
                {/if}

                <section class="space-y-4" data-tour="apply-personal-info">
                    <div class="flex items-center gap-1.5">
                        <h2
                            class="text-foreground text-lg font-semibold tracking-tight"
                        >
                            Personal information
                        </h2>
                        <InfoPopover
                            title="Official PSA records"
                            content="Enter your legal name, birthdate, and demographic information exactly as shown on your PSA birth certificate or official school documents for identity verification."
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="first_name"
                                >First name <span class="text-destructive"
                                    >*</span
                                ></Label
                            >
                            <Input
                                id="first_name"
                                bind:value={form.first_name}
                                required
                                class="min-h-11"
                            />
                            {#if form.errors.first_name}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.first_name}
                                </p>{/if}
                        </div>
                        <div class="space-y-2">
                            <Label for="last_name"
                                >Last name <span class="text-destructive"
                                    >*</span
                                ></Label
                            >
                            <Input
                                id="last_name"
                                bind:value={form.last_name}
                                required
                                class="min-h-11"
                            />
                            {#if form.errors.last_name}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.last_name}
                                </p>{/if}
                        </div>
                        <div class="space-y-2">
                            <Label for="middle_name"
                                >Middle name <span
                                    class="text-muted-foreground font-normal"
                                    >(optional)</span
                                ></Label
                            >
                            <Input
                                id="middle_name"
                                bind:value={form.middle_name}
                                class="min-h-11"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="suffix"
                                >Suffix <span
                                    class="text-muted-foreground font-normal"
                                    >(optional)</span
                                ></Label
                            >
                            <Input
                                id="suffix"
                                bind:value={form.suffix}
                                class="min-h-11"
                                placeholder="Jr., III"
                            />
                        </div>
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <Label for="email">Email <span class="text-destructive">*</span></Label>
                                {#if hasRememberedEmail}
                                    <button type="button" class="text-muted-foreground hover:text-foreground min-h-8 text-xs underline underline-offset-4" onclick={forgetRememberedEmail}>
                                        Forget remembered email
                                    </button>
                                {/if}
                            </div>
                            <Input
                                id="email"
                                type="email"
                                bind:value={form.email}
                                required
                                class="min-h-11"
                            />
                            {#if form.errors.email}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.email}
                                </p>{/if}
                        </div>
                        <ApplicantBirthdateInput
                            id="birthdate"
                            bind:value={birthdateInput}
                            error={birthdateFormatError ||
                                form.errors.birthdate}
                            oninput={clearBirthdateError}
                        />
                        <div class="space-y-2">
                            <Label for="sex"
                                >Sex <span class="text-destructive">*</span
                                ></Label
                            >
                            <select
                                id="sex"
                                bind:value={form.sex}
                                required
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="" disabled>Select</option>
                                <option value="female">Female</option>
                                <option value="male">Male</option>
                            </select>
                            {#if form.errors.sex}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.sex}
                                </p>{/if}
                        </div>
                        <div class="space-y-2">
                            <Label for="applicant_type"
                                >Applicant classification <span
                                    class="text-destructive">*</span
                                ></Label
                            >
                            <select
                                id="applicant_type"
                                bind:value={form.applicant_type}
                                required
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="new">New</option>
                                <option value="transferee">Transferee</option>
                            </select>
                        </div>
                    </div>
                </section>

                <section
                    class="space-y-4"
                    data-tour="apply-academic-background"
                >
                    <h2
                        class="text-foreground text-lg font-semibold tracking-tight"
                    >
                        Academic background
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <ApplicantIntakeSuggestionInput
                                id="last_school_enrolled"
                                label="Last school enrolled"
                                bind:value={form.last_school_enrolled}
                                suggestions={intake_suggestions.schools}
                                placeholder="Type a school name or acronym"
                                helperText={suggestionHelperText}
                                error={form.errors.last_school_enrolled}
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            {#if form.applicant_type === 'transferee'}
                                <ApplicantIntakeSuggestionInput
                                    id="strand"
                                    label={academicHistoryLabel}
                                    bind:value={form.strand}
                                    suggestions={academicHistorySuggestions}
                                    placeholder="Type a course or program name"
                                    helperText={suggestionHelperText}
                                    error={form.errors.strand}
                                />
                            {:else}
                                <div class="space-y-1.5">
                                    <Label for="strand">
                                        SHS Strand / Track <span class="text-destructive">*</span>
                                    </Label>
                                    <ApplicantStrandSelect
                                        options={shsStrandOptions}
                                        bind:value={form.strand}
                                        invalid={Boolean(form.errors.strand)}
                                    />
                                    {#if form.errors.strand}
                                        <p class="text-xs text-destructive">{form.errors.strand}</p>
                                    {:else}
                                        <p class="text-xs text-muted-foreground">Standardized DepEd senior high school track code</p>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <div class="flex items-baseline justify-between gap-2">
                                <Label for="gwa">
                                    {form.applicant_type === 'transferee' ? 'Recent TOR GWA' : 'General Weighted Average (GWA)'} <span class="text-destructive">*</span>
                                </Label>
                                {#if convertedGwaPreview !== null}
                                    <span class="font-mono text-xs text-muted-foreground">≈ {convertedGwaPreview.toFixed(2)} / 100</span>
                                {/if}
                            </div>
                            <div class="relative flex items-center">
                                <Input
                                    id="gwa"
                                    type="number"
                                    min={activeGwaScale.min}
                                    max={activeGwaScale.max}
                                    step={activeGwaScale.step}
                                    placeholder={activeGwaScale.placeholder}
                                    bind:value={form.gwa}
                                    required
                                    class="min-h-11 pr-44"
                                />
                                <div class="absolute inset-y-1.5 right-1.5 flex items-center">
                                    <select
                                        id="gwa_scale"
                                        bind:value={form.gwa_scale}
                                        class="h-full rounded-md border border-border/60 bg-muted/40 px-2 text-xs font-medium text-muted-foreground hover:bg-muted/70 hover:text-foreground focus:outline-none focus:ring-1 focus:ring-ring cursor-pointer"
                                        aria-label="Grading scale unit"
                                    >
                                        {#each GWA_SCALES as scale (scale.value)}
                                            <option value={scale.value}>{scale.label}</option>
                                        {/each}
                                    </select>
                                </div>
                            </div>
                            {#if form.errors.gwa}<p class="text-destructive text-sm">{form.errors.gwa}</p>{/if}
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium">Contact information</h2>
                        <span class="text-muted-foreground text-xs"
                            >Optional</span
                        >
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="phone"
                                >Phone <span
                                    class="text-muted-foreground font-normal"
                                    >(optional)</span
                                ></Label
                            >
                            <Input
                                id="phone"
                                type="tel"
                                bind:value={form.phone}
                                class="min-h-11"
                                placeholder="e.g. 09171234567"
                            />
                        </div>
                    </div>
                    <PhilippineAddressSelector
                        bind:address_line={form.address_line}
                        bind:city={form.city}
                        bind:province={form.province}
                        bind:zip_code={form.zip_code}
                        idPrefix="apply-addr"
                        label="Address"
                        allowManual={false}
                        errors={form.errors}
                    />
                </section>

                <section
                    class="space-y-4"
                    data-tour="apply-program-preferences"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-foreground text-lg font-semibold tracking-tight"
                        >
                            Program preferences
                        </h2>
                        <span class="text-muted-foreground text-xs"
                            >Degree choices</span
                        >
                    </div>
                    <div
                        class="border-border/70 bg-muted/20 text-muted-foreground rounded-xl border p-3.5 text-xs leading-relaxed"
                    >
                        Choose up to three programs in order of preference. Your
                        1st preference is your primary admission goal; 2nd and
                        3rd choices serve as alternative degree options if
                        first-choice quotas are reached.
                    </div>
                    <div class="space-y-3">
                        <div class="space-y-2">
                            <Label for="program_preference_1"
                                >First preference <span class="text-destructive"
                                    >*</span
                                ></Label
                            >
                            <select
                                id="program_preference_1"
                                bind:value={form.program_preference_1}
                                required
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="" disabled
                                    >Select a program</option
                                >
                                {#each programs as program (program.code)}
                                    <option value={program.code}
                                        >{program.name} ({program.code})</option
                                    >
                                {/each}
                            </select>
                            {#if form.errors.program_preference_1}
                                <p class="text-destructive text-sm">
                                    {form.errors.program_preference_1}
                                </p>
                            {/if}
                        </div>
                        <div class="space-y-2">
                            <Label for="program_preference_2">
                                Second preference <span
                                    class="text-muted-foreground font-normal"
                                    >(optional)</span
                                >
                            </Label>
                            <select
                                id="program_preference_2"
                                bind:value={form.program_preference_2}
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="">None</option>
                                {#each optionsFor2 as program (program.code)}
                                    <option value={program.code}
                                        >{program.name} ({program.code})</option
                                    >
                                {/each}
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label for="program_preference_3">
                                Third preference <span
                                    class="text-muted-foreground font-normal"
                                    >(optional)</span
                                >
                            </Label>
                            <select
                                id="program_preference_3"
                                bind:value={form.program_preference_3}
                                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="">None</option>
                                {#each optionsFor3 as program (program.code)}
                                    <option value={program.code}
                                        >{program.name} ({program.code})</option
                                    >
                                {/each}
                            </select>
                        </div>
                    </div>
                </section>

                <section
                    class="bg-muted/30 space-y-3 rounded-md border px-4 py-4"
                >
                    <label class="flex items-start gap-3 text-sm leading-6">
                        <input
                            type="checkbox"
                            bind:checked={form.terms_accepted}
                            class="mt-1"
                            required
                        />
                        <span>
                            I certify that the information provided is true and
                            complete, and I have read and agree to the
                            <button
                                type="button"
                                class="text-foreground font-medium underline underline-offset-4"
                                onclick={() => (policyDialogOpen = true)}
                            >
                                Privacy Policy
                            </button>
                            (including personal and academic background data, with physical Form 138 / TOR verification at the Registrar).
                        </span>
                    </label>
                    {#if form.errors.terms_accepted}
                        <p class="text-destructive text-sm">
                            {form.errors.terms_accepted}
                        </p>
                    {/if}
                </section>

                <Button
                    type="submit"
                    class="min-h-11 w-full sm:w-auto"
                    disabled={form.processing}
                >
                    {form.processing ? 'Submitting…' : 'Submit application'}
                </Button>
            </form>
        {/if}
    </div>

    <Dialog.Root bind:open={policyDialogOpen}>
        <Dialog.Content
            class="flex flex-col max-h-[88dvh] sm:max-h-[85vh] w-[calc(100vw-1.5rem)] sm:w-full max-w-none sm:max-w-2xl md:max-w-3xl overflow-hidden rounded-2xl border border-border/80 bg-card p-0 shadow-2xl"
        >
            <Dialog.Header
                class="border-border/60 bg-muted/30 shrink-0 border-b p-4 pr-11 sm:p-5 sm:pr-12"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="bg-primary/10 border-primary/20 text-primary flex size-8 shrink-0 items-center justify-center rounded-lg border"
                    >
                        <ShieldCheck class="size-4.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <Dialog.Title
                            class="text-foreground text-sm font-bold break-words sm:text-lg leading-snug"
                        >
                            {privacy_policy?.title ??
                                'Institutional Privacy Policy'}
                        </Dialog.Title>
                        <Dialog.Description
                            class="text-muted-foreground mt-0.5 text-xs truncate"
                        >
                            Data Privacy Act of 2012 (RA 10173) &bull; Verified
                            Institutional Notice
                        </Dialog.Description>
                    </div>
                </div>
            </Dialog.Header>

            <div
                class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6 text-sm leading-relaxed overscroll-contain"
            >
                <div
                    class="border-primary/20 bg-primary/5 flex items-start gap-3 rounded-xl border p-3.5 sm:p-4"
                >
                    <Lock class="text-primary mt-0.5 size-4 shrink-0" />
                    <div class="text-muted-foreground text-xs leading-relaxed">
                        <p class="text-foreground font-semibold">
                            Compliance & Credential Notice
                        </p>
                        <p class="mt-0.5">
                            Please review this statutory policy carefully before
                            accepting. Personal and academic background
                            information is collected for admissions review.
                            Physical Form 138 / TOR documents are verified at
                            the Registrar.
                        </p>
                    </div>
                </div>

                {#if policySections.length > 0}
                    {#each policySections as sec}
                        {#if sec.heading}
                            <div
                                class="border-border/60 bg-muted/20 space-y-2.5 rounded-xl border p-4"
                            >
                                <h4
                                    class="text-primary flex items-center gap-2 font-mono text-xs font-bold tracking-wider uppercase"
                                >
                                    <span
                                        class="bg-primary size-1.5 shrink-0 rounded-full"
                                    ></span>
                                    {sec.heading}
                                </h4>
                                <div
                                    class="text-foreground/90 space-y-1.5 text-xs leading-relaxed"
                                >
                                    {#each sec.body.split('\n') as line}
                                        {#if line.trim().startsWith('•')}
                                            <div
                                                class="flex items-start gap-2 pl-1 sm:pl-2"
                                            >
                                                <span
                                                    class="bg-primary/70 mt-1.5 size-1.5 shrink-0 rounded-full"
                                                ></span>
                                                <span class="flex-1"
                                                    >{line
                                                        .trim()
                                                        .replace(
                                                            /^•\s*/,
                                                            '',
                                                        )}</span
                                                >
                                            </div>
                                        {:else if line.trim()}
                                            <p>{line.trim()}</p>
                                        {/if}
                                    {/each}
                                </div>
                            </div>
                        {:else}
                            <div
                                class="text-muted-foreground border-border/40 border-b p-3 pb-3 text-xs leading-relaxed"
                            >
                                <p class="whitespace-pre-wrap">{sec.body}</p>
                            </div>
                        {/if}
                    {/each}
                {:else}
                    <div class="text-muted-foreground p-4 text-center text-xs">
                        No privacy policy has been published yet. Contact the
                        Registrar’s office before applying.
                    </div>
                {/if}
            </div>

            <Dialog.Footer
                class="border-border/60 bg-muted/40 m-0 flex w-full shrink-0 flex-col-reverse items-stretch justify-between gap-2 border-t p-3 sm:flex-row sm:items-center sm:gap-3 sm:rounded-b-2xl sm:p-4"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full px-4 sm:min-h-10 sm:w-auto"
                    onclick={() => (policyDialogOpen = false)}
                >
                    Close & Return
                </Button>
                <Button
                    type="button"
                    variant="default"
                    class="min-h-11 w-full gap-2 px-4 sm:min-h-10 sm:w-auto"
                    onclick={() => {
                        form.terms_accepted = true;
                        policyDialogOpen = false;
                    }}
                >
                    <CheckCircle2 class="size-4" />
                    I Agree & Accept Policy
                </Button>
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>

    <ExistingApplicationConflictModal
        bind:open={conflictModalOpen}
        conflict={application_conflict}
        onClose={() => (conflictModalOpen = false)}
    />

    <XiamNpcDialog
        tourKey="applicant-apply-guide"
        context={tourContext}
        bind:open={tourOpen}
    />
</HomeLayout>
