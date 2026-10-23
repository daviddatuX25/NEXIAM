<script>
    import CalendarViewToggle from '@/Components/applicant/CalendarViewToggle.svelte';
    import DateStrip from '@/Components/applicant/DateStrip.svelte';
    import MonthGrid from '@/Components/applicant/MonthGrid.svelte';

    let {
        heading = 'Available dates',
        headingId = 'applicant-available-dates',
        days = [],
        selectedDate = null,
        visibleMonth = '',
        view = 'month',
        onViewChange = () => {},
        onMonthChange = () => {},
        onSelectDate = () => {},
        children,
    } = $props();
</script>

<section class="space-y-4" aria-labelledby={headingId}>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 id={headingId} class="text-lg font-semibold">{heading}</h2>
        <CalendarViewToggle value={view} onChange={onViewChange} />
    </div>
    {#if view === 'month'}
        <MonthGrid
            {days}
            {visibleMonth}
            {selectedDate}
            {onSelectDate}
            {onMonthChange}
        />
    {:else}
        <DateStrip {days} {selectedDate} {onSelectDate} />
    {/if}
    {@render children?.()}
</section>
