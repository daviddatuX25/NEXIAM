<script>
    /**
     * Policy & FAQ Draft Authoring — Setup Hub (GF-22, AD-25).
     * Creates an unapproved draft version and initiates cryptographic extraction.
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Textarea } from '@/Components/ui/textarea';
    import { Link, useForm, usePage } from '@inertiajs/svelte';
    import {
        AlertCircle,
        ArrowLeft,
        BookOpen,
        Bot,
        Clock,
        Eye,
        FileText,
        Globe,
        HelpCircle,
        Lock,
        Plus,
        Shield,
        Sparkles,
    } from 'lucide-svelte';

    let {
        page_state = 'ready',
        audiences = [],
        source_types = [],
        current_office = null,
        governingPolicies = [],
        defaults = {},
        message = null,
    } = $props();

    const urlParams =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search)
            : null;
    const initialType =
        urlParams?.get('type') === 'faq' ? 'public_faq' : 'institution_policy';

    const form = useForm({
        source_class: initialType,
        audience:
            initialType === 'public_faq'
                ? 'staff_and_applicant_inquiry'
                : 'staff_only',
        title: '',
        question: '',
        answer: '',
        category: defaults.category ?? 'campus',
        governing_policy_id: '',
        body_text: '',
    });

    const page = usePage();
    const flashStatus = $derived(page.props.flash?.status ?? null);
    const flashError = $derived(page.props.flash?.error ?? null);

    const isFaq = $derived(form.source_class === 'public_faq');
    const isAdminOffice = $derived(current_office === 'admin');
    const owningOffice = $derived(officeLabel(current_office));
    const canSubmit = $derived(
        Boolean(current_office) && form.title.trim() !== '',
    );

    function officeLabel(office) {
        if (!office) return 'No owning office assigned';
        if (office === 'admin') return 'Central Admin';
        if (office === 'registrar') return 'Registrar';
        if (office === 'guidance') return 'Guidance';
        return String(office);
    }

    function selectType(type) {
        form.source_class = type;
        if (type === 'public_faq') {
            form.audience = 'staff_and_applicant_inquiry';
        } else {
            form.audience = 'staff_only';
        }
    }

    function submit(event) {
        event.preventDefault();
        if (!canSubmit) return;
        form.transform((data) => {
            const shared = {
                source_class: data.source_class,
                audience: data.audience,
                title: data.title,
            };

            return data.source_class === 'public_faq'
                ? {
                      ...shared,
                      question: data.question,
                      answer: data.answer,
                      category: data.category,
                      ...(data.governing_policy_id
                          ? {
                                governing_policy_id:
                                    data.governing_policy_id,
                            }
                          : {}),
                  }
                : { ...shared, body_text: data.body_text };
        });
        form.post('/knowledge');
    }

    const breadcrumbs = [
        { label: 'Communications & Support' },
        { label: 'FAQs', href: '/knowledge' },
        { label: isFaq ? 'New FAQ' : 'New Procedure' },
    ];
</script>

<svelte:head>
    <title>{isFaq ? 'New FAQ' : 'New Procedure'} · NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-7xl min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">
        <!-- Header with Back Button -->
        <div class="border-border/70 flex items-center gap-3 border-b pb-4">
            <Link href="/knowledge">
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 gap-1.5 text-xs font-medium"
                >
                    <ArrowLeft class="size-3.5" />
                    FAQs
                </Button>
            </Link>
            <div class="bg-border/80 h-4 w-px"></div>
            <span class="text-muted-foreground text-xs"
                >Draft authoring workspace</span
            >
        </div>

        <PageHeaderBar
            title={isFaq ? 'Author Official FAQ' : 'Author Internal Procedure'}
            description={isFaq
                ? 'Draft an explanatory question and verified answer for review. Intended audience does not enable applicant AI retrieval.'
                : 'Draft a genuine internal staff procedure. Publish canonical public policies through Communications → Public policies.'}
        />

        {#if flashStatus}
            <p
                role="status"
                class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-800 dark:text-emerald-300"
            >
                {flashStatus}
            </p>
        {/if}
        {#if flashError}
            <p
                role="alert"
                class="border-destructive/40 bg-destructive/10 text-destructive rounded-xl border px-4 py-3 text-sm"
            >
                {flashError}
            </p>
        {/if}
        {#if page_state === 'denied'}
            <div
                role="alert"
                class="border-destructive/30 bg-destructive/5 space-y-3 rounded-xl border p-8 text-center"
            >
                <Lock class="text-destructive/80 mx-auto size-9" />
                <h3 class="text-foreground text-base font-semibold">
                    Creation Denied
                </h3>
                <p class="text-muted-foreground mx-auto max-w-md text-sm">
                    You do not have permission to author knowledge sources.
                    Contact your office administrator for access.
                </p>
            </div>
        {:else if ['failed', 'error', 'conflict'].includes(page_state)}
            <div
                role="alert"
                class="border-destructive/40 bg-destructive/5 text-destructive rounded-xl border px-4 py-3 text-sm"
            >
                {message ?? 'The draft authoring workspace is unavailable.'}
            </div>
        {:else}
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <!-- Main Authoring Form (7 cols) -->
                <form
                    onsubmit={submit}
                    class="border-border/80 bg-card space-y-6 rounded-2xl border p-5 shadow-xs sm:p-6 lg:col-span-7"
                >
                    {#if Object.keys(form.errors ?? {}).length > 0}
                        <div
                            role="alert"
                            class="border-destructive/40 bg-destructive/10 text-destructive flex items-center gap-2 rounded-xl border px-4 py-3 text-xs sm:text-sm"
                        >
                            <AlertCircle class="size-4 shrink-0" />
                            <span
                                >Please review the highlighted validation errors
                                below.</span
                            >
                        </div>
                    {/if}

                    {#if !current_office}
                        <div
                            role="alert"
                            class="flex items-center gap-2 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-xs text-amber-800 sm:text-sm dark:text-amber-200"
                        >
                            <AlertCircle class="size-4 shrink-0" />
                            <span
                                >No owning office is assigned. Draft submission
                                is blocked until your office context is
                                resolved.</span
                            >
                        </div>
                    {/if}

                    <!-- Tactile Item Type Switcher -->
                    <div class="space-y-2">
                        <Label
                            class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                            >Source Classification</Label
                        >
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <button
                                type="button"
                                class={`flex flex-col items-start gap-1.5 rounded-xl border p-4 text-left transition-all ${!isFaq ? 'border-sky-500 bg-sky-500/5 shadow-xs ring-1 ring-sky-500/30' : 'border-border bg-card hover:bg-muted/40'}`}
                                onclick={() => selectType('institution_policy')}
                            >
                                <div
                                    class="flex w-full items-center justify-between"
                                >
                                    <div
                                        class="text-foreground flex items-center gap-2 text-sm font-semibold"
                                    >
                                        <FileText
                                            class="size-4 text-sky-600 dark:text-sky-400"
                                        />
                                        <span>Internal Procedure</span>
                                    </div>
                                    {#if !isFaq}
                                        <span
                                            class="rounded bg-sky-500/20 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 uppercase dark:text-sky-300"
                                            >Selected</span
                                        >
                                    {/if}
                                </div>
                                <p class="text-muted-foreground text-xs">
                                    Internal staff instructions, not public
                                    policies, cutoffs, or quotas.
                                </p>
                            </button>

                            <button
                                type="button"
                                class={`flex flex-col items-start gap-1.5 rounded-xl border p-4 text-left transition-all ${isFaq ? 'border-purple-500 bg-purple-500/5 shadow-xs ring-1 ring-purple-500/30' : 'border-border bg-card hover:bg-muted/40'}`}
                                onclick={() => selectType('public_faq')}
                            >
                                <div
                                    class="flex w-full items-center justify-between"
                                >
                                    <div
                                        class="text-foreground flex items-center gap-2 text-sm font-semibold"
                                    >
                                        <HelpCircle
                                            class="size-4 text-purple-600 dark:text-purple-400"
                                        />
                                        <span>Official FAQ</span>
                                    </div>
                                    {#if isFaq}
                                        <span
                                            class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-bold text-purple-700 uppercase dark:text-purple-300"
                                            >Selected</span
                                        >
                                    {/if}
                                </div>
                                <p class="text-muted-foreground text-xs">
                                    Reviewed explanatory questions and answers;
                                    applicant retrieval is separately gated.
                                </p>
                            </button>
                        </div>
                    </div>

                    <!-- Document Title -->
                    <div class="space-y-1.5">
                        <Label for="title" class="text-xs font-semibold"
                            >{isFaq
                                ? 'FAQ Topic / Working Title'
                                : 'Procedure Title'}</Label
                        >
                        <Input
                            id="title"
                            bind:value={form.title}
                            placeholder={isFaq
                                ? 'e.g. Document Submission FAQ'
                                : 'e.g. Registrar Document Verification Procedure'}
                            required
                            class="min-h-10 text-sm"
                        />
                        {#if form.errors?.title}
                            <p class="text-destructive mt-1 text-xs">
                                {form.errors.title}
                            </p>
                        {/if}
                    </div>
                    {#if isFaq}
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <Label for="category" class="text-xs font-semibold">
                                    {isAdminOffice
                                        ? 'General Institutional FAQ Category'
                                        : 'FAQ Category'}
                                </Label>
                                <select
                                    id="category"
                                    bind:value={form.category}
                                    class="border-input bg-background ring-offset-background focus-visible:ring-ring min-h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                >
                                    <option value="campus">Campus</option>
                                    <option value="admissions">Admissions</option>
                                    <option value="testing">Testing</option>
                                </select>
                                {#if form.errors?.category}
                                    <p class="text-destructive text-xs">
                                        {form.errors.category}
                                    </p>
                                {/if}
                                {#if isAdminOffice}
                                    <p class="text-muted-foreground text-xs">
                                        Central Admin FAQs cover general institutional information.
                                    </p>
                                {/if}
                            </div>
                            {#if Array.isArray(governingPolicies) && governingPolicies.length > 0}
                                <div class="space-y-1.5">
                                    <Label
                                        for="governing_policy_id"
                                        class="text-xs font-semibold"
                                    >
                                        Governing Public Policy
                                        <span class="text-muted-foreground font-normal">(optional)</span>
                                    </Label>
                                    <select
                                        id="governing_policy_id"
                                        bind:value={form.governing_policy_id}
                                        class="border-input bg-background ring-offset-background focus-visible:ring-ring min-h-10 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                                    >
                                        <option value="">No linked policy</option>
                                        {#each governingPolicies as policy (policy.id)}
                                            <option value={policy.id}>
                                                {policy.title}
                                                {#if policy.revision_label}
                                                    · {policy.revision_label}
                                                {/if}
                                            </option>
                                        {/each}
                                    </select>
                                    {#if form.errors?.governing_policy_id}
                                        <p class="text-destructive text-xs">
                                            {form.errors.governing_policy_id}
                                        </p>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    {/if}


                    <!-- Target Audience -->
                    <div class="space-y-2">
                        <Label class="text-xs font-semibold"
                            >Intended Audience (not an AI access grant)</Label
                        >
                        {#if isFaq}
                            <div class="grid grid-cols-1 gap-2.5">
                                <button
                                    type="button"
                                    aria-pressed={form.audience ===
                                        'staff_and_applicant_inquiry'}
                                    class={`flex flex-col justify-center rounded-xl border p-3 text-left transition-all ${form.audience === 'staff_and_applicant_inquiry' ? 'border-primary bg-primary/10 text-primary font-medium shadow-xs' : 'border-border bg-card text-muted-foreground hover:bg-muted/40'}`}
                                    onclick={() =>
                                        (form.audience =
                                            'staff_and_applicant_inquiry')}
                                >
                                    <div
                                        class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                                    >
                                        <Globe
                                            class="size-3.5 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <span>Staff and applicant inquiry</span>
                                    </div>
                                    <span
                                        class="text-muted-foreground mt-0.5 text-[11px]"
                                        >For reviewed explanatory FAQ content</span
                                    >
                                </button>
                            </div>
                        {:else}
                            <div class="grid grid-cols-1 gap-2.5">
                                <button
                                    type="button"
                                    aria-pressed={form.audience ===
                                        'staff_only'}
                                    class={`flex flex-col justify-center rounded-xl border p-3 text-left transition-all ${form.audience === 'staff_only' ? 'border-primary bg-primary/10 text-primary font-medium shadow-xs' : 'border-border bg-card text-muted-foreground hover:bg-muted/40'}`}
                                    onclick={() =>
                                        (form.audience = 'staff_only')}
                                >
                                    <div
                                        class="text-foreground flex items-center gap-1.5 text-xs font-semibold"
                                    >
                                        <Lock
                                            class="text-muted-foreground size-3.5"
                                        />
                                        <span>Internal to office</span>
                                    </div>
                                    <span
                                        class="text-muted-foreground mt-0.5 text-[11px]"
                                        >{owningOffice} staff only</span
                                    >
                                </button>
                            </div>
                            <p
                                class="text-muted-foreground text-xs leading-relaxed"
                            >
                                Internal procedures are visible to authorized
                                staff only. For public institutional policies,
                                use Communications → Public policies.
                            </p>
                        {/if}
                    </div>

                    <!-- Dynamic Content Fields -->
                    {#if isFaq}
                        <div class="space-y-1.5">
                            <Label for="question" class="text-xs font-semibold"
                                >Full Applicant Question</Label
                            >
                            <Textarea
                                id="question"
                                bind:value={form.question}
                                placeholder="e.g. When is the deadline for Senior High Form 137 submission?"
                                rows="3"
                                required
                                class="min-h-[85px] w-full text-sm leading-relaxed"
                            />
                            {#if form.errors?.question}
                                <p class="text-destructive text-xs">
                                    {form.errors.question}
                                </p>
                            {/if}
                        </div>

                        <div class="space-y-1.5">
                            <Label for="answer" class="text-xs font-semibold"
                                >Official Answer Text</Label
                            >
                            <Textarea
                                id="answer"
                                bind:value={form.answer}
                                placeholder="e.g. Form 137 must be submitted to the Registrar window no later than 5:00 PM on Friday, October 15. Late documents require an approved extension request."
                                rows="6"
                                required
                                class="min-h-[140px] w-full text-sm leading-relaxed"
                            />
                            {#if form.errors?.answer}
                                <p class="text-destructive text-xs">
                                    {form.errors.answer}
                                </p>
                            {/if}
                        </div>
                    {:else}
                        <div class="space-y-1.5">
                            <Label for="body_text" class="text-xs font-semibold"
                                >Internal Procedure Content</Label
                            >
                            <Textarea
                                id="body_text"
                                bind:value={form.body_text}
                                placeholder="Enter internal procedure or official FAQ text. Do not include individual applicant personal data."
                                rows="10"
                                required
                                class="min-h-[200px] w-full font-mono text-xs leading-relaxed sm:text-sm"
                            />
                            {#if form.errors?.body_text}
                                <p class="text-destructive text-xs">
                                    {form.errors.body_text}
                                </p>
                            {/if}
                        </div>
                    {/if}

                    <!-- Form Actions -->
                    <div
                        class="border-border/60 flex items-center gap-3 border-t pt-3"
                    >
                        <Button
                            type="submit"
                            class="min-h-10 gap-2 px-5 font-medium shadow-xs"
                            disabled={form.processing || !canSubmit}
                        >
                            <Plus class="size-4" />
                            {form.processing
                                ? 'Saving draft…'
                                : isFaq
                                  ? 'Save FAQ draft'
                                  : 'Save procedure draft'}
                        </Button>
                        <Link href="/knowledge">
                            <Button
                                type="button"
                                variant="outline"
                                class="min-h-10">Cancel</Button
                            >
                        </Link>
                    </div>
                </form>

                <!-- Right Side: Live RAG & Grounding Preview (5 cols) -->
                <div class="space-y-5 lg:col-span-5">
                    <div
                        class="border-border/80 bg-card space-y-4 rounded-2xl border p-5 shadow-xs"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Eye class="text-primary size-4" />
                                <span
                                    class="text-muted-foreground font-mono text-xs font-bold tracking-wider uppercase"
                                    >Live Citation Preview</span
                                >
                            </div>
                            <span
                                class="bg-muted text-muted-foreground rounded px-2 py-0.5 font-mono text-[10px]"
                                >Draft v1</span
                            >
                        </div>

                        <!-- Preview Card -->
                        <div
                            class="border-border/60 bg-muted/20 space-y-3 rounded-xl border p-4"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="bg-background border-border inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-semibold uppercase"
                                >
                                    {#if isFaq}FAQ{:else}Policy{/if}
                                </span>
                                <span
                                    class="text-muted-foreground text-[11px] font-medium"
                                >
                                    {owningOffice}
                                </span>
                            </div>

                            <div class="space-y-1">
                                <h4 class="text-foreground text-sm font-bold">
                                    {#if isFaq}
                                        {form.question ||
                                            form.title ||
                                            'Untitled question...'}
                                    {:else}
                                        {form.title || 'Untitled policy...'}
                                    {/if}
                                </h4>
                                <div class="text-muted-foreground text-[10px]">
                                    Scope: <span
                                        class="text-foreground font-medium"
                                        >{form.audience === 'staff_only'
                                            ? 'Office Internal'
                                            : 'Public & Applicants'}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="border-border/50 text-muted-foreground border-t pt-2 text-xs leading-relaxed"
                            >
                                {#if isFaq}
                                    <p
                                        class="text-foreground/80 mb-1 text-[10px] font-semibold uppercase"
                                    >
                                        Xiam Answer Citation
                                    </p>
                                    <p class="italic">
                                        {form.answer ||
                                            'Enter an official answer on the left to see the citation preview...'}
                                    </p>
                                {:else}
                                    <p
                                        class="text-foreground/80 mb-1 text-[10px] font-semibold uppercase"
                                    >
                                        Policy Scope Excerpt
                                    </p>
                                    <p class="italic">
                                        {form.body_text ||
                                            'Enter policy content on the left to preview canonical extraction text...'}
                                    </p>
                                {/if}
                            </div>
                        </div>

                        <!-- Assistant Grounding Note -->
                        <div
                            class="bg-primary/5 border-primary/20 text-muted-foreground space-y-1.5 rounded-xl border p-3 text-xs"
                        >
                            <div
                                class="text-primary flex items-center gap-1.5 text-[11px] font-semibold"
                            >
                                <Bot class="size-3.5" />
                                <span>Assistant Grounding Pipeline</span>
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                Saving creates an unapproved Draft version. The
                                background queue extracts canonical SHA-256
                                digests and seals review fixtures. An
                                independent supervisor must verify and sign off
                                before this source can be cited.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        {/if}
    </div>
</StaffLayout>
