<script>
    let {
        rows = 0,
        columns = 0,
        booklet_pattern = 'checkerboard',
        desks = [],
        max_desks = 400,
        title = 'Deterministic desk preview',
        compact = false,
    } = $props();

    function rowLabel(row) {
        let label = '';
        for (let value = row + 1; value > 0; value = Math.floor((value - 1) / 26)) {
            label = String.fromCharCode(65 + ((value - 1) % 26)) + label;
        }
        return label;
    }

    function bookletFor(index, row, column) {
        if (booklet_pattern === 'uniform') return 'A';
        if (booklet_pattern === 'checkerboard') return (row + column) % 2 === 0 ? 'A' : 'B';
        if (booklet_pattern === 'latin_four') return ['A', 'B', 'C', 'D'][(row + column) % 4];
        return index % 2 === 0 ? 'A' : 'B';
    }

    const totalDesks = $derived(Math.max(0, Number(rows || 0) * Number(columns || 0)));
    const previewDesks = $derived.by(() => {
        if (Array.isArray(desks) && desks.length > 0) {
            return desks.slice(0, max_desks).map((desk, index) => ({
                index: desk.index ?? index,
                label: desk.label ?? `${rowLabel(Number(desk.row ?? 0))}${Number(desk.column ?? index) + 1}`,
                booklet: desk.booklet ?? desk.set_code ?? '',
            }));
        }

        const result = [];
        const limit = Math.min(totalDesks, max_desks);
        const columnCount = Math.max(1, Number(columns || 0));
        for (let index = 0; index < limit; index += 1) {
            const row = Math.floor(index / columnCount);
            const column = index % columnCount;
            result.push({
                index,
                label: `${rowLabel(row)}${column + 1}`,
                booklet: bookletFor(index, row, column),
            });
        }
        return result;
    });

    const isLimited = $derived(totalDesks > previewDesks.length);
    const columnCount = $derived(Math.max(1, Number(columns || 0)));
</script>

<section
    class="border-border bg-card rounded-xl border {compact ? 'p-4' : 'p-5'}"
    aria-labelledby="seating-preview-title"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="seating-preview-title" class="font-semibold">{title}</h2>
            <p class="text-muted-foreground mt-1 text-sm">
                {totalDesks} {totalDesks === 1 ? 'desk' : 'desks'} · {rows || 0} rows × {columns || 0} columns
            </p>
        </div>
        {#if totalDesks > 0}
            <span class="text-muted-foreground rounded-md border border-border px-2 py-1 text-xs">
                {booklet_pattern === 'uniform'
                    ? 'Uniform distribution'
                    : booklet_pattern === 'checkerboard'
                      ? 'Checkerboard distribution'
                      : booklet_pattern === 'latin_four'
                        ? 'Latin four distribution'
                        : 'Alternating distribution'}
            </span>
        {/if}
    </div>

    {#if totalDesks === 0}
        <div class="border-border bg-muted/20 text-muted-foreground mt-4 rounded-lg border border-dashed p-6 text-center text-sm">
            Add rows and columns to preview the ordered desk manifest.
        </div>
    {:else}
        <div class="border-border bg-muted/10 mt-4 max-h-[28rem] overflow-auto rounded-lg border p-3" tabindex="0" aria-label="Scrollable seating arrangement preview">
            <div
                class="grid min-w-max gap-2"
                style={`grid-template-columns: repeat(${columnCount}, minmax(3rem, 1fr));`}
                role="grid"
                aria-rowcount={rows}
                aria-colcount={columns}
            >
                {#each previewDesks as desk (desk.index)}
                    <div
                        class="border-border bg-background flex min-h-12 min-w-12 flex-col items-center justify-center rounded-md border text-center shadow-sm"
                        role="gridcell"
                        aria-label={`${desk.label}${desk.booklet ? ` · distribution variant ${desk.booklet}` : ''}`}
                    >
                        <span class="font-mono text-xs font-semibold">{desk.label}</span>
                        {#if desk.booklet}
                            <span class="text-muted-foreground mt-0.5 text-[10px]">{desk.booklet}</span>
                        {/if}
                    </div>
                {/each}
            </div>
        </div>
        {#if isLimited}
            <p class="text-muted-foreground mt-2 text-xs">
                Preview shows the first {previewDesks.length} desks. The saved manifest still includes all {totalDesks} desks in row-major order.
            </p>
        {/if}
    {/if}
</section>
