<script>
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import ExtendApplicationWindowDialog from '@/Components/Setup/ExtendApplicationWindowDialog.svelte';
  import OpeningAlertSubscribersDialog from '@/Pages/Setup/Cycles/OpeningAlertSubscribersDialog.svelte';
  import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-svelte';
  import { formatUtcDate, formatUtcInstant, shiftedUtcMonth, utcCalendarWeeks, utcDayKey } from '@/lib/utc-calendar.js';

  let { calendar = null, opening_alerts_visible = false } = $props();
  let visibleMonth = $state('');

  const displayWindow = $derived(calendar?.effective_window ?? (calendar?.opens_at && calendar?.closes_at
    ? { opens_at: calendar.opens_at, closes_at: calendar.closes_at }
    : null));
  const startDay = $derived(utcDayKey(displayWindow?.opens_at));
  const endDay = $derived(utcDayKey(displayWindow?.closes_at));
  const spanIsPublished = $derived(Boolean(calendar?.effective_window) || calendar?.status === 'published');
  const todayDay = $derived(utcDayKey(new Date().toISOString()));
  const monthTitle = $derived(monthLabel(visibleMonth));
  const weeks = $derived.by(() => utcCalendarWeeks(visibleMonth));

  $effect(() => {
    calendar?.cycle_id;
    calendar?.opens_at;
    calendar?.closes_at;
    calendar?.effective_window?.id;
    calendar?.effective_window?.opens_at;
    calendar?.effective_window?.closes_at;
    visibleMonth = defaultMonth(calendar);
  });

  function defaultMonth(value) {
    const now = new Date();
    const currentDay = utcDayKey(now.toISOString());
    const displayWindow = value?.effective_window ?? value;
    const opensAt = displayWindow?.opens_at ? new Date(displayWindow.opens_at) : null;
    const closesAt = displayWindow?.closes_at ? new Date(displayWindow.closes_at) : null;
    const today = now.getTime();

    if (opensAt && closesAt && opensAt.getTime() <= today && closesAt.getTime() >= today) {
      return monthKey(now);
    }
    if (opensAt && opensAt.getTime() > today) return monthKey(opensAt);
    if (closesAt && closesAt.getTime() < today) return monthKey(closesAt);

    return currentDay.slice(0, 7);
  }

  function monthKey(date) {
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
  }

  function monthLabel(value) {
    if (!value) return '';
    const date = new Date(`${value}-01T12:00:00Z`);
    if (Number.isNaN(date.getTime())) return '';

    return new Intl.DateTimeFormat(undefined, {
      month: 'long',
      year: 'numeric',
      timeZone: 'UTC',
    }).format(date);
  }


  function shiftMonth(offset) {
    visibleMonth = shiftedUtcMonth(visibleMonth, offset);
  }

  function goToToday() {
    visibleMonth = monthKey(new Date());
  }

  function labelForAvailability(availability) {
    const labels = {
      no_active_cycle: 'No active academic year',
      no_window: 'No application window',
      missing_dates: 'Dates unavailable',
      cycle_upcoming: 'Academic year not started',
      cycle_ended: 'Academic year ended',
      unpublished: 'Unpublished window',
      upcoming: 'Opening soon',
      ended: 'Window ended',
      open: 'Applications open',
      another_window_open: 'Applications open in another window',
      policy_unavailable: 'Privacy policy unavailable',
      not_available: 'Not accepting applications',
    };

    return labels[availability] ?? 'Availability unavailable';
  }

  function messageForAvailability(value) {
    const messages = {
      no_active_cycle: 'There is no active academic year. Applications remain closed until a year is activated.',
      no_window: 'The active academic year has no application window to display.',
      missing_dates: 'The active application window is missing date information.',
      cycle_upcoming: 'The academic year has not started, so applications are not open.',
      cycle_ended: 'The academic year has ended. Use Edit for an explicit reopening.',
      unpublished: 'This window is not published. An active academic year alone does not open applications.',
      upcoming: 'This published window has not opened yet.',
      ended: 'This application window has ended. Use Edit for an explicit reopening.',
      open: 'The current intake resolver confirms that applications are open for this window.',
      another_window_open: 'A different application window is currently selected for public intake.',
      policy_unavailable: 'Applications are not open because the designated published privacy policy is unavailable.',
      not_available: 'The active window is not the effective public intake selected by the current intake resolver.',
    };
    if (value?.availability === 'another_window_open' && value.effective_window) {
      return `${value.effective_window.name} (#${value.effective_window.id}) is currently accepting applications. Primary window #${value.window_id} is not the selected public intake and cannot be extended here.`;
    }

    if (value?.availability === 'open' && !value.can_extend) {
      return 'Applications are open, but the academic-year and window deadlines differ. Use Edit to review and align them before extending.';
    }

    return messages[value?.availability] ?? 'Application availability could not be determined.';
  }

  function cellDescription(date) {
    const markers = [];
    if (date === startDay) markers.push('Opening day');
    if (date === endDay) markers.push('Deadline');
    if (date === todayDay) markers.push('Today');
    if (startDay && endDay && date >= startDay && date <= endDay) markers.push(spanIsPublished ? 'Published application period' : 'Unpublished application period');

    return `${formatUtcDate(date, 'long')}${markers.length ? ` · ${markers.join(' · ')}` : ''}`;
  }

  function isInPeriod(date) {
    return Boolean(date && startDay && endDay && date >= startDay && date <= endDay);
  }
</script>

<section class="min-w-0 space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5" aria-labelledby="application-calendar-title">
  <header class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0 space-y-1">
      <div class="flex flex-wrap items-center gap-2">
        <CalendarDays class="size-4 text-primary" aria-hidden="true" />
        <h2 id="application-calendar-title" class="text-base font-semibold tracking-tight">Application window calendar</h2>
        {#if calendar}
          <Badge variant={calendar.availability === 'open' ? 'default' : 'secondary'}>{labelForAvailability(calendar.availability)}</Badge>
        {/if}
      </div>
      <p class="text-sm text-muted-foreground">
        {calendar?.label ?? 'Current institution application availability'}
      </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      {#if opening_alerts_visible}
        <OpeningAlertSubscribersDialog />
      {/if}
      {#if calendar?.can_extend}
        <ExtendApplicationWindowDialog {calendar} />
      {/if}
    </div>
  </header>

  {#if !calendar || calendar.availability === 'no_active_cycle'}
    <div class="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-5 text-sm text-muted-foreground" role="status">
      {messageForAvailability(calendar)}
    </div>
  {:else}
    {#if calendar.availability !== 'open' || !calendar.can_extend}
      <p class="text-sm text-muted-foreground" role="status">{messageForAvailability(calendar)}</p>
    {/if}
    <div class="grid gap-2 text-sm sm:grid-cols-2 sm:gap-x-6">
      <p><span class="font-medium text-foreground">Opens:</span> {formatUtcInstant(displayWindow?.opens_at)}</p>
      <p><span class="font-medium text-foreground">Deadline:</span> {formatUtcInstant(displayWindow?.closes_at)}</p>
      {#if calendar.effective_window && calendar.effective_window.id !== calendar.window_id}
        <p class="text-muted-foreground sm:col-span-2">Showing {calendar.effective_window.name} · window #{calendar.effective_window.id}.</p>
        <details class="text-muted-foreground sm:col-span-2">
          <summary class="cursor-pointer font-medium text-foreground">Other configured window</summary>
          <div class="mt-2 space-y-1">
            <p>Primary window #{calendar.window_id} · {calendar.status}</p>
            <p>Opens {formatUtcInstant(calendar.opens_at)} · deadline {formatUtcInstant(calendar.closes_at)}</p>
            <p>Academic-year deadline: {formatUtcDate(calendar.cycle_closes_on)}. Use Edit to review this separate window.</p>
          </div>
        </details>
      {:else if !calendar.can_extend && calendar.availability === 'open'}
        <p class="text-muted-foreground sm:col-span-2">Academic-year deadline: {formatUtcDate(calendar.cycle_closes_on)}. Use Edit to review the separate date bounds.</p>
      {/if}
    </div>

    {#if startDay && endDay}
      <div class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-sm font-semibold" aria-live="polite">{monthTitle}</h3>
          <div class="flex items-center gap-2">
            <Button type="button" variant="outline" class="size-10 p-0" aria-label="Previous month" onclick={() => shiftMonth(-1)}>
              <ChevronLeft class="size-4" />
            </Button>
            <Button type="button" variant="outline" class="min-h-10 px-3 text-sm" onclick={goToToday}>Today</Button>
            <Button type="button" variant="outline" class="size-10 p-0" aria-label="Next month" onclick={() => shiftMonth(1)}>
              <ChevronRight class="size-4" />
            </Button>
          </div>
        </div>
        <div role="grid" aria-label={`${monthTitle} ${calendar.effective_window ? `${calendar.effective_window.name} effective application span` : 'primary application window'}; display only`} class="min-w-0 space-y-1">
          <div role="row" class="grid grid-cols-7 gap-1 text-center text-xs font-medium text-muted-foreground">
            {#each [{ short: 'Mo', full: 'Monday' }, { short: 'Tu', full: 'Tuesday' }, { short: 'We', full: 'Wednesday' }, { short: 'Th', full: 'Thursday' }, { short: 'Fr', full: 'Friday' }, { short: 'Sa', full: 'Saturday' }, { short: 'Su', full: 'Sunday' }] as weekday (weekday.full)}
              <div role="columnheader" class="py-1"><span aria-hidden="true">{weekday.short}</span><span class="sr-only">{weekday.full}</span></div>
            {/each}
          </div>
          {#each weeks as week, weekIndex (`${visibleMonth}-week-${weekIndex}`)}
            <div role="row" class="grid grid-cols-7 gap-1">
              {#each week as cell (cell.key)}
                {#if cell.date}
                  <div
                    role="gridcell"
                    aria-label={cellDescription(cell.date)}
                    class={`relative flex min-h-14 min-w-0 flex-col items-center justify-center gap-0.5 rounded-md border px-0.5 py-1 text-center ${isInPeriod(cell.date)
                      ? spanIsPublished
                        ? 'border-primary/30 bg-primary/10'
                        : 'border-dashed border-border bg-muted/40'
                      : 'border-transparent'} ${cell.date === todayDay ? 'ring-2 ring-ring ring-offset-1 ring-offset-background' : ''}`}
                  >
                    <span class="text-xs font-medium tabular-nums">{Number(cell.date.slice(-2))}</span>
                    <span class="flex min-h-3 flex-wrap items-center justify-center gap-0.5 text-xs font-semibold leading-none">
                      {#if cell.date === startDay}<span class="rounded-full bg-primary px-1 text-primary-foreground">O</span>{/if}
                      {#if cell.date === endDay}<span class="rounded-sm bg-amber-500/20 px-1 text-amber-800 dark:text-amber-200">D</span>{/if}
                      {#if cell.date === todayDay}<span class="text-foreground">Today</span>{/if}
                    </span>
                  </div>
                {:else}
                  <div role="gridcell" aria-hidden="true" class="min-h-14 rounded-md"></div>
                {/if}
              {/each}
            </div>
          {/each}
        </div>
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground" aria-label="Calendar markers">
          <span class="inline-flex items-center gap-1.5"><span class="rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground">O</span> Opening day</span>
          <span class="inline-flex items-center gap-1.5"><span class="rounded-sm bg-amber-500/20 px-1 text-xs font-semibold text-amber-800 dark:text-amber-200">D</span> Deadline</span>
          <span class="inline-flex items-center gap-1.5"><span class="size-3 rounded-sm border-2 border-ring"></span> Today</span>
          <span>{calendar.effective_window ? `Effective public intake · ${calendar.effective_window.name}` : spanIsPublished ? 'Published primary period' : 'Unpublished primary period (dashed)'}</span>
        </div>
      </div>
    {:else}
      <div class="rounded-lg border border-dashed border-border bg-muted/20 px-4 py-5 text-sm text-muted-foreground" role="status">
        The application window is missing dates, so no calendar span is shown.
      </div>
    {/if}
  {/if}
</section>
