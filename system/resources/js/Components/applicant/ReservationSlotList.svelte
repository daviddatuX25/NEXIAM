<script>
  /**
   * Shared reservation slot list (2026-08-15-applicant-portal-interface).
   * One anatomy for exam slots and consultation sessions: time · venue · code
   * · capacity · state. Rows are plain buttons; the confirm sheet is a sibling.
   */
  import { CalendarDays } from 'lucide-svelte';

  /** @type {Array<{date?: string|null, label?: string|null, caption?: string|null, slots: Array<{id: string|number, timeLabel: string, venue: string|null, code: string, remaining: number|null, capacity: number|null, full: boolean}>}>} */
  let { groups = [], onSelect = () => {} } = $props();
</script>

<div class="space-y-4">
  {#each groups as group (group.date ?? group.label ?? group.slots[0]?.id)}
    {#if group.label}
      <div class="flex items-baseline gap-2">
        <span class="text-2xl font-semibold tabular-nums">{group.label}</span>
        {#if group.caption}
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{group.caption}</p>
          </div>
        {/if}
      </div>
    {/if}
    <ul class="space-y-2">
      {#each group.slots as slot (slot.id)}
        <li>
          <button
            type="button"
            class="flex w-full items-start gap-3 rounded-lg border px-3 py-3 text-left text-sm transition
              {slot.full
                ? 'cursor-not-allowed border-border/50 bg-muted/40 text-muted-foreground'
                : 'border-border hover:border-primary/50 hover:bg-muted/20'}"
            disabled={slot.full}
            onclick={() => onSelect(slot)}
          >
            <CalendarDays class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <span class="min-w-0 flex-1 space-y-0.5">
              <span class="block font-medium">{slot.timeLabel}</span>
              <span class="block text-xs text-muted-foreground">
                {slot.venue ?? 'Room TBD'}{#if slot.code} · {slot.code}{/if}
              </span>
              <span class="block text-xs">
                {#if slot.full}
                  Full
                {:else if slot.remaining !== null && slot.capacity !== null}
                  {slot.remaining} of {slot.capacity} seats left
                {:else}
                  Seats available
                {/if}
              </span>
            </span>
          </button>
        </li>
      {/each}
    </ul>
  {/each}
</div>
