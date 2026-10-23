<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { filterFaqs, type PublicFaqCategory, type PublicFaqItem } from '@/lib/public-faq';

    let { faqs = [], categories = [] }: { faqs?: PublicFaqItem[]; categories?: PublicFaqCategory[] } = $props();
    let search = $state('');
    let selectedCategory = $state('all');
    let expandedFaqs = $state<Record<string, boolean>>({});

    const categoryOptions = $derived([
        { id: 'all', label: 'All topics' },
        ...categories.filter((category) => category.id !== 'all'),
    ]);

    const filteredFaqs = $derived(filterFaqs(faqs, search, selectedCategory));

    function toggleFaq(id: string): void {
        expandedFaqs[id] = !expandedFaqs[id];
    }
</script>

<section class="border-border/80 bg-card min-w-0 max-w-full overflow-x-hidden space-y-4 rounded-xl border p-4 shadow-xs sm:p-5" aria-labelledby="applicant-faq-heading">
    <div class="flex flex-col gap-1">
        <h2 id="applicant-faq-heading" class="text-foreground text-lg font-semibold">Frequently asked questions</h2>
        <p class="text-muted-foreground text-sm">Find quick answers before opening an inquiry.</p>
    </div>

    <div class="flex min-w-0 flex-col gap-3">
        <label class="sr-only" for="applicant-faq-search">Search frequently asked questions</label>
        <div class="relative min-w-0">
            <input
                id="applicant-faq-search"
                type="search"
                bind:value={search}
                placeholder="Search questions or answers"
                class="border-border/80 bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-xl border px-3 pr-12 text-sm outline-none focus-visible:ring-2"
            />
            {#if search}
                <button type="button" class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute right-1 top-1 inline-flex min-h-[44px] size-[44px] items-center justify-center rounded-lg focus-visible:outline-none focus-visible:ring-2" aria-label="Clear FAQ search" onclick={() => search = ''}>×</button>
            {/if}
        </div>
        <div class="flex flex-wrap gap-2" aria-label="FAQ categories">
            {#each categoryOptions as category (category.id)}
                <button
                    type="button"
                    aria-pressed={selectedCategory === category.id}
                    class="border-border/80 min-h-[44px] rounded-full border px-3 text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {selectedCategory === category.id ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-muted-foreground hover:text-foreground'}"
                    onclick={() => selectedCategory = category.id}
                >{category.label}</button>
            {/each}
        </div>
    </div>

    <p class="text-muted-foreground text-xs" aria-live="polite">{filteredFaqs.length} {filteredFaqs.length === 1 ? 'answer' : 'answers'}</p>

    {#if filteredFaqs.length}
        <div class="space-y-2">
            {#each filteredFaqs as faq (faq.id)}
                <article id={`applicant-faq-${faq.slug}`} class="border-border/80 rounded-xl border px-3 py-2">
                    <button
                        type="button"
                        aria-expanded={Boolean(expandedFaqs[faq.id])}
                        aria-controls={`applicant-faq-answer-${faq.id}`}
                        class="text-foreground flex min-h-[44px] w-full min-w-0 items-center justify-between gap-3 text-left text-sm font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        onclick={() => toggleFaq(faq.id)}
                    >
                        <span class="min-w-0 break-words">{faq.question}</span>
                        <span class="text-muted-foreground shrink-0 text-xs" aria-hidden="true">{expandedFaqs[faq.id] ? '−' : '+'}</span>
                    </button>
                    {#if expandedFaqs[faq.id]}
                        <div id={`applicant-faq-answer-${faq.id}`} class="border-border/50 border-t pb-3 pt-3">
                            <p class="text-muted-foreground whitespace-pre-line break-words text-sm leading-6">{faq.answer}</p>
                            {#if faq.governing_policy}
                                <p class="mt-2 flex flex-wrap items-center gap-1 text-xs">
                                    <span class="text-muted-foreground">Policy:</span>
                                    <Link href={`/policies/${faq.governing_policy.slug}`} class="text-primary min-h-[44px] inline-flex items-center underline underline-offset-4">{faq.governing_policy.title} ({faq.governing_policy.revision_label})</Link>
                                </p>
                            {/if}
                            <Link href={`/faqs#${faq.slug}`} class="text-primary mt-2 inline-flex min-h-[44px] items-center text-sm font-medium underline underline-offset-4">View on full FAQ page</Link>
                        </div>
                    {/if}
                </article>
            {/each}
        </div>
    {:else}
        <p role="status" class="border-border/80 text-muted-foreground rounded-xl border border-dashed p-4 text-sm">
            {faqs.length ? 'No answers match your search or topic.' : 'No published answers are available yet.'}
        </p>
    {/if}

    <Link href="/faqs" class="text-primary inline-flex min-h-[44px] items-center text-sm font-semibold underline underline-offset-4">Browse all frequently asked questions</Link>
</section>
