<script>
    import { BookOpen } from 'lucide-svelte';

    let {
        src = null,
        alt = '',
        kind = 'answer_sheet',
        title = '',
        page_count = 0,
        sets = [],
        mode = null,
        builder_json = null,
        orientation = 'portrait',
        overlay = null,
    } = $props();

    let imageFailed = $state(false);
    $effect(() => {
        if (src) imageFailed = false;
    });

    const previewRows = [0, 1, 2, 3, 4];
    const previewBubbles = [0, 1, 2, 3];
    const visibleSets = $derived(sets.slice(0, 4));
    const previewBlocks = $derived(
        Array.isArray(builder_json?.components)
            ? builder_json.components
                  .filter((component) =>
                      [
                          'header',
                          'kpi_grid',
                          'data_table',
                          'score_table',
                          'identity',
                          'recommendation',
                          'signature',
                          'footer',
                          'divider',
                          'text',
                      ].includes(component?.type),
                  )
                  .slice(0, 5)
                  .map((component) => component.type)
            : [],
    );
    const accessibleLabel = $derived(
        alt ||
            (kind === 'answer_keys_only'
                ? `${title} external Booklet; question content is not stored`
                : `${title} document preview`),
    );
</script>

<div
    class={`border-border bg-muted/35 relative shrink-0 overflow-hidden rounded-lg border shadow-[0_8px_20px_-16px_rgba(15,23,42,0.45)] ${orientation === 'landscape' ? 'aspect-[1.414] w-28 sm:w-32' : 'aspect-[0.707] w-24 sm:w-28'}`}
    role="img"
    aria-label={accessibleLabel}
>
    {#if src && !imageFailed}
        <img
            {src}
            {alt}
            class="bg-background h-full w-full object-contain"
            loading="lazy"
            onerror={() => {
                imageFailed = true;
            }}
        />
    {:else}
        <div
            class="bg-background absolute inset-1.5 flex flex-col overflow-hidden rounded-[5px] border p-2 sm:inset-2"
        >
            {#if previewBlocks.length > 0}
                <div class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-hidden">
                    {#each previewBlocks as block, index (`${block}-${index}`)}
                        {#if block === 'header'}
                            <div class="space-y-1">
                                <div class="bg-primary/35 h-2 w-full rounded-xs"></div>
                                <div class="bg-muted-foreground/25 h-1 w-3/4 rounded-full"></div>
                            </div>
                        {:else if block === 'kpi_grid'}
                            <div class="grid grid-cols-3 gap-1">
                                {#each [0, 1, 2] as metric (metric)}
                                    <div class="border-muted-foreground/20 bg-muted/40 rounded-[3px] border p-1">
                                        <div class="bg-muted-foreground/25 h-0.5 w-3/4 rounded-full"></div>
                                        <div class="bg-primary/45 mt-1 h-1.5 rounded-xs"></div>
                                    </div>
                                {/each}
                            </div>
                        {:else if block === 'data_table' || block === 'score_table'}
                            <div class="border-muted-foreground/25 overflow-hidden rounded-[3px] border">
                                <div class="bg-muted-foreground/15 grid grid-cols-3 gap-1 p-1">
                                    {#each [0, 1, 2] as column (column)}
                                        <div class="bg-muted-foreground/35 h-1 rounded-full"></div>
                                    {/each}
                                </div>
                                {#each [0, 1, 2] as row (row)}
                                    <div class="grid grid-cols-3 gap-1 border-t border-muted-foreground/15 px-1 py-1">
                                        {#each [0, 1, 2] as column (column)}
                                            <div class="bg-muted-foreground/20 h-0.5 rounded-full"></div>
                                        {/each}
                                    </div>
                                {/each}
                            </div>
                        {:else if block === 'identity' || block === 'recommendation'}
                            <div class="border-muted-foreground/20 bg-muted/25 rounded-[3px] border p-1.5">
                                <div class="bg-muted-foreground/35 h-1 w-2/5 rounded-full"></div>
                                <div class="bg-muted-foreground/20 mt-1.5 h-1 w-full rounded-full"></div>
                                <div class="bg-muted-foreground/20 mt-1 h-1 w-4/5 rounded-full"></div>
                            </div>
                        {:else if block === 'signature'}
                            <div class="mt-auto flex flex-col items-center px-2 pt-1">
                                <div class="border-muted-foreground/40 w-3/4 border-t"></div>
                                <div class="bg-muted-foreground/25 mt-1 h-0.5 w-2/5 rounded-full"></div>
                            </div>
                        {:else if block === 'footer'}
                            <div class="bg-muted-foreground/20 mt-auto h-0.5 w-3/5 rounded-full"></div>
                        {:else if block === 'divider'}
                            <div class="border-muted-foreground/25 border-t"></div>
                        {:else if block === 'text'}
                            <div class="space-y-1">
                                <div class="bg-muted-foreground/25 h-1 w-full rounded-full"></div>
                                <div class="bg-muted-foreground/25 h-1 w-4/5 rounded-full"></div>
                            </div>
                        {/if}
                    {/each}
                </div>
            {:else if kind === 'answer_sheet'}
                <div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div>
                <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div>
                <div class="mt-3 grid grid-cols-[1fr_auto] gap-x-1.5 gap-y-1.5">
                    {#each previewRows as row (row)}
                        <div class="bg-muted h-1 rounded-full"></div>
                        <div class="flex gap-0.5">
                            {#each previewBubbles as bubble (bubble)}
                                <span
                                    class="border-muted-foreground/45 size-1.5 rounded-full border"
                                ></span>
                            {/each}
                        </div>
                    {/each}
                </div>
                <div class="border-muted-foreground/20 mt-auto border-t pt-1.5">
                    <div class="bg-muted h-1 w-4/5 rounded-full"></div>
                </div>
            {:else if kind === 'stored_questions'}
                <div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div>
                <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div>
                <p
                    class="text-foreground mt-3 line-clamp-2 text-[7px] leading-tight font-semibold uppercase sm:text-[8px]"
                >
                    {title}
                </p>
                <div class="mt-2 space-y-1.5">
                    {#each previewBubbles as row (row)}
                        <div class="space-y-0.5">
                            <div
                                class="bg-muted-foreground/30 h-1 rounded-full"
                            ></div>
                            <div class="bg-muted h-1 w-4/5 rounded-full"></div>
                        </div>
                    {/each}
                </div>
            {:else if kind === 'report'}
                <div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div>
                <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div>
                <div class="mt-3 grid grid-cols-3 gap-1">
                    {#each [0, 1, 2] as metric (metric)}
                        <div class="border-muted-foreground/20 bg-muted/40 rounded-[3px] border p-1">
                            <div class="bg-muted-foreground/25 h-0.5 w-3/4 rounded-full"></div>
                            <div class="bg-primary/45 mt-1 h-1.5 rounded-xs"></div>
                        </div>
                    {/each}
                </div>
                <div class="border-muted-foreground/25 mt-2 overflow-hidden rounded-[3px] border">
                    <div class="bg-muted-foreground/15 grid grid-cols-3 gap-1 p-1">
                        {#each [0, 1, 2] as column (column)}
                            <div class="bg-muted-foreground/35 h-1 rounded-full"></div>
                        {/each}
                    </div>
                    {#each [0, 1, 2] as row (row)}
                        <div class="grid grid-cols-3 gap-1 border-t border-muted-foreground/15 px-1 py-1">
                            {#each [0, 1, 2] as column (column)}
                                <div class="bg-muted-foreground/20 h-0.5 rounded-full"></div>
                            {/each}
                        </div>
                    {/each}
                </div>
                <div class="border-muted-foreground/20 mt-auto flex justify-center border-t pt-1.5">
                    <div class="border-muted-foreground/40 w-2/3 border-t pt-1">
                        <div class="bg-muted-foreground/25 mx-auto h-0.5 w-2/5 rounded-full"></div>
                    </div>
                </div>
            {:else if kind === 'result_sheet'}
                <div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div>
                <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div>
                <div class="space-y-1">
                    <div class="bg-primary/25 h-1.5 w-full rounded-xs"></div>
                    <div class="bg-muted-foreground/30 h-1 w-3/4 rounded-full"></div>
                </div>
                <div class="border-muted-foreground/20 bg-muted/25 mt-2 rounded-[3px] border p-1 space-y-0.5">
                    <div class="bg-muted-foreground/40 h-1 w-1/2 rounded-full"></div>
                    <div class="bg-foreground/50 h-1 w-4/5 rounded-full"></div>
                </div>
                <div class="mt-2 space-y-1">
                    {#each [0, 1, 2] as row (row)}
                        <div class="grid grid-cols-[1fr_auto] items-center gap-1">
                            <div class="bg-muted-foreground/25 h-1 rounded-full"></div>
                            <div class="bg-primary/50 h-1 w-3 rounded-full"></div>
                        </div>
                    {/each}
                </div>
                <div class="border-muted-foreground/20 mt-auto flex items-center justify-between border-t pt-1.5">
                    <div class="bg-emerald-500/40 h-1.5 w-2/5 rounded-xs"></div>
                    <div class="bg-muted-foreground/30 h-0.5 w-1/4 rounded-full"></div>
                </div>
            {:else}
                <div class="bg-foreground/75 h-1.5 w-8 rounded-full"></div>
                <div class="bg-muted-foreground/25 mt-1 h-1 w-11 rounded-full"></div>
                <div class="my-auto text-center">
                    <p
                        class="text-muted-foreground text-[7px] leading-tight font-semibold tracking-wide uppercase sm:text-[8px]"
                    >
                        Answer Key Only
                    </p>
                    <p
                        class="text-muted-foreground/75 mt-1 text-[6px] leading-tight sm:text-[7px]"
                    >
                        Questions external
                    </p>
                </div>
            {/if}
        </div>
    {/if}
    {#if overlay === 'booklet' || ((kind === 'stored_questions' || kind === 'answer_keys_only') && src && !imageFailed)}
        <div
            class="absolute top-1.5 left-1.5 flex items-center gap-1 rounded bg-slate-900/85 px-1.5 py-0.5 text-white shadow-xs backdrop-blur-xs border border-white/20"
            title="Question Booklet (Paired Answer Sheet)"
            aria-label="Question Booklet"
        >
            <BookOpen class="size-3 text-amber-300" />
            <span class="text-[8px] font-semibold tracking-wide uppercase">{kind === 'answer_keys_only' ? 'Booklet · Key' : 'Booklet'}</span>
        </div>
    {/if}

    {#if page_count > 1}
        <span
            class="bg-foreground text-background absolute right-1.5 bottom-1.5 rounded px-1.5 py-0.5 text-[9px] font-semibold"
        >
            +{page_count - 1}
        </span>
    {:else if visibleSets.length > 0}
        <div
            class="absolute right-1.5 bottom-1.5 flex gap-0.5"
            aria-hidden="true"
        >
            {#each visibleSets as set (set.code)}
                <span
                    class="bg-foreground text-background flex size-4 items-center justify-center rounded text-[8px] font-semibold"
                    class:opacity-55={!set.is_complete}>{set.code}</span
                >
            {/each}
        </div>
    {:else if mode}
        <span
            class="bg-foreground text-background absolute right-1.5 bottom-1.5 rounded px-1.5 py-0.5 font-mono text-[8px] font-semibold tracking-wider uppercase"
        >
            {mode}
        </span>
    {/if}
</div>
