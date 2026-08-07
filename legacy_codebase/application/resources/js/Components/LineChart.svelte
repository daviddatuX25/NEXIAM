<script>
  /**
   * Chart.js Line/Area chart — auto-reacts to theme toggle via MutationObserver.
   */
  import { Chart, LineController, LineElement, PointElement, Filler, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';
  import { onMount, onDestroy } from 'svelte';

  Chart.register(LineController, LineElement, PointElement, Filler, LinearScale, CategoryScale, Tooltip, Legend);

  let {
    title,
    subtitle,
    labels = [],
    datasets = [],
    values = null,
    height = 240,
    color = 'rgb(59, 130, 246)',
  } = $props();

  let canvasEl = $state(null);
  let chart = null;
  let observer = null;

  function isDark() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
  }

  function tc() { return isDark() ? '#e2e8f0' : '#334155'; }

  function hexToRgba(hex, alpha) {
    const c = hex.replace('#', '');
    if (c.length < 6) return `rgba(59, 130, 246, ${alpha})`;
    const r = parseInt(c.substring(0, 2), 16);
    const g = parseInt(c.substring(2, 4), 16);
    const b = parseInt(c.substring(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  function resolveHex(c) {
    if (!c || c.startsWith('rgb')) return '#3b82f6';
    return c;
  }

  function buildDatasets() {
    const dark = isDark();
    if (values && !datasets.length) {
      return [{
        label: title || 'Value',
        data: values,
        borderColor: color,
        backgroundColor: hexToRgba(resolveHex(color), dark ? 0.15 : 0.1),
        fill: true,
        tension: 0.35,
        pointRadius: 2,
        pointHoverRadius: 5,
        pointBackgroundColor: color,
        borderWidth: 2,
      }];
    }

    return datasets.map((ds) => ({
      label: ds.label,
      data: ds.data,
      borderColor: ds.color ?? color,
      backgroundColor: ds.fill !== false ? hexToRgba(resolveHex(ds.color ?? color), dark ? 0.12 : 0.08) : 'transparent',
      fill: ds.fill !== false,
      tension: 0.35,
      pointRadius: 2,
      pointHoverRadius: 5,
      pointBackgroundColor: ds.color ?? color,
      borderWidth: 2,
    }));
  }

  function buildChart() {
    if (!canvasEl || !labels.length) return;
    if (chart) chart.destroy();

    const dark = isDark();
    const textCol = tc();
    const gc = dark ? 'rgba(148, 163, 184, 0.12)' : 'rgba(148, 163, 184, 0.2)';
    Chart.defaults.color = textCol;

    chart = new Chart(canvasEl, {
      type: 'line',
      data: { labels, datasets: buildDatasets() },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: {
            grid: { display: false },
            ticks: { maxTicksLimit: 8, font: { size: 11 }, color: textCol },
          },
          y: {
            beginAtZero: true,
            grid: { color: gc },
            ticks: { font: { size: 11 }, color: textCol },
          },
        },
        plugins: {
          legend: {
            display: datasets.length > 1,
            position: 'bottom',
            labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12 }, color: textCol },
          },
          tooltip: {
            backgroundColor: dark ? 'rgba(30, 41, 59, 0.95)' : 'rgba(15, 23, 42, 0.9)',
            titleColor: '#f1f5f9',
            bodyColor: '#f1f5f9',
            titleFont: { size: 12 },
            bodyFont: { size: 12 },
            padding: 10,
            cornerRadius: 8,
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
    const _l = labels;
    const _d = datasets;
    const _v = values;
    if (canvasEl) setTimeout(buildChart, 80);
  });
</script>

<div class="space-y-3">
  {#if title || subtitle}
    <div>
      {#if title}<h3 class="font-semibold text-foreground text-sm">{title}</h3>{/if}
      {#if subtitle}<p class="text-xs text-muted-foreground mt-0.5">{subtitle}</p>{/if}
    </div>
  {/if}

  <div style="height: {height}px" class="relative w-full">
    <canvas bind:this={canvasEl}></canvas>
  </div>
</div>
