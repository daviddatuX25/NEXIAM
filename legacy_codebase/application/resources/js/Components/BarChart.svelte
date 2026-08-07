<script>
  /**
   * Chart.js horizontal bar chart for pipeline funnel visualization.
   *
   * Props:
   * - title?: string
   * - subtitle?: string
   * - labels: string[]
   * - data: number[]
   * - colors: string[]  (per-bar colors)
   * - height?: number (default 320)
   * - horizontal?: boolean (default true)
   */
  import { Chart, BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';
  import { onMount, onDestroy } from 'svelte';

  Chart.register(BarController, BarElement, LinearScale, CategoryScale, Tooltip, Legend);

  let {
    title,
    subtitle,
    labels = [],
    data = [],
    colors = [],
    height = 320,
    horizontal = true,
  } = $props();

  let canvasEl = $state(null);
  let chart = null;

  function buildChart() {
    if (!canvasEl || !labels.length) return;
    if (chart) chart.destroy();

    const total = data.reduce((a, b) => a + b, 0);

    chart = new Chart(canvasEl, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: colors.length ? colors : labels.map(() => '#3b82f6'),
          borderWidth: 0,
          borderRadius: 6,
          maxBarThickness: 32,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: 'rgba(148, 163, 184, 0.1)' },
            ticks: { font: { size: 11 }, color: 'rgb(148, 163, 184)' },
          },
          y: {
            grid: { display: false },
            ticks: { font: { size: 12, weight: 500 }, color: 'rgb(148, 163, 184)' },
          },
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15, 23, 42, 0.9)',
            titleFont: { size: 12 },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label(ctx) {
                const val = ctx.parsed[horizontal ? 'x' : 'y'];
                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                return ` ${val} applicants (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  onMount(() => {
    setTimeout(buildChart, 30);
  });

  onDestroy(() => {
    if (chart) chart.destroy();
  });

  $effect(() => {
    const _l = labels;
    const _d = data;
    const _c = colors;
    if (canvasEl) setTimeout(buildChart, 30);
  });
</script>

<div class="space-y-3">
  {#if title || subtitle}
    <div>
      {#if title}<h3 class="font-semibold text-foreground text-sm">{title}</h3>{/if}
      {#if subtitle}<p class="text-xs text-muted-foreground mt-0.5">{subtitle}</p>{/if}
    </div>
  {/if}

  {#if labels.length > 0}
    <div style="height: {height}px" class="relative w-full">
      <canvas bind:this={canvasEl}></canvas>
    </div>
  {:else}
    <div class="flex items-center justify-center p-6 border border-dashed border-border/50 rounded-xl text-sm text-muted-foreground">
      No data available
    </div>
  {/if}
</div>
