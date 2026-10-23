<script>
  /**
   * Chart.js Line/Area chart for greenfield dashboards.
   * Auto-reacts to theme toggle via MutationObserver.
   */
  import Chart from 'chart.js/auto';
  import { onMount, onDestroy } from 'svelte';

  let {
    title = '',
    subtitle = '',
    labels = [],
    datasets = [],
    values = null,
    height = 240,
    color = '#10b981',
  } = $props();

  let canvasEl = $state(null);
  let chart = null;
  let observer = null;

  const safeLabels = $derived($state.snapshot(labels) ?? []);
  const safeValues = $derived(values ? $state.snapshot(values) : null);
  const safeDatasets = $derived($state.snapshot(datasets) ?? []);

  function isDark() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
  }
  function tc() {
    return isDark() ? '#94a3b8' : '#475569';
  }

  function gridColor() {
    return isDark() ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.06)';
  }

  function hexToRgba(hex, alpha) {
    const c = hex.replace('#', '');
    if (c.length < 6) return `rgba(16, 185, 129, ${alpha})`;
    const r = parseInt(c.substring(0, 2), 16);
    const g = parseInt(c.substring(2, 4), 16);
    const b = parseInt(c.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  function resolveHex(c) {
    if (!c || c.startsWith('rgb')) return '#10b981';
    return c;
  }

  function buildDatasets() {
    const dark = isDark();
    if (safeValues && !safeDatasets.length) {
      return [{
        label: title || 'Value',
        data: [...safeValues],
        borderColor: color,
        backgroundColor: hexToRgba(resolveHex(color), dark ? 0.15 : 0.08),
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 6,
        pointBackgroundColor: color,
        borderWidth: 2,
      }];
    }

    return safeDatasets.map((ds) => ({
      label: ds.label,
      data: Array.isArray(ds.data) ? [...ds.data] : [],
      borderColor: ds.color ?? color,
      backgroundColor: ds.fill !== false ? hexToRgba(resolveHex(ds.color ?? color), dark ? 0.15 : 0.08) : 'transparent',
      fill: ds.fill !== false,
      tension: 0.35,
      pointRadius: 2,
      pointHoverRadius: 5,
      pointBackgroundColor: ds.color ?? color,
      borderWidth: 2,
      borderDash: ds.borderDash ?? undefined,
    }));
  }

  function renderChart() {
    if (!canvasEl) return;
    if (chart) chart.destroy();

    const textCol = tc();
    const gridCol = gridColor();
    Chart.defaults.color = textCol;

    chart = new Chart(canvasEl, {
      type: 'line',
      data: {
        labels: [...safeLabels],
        datasets: buildDatasets(),
      },
      options: {
        animation: false,
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        plugins: {
          legend: {
            display: datasets.length > 1,
            position: 'top',
            align: 'end',
            labels: {
              boxWidth: 8,
              boxHeight: 8,
              usePointStyle: true,
              color: textCol,
              font: { size: 11, family: 'IBM Plex Mono, monospace' },
            },
          },
          tooltip: {
            backgroundColor: isDark() ? '#0e131f' : '#ffffff',
            titleColor: isDark() ? '#ffffff' : '#0f172a',
            bodyColor: isDark() ? '#94a3b8' : '#475569',
            borderColor: isDark() ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)',
            borderWidth: 1,
            padding: 10,
            cornerRadius: 8,
          },
        },
        scales: {
          x: {
            grid: { color: gridCol },
            ticks: { color: textCol, font: { size: 11 } },
          },
          y: {
            grid: { color: gridCol },
            ticks: { color: textCol, font: { size: 11 } },
            beginAtZero: true,
          },
        },
      },
    });
  }

  $effect(() => {
    labels;
    datasets;
    values;
    color;
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
    <div class="flex items-center justify-between">
      <div>
        <h4 class="text-sm font-bold tracking-tight text-foreground">{title}</h4>
        {#if subtitle}
          <p class="text-xs text-muted-foreground font-mono">{subtitle}</p>
        {/if}
      </div>
    </div>
  {/if}
  <div style={`height: ${height}px`} class="w-full relative">
    <canvas bind:this={canvasEl}></canvas>
  </div>
</div>
