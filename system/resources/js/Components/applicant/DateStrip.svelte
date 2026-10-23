<script>
  import { formatDate } from '@/lib/date-utils.js';

  let { days = [], selectedDate = null, onSelectDate = () => {} } = $props();

  function availableCount(day) {
    return day.slots.filter((slot) => !slot.full && (slot.remaining ?? 1) > 0).length;
  }

  function weekday(date) {
    return new Date(`${date}T12:00:00`).toLocaleDateString(undefined, { weekday: 'short' });
  }

  function dayNumber(date) {
    return Number(date.slice(-2));
  }
</script>

<div class="max-w-full overflow-x-auto pb-2" aria-label="Available dates">
  <div class="flex w-max gap-2">
    {#each days as day (day.date)}
      {@const count = availableCount(day)}
      <button
        type="button"
        class="flex min-h-11 min-w-[5.5rem] flex-col items-center justify-center rounded-xl border px-3 py-2 text-center transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {selectedDate === day.date ? 'border-primary bg-primary text-primary-foreground' : count > 0 ? 'border-border bg-background hover:border-primary/50 hover:bg-muted/40' : 'cursor-not-allowed border-border/50 bg-muted/30 text-muted-foreground'}"
        disabled={count === 0}
        aria-label={`${formatDate(day.date, 'long')}, ${count > 0 ? `${count} available ${count === 1 ? 'slot' : 'slots'}` : 'Full'}`}
        aria-pressed={selectedDate === day.date}
        onclick={() => onSelectDate(day.date)}
      >
        <span class="text-xs font-medium">{weekday(day.date)}</span>
        <span class="text-base font-semibold tabular-nums">{dayNumber(day.date)}</span>
        <span class="text-[0.65rem]">{count > 0 ? `${count} ${count === 1 ? 'slot' : 'slots'}` : 'Full'}</span>
      </button>
    {/each}
  </div>
</div>
