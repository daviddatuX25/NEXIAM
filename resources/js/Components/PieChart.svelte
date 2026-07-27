<script>
  /**
   * Minimal SVG pie chart (no deps).
   *
   * Props:
   * - title?: string
   * - data: Array<{ label: string; value: number; color?: string }>
   * - size?: number
   */
  let { title, data, size = 168 } = $props();

  const safeData = $derived(Array.isArray(data) ? data : []);
  const total = $derived(safeData.reduce((acc, d) => acc + (Number(d?.value) || 0), 0));
  const cx = $derived(size / 2);
  const cy = $derived(size / 2);
  const r = $derived((size / 2) - 6);

  const palette = [
    '#22c55e', // green
    '#3b82f6', // blue
    '#f59e0b', // amber
    '#a855f7', // purple
    '#ef4444', // red
    '#14b8a6', // teal
  ];

  function polarToCartesian(cx, cy, r, angleDeg) {
    const angleRad = ((angleDeg - 90) * Math.PI) / 180;
    return { x: cx + r * Math.cos(angleRad), y: cy + r * Math.sin(angleRad) };
  }

  function arcPath(cx, cy, r, startAngle, endAngle) {
    const start = polarToCartesian(cx, cy, r, endAngle);
    const end = polarToCartesian(cx, cy, r, startAngle);
    const largeArcFlag = endAngle - startAngle <= 180 ? 0 : 1;
    return `M ${cx} ${cy} L ${start.x} ${start.y} A ${r} ${r} 0 ${largeArcFlag} 0 ${end.x} ${end.y} Z`;
  }

  const slices = $derived(() => {
    if (total <= 0) return [];
    let angle = 0;
    return safeData.map((d, idx) => {
      const value = Number(d?.value) || 0;
      const delta = (value / total) * 360;
      const start = angle;
      const end = angle + delta;
      angle = end;
      return {
        label: d?.label ?? `Item ${idx + 1}`,
        value,
        color: d?.color ?? palette[idx % palette.length],
        start,
        end,
      };
    });
  });
</script>

<div class="space-y-3">
  {#if title}
    <div class="text-sm font-semibold text-foreground">{title}</div>
  {/if}

  {#if total > 0}
    <div class="flex items-center gap-4">
      <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} class="shrink-0">
        {#each slices() as s (s.label)}
          <path d={arcPath(cx, cy, r, s.start, s.end)} fill={s.color} opacity="0.95" />
        {/each}
        <circle cx={cx} cy={cy} r={r * 0.55} class="fill-white/20 dark:fill-black/20" />
      </svg>

      <div class="space-y-2 min-w-0">
        {#each slices() as s (s.label)}
          <div class="flex items-center justify-between gap-3 text-sm">
            <div class="flex items-center gap-2 min-w-0">
              <span class="inline-block h-2.5 w-2.5 rounded-sm" style={`background:${s.color}`}></span>
              <span class="truncate text-foreground/90">{s.label}</span>
            </div>
            <div class="shrink-0 tabular-nums text-muted-foreground">
              {s.value}
              <span class="ml-2 text-xs">
                ({Math.round((s.value / total) * 100)}%)
              </span>
            </div>
          </div>
        {/each}
      </div>
    </div>
  {:else}
    <div class="text-sm text-muted-foreground">No data.</div>
  {/if}
</div>

