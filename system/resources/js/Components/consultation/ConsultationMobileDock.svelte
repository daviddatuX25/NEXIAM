<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowLeft, Search, ClipboardPen } from 'lucide-svelte';

    /**
     * @typedef {Object} Props
     * @property {'index' | 'detail'} [mode]
     * @property {() => void} [onSearch]
     * @property {string} [listHref]
     * @property {() => void} [onRecord]
     * @property {boolean} [recordDisabled]
     */

    let {
        mode = 'index',
        onSearch = () => {},
        listHref = '/consultation',
        onRecord = () => {},
        recordDisabled = false,
    } = $props();
</script>

<!-- Attached full-width bottom navigation bar (GCash / payment-app mobile layout) -->
<nav
    aria-label="Release of Results mobile actions"
    class="fixed inset-x-0 bottom-0 z-40 w-full border-t border-border/80 bg-background/95 backdrop-blur-md pb-[env(safe-area-inset-bottom)] lg:hidden md:pl-56"
>
    {#if mode === 'detail'}
        <div class="relative mx-auto grid h-16 max-w-md grid-cols-3 items-center px-4">
            <!-- Left Action: Return to Consultation List -->
            <div class="flex justify-start">
                <Link
                    href={listHref}
                    class="flex min-h-12 min-w-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-muted-foreground transition-colors hover:text-foreground active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <ArrowLeft class="size-5" aria-hidden="true" />
                    <span>List</span>
                </Link>
            </div>

            <!-- Center Action: Elevated Search Circle -->
            <div class="flex flex-col items-center justify-center">
                <button
                    type="button"
                    onclick={onSearch}
                    aria-label="Search applicants"
                    class="-mt-6 flex size-14 items-center justify-center rounded-full border-4 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <Search class="size-6" aria-hidden="true" />
                </button>
                <span class="mt-0.5 text-[11px] font-medium text-muted-foreground">Search</span>
            </div>

            <!-- Right Action: Record Consultation Modal -->
            <div class="flex justify-end">
                <button
                    type="button"
                    onclick={onRecord}
                    disabled={recordDisabled}
                    aria-label="Record Academic Advising"
                    title={recordDisabled ? 'Finalize the result before recording Academic Advising' : 'Record Academic Advising'}
                    class="flex min-h-12 min-w-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-foreground transition-colors hover:text-primary active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-40"
                >
                    <ClipboardPen class="size-5" aria-hidden="true" />
                    <span>Record</span>
                </button>
            </div>
        </div>
    {:else}
        <!-- Index Mode: Single Centered Raised Search Control -->
        <div class="relative mx-auto flex h-16 max-w-md items-center justify-center px-4">
            <div class="flex flex-col items-center justify-center">
                <button
                    type="button"
                    onclick={onSearch}
                    aria-label="Search applicants"
                    class="-mt-6 flex size-14 items-center justify-center rounded-full border-4 border-background bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <Search class="size-6" aria-hidden="true" />
                </button>
                <span class="mt-0.5 text-[11px] font-medium text-muted-foreground">Search</span>
            </div>
        </div>
    {/if}
</nav>

<style>
    @media (width < 64rem) {
        :global(body:has(nav[aria-label='Release of Results mobile actions'] details[data-xiam-assist-root])) {
            bottom: calc(5rem + env(safe-area-inset-bottom));
        }
    }
</style>
