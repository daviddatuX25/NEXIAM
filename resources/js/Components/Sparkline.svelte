<script>
  /**
   * Minimal SVG sparkline (no deps).
   *
   * Props:
   * - title?: string
   * - subtitle?: string
   * - labels?: string[]
   * - values: number[]
   * - height?: number
   */
  let { title, subtitle, labels = [], values, height = 72 } = $props();

  const safeValues = $derived(Array.isArray(values) ? values.map((v) => Number(v) || 0) : []);
  const min = $derived(safeValues.length ? Math.min(...safeValues) : 0);
  const max = $derived(safeValues.length ? Math.max(...safeValues) : 0);
  const range = $derived(max - min || 1);

  function points(w, h) {
    const n = safeValues.length;
    if (n <= 1) return '';
    const pad = 6;
    const innerW = w - pad * 2;
    const innerH = h - pad * 2;
    return safeValues
      .map((v, i) => {
        const x = pad + (innerW * i) / (n - 1);
        const y = pad + innerH - ((v - min) / range) * innerH;
        return `${x},${y}`;
      })
      .join(' ');
  }

  function last(arr) {
    return arr.length ? arr[arr.length - 1] : 0;
  }
</script>

<div class="space-y-2">
  <div class="flex items-baseline justify-between gap-4">
    <div class="min-w-0">
      {#if title}<div class="text-sm font-semibold text-foreground truncate">{title}</div>{/if}
      {#if subtitle}<div class="text-xs text-muted-foreground truncate">{subtitle}</div>{/if}
    </div>
    <div class="shrink-0 text-sm font-semibold tabular-nums text-foreground/90">
      {last(safeValues)}
    </div>
  </div>

  {#if safeValues.length > 1}
    <svg viewBox={`0 0 240 ${height}`} class="w-full">
      <defs>
        <linearGradient id="sparkFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="rgb(59 130 246)" stop-opacity="0.30" />
          <stop offset="100%" stop-color="rgb(59 130 246)" stop-opacity="0.02" />
        </linearGradient>
      </defs>

      <polyline
        points={points(240, height)}
        fill="none"
        stroke="rgb(59 130 246)"
        stroke-width="2"
        stroke-linecap="round"
        stroke-linejoin="round"
      />
      <polyline
        points={`${points(240, height)} 234,${height - 6} 6,${height - 6}`}
        fill="url(#sparkFill)"
        stroke="none"
      />
    </svg>
  {:else}
    <div class="text-sm text-muted-foreground">Not enough data.</div>
  {/if}
</div>

