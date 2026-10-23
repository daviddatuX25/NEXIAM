<script lang="ts">
    import { Link, router, usePage } from '@inertiajs/svelte';
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import { filterFaqs, type PublicFaqItem } from '@/lib/public-faq';

    let {
        institution = {},
        alerts = [],
        faqs = [] as PublicFaqItem[],
        categories = [] as Array<{ id: string; label: string }>,
        selectedCategory = 'all',
        search = '',
    } = $props();

    const page = usePage();
    const isAuthenticatedApplicant = $derived(Boolean(page.props.auth?.user?.is_applicant));
    let activeCategory = $state(selectedCategory || 'all');
    let query = $state(search);
    let openFaqs = $state<Record<string, boolean>>({});
    const categoryOptions = $derived([
        { id: 'all', label: 'All Topics' },
        { id: 'campus', label: 'General / Admin' },
        { id: 'admissions', label: 'Admissions / Reg.' },
        { id: 'testing', label: 'Guidance / Testing' },
    ]);

    const filteredFaqs = $derived(filterFaqs(faqs, query, activeCategory));

    function toggleFaq(id: string): void {
        openFaqs[id] = !openFaqs[id];
    }

    function selectCategory(id: string): void {
        activeCategory = id;
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            if (url.searchParams.has('category') || url.searchParams.has('search') || url.searchParams.has('office')) {
                const params: Record<string, string> = {};
                if (id !== 'all') params.category = id;
                if (query.trim()) params.search = query.trim();
                router.get('/faqs', params, { preserveState: true, replace: true });
            }
        }
    }

    function clearSearch(): void {
        query = '';
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            if (url.searchParams.has('search')) {
                const params: Record<string, string> = {};
                if (activeCategory !== 'all') params.category = activeCategory;
                router.get('/faqs', params, { preserveState: true, replace: true });
            }
        }
    }
    function officeBadge(faq: PublicFaqItem): string {
        const code = faq.office_code.toLocaleLowerCase();
        if (code === 'admin' || code === 'central_admin') return 'Central Admin';
        if (code === 'registrar' || code === 'admissions') return 'Registrar / Admissions';
        if (code === 'guidance') return 'Guidance / Testing';
        return faq.office_label;
    }
</script>

<svelte:head>
    <title>Frequently Asked Questions — {institution.name ?? 'Institution'}</title>
</svelte:head>

<HomeLayout {institution} {alerts}>
    <div class="mx-auto w-full max-w-full max-w-5xl overflow-x-hidden px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
        <header class="max-w-[72ch]">
            <Link
                href="/"
                class="text-primary mb-4 inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4"
            >
                Back to Home
            </Link>
            <h1 class="text-foreground text-4xl font-semibold tracking-tight sm:text-5xl">
                Frequently Asked Questions
            </h1>
            <p class="text-muted-foreground mt-4 text-base leading-7">
                Official, verified guidance for admissions, examinations, and university life.
            </p>
        </header>

        <section class="mt-8 space-y-5" aria-label="Frequently asked questions">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label for="faq-search" class="sr-only">Search frequently asked questions</label>
                <div class="relative min-w-0 flex-1">
                    <input
                        id="faq-search"
                        type="search"
                        bind:value={query}
                        placeholder="Search questions or answers"
                        class="border-border/80 bg-card text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-xl border px-4 pr-12 shadow-xs outline-none focus-visible:ring-2"
                    />
                    {#if query}
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute right-1 top-1 inline-flex min-h-[44px] size-[44px] items-center justify-center rounded-lg focus-visible:outline-none focus-visible:ring-2"
                            aria-label="Clear search"
                            onclick={clearSearch}
                        >×</button>
                    {/if}
                </div>
            </div>

            <div class="flex flex-wrap gap-2" aria-label="FAQ categories">
                {#each categoryOptions as category (category.id)}
                    <button
                        type="button"
                        aria-pressed={activeCategory === category.id}
                        class="border-border/80 min-h-[44px] rounded-full border px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {activeCategory === category.id ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-muted-foreground hover:text-foreground'}"
                        onclick={() => selectCategory(category.id)}
                    >{category.label}</button>
                {/each}
            </div>

            <p class="text-muted-foreground text-sm" aria-live="polite">
                {filteredFaqs.length} {filteredFaqs.length === 1 ? 'answer' : 'answers'}
            </p>

            {#if filteredFaqs.length > 0}
                <div class="space-y-3">
                    {#each filteredFaqs as faq (faq.id)}
                        <article id={faq.slug} class="border-border/80 bg-card rounded-xl border p-4 shadow-xs transition-shadow hover:shadow-sm sm:p-5">
                            <button
                                type="button"
                                aria-expanded={Boolean(openFaqs[faq.id])}
                                aria-controls={`faq-answer-${faq.id}`}
                                onclick={() => toggleFaq(faq.id)}
                                class="flex min-h-[44px] w-full min-w-0 items-start justify-between gap-3 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <span class="text-foreground min-w-0 break-words text-base font-semibold sm:text-lg">{faq.question}</span>
                                <span class="bg-muted text-muted-foreground shrink-0 rounded-full px-2.5 py-1 text-xs font-medium">{officeBadge(faq)}</span>
                            </button>
                            {#if openFaqs[faq.id]}
                                <div id={`faq-answer-${faq.id}`} class="border-border/50 mt-4 border-t pt-4">
                                    <p class="text-muted-foreground whitespace-pre-line break-words text-sm leading-6">{faq.answer}</p>
                                    {#if faq.governing_policy}
                                        <p class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                            <span class="text-muted-foreground">Governing policy:</span>
                                            <Link href={`/policies/${faq.governing_policy.slug}`} class="text-primary min-h-[44px] inline-flex items-center font-medium underline underline-offset-4">
                                                {faq.governing_policy.title} ({faq.governing_policy.revision_label})
                                            </Link>
                                        </p>
                                    {/if}
                                </div>
                            {/if}
                        </article>
                    {/each}
                </div>
            {:else}
                <p role="status" class="border-border/80 bg-card text-muted-foreground rounded-xl border border-dashed p-6 text-sm leading-6">
                    {faqs.length === 0 ? 'No published frequently asked questions are available yet.' : 'No frequently asked questions match your search or topic.'}
                </p>
            {/if}
        </section>

        <aside class="border-border/80 bg-card mt-8 flex flex-col gap-3 rounded-xl border p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between">
            <p class="text-foreground font-medium">Still need help?</p>
            {#if isAuthenticatedApplicant}
                <Link href="/applicant/admission-support?group=offices" class="bg-primary text-primary-foreground inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 text-center text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                    Open an official support inquiry
                </Link>
            {:else}
                <button
                    type="button"
                    data-faqs-open-inquiry-modal
                    onclick={() =>
                        window.dispatchEvent(
                            new CustomEvent('nexiam:open-faq-inquiry-modal', {
                                detail: { view: 'new_inquiry' },
                            }),
                        )}
                    class="bg-primary text-primary-foreground inline-flex min-h-[44px] items-center justify-center rounded-lg px-4 text-center text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    Open an official support inquiry
                </button>
            {/if}
        </aside>
</HomeLayout>
