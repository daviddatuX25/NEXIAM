<script>
  /**
   * Chart.js Doughnut chart for greenfield dashboards.
   * Auto-reacts to theme toggle via MutationObserver.
   */
  import Chart from 'chart.js/auto';
  import { onMount, onDestroy } from 'svelte';

  const defaultPalette = ['#10b981', '#38bdf8', '#6366f1', '#a855f7', '#f59e0b', '#ec4899', '#64748b'];

  let {
    title = '',
    data = [],
    height = 220,
    cutout = '65%',
  } = $props();

  let canvasEl = $state(null);
  let chart = null;
  let observer = null;

  function isDark() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
  }

  function tc() {
    return isDark() ? '#94a3b8' : '#475569';
  }
  const safeData = $derived(Array.isArray(data) ? ($state.snapshot(data) ?? []) : []);
  const hasPositiveValue = $derived(safeData.some((d) => (Number(d.value) || 0) > 0));

  function renderChart() {
    if (!canvasEl || safeData.length === 0) return;
    if (chart) chart.destroy();

    const textCol = tc();
    Chart.defaults.color = textCol;

    chart = new Chart(canvasEl, {
      type: 'doughnut',
      data: {
        labels: safeData.map((d) => String(d.label ?? '')),
        datasets: [{
          data: safeData.map((d) => Number(d.value) || 0),
          backgroundColor: safeData.map((d, i) => d.color ?? defaultPalette[i % defaultPalette.length]),
          borderWidth: 0,
          borderRadius: 4,
          hoverOffset: 6,
        }],
      },
      options: {
        animation: false,
        responsive: true,
        maintainAspectRatio: false,
        cutout,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 12,
              usePointStyle: true,
              pointStyle: 'circle',
              boxWidth: 8,
              boxHeight: 8,
              color: textCol,
              font: { size: 10, family: 'IBM Plex Mono, monospace' },
            },
          },
          tooltip: {
            backgroundColor: isDark() ? '#0e131f' : '#ffffff',
            titleColor: isDark() ? '#ffffff' : '#0f172a',
            bodyColor: isDark() ? '#94a3b8' : '#475569',
            borderColor: isDark() ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
            borderWidth: 1,
            padding: 8,
            cornerRadius: 8,
            callbacks: {
              label: (ctx) => ` ${ctx.label}: ${ctx.raw}`,
            },
          },
        },
      },
    });
  }

  $effect(() => {
    safeData;
    if (canvasEl) renderChart();
  });

  onMount(() => {
    renderChart();
    if (typeof MutationObserver !== 'undefined') {
      observer = new MutationObserver(() => {
        if (chart) renderChart();
      });
      observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
      });
    }
  });

  onDestroy(() => {
    if (chart) chart.destroy();
    if (observer) observer.disconnect();
  });
</script>

<div class="space-y-2">
  {#if title}
    <h4 class="text-sm font-bold tracking-tight text-foreground">{title}</h4>
  {/if}
  <div style={`height: ${height}px`} class="w-full relative flex items-center justify-center">
    {#if hasPositiveValue}
      <canvas bind:this={canvasEl}></canvas>
    {:else}
      <div class="flex h-full w-full flex-col items-center justify-center rounded-lg border border-dashed border-border/60 bg-muted/20 p-4 text-center">
        <span class="font-mono text-xs text-muted-foreground">No records in current scope</span>
      </div>
    {/if}
  </div>
</div>
