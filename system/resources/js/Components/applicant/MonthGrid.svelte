<script>
    import { ChevronLeft, ChevronRight } from 'lucide-svelte';
    import { formatDate } from '@/lib/date-utils.js';

    let {
        days = [],
        selectedDate = null,
        onSelectDate = () => {},
        visibleMonth = '',
        onMonthChange = () => {},
    } = $props();

    const weekdays = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
    const monthDate = $derived(
        visibleMonth ? new Date(`${visibleMonth}-01T12:00:00`) : new Date(),
    );
    const monthTitle = $derived(
        monthDate.toLocaleDateString(undefined, {
            month: 'long',
            year: 'numeric',
        }),
    );
    const monthCells = $derived.by(() => {
        const [year, month] = visibleMonth.split('-').map(Number);
        if (!year || !month) return [];
        const firstWeekday =
            (new Date(year, month - 1, 1, 12).getDay() + 6) % 7;
        const daysInMonth = new Date(year, month, 0, 12).getDate();
        const byDate = new Map(days.map((day) => [day.date, day]));
        return [
            ...Array.from({ length: firstWeekday }, (_, index) => ({
                key: `blank-${index}`,
                date: null,
                slots: [],
            })),
            ...Array.from({ length: daysInMonth }, (_, index) => {
                const date = `${visibleMonth}-${String(index + 1).padStart(2, '0')}`;
                return {
                    key: date,
                    date,
                    slots: byDate.get(date)?.slots ?? [],
                };
            }),
        ];
    });

    function changeMonth(offset) {
        const [year, month] = visibleMonth.split('-').map(Number);
        const next = new Date(year, month - 1 + offset, 1, 12);
        onMonthChange(
            `${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}`,
        );
    }

    function dateNumber(date) {
        return Number(date.slice(-2));
    }

    function accessibleDate(date) {
        return formatDate(date, 'long');
    }
</script>

<div class="space-y-3 max-[359px]:-mx-4 max-[359px]:px-1.5">
    <div class="flex items-center justify-between gap-2">
        <button
            type="button"
            class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex size-11 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
            aria-label="Previous month"
            onclick={() => changeMonth(-1)}
            ><ChevronLeft class="size-5" /></button
        >
        <h3 class="min-w-0 text-center text-sm font-semibold">{monthTitle}</h3>
        <button
            type="button"
            class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring inline-flex size-11 shrink-0 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none"
            aria-label="Next month"
            onclick={() => changeMonth(1)}
            ><ChevronRight class="size-5" /></button
        >
    </div>

    <div
        class="grid grid-cols-7 gap-0 text-center"
        aria-label="Available dates"
    >
        {#each weekdays as weekday (weekday)}
            <div
                class="text-muted-foreground flex min-h-8 items-center justify-center text-xs font-medium"
            >
                {weekday}
            </div>
        {/each}
        {#each monthCells as cell (cell.key)}
            {#if cell.date}
                {@const selectable = cell.slots.some(
                    (slot) => !slot.full && (slot.remaining ?? 1) > 0,
                )}
                {@const count = cell.slots.filter(
                    (slot) => !slot.full && (slot.remaining ?? 1) > 0,
                ).length}
                <button
                    type="button"
                    class="focus-visible:ring-ring flex min-h-11 min-w-0 flex-col items-center justify-center rounded-md text-sm tabular-nums transition-colors focus-visible:z-10 focus-visible:ring-2 focus-visible:outline-none {selectedDate ===
                    cell.date
                        ? 'bg-primary text-primary-foreground ring-primary ring-2'
                        : selectable
                          ? 'hover:bg-muted'
                          : 'text-muted-foreground/45 cursor-not-allowed'}"
                    disabled={!selectable}
                    aria-label={`${accessibleDate(cell.date)}${selectable ? `, ${count} available ${count === 1 ? 'slot' : 'slots'}` : ', no available slots'}`}
                    aria-pressed={selectedDate === cell.date}
                    onclick={() => onSelectDate(cell.date)}
                >
                    <span>{dateNumber(cell.date)}</span>
                    {#if selectable}<span
                            class="mt-0.5 size-1 rounded-full bg-current"
                            aria-hidden="true"
                        ></span>{:else}<span
                            class="mt-0.5 size-1"
                            aria-hidden="true"
                        ></span>{/if}
                </button>
            {:else}
                <span class="min-h-11" aria-hidden="true"></span>
            {/if}
        {/each}
    </div>
</div>
