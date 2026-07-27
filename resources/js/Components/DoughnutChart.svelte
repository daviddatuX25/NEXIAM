<script>
  /**
   * Chart.js Doughnut chart — auto-reacts to theme toggle via MutationObserver.
   */
  import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js';
  import { onMount, onDestroy } from 'svelte';

  Chart.register(DoughnutController, ArcElement, Tooltip, Legend);

  const defaultPalette = ['#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#ef4444', '#14b8a6', '#6366f1', '#ec4899'];

  let {
    title,
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

  function tc() { return isDark() ? '#e2e8f0' : '#334155'; }

  const safeData = $derived(Array.isArray(data) ? data : []);
  const total = $derived(safeData.reduce((acc, d) => acc + (Number(d?.value) || 0), 0));

  function buildChart() {
    if (!canvasEl || safeData.length === 0) return;
    if (chart) chart.destroy();

    const textCol = tc();
    const dark = isDark();
    Chart.defaults.color = textCol;

    chart = new Chart(canvasEl, {
      type: 'doughnut',
      data: {
        labels: safeData.map(d => d.label),
        datasets: [{
          data: safeData.map(d => d.value),
          backgroundColor: safeData.map((d, i) => d.color ?? defaultPalette[i % defaultPalette.length]),
          borderWidth: 0,
          borderRadius: 4,
          hoverOffset: 6,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 14,
              usePointStyle: true,
              pointStyle: 'circle',
              font: { size: 12, weight: '500' },
              color: textCol,
              generateLabels(chart) {
                const ds = chart.data.datasets[0];
                return chart.data.labels.map((label, i) => ({
                  text: `${label} (${ds.data[i]})`,
                  fillStyle: ds.backgroundColor[i],
                  strokeStyle: 'transparent',
                  fontColor: textCol,
                  pointStyle: 'circle',
                  hidden: false,
                  index: i,
                }));
              },
            },
          },
          tooltip: {
            backgroundColor: dark ? 'rgba(30, 41, 59, 0.95)' : 'rgba(15, 23, 42, 0.9)',
            titleColor: '#f1f5f9',
            bodyColor: '#f1f5f9',
            titleFont: { size: 12 },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 8,
            callbacks: {
              label(ctx) {
                const val = ctx.parsed;
                const t = ctx.dataset.data.reduce((a, b) => a + b, 0);
                const pct = t > 0 ? Math.round((val / t) * 100) : 0;
                return ` ${ctx.label}: ${val} (${pct}%)`;
              },
            },
          },
        },
      },
    });
  }

  onMount(() => {
    setTimeout(buildChart, 80);

    // Watch for theme toggle (dark class changes on <html>)
    if (typeof document !== 'undefined') {
      observer = new MutationObserver(() => {
        setTimeout(buildChart, 50);
      });
      observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    }
  });

  onDestroy(() => {
    if (chart) chart.destroy();
    if (observer) observer.disconnect();
  });

  $effect(() => {
    const _d = data;
    if (canvasEl) setTimeout(buildChart, 80);
  });
</script>

<div class="space-y-3">
  {#if title}
    <h3 class="font-semibold text-foreground text-sm">{title}</h3>
  {/if}

  {#if total > 0}
    <div style="height: {height}px" class="relative w-full">
      <canvas bind:this={canvasEl}></canvas>
    </div>
  {:else}
    <div class="flex items-center justify-center p-6 border border-dashed border-border/50 rounded-xl text-sm text-muted-foreground">
      No data available
    </div>
  {/if}
</div>
